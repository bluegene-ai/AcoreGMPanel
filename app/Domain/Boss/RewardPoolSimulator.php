<?php
/**
 * File: app/Domain/Boss/RewardPoolSimulator.php
 * Purpose: 离线模拟 boss.lua 的击杀奖池结算（§OnBossDied 里的 6 个独立奖池），
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Support\GameNameResolver;
use PDO;
use Throwable;

/**
 * 奖池结算模拟器。
 *
 * 算法逐条镜像 Release/lua_scripts/acore-boss-smartai/boss.lua：
 *   - 触发：每个「已启用 + 奖品非空」的池 roll once，random(1,100) <= chance（Lua 用 math.random(100)）。
 *   - 获奖名单：winner_mode=all → 全部有效参战；count → min(winner_count, 参战数) 人，
 *     按 SelectWeightedRewardWinners 抽（不放回）。权重 = max(0.01, score)，
 *     threshold = randomFloat() * totalWeight 沿累积和找第一个 >= threshold 的人（weighted）；
 *     mode=random 时退化成 math.random(#pool) 的等概率抽取（对应 Lua 的 randomRewardMode == "random"）。
 *   - 奖品：每位获奖者从"他能用"的候选里等概率抽 1 件（PickRewardPoolItemFor）；
 *     候选为空 → 本次不发（绝不发不能用的奖品）。
 *   - 可用性（class_filter=1）：物品在 classItemMap 里 → 只按该映射判职业；
 *     不在映射里 → 退回核心式检查（world 库 item_template 的 AllowableClass 位掩码 +
 *     RequiredLevel 对比等级）。AllowableClass 为 0 或 -1 = 全职业；行缺失 / 查不动 = 按可用处理。
 *     class_filter=0 → 人人可拿全部奖品。
 *   - 贡献分（ComputeContributionScore）：各分项占总量的比例 × 对应权重之和，杀手再加 kill 权重。
 *     调用方直接给 'score' 时不重算，原样使用。
 *
 * 与 Lua 的两处已知差异（都是面板侧无法复刻的部分，已在 notes / 文档里说明）：
 *   1) Lua 只在有"核心贡献"的人里抽 count 名额（贡献池为空时才拿参战名单兜底）。
 *      面板由调用方决定 $participants 是谁，所以这里假定传进来的就是有效参战名单。
 *   2) Lua 用服务器全局 math.random 状态；这里不传 seed 时用 random_int，
 *      传 seed 时用自带 32 位 LCG（可复现，且不污染全局 mt_rand 状态）。
 *
 * 返回结构（simulate）：
 *   [
 *     'rounds' => int,
 *     'participants' => [
 *         ['guid'=>int,'name'=>string,'class_id'=>int,'score'=>float,
 *          'items_per_round'=>float,'win_rate'=>float,'pools'=>[1,3]], ...
 *     ],  // 顺序 = 调用方传入的顺序；pools = 中过奖的池号（升序）；win_rate = 中过奖的轮数 / 轮数
 *     'pools' => [
 *         1 => ['enabled'=>bool,'chance'=>int,'trigger_rate'=>float,'winner_mode'=>string,
 *               'winner_count'=>int,'items'=>int,'winners_per_round'=>float,
 *               'top_winners'=>[['name'=>string,'class_id'=>int,'hits'=>float], ...]], ...
 *     ],  // 键 = 池号 1..6（含未启用的池）；hits = 该玩家在该池的平均中奖次数 / 轮
 *     'last_round' => [
 *         ['pool'=>1,'triggered'=>bool,
 *          'grants'=>[['name'=>string,'class_id'=>int,'item_id'=>int,'item_name'=>string], ...]], ...
 *     ],  // 固定 6 条（池 1..6），只为最后一轮
 *     'notes' => [string, ...],  // 中文提示（世界库不可用这类降级说明）
 *   ]
 */
class RewardPoolSimulator
{
    
