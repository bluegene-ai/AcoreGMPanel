<?php

declare(strict_types=1);

/**
 * 招募管理运行时校验：
 *   - 区块 AJAX 接口返回可替换的 HTML 片段 + 统计值
 *   - 统计卡下钻接口的口径、分页与错误分支
 *   - 统计与明细共用同一套条件（rewarded_only / default_only）
 *
 * 使用伪造仓储，不需要数据库连接。
 */

define('PANEL_CLI_AUTH_BYPASS', true);

require dirname(__DIR__) . '/bootstrap/autoload.php';

// 视图片段会用到 __() / format_* 等模板助手
require_once dirname(__DIR__) . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Raf\RafRepository;
use Acme\Panel\Http\Controllers\Raf\RafController;
use Acme\Panel\Support\Paginator;

Config::init(dirname(__DIR__) . '/config');
Lang::init();

// 布局层会真正开启 PHP session，先落一个真实 session 再写登录态，
// 否则被 session_start() 换掉的 $_SESSION 会让第二次请求变成"未登录"
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/raf';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// 排查用：RAF_DEBUG=1 php tools/verify_raf_runtime.php
if (getenv('RAF_DEBUG')) {
    fwrite(STDERR, 'DBG auth=' . var_export(\Acme\Panel\Support\Auth::check(), true) . PHP_EOL);
}

final class FakeRafRepository extends RafRepository
{
    public array $calls = [];
    public array $lastFilters = [];
    public bool $ready = true;
    public bool $rewardLogReady = true;

    public function __construct()
    {
    }

    public function rebind(?int $serverId = null): void
    {
    }

    public function currentRealmId(): int
    {
        return 1;
    }

    public function schemaStatus(): array
    {
        return [
            'ready' => $this->ready,
            'missing_tables' => $this->ready ? [] : ['recruit_a_friend_links'],
            'reward_log_ready' => $this->rewardLogReady,
            'missing_reward_log_tables' => $this->rewardLogReady ? [] : ['recruit_a_friend_reward_log'],
        ];
    }

    public function listLinks(array $filters, int $page, int $perPage): Paginator
    {
        $this->calls[] = ['method' => 'listLinks', 'page' => $page, 'perPage' => $perPage, 'filters' => $filters];
        $this->lastFilters = $filters;

        if (!empty($filters['rewarded_only']) && empty($filters['rewarded_account_ids'])) {
            throw new RuntimeException('rewarded_only 必须先解析成账号列表');
        }

        $rows = [
            [
                'account_id' => 1001,
                'account_username' => 'RecruitOne',
                'recruiter_guid' => 55,
                'recruiter_name' => 'RecruiterOne',
                'recruiter_account_id' => 12,
                'time_stamp' => 1700000000,
                'complete' => 0,
                'ip_abuse_counter' => 0,
                'kick_counter' => 0,
                'reward_level' => 2,
                'comment' => 'verified',
                'status_key' => 'active',
            ],
        ];

        return new Paginator($rows, 1, $page, $perPage);
    }

    public function stats(array $filters): array
    {
        $this->calls[] = ['method' => 'stats', 'filters' => $filters];

        if (!empty($filters['rewarded_only']) && empty($filters['rewarded_account_ids'])) {
            throw new RuntimeException('rewarded_only 必须先解析成账号列表');
        }

        return [
            'total' => 6,
            'active' => 3,
            'completed' => 1,
            'inactive' => 1,
            'permanent_blocked' => 1,
            'rewarded_accounts' => 2,
        ];
    }

    public function rewardedAccountIds(array $filters): array
    {
        $this->calls[] = ['method' => 'rewardedAccountIds', 'filters' => $filters];

        return [1001, 1002];
    }

    /**
     * 伪造仓储不连库：把跨表条件直接解析成固定账号列表。
     */
    protected function filterBindingsForExecution(array $filters): array
    {
        if (!empty($filters['rewarded_only'])) {
            $filters['rewarded_account_ids'] = $this->rewardedAccountIds($filters);
        }

        return $filters;
    }

    protected function filterRewardLogsForExecution(array $filters): array
    {
        return $filters;
    }

