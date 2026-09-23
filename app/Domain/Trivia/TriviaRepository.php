<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Trivia;

use Acme\Panel\Core\Config;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\GameNameResolver;
use Acme\Panel\Support\Paginator;
use PDO;
use Throwable;

/**
 * 聊天答题（TriviaReward.lua）在 ac_eluna 下的数据访问层。
 *
 * 四张表全部由 Lua 脚本建表并初始化：
 *   trivia_reward_settings   单行配置（id = 1）
 *   trivia_reward_questions  题库
 *   trivia_reward_presets    奖励预设
 *   trivia_reward_winners    答对排行
 */
class TriviaRepository extends MultiServerRepository
{
    /**
     * 设置列 => 类型（与 TriviaReward.lua 的 SETTING_FIELDS 一一对应，改一边必须改另一边）。
     */
    public const SETTINGS_COLUMNS = [
        'enabled' => 'bool',
        'interval_seconds' => 'int',
        'answer_seconds' => 'int',
        'remind_every_seconds' => 'int',
        'first_delay_seconds' => 'int',
        'min_players_online' => 'int',
        'min_level' => 'int',
        'answer_say' => 'bool',
        'answer_yell' => 'bool',
        'answer_emote' => 'bool',
        'answer_whisper' => 'bool',
        'answer_channel_ids' => 'string',
        'answer_prefix' => 'string',
        'allow_number_answer' => 'bool',
        'allow_latin_letters' => 'bool',
        'allow_text_answer' => 'bool',
        'attempts_per_player' => 'int',
        'option_labels' => 'string',
        'option_format' => 'string',
        'reward_mode' => 'string',
        'default_reward_preset' => 'string',
        'pool_presets' => 'string',
        'use_builtin_questions' => 'bool',
        'announce_on_login' => 'bool',
        'reply_wrong_answer' => 'bool',
        'reply_already_answered' => 'bool',
        'min_gm_rank_for_command' => 'int',
        'sender_guid' => 'int',
        'mail_stationery' => 'int',
        'item_link_locale' => 'int',
        'mail_subject' => 'string',
        'mail_body' => 'string',
    ];

    private string $customDbName;

    /** @var array<string,string> */
    private array $tables;