    private const POOL_COUNT = 6;

    
    private const DEFAULT_WEIGHTS = [
        'damage' => 100,
        'healing' => 80,
        'threat' => 35,
        'presence' => 10,
        'kill' => 3,
    ];

    
    private const DEFAULT_TOP_WINNERS = 10;

    
    private const NOTE_NO_WORLD = '世界库不可用 → 未在职业映射中的奖品按可用处理';

    
    private const NOTE_WORLD_READ_FAILED = 'world.item_template 读取失败 → 未在职业映射中的奖品按可用处理';

    private ?PDO $world;

    
    private array $options;

    
    private array $usabilityCache = [];

    
    private array $notes = [];

    
    private ?int $rngState = null;

    /**
     * @param PDO|null $world   仅用于 class_filter 的 item_template 兜底检查与物品名兜底；为 null 时优雅降级。
     * @param array<string,mixed> $options 目前支持：
     *                          - 'random_reward_mode' => 'weighted'|'random'（$weights['mode'] 缺失时用它，默认 weighted）
     *                          - 'top_winners' => int（每个池最多列几人，默认 10，0 = 不限）
     */
    public function __construct(?PDO $world = null, array $options = [])
    {
        $this->world = $world;
        $this->options = $options;
    }

    /**
     * 模拟 $rounds 轮击杀结算。
     *
     * @param array<string,mixed> $pools         面板存的原始 ext 配置数组（reward_pool_N_{enabled,chance,winner_mode,winner_count,class_filter,items_text}）
     * @param array<int,array<string,mixed>> $participants 每位参战者：
     *        ['guid'=>int,'name'=>string,'classId'=>int,'level'=>int,'damage'=>int,'healing'=>int,
     *         'threat'=>int,'presence'=>int,'is_killer'=>bool]
     *        给了 'score' => float 时按现成分数用，不再按分项重算。
     * @param array<int,array<int,int>> $classItemMap [classId => [itemId, ...]]（已从 class_reward_items_text 解析好）
     * @param array<string,mixed> $weights ['damage'=>100,'healing'=>80,'threat'=>35,'presence'=>10,'kill'=>3,'mode'=>'weighted']
     * @param int $rounds 轮数（<1 按 1 处理）
     * @param int|null $seed 传了就结果可复现（自带 LCG），不传用 random_int
     * @return array<string,mixed> 见类 docblock
     */
    public function simulate(
        array $pools,
        array $participants,
        array $classItemMap,
        array $weights,
        int $rounds = 1,
        ?int $seed = null
    ): array {
        $rounds = max(1, $rounds);
        $this->notes = [];
        $this->usabilityCache = [];
        $this->rngState = $seed === null ? null : (($seed & 0x7FFFFFFF) | 1);

        $contributors = $this->prepareParticipants($participants, $weights);
        $classIndex = $this->buildClassItemIndex($classItemMap);
        $specs = $this->normalizePools($pools);
        $mode = $this->resolveMode($weights, $pools);
        $topWinnersLimit = max(0, (int) ($this->options['top_winners'] ?? self::DEFAULT_TOP_WINNERS));
        $participantCount = count($contributors);

        $grantTotal = array_fill(0, $participantCount, 0);
        $rewardedRounds = array_fill(0, $participantCount, 0);
        $poolsWon = array_fill(0, $participantCount, []);

        
        $poolStats = [];
        foreach ($specs as $index => $spec) {
            if ($spec['enabled'] && $spec['items'] === []) {
                
                $this->note(sprintf('奖池%d 已启用但没有奖品，跳过（不参与触发判定）', $index));
            }

            $poolStats[$index] = [
                'enabled' => $spec['enabled'],
                'chance' => $spec['chance'],
                'winner_mode' => $spec['winner_mode'],
                'winner_count' => $spec['winner_count'],
                'items' => count($spec['items']),
                'triggered_rounds' => 0,
                'grants' => 0,
                'winners' => [],
            ];
        }

        $lastRound = [];

        for ($round = 0; $round < $rounds; $round++) {
            $roundResult = [];

            foreach ($specs as $index => $spec) {
                $entry = ['pool' => $index, 'triggered' => false, 'grants' => []];

                
                if (
                    $spec['enabled']
                    && $spec['items'] !== []
                    && $this->randInt(1, 100) <= $spec['chance']
                ) {
                    $entry['triggered'] = true;
                    $poolStats[$index]['triggered_rounds']++;

                    $touched = [];
                    foreach ($this->selectRecipients($spec, $contributors, $mode) as $position) {
                        $itemId = $this->pickPoolItem($spec, $contributors[$position], $classIndex);
                        if ($itemId === null) {
                            continue; 
                        }

                        $entry['grants'][] = [
                            'name' => $contributors[$position]['name'],
                            'class_id' => $contributors[$position]['class_id'],
                            'item_id' => $itemId,
                            'item_name' => '', // 最后一轮跑完后一次性补名，避免逐轮查名
                        ];

                        $grantTotal[$position]++;
                        $touched[$position] = true;
                        $poolStats[$index]['grants']++;
                        $poolStats[$index]['winners'][$position] =
                            ($poolStats[$index]['winners'][$position] ?? 0) + 1;
                        $poolsWon[$position][$index] = true;
                    }

                    foreach (array_keys($touched) as $position) {
                        $rewardedRounds[$position]++;
                    }
                }

                $roundResult[] = $entry;
            }

            $lastRound = $roundResult;
        }

        $lastRound = $this->fillItemNames($lastRound);

        $participantOutput = [];
        foreach ($contributors as $position => $contributor) {
            $pools = array_keys($poolsWon[$position]);
            sort($pools);

            $participantOutput[] = [
                'guid' => $contributor['guid'],
                'name' => $contributor['name'],
                'class_id' => $contributor['class_id'],
                'score' => round($contributor['score'], 6),
                'items_per_round' => round($grantTotal[$position] / $rounds, 4),
                'win_rate' => round($rewardedRounds[$position] / $rounds, 4),
                'pools' => $pools,
            ];
        }

        $poolOutput = [];
        foreach ($poolStats as $index => $stats) {
            $top = [];
            foreach ($stats['winners'] as $position => $hits) {
                $top[] = [
                    'name' => $contributors[$position]['name'],
                    'class_id' => $contributors[$position]['class_id'],
                    'hits' => round($hits / $rounds, 4),
                ];
            }

            usort($top, static fn (array $a, array $b): int => $b['hits'] <=> $a['hits']);
            if ($topWinnersLimit > 0) {
                $top = array_slice($top, 0, $topWinnersLimit);
            }

            $poolOutput[$index] = [
                'enabled' => $stats['enabled'],
                'chance' => $stats['chance'],
                'trigger_rate' => round($stats['triggered_rounds'] / $rounds, 4),
                'winner_mode' => $stats['winner_mode'],
                'winner_count' => $stats['winner_count'],
                'items' => $stats['items'],
                'winners_per_round' => round($stats['grants'] / $rounds, 4),
                'top_winners' => $top,
            ];
        }

        return [
            'rounds' => $rounds,
            'participants' => $participantOutput,
            'pools' => $poolOutput,
            'last_round' => $lastRound,
            'notes' => array_keys($this->notes),
        ];
    }