    public function listRewardLogs(array $filters, int $page, int $perPage): Paginator
    {
        $this->calls[] = ['method' => 'listRewardLogs', 'page' => $page, 'perPage' => $perPage, 'filters' => $filters];

        $rows = [
            [
                'id' => 9,
                'granted_at' => 1700000000,
                'recruiter_guid' => 55,
                'recruiter_account' => 12,
                'recruiter_realm' => 1,
                'recruit_account_id' => 1001,
                'reward_level' => 2,
                'target_level' => 80,
                'reward_money' => 0,
                'reward_source' => 'login',
                'used_default' => 1,
                'mail_subject' => 'RAF reward',
                'recruiter_name' => 'RecruiterOne',
                'recruit_account_username' => 'RecruitOne',
                'recruiter_account_username' => 'RecruiterAcc',
                'reward_item_list' => [
                    ['entry' => 6948, 'count' => 1, 'name' => 'Hearthstone', 'quality' => 1],
                ],
            ],
        ];

        return new Paginator($rows, 1, $page, $perPage);
    }

    public function rewardLogStats(array $filters = []): array
    {
        $this->calls[] = ['method' => 'rewardLogStats', 'filters' => $filters];

        return [
            'total' => 4,
            'recruiters' => 2,
            'recruits' => 3,
            'default_rewards' => !empty($filters['default_only']) ? 4 : 2,
            'latest_granted_at' => 1700000000,
        ];
    }

    public function findLink(int $accountId): ?array
    {
        return null;
    }

    public function updateComment(int $accountId, string $comment): bool
    {
        return true;
    }
}

function makeRafRequest(array $get = [], string $method = 'GET'): Request
{
    $request = new Request();
    $request->method = $method;
    $request->uri = '/raf';
    $request->get = $get;
    $request->post = [];
    $request->server = $_SERVER;

    return $request;
}

function setRafRefProperty(string $className, object $object, string $property, $value): void
{
    $ref = new ReflectionProperty($className, $property);
    $ref->setAccessible(true);
    $ref->setValue($object, $value);
}

function invokeRafRefMethod(string $className, object $object, string $method, array $args = [])
{
    $ref = new ReflectionMethod($className, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($object, $args);
}

/**
 * 带引用参数的私有方法：数组元素必须是真正的变量引用，否则 &$params 拿不到回填值。
 */
function invokeRafRefMethodWithRefs(string $className, object $object, string $method, array &$args)
{
    $ref = new ReflectionMethod($className, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($object, $args);
}

function makeByRefArgs(array $values): array
{
    $args = [];
    foreach ($values as $key => $value) {
        $args[$key] = &$values[$key];
    }

    return $args;
}

function responsePayload(Response $response): array
{
    return [
        'status' => responseStatus($response),
        'body' => json_decode(responseContent($response), true),
    ];
}

function responseContent(Response $response): string
{
    $content = new ReflectionProperty(Response::class, 'content');
    $content->setAccessible(true);

    return (string) $content->getValue($response);
}

function responseStatus(Response $response): int
{
    $status = new ReflectionProperty(Response::class, 'status');
    $status->setAccessible(true);

    return (int) $status->getValue($response);
}

function assertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $label);
    }
}

$controller = new RafController();
$repo = new FakeRafRepository();
setRafRefProperty(RafController::class, $controller, 'repo', $repo);

$checks = [];

// --- 1. 绑定列表区块片段 ---
$bindings = responsePayload($controller->apiBindings(makeRafRequest(['search' => 'abc', 'status' => 'active'])));
assertTrue(($bindings['status'] ?? 0) === 200, 'bindings endpoint returns 200');
assertTrue(($bindings['body']['success'] ?? false) === true, 'bindings endpoint succeeds');
assertTrue(($bindings['body']['section'] ?? '') === 'bindings', 'bindings section identifier');
assertTrue(str_contains((string) ($bindings['body']['html'] ?? ''), 'data-raf-section="bindings"'), 'bindings fragment contains section root');
assertTrue(str_contains((string) ($bindings['body']['html'] ?? ''), 'data-raf-form="bindings"'), 'bindings fragment contains filter form');
assertTrue(!str_contains((string) ($bindings['body']['html'] ?? ''), '<!DOCTYPE'), 'bindings fragment has no layout');
assertTrue(($bindings['body']['stats']['bindings']['total'] ?? 0) === 6, 'bindings fragment carries stats');
$checks[] = ['name' => 'raf.bindings_section', 'status' => 'passed', 'detail' => ['keys' => array_keys((array) $bindings['body'])]];
$bindingsBody = $bindings['body'];