    /** @var array<string,bool>|null */
    private ?array $tableAvailability = null;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);

        $this->customDbName = (string) Config::get('trivia.custom_db_name', 'ac_eluna');
        $this->tables = [
            'settings' => (string) Config::get('trivia.settings_table', 'trivia_reward_settings'),
            'questions' => (string) Config::get('trivia.questions_table', 'trivia_reward_questions'),
            'presets' => (string) Config::get('trivia.presets_table', 'trivia_reward_presets'),
            'winners' => (string) Config::get('trivia.winners_table', 'trivia_reward_winners'),
        ];
    }

    public function customDbName(): string
    {
        return $this->customDbName;
    }

    /**
     * 全限定表名（跨库访问：面板用 characters 连接，但表在 ac_eluna 里）。
     */
    public function table(string $key): string
    {
        $name = $this->tables[$key] ?? $key;

        return '`' . str_replace('`', '', $this->customDbName) . '`.`' . str_replace('`', '', $name) . '`';
    }

    public function tableName(string $key): string
    {
        return $this->tables[$key] ?? $key;
    }

    public function tableExists(string $key): bool
    {
        $availability = $this->availability();

        return $availability[$key] ?? false;
    }

    /**
     * 四张表是否都已建好；Lua 脚本没跑过（或没建库）时页面只读展示提示。
     *
     * @return array{ready:bool,partial:bool,tables:array<string,bool>,missing:array<int,string>}
     */
    public function schemaStatus(): array
    {
        $availability = $this->availability();
        $missing = [];
        foreach ($availability as $key => $exists) {
            if (!$exists) {
                $missing[] = $this->tableName($key);
            }
        }

        return [
            'ready' => $missing === [],
            'partial' => $missing !== [] && count($missing) < count($availability),
            'tables' => $availability,
            'missing' => $missing,
        ];
    }

    /**
     * @return array<string,bool>
     */
    private function availability(): array
    {
        if ($this->tableAvailability !== null) {
            return $this->tableAvailability;
        }

        $names = array_values($this->tables);
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $found = [];

        try {
            $stmt = $this->characters()->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (' . $placeholders . ')'
            );
            $stmt->execute(array_merge([$this->customDbName], $names));
            foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $tableName) {
                $found[(string) $tableName] = true;
            }
        } catch (Throwable $exception) {
            $found = [];
        }

        $availability = [];
        foreach ($this->tables as $key => $name) {
            $availability[$key] = isset($found[$name]);
        }

        return $this->tableAvailability = $availability;
    }

    // ------------------------------------------------------------------ 设置

    /**
     * @return array<string,mixed>
     */
    public function settings(): array
    {
        if (!$this->tableExists('settings')) {
            return [];
        }

        $stmt = $this->characters()->query('SELECT * FROM ' . $this->table('settings') . ' WHERE `id` = 1');
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * 逐列更新设置；$values 里没出现的列保持不动。
     *
     * 行被手工删过时用 INSERT IGNORE 补一行（不会覆盖已有行）。
     *
     * @param array<string,mixed> $values
     */
    public function saveSettings(array $values): bool
    {
        if (!$this->tableExists('settings')) {
            throw new \RuntimeException('trivia_settings_storage_missing');
        }

        // 按 SETTINGS_COLUMNS 的顺序落地，保证 UPDATE 与 INSERT 两边的列值一一对应
        $pairs = [];
        foreach (self::SETTINGS_COLUMNS as $column => $type) {
            if (!array_key_exists($column, $values)) {
                continue;
            }
            $pairs[$column] = $this->castValue($type, $values[$column]);
        }

        if ($pairs === []) {
            return false;
        }

        $sets = [];
        $params = [];
        foreach ($pairs as $column => $value) {
            $sets[] = '`' . $column . '` = ?';
            $params[] = $value;
        }
        $sets[] = '`updated_at` = ?';
        $params[] = time();

        $stmt = $this->characters()->prepare(
            'UPDATE ' . $this->table('settings') . ' SET ' . implode(', ', $sets) . ' WHERE `id` = 1'
        );
        $stmt->execute($params);

        $insertColumns = ['`id`'];
        $placeholders = ['1'];
        foreach (array_keys($pairs) as $column) {
            $insertColumns[] = '`' . $column . '`';
            $placeholders[] = '?';
        }

        $insert = $this->characters()->prepare(
            'INSERT IGNORE INTO ' . $this->table('settings') . ' (' . implode(',', $insertColumns) . ') VALUES (' . implode(',', $placeholders) . ')'
        );
        $insert->execute(array_values($pairs));

        return true;
    }

    private function castValue(string $type, mixed $value): mixed
    {
        if ($type === 'bool') {
            return ((int) $value) === 1 ? 1 : 0;
        }

        if ($type === 'int') {
            return (int) $value;
        }

        return (string) $value;
    }

    // ------------------------------------------------------------------ 题库

    /**
     * @param array<string,mixed> $filters
     */
    public function listQuestions(array $filters, int $page, int $perPage): Paginator
    {
        if (!$this->tableExists('questions')) {
            return new Paginator([], 0, $page, $perPage);
        }

        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(`question` LIKE ? OR `option1` LIKE ? OR `option2` LIKE ? OR `option3` LIKE ? OR `option4` LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'enabled') {
            $where[] = '`enabled` = 1';
        } elseif ($status === 'disabled') {
            $where[] = '`enabled` = 0';
        }

        $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

        $countStmt = $this->characters()->prepare('SELECT COUNT(*) FROM ' . $this->table('questions') . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        if ($total <= 0) {
            return new Paginator([], 0, $page, $perPage);
        }

        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT * FROM ' . $this->table('questions') . $whereSql
            . ' ORDER BY `enabled` DESC, `sort_order` ASC, `id` ASC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $this->characters()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return new Paginator(array_map([$this, 'normalizeQuestionRow'], $rows), $total, $page, $perPage);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findQuestion(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('questions')) {
            return null;
        }

        $stmt = $this->characters()->prepare('SELECT * FROM ' . $this->table('questions') . ' WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->normalizeQuestionRow($row) : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return int 题目 ID（新增时是自增 ID）
     */
    public function saveQuestion(array $data): int
    {
        if (!$this->tableExists('questions')) {
            throw new \RuntimeException('trivia_questions_storage_missing');
        }

        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'question' => (string) ($data['question'] ?? ''),
            'option1' => (string) ($data['option1'] ?? ''),
            'option2' => (string) ($data['option2'] ?? ''),
            'option3' => (string) ($data['option3'] ?? ''),
            'option4' => (string) ($data['option4'] ?? ''),
            'answer_index' => (int) ($data['answer_index'] ?? 1),
            'labels' => (string) ($data['labels'] ?? ''),
            'reward_preset' => (string) ($data['reward_preset'] ?? ''),
            'reward_items' => (string) ($data['reward_items'] ?? ''),
            'reward_money' => (int) ($data['reward_money'] ?? 0),
            'enabled' => ((int) ($data['enabled'] ?? 1)) === 1 ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => time(),
        ];

        if ($id > 0) {
            $sets = [];
            $params = [];
            foreach ($payload as $column => $value) {
                $sets[] = '`' . $column . '` = ?';
                $params[] = $value;
            }
            $params[] = $id;
            $stmt = $this->characters()->prepare(
                'UPDATE ' . $this->table('questions') . ' SET ' . implode(', ', $sets) . ' WHERE `id` = ?'
            );
            $stmt->execute($params);

            return $id;
        }

        $columns = array_keys($payload);
        $sql = 'INSERT INTO ' . $this->table('questions')
            . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $stmt = $this->characters()->prepare($sql);
        $stmt->execute(array_values($payload));

        return (int) $this->characters()->lastInsertId();
    }

    public function deleteQuestion(int $id): bool
    {
        if ($id <= 0 || !$this->tableExists('questions')) {
            return false;
        }

        $stmt = $this->characters()->prepare('DELETE FROM ' . $this->table('questions') . ' WHERE `id` = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 批量插入题目（模板导入用），单事务，出错整体回滚。
     *
     * @param array<int,array<string,mixed>> $rows
     * @return int 实际写入条数
     */
    public function insertQuestions(array $rows, string $source = 'import'): int
    {
        if ($rows === [] || !$this->tableExists('questions')) {
            return 0;
        }

        $pdo = $this->characters();
        $columns = [
            'question', 'option1', 'option2', 'option3', 'option4', 'answer_index', 'labels',
            'reward_preset', 'reward_items', 'reward_money', 'enabled', 'sort_order', 'source',
            'updated_at', 'created_at',
        ];
        $sql = 'INSERT INTO ' . $this->table('questions')
            . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $stmt = $pdo->prepare($sql);

        $timestamp = time();
        $inserted = 0;

        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    (string) ($row['question'] ?? ''),
                    (string) ($row['option1'] ?? ''),
                    (string) ($row['option2'] ?? ''),
                    (string) ($row['option3'] ?? ''),
                    (string) ($row['option4'] ?? ''),
                    (int) ($row['answer_index'] ?? 1),
                    (string) ($row['labels'] ?? ''),
                    (string) ($row['reward_preset'] ?? ''),
                    (string) ($row['reward_items'] ?? ''),
                    (int) ($row['reward_money'] ?? 0),
                    ((int) ($row['enabled'] ?? 1)) === 1 ? 1 : 0,
                    (int) ($row['sort_order'] ?? 0),
                    mb_substr($source, 0, 16),
                    $timestamp,
                    $timestamp,
                ]);
                $inserted++;
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $inserted;
    }

    /**
     * 导出全部题目（不分页）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function allQuestions(): array
    {
        if (!$this->tableExists('questions')) {
            return [];
        }

        $rows = $this->characters()
            ->query('SELECT * FROM ' . $this->table('questions') . ' ORDER BY `enabled` DESC, `sort_order` ASC, `id` ASC')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'normalizeQuestionRow'], $rows);
    }

    /**
     * 按来源统计（面板显示"哪些是模板导入 / 种子题库"）。
     *
     * @return array<string,int>
     */
    public function questionSourceStats(): array
    {
        if (!$this->tableExists('questions')) {
            return [];
        }

        $rows = $this->characters()
            ->query('SELECT `source`, COUNT(*) AS `c` FROM ' . $this->table('questions') . ' GROUP BY `source`')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stats = [];
        foreach ($rows as $row) {
            $stats[(string) ($row['source'] ?? '')] = (int) ($row['c'] ?? 0);
        }

        return $stats;
    }

    public function setQuestionEnabled(int $id, bool $enabled): bool
    {
        if ($id <= 0 || !$this->tableExists('questions')) {
            return false;
        }

        $stmt = $this->characters()->prepare(
            'UPDATE ' . $this->table('questions') . ' SET `enabled` = ?, `updated_at` = ? WHERE `id` = ?'
        );
        $stmt->execute([$enabled ? 1 : 0, time(), $id]);

        // 不能用 rowCount() 判断题目是否存在：MySQL 在写入值与现值相同时返回 0 行受影响，
        // 重复点"启用/停用"（或已经是目标状态）就会被误报成"找不到这道题"。
        $check = $this->characters()->prepare(
            'SELECT COUNT(*) FROM ' . $this->table('questions') . ' WHERE `id` = ?'
        );
        $check->execute([$id]);

        return ((int) $check->fetchColumn()) > 0;
    }

    public function questionStats(): array
    {
        if (!$this->tableExists('questions')) {
            return ['total' => 0, 'enabled' => 0, 'disabled' => 0, 'with_reward' => 0];
        }

        $sql = 'SELECT COUNT(*) AS total,'
            . ' SUM(CASE WHEN `enabled` = 1 THEN 1 ELSE 0 END) AS enabled_count,'
            . ' SUM(CASE WHEN `enabled` = 0 THEN 1 ELSE 0 END) AS disabled_count,'
            . ' SUM(CASE WHEN `reward_preset` <> "" OR `reward_items` <> "" OR `reward_money` > 0 THEN 1 ELSE 0 END) AS reward_count'
            . ' FROM ' . $this->table('questions');
        $row = $this->characters()->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'enabled' => (int) ($row['enabled_count'] ?? 0),
            'disabled' => (int) ($row['disabled_count'] ?? 0),
            'with_reward' => (int) ($row['reward_count'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeQuestionRow(array $row): array
    {
        $options = [];
        for ($i = 1; $i <= 4; $i++) {
            $options[$i] = (string) ($row['option' . $i] ?? '');
        }

        $items = $this->parseItemsText((string) ($row['reward_items'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'question' => (string) ($row['question'] ?? ''),
            'option1' => $options[1],
            'option2' => $options[2],
            'option3' => $options[3],
            'option4' => $options[4],
            'options' => array_values(array_filter($options, static fn(string $v): bool => $v !== '')),
            'answer_index' => (int) ($row['answer_index'] ?? 1),
            'answer_text' => $options[(int) ($row['answer_index'] ?? 1)] ?? '',
            'labels' => (string) ($row['labels'] ?? ''),
            'reward_preset' => (string) ($row['reward_preset'] ?? ''),
            'reward_items' => (string) ($row['reward_items'] ?? ''),
            'reward_items_parsed' => $items,
            'reward_money' => (int) ($row['reward_money'] ?? 0),
            'enabled' => ((int) ($row['enabled'] ?? 1)) === 1,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'updated_at' => (int) ($row['updated_at'] ?? 0),
        ];
    }

    /**
     * "33470:5,33447:2" / "33470:5;33447 x2" → [['entry'=>33470,'count'=>5], ...]
     *
     * 只按逗号/分号切分：条目内部的空格（"33447 x2"）要保留给模式匹配。
     *
     * @return array<int,array{entry:int,count:int}>
     */
    public function parseItemsText(string $text): array
    {
        $items = [];
        $text = trim($text);
        if ($text === '') {
            return $items;
        }

        foreach (preg_split('/[,;]+/', $text) ?: [] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s*[:xX*]\s*(\d+)$/', $chunk, $m)) {
                $items[] = ['entry' => (int) $m[1], 'count' => max(1, (int) $m[2])];
            } elseif (preg_match('/^(\d+)$/', $chunk, $m)) {
                $items[] = ['entry' => (int) $m[1], 'count' => 1];
            }
        }

        return $items;
    }

    /**
     * 把界面上的 "33470:5" 文本规范化（去掉不合法片段，去重）。
     */
    public function normalizeItemsText(string $text): string
    {
        $items = $this->parseItemsText($text);
        $seen = [];
        $parts = [];
        foreach ($items as $item) {
            $key = $item['entry'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $parts[] = $item['entry'] . ':' . $item['count'];
        }

        return implode(',', $parts);
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,string>
     */
    public function itemNames(array $ids): array
    {
        $names = GameNameResolver::resolveMany('item', $ids);

        // DBC 里查不到（自定义物品）时回落到 world 库的 item_template
        $missing = array_values(array_filter($ids, static fn(int $id): bool => !isset($names[$id]) && $id > 0));
        if ($missing === []) {
            return $names;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($missing), '?'));
            $stmt = $this->world()->prepare('SELECT entry, name FROM item_template WHERE entry IN (' . $placeholders . ')');
            $stmt->execute($missing);
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $names[(int) $row['entry']] = (string) $row['name'];
            }
        } catch (Throwable $exception) {
            // 读不到名字不影响功能，前端显示 #entry
        }

        return $names;
    }

    // ------------------------------------------------------------------ 奖励预设

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listPresets(bool $onlyEnabled = false): array
    {
        if (!$this->tableExists('presets')) {
            return [];
        }

        $sql = 'SELECT * FROM ' . $this->table('presets');
        if ($onlyEnabled) {
            $sql .= ' WHERE `enabled` = 1';
        }
        $sql .= ' ORDER BY `name` ASC';

        $rows = $this->characters()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['name'] ?? ''),
                'items' => (string) ($row['items'] ?? ''),
                'money' => (int) ($row['money'] ?? 0),
                'enabled' => ((int) ($row['enabled'] ?? 1)) === 1,
                'updated_at' => (int) ($row['updated_at'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findPreset(string $name): ?array
    {
        $name = trim($name);
        if ($name === '' || !$this->tableExists('presets')) {
            return null;
        }

        $stmt = $this->characters()->prepare('SELECT * FROM ' . $this->table('presets') . ' WHERE `name` = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function savePreset(array $data, ?string $originalName = null): bool
    {
        if (!$this->tableExists('presets')) {
            throw new \RuntimeException('trivia_presets_storage_missing');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return false;
        }

        $existing = $this->findPreset($name);
        if ($existing !== null && ($originalName === null || $originalName === $name)) {
            $stmt = $this->characters()->prepare(
                'UPDATE ' . $this->table('presets') . ' SET `items` = ?, `money` = ?, `enabled` = ?, `updated_at` = ? WHERE `name` = ?'
            );
            $stmt->execute([
                (string) ($data['items'] ?? ''),
                (int) ($data['money'] ?? 0),
                ((int) ($data['enabled'] ?? 1)) === 1 ? 1 : 0,
                time(),
                $name,
            ]);

            return true;
        }

        $stmt = $this->characters()->prepare(
            'INSERT INTO ' . $this->table('presets') . ' (`name`,`items`,`money`,`enabled`,`updated_at`) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $name,
            (string) ($data['items'] ?? ''),
            (int) ($data['money'] ?? 0),
            ((int) ($data['enabled'] ?? 1)) === 1 ? 1 : 0,
            time(),
        ]);

        return true;
    }

    public function deletePreset(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || !$this->tableExists('presets')) {
            return false;
        }

        $stmt = $this->characters()->prepare('DELETE FROM ' . $this->table('presets') . ' WHERE `name` = ?');
        $stmt->execute([$name]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 预设被哪些题目引用（删除前提示用）。
     *
     * @return array<int,array{id:int,question:string}>
     */
    public function presetUsage(string $name): array
    {
        if ($name === '' || !$this->tableExists('questions')) {
            return [];
        }

        $stmt = $this->characters()->prepare(
            'SELECT `id`, `question` FROM ' . $this->table('questions') . ' WHERE `reward_preset` = ? ORDER BY `id` ASC LIMIT 50'
        );
        $stmt->execute([$name]);

        return array_map(static function (array $row): array {
            return ['id' => (int) ($row['id'] ?? 0), 'question' => (string) ($row['question'] ?? '')];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    // ------------------------------------------------------------------ 答对排行

    /**
     * @param array<string,mixed> $filters
     */
    public function listWinners(array $filters, int $page, int $perPage): Paginator
    {
        if (!$this->tableExists('winners')) {
            return new Paginator([], 0, $page, $perPage);
        }

        $where = [];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '`name` LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

        $countStmt = $this->characters()->prepare('SELECT COUNT(*) FROM ' . $this->table('winners') . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $this->characters()->prepare(
            'SELECT * FROM ' . $this->table('winners') . $whereSql
            . ' ORDER BY `wins` DESC, `last_win_at` DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $row): array {
            return [
                'guid' => (int) ($row['guid'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'wins' => (int) ($row['wins'] ?? 0),
                'total_money' => (int) ($row['total_money'] ?? 0),
                'last_win_at' => (int) ($row['last_win_at'] ?? 0),
                'last_question' => (string) ($row['last_question'] ?? ''),
            ];
        }, $rows);

        return new Paginator($items, $total, $page, $perPage);
    }

    public function winnerStats(): array
    {
        if (!$this->tableExists('winners')) {
            return ['players' => 0, 'wins' => 0, 'money' => 0, 'latest' => 0];
        }

        $sql = 'SELECT COUNT(*) AS players, COALESCE(SUM(`wins`),0) AS wins,'
            . ' COALESCE(SUM(`total_money`),0) AS money, COALESCE(MAX(`last_win_at`),0) AS latest'
            . ' FROM ' . $this->table('winners');
        $row = $this->characters()->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'players' => (int) ($row['players'] ?? 0),
            'wins' => (int) ($row['wins'] ?? 0),
            'money' => (int) ($row['money'] ?? 0),
            'latest' => (int) ($row['latest'] ?? 0),
        ];
    }

    public function clearWinners(): int
    {
        if (!$this->tableExists('winners')) {
            return 0;
        }

        $stmt = $this->characters()->exec('DELETE FROM ' . $this->table('winners'));

        return (int) $stmt;
    }
}