    /**
     * 物品 ID → 物品名（奖品预览用），与 BossRepository::itemNames() 同一套路：
     * 先走 GameNameResolver（world 库 + 按区/语言的磁盘缓存），查不到的再补一次
     * world 库 item_template；两边都查不到时回落到 "#ID"。
     *
     * @param array<int,int|string> $itemIds
     * @return array<int,string> id => 显示名
     */
    public function itemNames(array $itemIds): array
    {
        $ids = [];
        foreach ($itemIds as $itemId) {
            $itemId = (int) $itemId;
            if ($itemId > 0) {
                $ids[$itemId] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            return [];
        }

        try {
            $names = GameNameResolver::resolveMany('item', $ids);
        } catch (Throwable $exception) {
            $names = [];
        }

        $missing = array_values(array_filter(
            $ids,
            static fn (int $id): bool => !isset($names[$id])
        ));

        if ($missing !== [] && $this->world !== null) {
            try {
                $placeholders = implode(',', array_fill(0, count($missing), '?'));
                $stmt = $this->world->prepare(
                    'SELECT entry, name FROM item_template WHERE entry IN (' . $placeholders . ')'
                );
                $stmt->execute($missing);
                foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                    $names[(int) $row['entry']] = (string) $row['name'];
                }
            } catch (Throwable $exception) {
                $this->note(self::NOTE_WORLD_READ_FAILED);
            }
        }

        $resolved = [];
        foreach ($ids as $id) {
            $name = trim((string) ($names[$id] ?? ''));
            $resolved[$id] = $name !== '' ? $name : '#' . $id;
        }

        return $resolved;
    }

    
    
    