// --- 2. 奖励记录区块片段 ---
$rewardLogs = responsePayload($controller->apiRewardLogs(makeRafRequest(['log_search' => 'RecruitOne'])));
assertTrue(($rewardLogs['body']['success'] ?? false) === true, 'reward log endpoint succeeds');
assertTrue(str_contains((string) ($rewardLogs['body']['html'] ?? ''), 'data-raf-section="reward-log"'), 'reward log fragment contains section root');
assertTrue(str_contains((string) ($rewardLogs['body']['html'] ?? ''), 'Hearthstone'), 'reward log fragment renders item names');
assertTrue(($rewardLogs['body']['stats']['reward_log']['total'] ?? 0) === 4, 'reward log fragment carries stats');
assertTrue(
    ($rewardLogs['body']['stats']['reward_log']['latest_granted_at_text'] ?? '') !== '',
    'reward log stats carry server-formatted time text'
);
$checks[] = ['name' => 'raf.reward_log_section', 'status' => 'passed', 'detail' => ['keys' => array_keys((array) $rewardLogs['body'])]];
$rewardSectionBody = $rewardLogs['body'];

// --- 3. 统计卡下钻：绑定 ---
$inactiveCard = responsePayload($controller->apiCard(makeRafRequest([
    'card' => 'bindings',
    'key' => 'inactive',
    'detail_limit' => '20',
])));
assertTrue(($inactiveCard['body']['success'] ?? false) === true, 'inactive card succeeds');
assertTrue(($inactiveCard['body']['value'] ?? 0) === 1, 'inactive card value matches stats');
assertTrue(!empty($inactiveCard['body']['hint']), 'inactive card carries hint');
assertTrue(str_contains((string) ($inactiveCard['body']['html'] ?? ''), 'raf-table'), 'inactive card renders table');
assertTrue(!str_contains((string) ($inactiveCard['body']['html'] ?? ''), 'data-raf-page='), 'detail table has no pagination buttons');
$actions = $inactiveCard['body']['actions'] ?? [];
assertTrue(count($actions) === 1 && !empty($actions[0]['href']), 'inactive card exposes a view-all action');
$checks[] = ['name' => 'raf.card_inactive', 'status' => 'passed', 'detail' => ['filters' => $repo->lastFilters]];

// 统计口径与明细条件必须一致
$cardCatalog = invokeRafRefMethod(RafController::class, $controller, 'bindingCards', [$repo->stats([])]);
$inactiveFilters = $cardCatalog['inactive']['filters'];
assertTrue(($inactiveFilters['status'] ?? '') === 'inactive', 'inactive card uses inactive status');
assertTrue(!isset($inactiveFilters['ip_abuse_max']), 'inactive card avoids duplicate threshold placeholder');
assertTrue((int) ($cardCatalog['inactive']['value'] ?? -1) === 1, 'inactive card value comes from stats');
$rewardedFilters = $cardCatalog['rewarded_accounts']['filters'];
assertTrue(!empty($rewardedFilters['rewarded_only']), 'rewarded card uses cross-table flag');
$checks[] = ['name' => 'raf.card_filter_parity', 'status' => 'passed', 'detail' => ['inactive' => $inactiveFilters, 'rewarded' => $rewardedFilters]];

// --- 4. 统计卡下钻：奖励记录 ---
$defaultCard = responsePayload($controller->apiCard(makeRafRequest([
    'card' => 'reward-log',
    'key' => 'default_rewards',
])));
assertTrue(($defaultCard['body']['success'] ?? false) === true, 'default rewards card succeeds');
assertTrue(($defaultCard['body']['value'] ?? 0) === 2, 'default rewards card value matches stats');
assertTrue(str_contains((string) ($defaultCard['body']['html'] ?? ''), 'raf-table'), 'default rewards card renders table');
$checks[] = ['name' => 'raf.card_default_rewards', 'status' => 'passed', 'detail' => ['actions' => $defaultCard['body']['actions'] ?? []]];

$latestCard = responsePayload($controller->apiCard(makeRafRequest([
    'card' => 'reward-log',
    'key' => 'latest',
])));
assertTrue(($latestCard['body']['success'] ?? false) === true, 'latest card succeeds');
assertTrue(($latestCard['body']['value'] ?? 0) === 1700000000, 'latest card exposes timestamp');
assertTrue(($latestCard['body']['shown'] ?? 0) === 1, 'latest card shows a single row');
$checks[] = ['name' => 'raf.card_latest', 'status' => 'passed', 'detail' => ['shown' => $latestCard['body']['shown']]];

// --- 5. 错误分支 ---
$unknown = responsePayload($controller->apiCard(makeRafRequest(['card' => 'bindings', 'key' => 'nope'])));
assertTrue(($unknown['status'] ?? 0) === 422, 'unknown card key rejected');
assertTrue(($unknown['body']['success'] ?? true) === false, 'unknown card returns failure');