    /**
     * 归一参战者并算好每人分数：给了 'score' 就原样用，否则按 Lua 的 ComputeContributionScore 算。
     *
     * @param array<int,array<string,mixed>> $participants
     * @param array<string,mixed> $weights
     * @return array<int,array<string,mixed>>
     */
    private function prepareParticipants(array $participants, array $weights): array
    {
        $fields = ['damage', 'healing', 'threat', 'presence'];
        $totals = array_fill_keys($fields, 0);
        $prepared = [];

        foreach ($participants as $participant) {
            if (!is_array($participant)) {
                continue;
            }

            $record = [
                'guid' => (int) ($participant['guid'] ?? 0),
                'name' => trim((string) ($participant['name'] ?? '')),
                'class_id' => (int) ($participant['classId'] ?? $participant['class_id'] ?? 0),
                'level' => max(0, (int) ($participant['level'] ?? 0)),
                'is_killer' => $this->toFlag(
                    $participant['is_killer'] ?? $participant['isKiller'] ?? false
                ),
                'score' => null,
            ];

            foreach ($fields as $field) {
                $record[$field] = max(0, (int) ($participant[$field] ?? 0));
                $totals[$field] += $record[$field];
            }

            if (array_key_exists('score', $participant) && is_numeric($participant['score'])) {
                $record['score'] = (float) $participant['score'];
            }

            if ($record['name'] === '') {
                $record['name'] = $record['guid'] > 0 ? '#' . $record['guid'] : '?';
            }

            $prepared[] = $record;
        }

        foreach ($prepared as &$record) {
            if ($record['score'] === null) {
                $record['score'] = $this->computeScore($record, $totals, $weights);
            }
        }
        unset($record);

        return $prepared;
    }

    /**
     * 贡献分 = Σ(分项占总量比例 × 权重) + 杀手加成（= boss.lua ComputeContributionScore）。
     *
     * @param array<string,mixed> $record
     * @param array<string,int> $totals
     * @param array<string,mixed> $weights
     */
    private function computeScore(array $record, array $totals, array $weights): float
    {
        $score = 0.0;

        foreach (self::DEFAULT_WEIGHTS as $field => $default) {
            if ($field === 'kill') {
                continue;
            }

            $total = (float) ($totals[$field] ?? 0);
            if ($total <= 0.0) {
                continue;
            }

            $weight = is_numeric($weights[$field] ?? null)
                ? (float) $weights[$field]
                : (float) $default;

            $score += ((float) $record[$field] / $total) * $weight;
        }

        if ($record['is_killer'] === true) {
            $score += is_numeric($weights['kill'] ?? null)
                ? (float) $weights['kill']
                : (float) self::DEFAULT_WEIGHTS['kill'];
        }

        return $score;
    }

    /**
     * 抽取模式（对应 Lua 的 REWARD_PROBABILITIES.randomRewardMode）：
     * $weights['mode'] 优先，其次 $pools['random_reward_mode']（调用方把主配置并进来时用得上），
     * 再次构造参数 random_reward_mode，默认 weighted。
     *
     * @param array<string,mixed> $weights
     * @param array<string,mixed> $pools
     */
    private function resolveMode(array $weights, array $pools): string
    {
        $mode = $weights['mode']
            ?? $pools['random_reward_mode']
            ?? $this->options['random_reward_mode']
            ?? 'weighted';

        return strtolower(trim((string) $mode)) === 'random' ? 'random' : 'weighted';
    }

    
    
    

    /**
     * 把面板的原始 ext 数组归一成 1..6 的池描述（规则 = boss.lua NormalizeRewardPools）：
     * chance 截到 0..100，winner_count 截到 1..100，winner_mode 只认 all/count，
     * class_filter 缺省视为 true（Lua: pool.classFilter ~= false），items 取文本里所有正整数并去重。
     *
     * @param array<string,mixed> $pools
     * @return array<int,array{enabled:bool,chance:int,winner_mode:string,winner_count:int,class_filter:bool,items:array<int,int>}>
     */
    private function normalizePools(array $pools): array
    {
        $specs = [];

        for ($index = 1; $index <= self::POOL_COUNT; $index++) {
            $prefix = 'reward_pool_' . $index . '_';
            $classFilterKey = $prefix . 'class_filter';
            $mode = strtolower(trim((string) ($pools[$prefix . 'winner_mode'] ?? '')));

            $specs[$index] = [
                'enabled' => $this->toFlag($pools[$prefix . 'enabled'] ?? 0),
                'chance' => $this->clampInt($pools[$prefix . 'chance'] ?? 0, 0, 100),
                'winner_mode' => $mode === 'all' ? 'all' : 'count',
                'winner_count' => $this->clampInt($pools[$prefix . 'winner_count'] ?? 1, 1, 100),
                'class_filter' => $this->toFlag($pools[$classFilterKey] ?? true, true),
                'items' => $this->parseItemList((string) ($pools[$prefix . 'items_text'] ?? '')),
            ];
        }

        return $specs;
    }

    /**
     * 奖品列表解析 = Lua ParsePositiveIntegerList：抓出文本里所有数字串，>0 且去重，保持出现顺序。
     *
     * @return array<int,int>
     */
    private function parseItemList(string $text): array
    {
        if ($text === '' || !preg_match_all('/\d+/', $text, $matches)) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach ($matches[0] as $token) {
            $itemId = (int) $token;
            if ($itemId > 0 && !isset($seen[$itemId])) {
                $seen[$itemId] = true;
                $items[] = $itemId;
            }
        }

        return $items;
    }

    /**
     * 定获奖名单（= boss.lua SelectWeightedRewardWinners 的外层调用）。
     *
     * all   → 全部参战者（顺序 = 调用方传入顺序）。
     * count → min(winner_count, 参战数) 人，不放回抽样；weighted 用 max(0.01, 分数) 作权重，
     *         random 用等概率。抽之前按分数降序（同分看伤害）排序，与 Lua 的贡献池排序一致。
     *
     * @param array<string,mixed> $spec
     * @param array<int,array<string,mixed>> $contributors
     * @return array<int,int> 参战者在 $contributors 里的下标
     */
    private function selectRecipients(array $spec, array $contributors, string $mode): array
    {
        $count = count($contributors);
        if ($count === 0) {
            return [];
        }

        if ($spec['winner_mode'] === 'all') {
            return range(0, $count - 1);
        }

        $limit = min($spec['winner_count'], $count);
        $pool = $this->sortedContributorOrder($contributors);
        $selected = [];

        while (count($selected) < $limit && $pool !== []) {
            if ($mode === 'random') {
                $position = $this->randInt(1, count($pool)) - 1;
                $selected[] = $pool[$position];
                array_splice($pool, $position, 1);
                continue;
            }

            $totalWeight = 0.0;
            foreach ($pool as $candidate) {
                $totalWeight += max(0.01, $contributors[$candidate]['score']);
            }

            $threshold = $this->randFloat() * $totalWeight;
            $cursor = 0.0;
            $position = count($pool) - 1; 

            foreach ($pool as $offset => $candidate) {
                $cursor += max(0.01, $contributors[$candidate]['score']);
                if ($threshold <= $cursor) {
                    $position = $offset;
                    break;
                }
            }

            $selected[] = $pool[$position];
            array_splice($pool, $position, 1);
        }

        return $selected;
    }