$unknownGroup = responsePayload($controller->apiCard(makeRafRequest(['card' => 'other', 'key' => 'total'])));
assertTrue(($unknownGroup['status'] ?? 0) === 422, 'unknown card group rejected');
$checks[] = ['name' => 'raf.card_errors', 'status' => 'passed', 'detail' => ['status' => $unknown['status']]];

// 表结构缺失时不能编造明细
$broken = new FakeRafRepository();
$broken->ready = false;
$brokenController = new RafController();
setRafRefProperty(RafController::class, $brokenController, 'repo', $broken);
$brokenCard = responsePayload($brokenController->apiCard(makeRafRequest(['card' => 'bindings', 'key' => 'total'])));
assertTrue(($brokenCard['status'] ?? 0) === 422, 'missing schema rejected for drill-down');
$brokenSection = responsePayload($brokenController->apiBindings(makeRafRequest([])));
assertTrue(($brokenSection['body']['success'] ?? false) === true, 'bindings section still renders without schema');
assertTrue(str_contains((string) ($brokenSection['body']['html'] ?? ''), 'panel-flash'), 'bindings section surfaces schema error');
$checks[] = ['name' => 'raf.schema_guard', 'status' => 'passed', 'detail' => ['status' => $brokenCard['status']]];

// --- 6. 真实仓储的条件拼装（不连库，只校验私有 SQL 构造） ---
$sqlRepo = new RafRepository();

$logFilters = ['default_only' => 1, 'search' => ''];
$logParams = [];
$logWhereArgs = makeByRefArgs([$logFilters, &$logParams]);
$where = invokeRafRefMethodWithRefs(RafRepository::class, $sqlRepo, 'buildRewardLogWhere', $logWhereArgs);
assertTrue(str_contains((string) $where, 'used_default = 1'), 'default_only 落成 SQL 条件');

$bindingFilters = [
    'status' => 'inactive',
    'rewarded_only' => 1,
    'rewarded_account_ids' => [1001, 1002],
];
$bindingParams = [];
$whereArgs = makeByRefArgs([$bindingFilters, &$bindingParams]);
$where = invokeRafRefMethodWithRefs(RafRepository::class, $sqlRepo, 'buildWhere', $whereArgs);
$boundParams = $bindingParams;
assertTrue(str_contains((string) $where, 'l.account_id IN (:rewarded_account_0, :rewarded_account_1)'), 'rewarded 账号列表落成 IN 条件');
assertTrue(($boundParams[':rewarded_account_0'] ?? null) === 1001, 'rewarded 参数绑定正确');
assertTrue(substr_count((string) $where, ':status_inactive_threshold') === 1, 'inactive 阈值只出现一次');

// rewarded_only 命中 0 个账号时不能退化成"全部绑定"
$emptyFilters = ['rewarded_only' => 1, 'rewarded_account_ids' => []];
$emptyParams = [];
$emptyArgs = makeByRefArgs([$emptyFilters, &$emptyParams]);
$emptyWhere = invokeRafRefMethodWithRefs(RafRepository::class, $sqlRepo, 'buildWhere', $emptyArgs);
assertTrue(str_contains((string) $emptyWhere, '1 = 0'), 'rewarded_only 空集合落成 1 = 0');
assertTrue(!str_contains((string) $emptyWhere, 'l.account_id IN'), 'rewarded_only 空集合不得省略过滤');

// 命名占位符不能重复绑定（MySQL 原生预处理会直接报 42S21）
preg_match_all('/:[a-z0-9_]+/i', (string) $where, $matches);
$duplicated = array_filter(array_count_values($matches[0]), static function (int $count): bool {
    return $count > 1;
});
assertTrue($duplicated === [], '命名占位符无重复');

$rewardedArgs = [['rewarded_only' => 1]];
$resolved = invokeRafRefMethod(RafRepository::class, $sqlRepo, 'filterBindingsForExecution', $rewardedArgs);
assertTrue(is_array($resolved['rewarded_account_ids'] ?? null), 'rewarded_only 解析为账号列表');
$checks[] = ['name' => 'raf.sql_conditions', 'status' => 'passed', 'detail' => ['where' => $where]];

// --- 7. 整页结构：统计区只能有一份，区块与表单数量固定，语言 key 不能泄漏 ---
$pageController = new RafController();
$pageRepo = new FakeRafRepository();
$pageRepo->rewardLogReady = true;
setRafRefProperty(RafController::class, $pageController, 'repo', $pageRepo);