    /**
     * 参战者下标按分数降序排列（同分比伤害），镜像 Lua 贡献池的 table.sort。
     *
     * @param array<int,array<string,mixed>> $contributors
     * @return array<int,int>
     */
    private function sortedContributorOrder(array $contributors): array
    {
        $order = array_keys($contributors);

        usort($order, static function (int $a, int $b) use ($contributors): int {
            $left = (float) $contributors[$a]['score'];
            $right = (float) $contributors[$b]['score'];

            if (abs($left - $right) < 0.0001) {
                return $contributors[$b]['damage'] <=> $contributors[$a]['damage'];
            }

            return $right <=> $left;
        });

        return $order;
    }

    /**
     * 从池里给某位获奖者挑 1 件他能用的物品（= boss.lua PickRewardPoolItemFor）；
     * 候选为空返回 null（本次跳过）。
     *
     * @param array<string,mixed> $spec
     * @param array<string,mixed> $contributor
     * @param array<int,array<int,bool>> $classIndex
     */
    private function pickPoolItem(array $spec, array $contributor, array $classIndex): ?int
    {
        $candidates = [];

        foreach ($spec['items'] as $itemId) {
            if ($spec['class_filter'] === false || $this->isItemUsable($itemId, $contributor, $classIndex)) {
                $candidates[] = $itemId;
            }
        }

        if ($candidates === []) {
            return null;
        }

        return $candidates[$this->randInt(0, count($candidates) - 1)];
    }

    /**
     * 职业奖励映射的反向索引（= boss.lua BuildClassItemIndex）：itemId => [classId => true]。
     *
     * @param array<int,array<int,int|string>> $classItemMap
     * @return array<int,array<int,bool>>
     */
    private function buildClassItemIndex(array $classItemMap): array
    {
        $index = [];

        foreach ($classItemMap as $classId => $itemIds) {
            $classId = (int) $classId;
            if ($classId <= 0 || !is_array($itemIds)) {
                continue;
            }

            foreach ($itemIds as $itemId) {
                $itemId = (int) $itemId;
                if ($itemId > 0) {
                    $index[$itemId][$classId] = true;
                }
            }
        }

        return $index;
    }

    /**
     * 该玩家能不能拿这件奖品（= boss.lua IsItemUsableByPlayer）。
     *
     * @param array<string,mixed> $contributor
     * @param array<int,array<int,bool>> $classIndex
     */
    private function isItemUsable(int $itemId, array $contributor, array $classIndex): bool
    {
        if ($itemId <= 0) {
            return false;
        }

        
        if (isset($classIndex[$itemId])) {
            $classId = $contributor['class_id'];

            return $classId > 0 && isset($classIndex[$itemId][$classId]);
        }

        
        return $this->coreUsableCheck($itemId, $contributor['class_id'], $contributor['level']);
    }

    /**
     * 用 world 库 item_template 复刻核心的 Player:CanUseItem 结论（只覆盖职业/等级两项限制）：
     *   - RequiredLevel > 0 且玩家等级更低 → 不能用
     *   - AllowableClass 为 0 或 -1 → 全职业可用；否则按 1 << (classId - 1) 位掩码判职业
     *   - 行缺失 / 读不动 / 等级或职业未知 → 按"可用"处理（宁可不卡，也不让奖池整体发不出东西）
     */
    private function coreUsableCheck(int $itemId, int $classId, int $level): bool
    {
        $row = $this->itemTemplateUsability($itemId);
        if ($row === null) {
            return true;
        }

        $requiredLevel = $row['required_level'];
        if ($requiredLevel > 0 && $level > 0 && $level < $requiredLevel) {
            return false;
        }

        $allowableClass = $row['allowable_class'];
        if ($allowableClass === 0 || $allowableClass === -1) {
            return true;
        }

        if ($classId <= 0) {
            return true; 
        }

        return ($allowableClass & (1 << ($classId - 1))) !== 0;
    }

    /**
     * 读 item_template 的 AllowableClass / RequiredLevel（带缓存）。
     * 返回 null = 查不到行或查不动，调用方按"可用"处理。
     *
     * @return array{allowable_class:int,required_level:int}|null
     */
    private function itemTemplateUsability(int $itemId): ?array
    {
        if (array_key_exists($itemId, $this->usabilityCache)) {
            return $this->usabilityCache[$itemId];
        }

        if ($this->world === null) {
            $this->note(self::NOTE_NO_WORLD);

            return $this->usabilityCache[$itemId] = null;
        }

        try {
            $stmt = $this->world->prepare(
                'SELECT AllowableClass, RequiredLevel FROM item_template WHERE entry = ? LIMIT 1'
            );
            $stmt->execute([$itemId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                return $this->usabilityCache[$itemId] = null;
            }

            return $this->usabilityCache[$itemId] = [
                'allowable_class' => (int) ($row['AllowableClass'] ?? -1),
                'required_level' => (int) ($row['RequiredLevel'] ?? 0),
            ];
        } catch (Throwable $exception) {
            $this->note(self::NOTE_WORLD_READ_FAILED);

            return $this->usabilityCache[$itemId] = null;
        }
    }

    
    
    

    /**
     * 给最后一轮的中奖记录补物品名（一次性批量解析，避免逐轮/逐件查库）。
     *
     * @param array<int,array<string,mixed>> $lastRound
     * @return array<int,array<string,mixed>>
     */
    private function fillItemNames(array $lastRound): array
    {
        $ids = [];
        foreach ($lastRound as $entry) {
            foreach (($entry['grants'] ?? []) as $grant) {
                $ids[(int) $grant['item_id']] = true;
            }
        }

        $names = $ids === [] ? [] : $this->itemNames(array_keys($ids));

        foreach ($lastRound as &$entry) {
            foreach ($entry['grants'] as &$grant) {
                $itemId = (int) $grant['item_id'];
                $grant['item_name'] = $names[$itemId] ?? ('#' . $itemId);
            }
            unset($grant);
        }
        unset($entry);

        return $lastRound;
    }

    private function note(string $message): void
    {
        if ($message !== '' && !isset($this->notes[$message])) {
            $this->notes[$message] = true;
        }
    }

    
    
    

    // 开关值归一：接受 bool / 0/1 / '0'/'1' / 'on'/'true'/'yes'；null 或空串用 $default。

    private function toFlag(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_string($value) && !is_numeric($value)) {
            return in_array(strtolower(trim($value)), ['true', 'on', 'yes'], true);
        }

        return (int) $value !== 0;
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $min;
        }

        return max($min, min($max, (int) $value));
    }

    // 池内计数/概率取整的随机数：不传 seed 时 random_int，传 seed 时用自带 LCG（可复现）。

    private function randInt(int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        if ($this->rngState === null) {
            try {
                return random_int($min, $max);
            } catch (Throwable $exception) {
                return mt_rand($min, $max);
            }
        }

        return $min + (int) floor($this->nextUnit() * ($max - $min + 1));
    }

    // [0, 1) 的随机浮点，对应 Lua 的 math.random()（用于加权抽样的 threshold）。

    private function randFloat(): float
    {
        if ($this->rngState === null) {
            try {
                return random_int(0, PHP_INT_MAX - 1) / (float) PHP_INT_MAX;
            } catch (Throwable $exception) {
                return mt_rand() / (mt_getrandmax() + 1);
            }
        }

        return $this->nextUnit();
    }

    // 自带 32 位线性同余发生器：同一个 seed 得到同一串结果，

    private function nextUnit(): float
    {
        $this->rngState = (int) ((1103515245 * (int) $this->rngState + 12345) & 0x7FFFFFFF);

        return $this->rngState / 2147483648.0;
    }
}