$pageHtml = responseContent($pageController->index(makeRafRequest([])));

assertTrue(substr_count($pageHtml, 'class="raf-stats"') === 1, '顶部统计区只出现一次');
assertTrue(!str_contains($pageHtml, 'raf-summary-grid'), '旧的重复统计网格已移除');
assertTrue(substr_count($pageHtml, 'data-raf-form="') === 2, '两个区块各有一个筛选表单');
assertTrue(substr_count($pageHtml, 'data-raf-section="bindings"') === 1, '绑定区块唯一');
assertTrue(substr_count($pageHtml, 'data-raf-section="reward-log"') === 1, '奖励记录区块唯一');
assertTrue(substr_count($pageHtml, 'data-raf-card="open"') === 11, '统计卡共 11 张可下钻');
assertTrue(str_contains($pageHtml, 'data-raf-stat-format="time"'), '时间型统计卡标记服务端格式化');

preg_match_all('/app\.(?:raf|common)\.[A-Za-z0-9_.]+/', $pageHtml, $keyLeaks);
$keyLeaks = array_values(array_unique(array_filter($keyLeaks[0], static function (string $key): bool {
    return !str_ends_with($key, '.');
})));
assertTrue($keyLeaks === [], '整页没有未翻译的语言 key');
$checks[] = ['name' => 'raf.page_structure', 'status' => 'passed', 'detail' => [
    'bytes' => strlen($pageHtml),
    'cards' => substr_count($pageHtml, 'data-raf-card="open"'),
    'leaks' => $keyLeaks,
]];

// --- 8. 筛选条件必须在表单里回填，否则无 JS 降级时提交一次就丢条件 ---
$prefilledHtml = responseContent($pageController->index(makeRafRequest([
    'search' => 'RecruitOne',
    'recruiter_guid' => '55',
    'status' => 'active',
    'sort' => 'account_id',
    'dir' => 'ASC',
])));
assertTrue(str_contains($prefilledHtml, 'name="search"'  ) && preg_match('/name="search"[^>]*value="RecruitOne"/', $prefilledHtml) === 1, '搜索词回填到表单');
assertTrue(preg_match('/name="recruiter_guid"[^>]*value="55"/', $prefilledHtml) === 1, '招募角色 GUID 回填到表单');
assertTrue(preg_match('/<option value="active" selected>/', $prefilledHtml) === 1, '状态回填为选中项');
assertTrue(preg_match('/<option value="account_id" selected>/', $prefilledHtml) === 1, '排序字段回填为选中项');
assertTrue(preg_match('/<option value="ASC" selected>/', $prefilledHtml) === 1, '排序方向回填为选中项');
$checks[] = ['name' => 'raf.filter_prefill', 'status' => 'passed', 'detail' => 'search / recruiter_guid / status / sort / dir 均回填'];

$prefilledLogHtml = responseContent($pageController->index(makeRafRequest([
    'log_search' => 'RecruitOne',
    'log_level' => '3',
    'log_source' => 'login',
    'log_from' => '2026-01-01',
    'log_to' => '2026-02-01',
    'log_default' => '1',
])));
assertTrue(preg_match('/name="log_search"[^>]*value="RecruitOne"/', $prefilledLogHtml) === 1, '记录搜索词回填');
assertTrue(preg_match('/name="log_level"[^>]*value="3"/', $prefilledLogHtml) === 1, '奖励等级回填');
assertTrue(preg_match('/<option value="login" selected>/', $prefilledLogHtml) === 1, '触发来源回填');
assertTrue(preg_match('/name="log_from"[^>]*value="2026-01-01"/', $prefilledLogHtml) === 1, '起始日期回填');
assertTrue(preg_match('/name="log_to"[^>]*value="2026-02-01"/', $prefilledLogHtml) === 1, '结束日期回填');
assertTrue(preg_match('/<option value="1" selected>[^<]*<\/option>/', $prefilledLogHtml) === 1, '默认组合筛选回填');
$checks[] = ['name' => 'raf.log_filter_prefill', 'status' => 'passed', 'detail' => '记录侧筛选条件均回填'];

echo json_encode([
    'success' => true,
    'checks' => $checks,
    'sample' => [
        'bindings_html_length' => strlen((string) ($bindingsBody['html'] ?? '')),
        'reward_log_html_length' => strlen((string) ($rewardSectionBody['html'] ?? '')),
        'page_html_length' => strlen($pageHtml),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
