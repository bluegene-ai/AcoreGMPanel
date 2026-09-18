<?php

declare(strict_types=1);

/**
 * 招募管理页面结构冒烟测试（需要可用的数据库连接）。
 *
 * 关注的是"页面看起来对不对"：
 *   - 顶部只有一个统计区，没有第二份统计/查询区
 *   - 两个区块各只有一个筛选表单，页面上没有多余表单
 *   - 没有未翻译的 app.raf.* key 泄漏到 HTML
 *   - 统计卡带下钻标记，奖励记录表缺失时卡片自动禁用
 *   - 区块 AJAX 接口返回可替换片段，下钻接口返回明细
 *
 * 用法：php tools/verify_raf_page.php [serverId]
 *
 * 注意：整页结构断言已经并入 verify_raf_runtime.php（无需数据库，运行稳定），
 * 本脚本保留为"真实数据 + 真实仓储"的人工复核手段。
 * 受限环境下直接执行可能拿不到 stdout，报告会落到
 * storage/logs/raf_page_report.json，可据此独立确认结果。
 */

define('PANEL_CLI_AUTH_BYPASS', true);

require dirname(__DIR__) . '/bootstrap/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Raf\RafRepository;
use Acme\Panel\Http\Controllers\Raf\RafController;
use Acme\Panel\Support\ServerContext;

Config::init(dirname(__DIR__) . '/config');
Lang::init();

// 布局层会真正开启 PHP session，先落一个真实 session 再写登录态
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$serverId = isset($argv[1]) ? (int) $argv[1] : ServerContext::defaultId();
ServerContext::set($serverId);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/raf';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$checks = [];
$failures = [];

function expect(bool $condition, string $label, array &$checks, array &$failures, $detail = null): void
{
    $checks[] = ['name' => $label, 'status' => $condition ? 'passed' : 'failed', 'detail' => $detail];
    if (!$condition) {
        $failures[] = $label;
    }
}

function makeRequest(array $get): Request
{
    $request = new Request();
    $request->method = 'GET';
    $request->uri = '/raf';
    $request->get = $get;
    $request->post = [];
    $request->server = $_SERVER;

    return $request;
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

function responseJson(Response $response): ?array
{
    $decoded = json_decode(responseContent($response), true);

    return is_array($decoded) ? $decoded : null;
}

try {
    $repo = new RafRepository($serverId);
    $schema = $repo->schemaStatus();

    $controller = new RafController();
    $html = responseContent($controller->index(makeRequest([])));
    $checks[] = ['name' => 'raf.page_rendered', 'status' => 'passed', 'detail' => ['bytes' => strlen($html)]];

    expect(str_contains($html, 'class="raf-page"'), '页面包含 raf-page 容器', $checks, $failures);

    // 1. 统计区唯一
    $statsBlocks = substr_count($html, 'class="raf-stats"');
    expect($statsBlocks === 1, '顶部统计区只出现一次', $checks, $failures, ['count' => $statsBlocks]);
    expect(!str_contains($html, 'raf-summary-grid'), '旧的重复统计网格已移除', $checks, $failures);

    // 2. 两个区块各一个筛选表单（记录表缺失时只保留绑定侧表单）
    $formCount = substr_count($html, 'data-raf-form="');
    $expectedForms = $schema['reward_log_ready'] ? 2 : 1;
    expect($formCount === $expectedForms, '筛选表单数量符合当前表结构', $checks, $failures, ['count' => $formCount, 'expected' => $expectedForms]);
    expect(substr_count($html, 'data-raf-section="bindings"') === 1, '绑定区块唯一', $checks, $failures);
    expect(substr_count($html, 'data-raf-section="reward-log"') === 1, '奖励记录区块唯一', $checks, $failures);

    // 3. 文案全部翻译到位
    preg_match_all('/app\.(?:raf|common)\.[A-Za-z0-9_.]+/', $html, $leaks);
    $leaks = array_values(array_unique(array_filter($leaks[0], static function (string $key): bool {
        // 资源路径里可能出现 app.xxx 形状，但语言 key 一定带 raf. / common.
        return str_starts_with($key, 'app.raf.') || str_starts_with($key, 'app.common.');
    })));
    expect($leaks === [], '没有未翻译的语言 key', $checks, $failures, ['leaks' => $leaks]);

    // 4. 统计卡可下钻
    $cardCount = substr_count($html, 'data-raf-card="open"');
    expect($cardCount === 11, '统计卡数量为 11（6 绑定 + 5 奖励）', $checks, $failures, ['count' => $cardCount]);
    expect(str_contains($html, 'data-raf-stat="bindings.total"'), '绑定统计卡带数值锚点', $checks, $failures);
    expect(str_contains($html, 'data-raf-stat-format="time"'), '时间型统计卡标记为服务端格式化', $checks, $failures);

    if ($schema['reward_log_ready']) {
        expect(str_contains($html, 'data-raf-form="reward-log"'), '记录表就绪时渲染记录筛选表单', $checks, $failures);
    } else {
        expect(str_contains($html, 'raf-stats__badge'), '记录表缺失时统计区给出提示', $checks, $failures);
        expect(substr_count($html, 'data-raf-card-group="reward-log"') === 5, '记录表缺失时仍渲染 5 张卡片', $checks, $failures);
        $checks[] = ['name' => 'raf.reward_log_branch_skipped', 'status' => 'passed', 'detail' => 'reward_log 表不存在，记录侧下钻未在真实数据上验证'];
    }

    // 5. 区块片段接口
    $bindingsJson = responseJson($controller->apiBindings(makeRequest(['search' => '', 'status' => 'all'])));
    expect(($bindingsJson['success'] ?? false) === true, '绑定区块接口成功', $checks, $failures);
    expect(!str_contains((string) ($bindingsJson['html'] ?? ''), '<!DOCTYPE'), '绑定片段不含完整布局', $checks, $failures);

    $logJson = responseJson($controller->apiRewardLogs(makeRequest([])));
    expect(($logJson['success'] ?? false) === true, '奖励记录区块接口成功', $checks, $failures);

    // 6. 下钻接口（绑定侧一定有数据可查）
    $cardJson = responseJson($controller->apiCard(makeRequest(['card' => 'bindings', 'key' => 'total'])));
    expect(($cardJson['success'] ?? false) === true, '绑定下钻接口成功', $checks, $failures);
    expect(isset($cardJson['value'], $cardJson['hint'], $cardJson['actions']), '下钻响应包含数值/说明/跳转', $checks, $failures);
    expect((int) ($cardJson['total'] ?? -1) === (int) ($cardJson['value'] ?? -2), '下钻条数与卡片数值一致', $checks, $failures, [
        'total' => $cardJson['total'] ?? null,
        'value' => $cardJson['value'] ?? null,
    ]);

    $rewardedJson = responseJson($controller->apiCard(makeRequest(['card' => 'bindings', 'key' => 'rewarded_accounts'])));
    expect(($rewardedJson['success'] ?? false) === true, '跨表下钻接口成功', $checks, $failures);
    expect((int) ($rewardedJson['total'] ?? -1) === (int) ($rewardedJson['value'] ?? -2), '跨表下钻条数与卡片数值一致', $checks, $failures, [
        'total' => $rewardedJson['total'] ?? null,
        'value' => $rewardedJson['value'] ?? null,
    ]);

    if ($schema['reward_log_ready']) {
        $logCardJson = responseJson($controller->apiCard(makeRequest(['card' => 'reward-log', 'key' => 'total'])));
        expect(($logCardJson['success'] ?? false) === true, '奖励记录下钻接口成功', $checks, $failures);
    } else {
        $missingJson = responseJson($controller->apiCard(makeRequest(['card' => 'reward-log', 'key' => 'total'])));
        expect(($missingJson['success'] ?? true) === false, '记录表缺失时下钻返回明确失败', $checks, $failures, $missingJson);
        expect(responseStatus($controller->apiCard(makeRequest(['card' => 'reward-log', 'key' => 'total']))) === 422, '记录表缺失时状态码 422', $checks, $failures);
    }

    // 7. 搜索参数必须落到两个区块各自的接口上
    $searchJson = responseJson($controller->apiBindings(makeRequest(['search' => 'zzz-no-such-account'])));
    expect(($searchJson['success'] ?? false) === true, '搜索条件下区块接口仍成功', $checks, $failures);
    expect((int) ($searchJson['stats']['bindings']['total'] ?? -1) === 0, '搜索条件下统计随筛选归零', $checks, $failures, $searchJson['stats']['bindings'] ?? []);

    $report = [
        'success' => $failures === [],
        'server_id' => $serverId,
        'schema' => $schema,
        'failures' => $failures,
        'checks' => $checks,
    ];
    $reportJson = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // 某些受限环境下 stdout 会被吞掉，报告同时落盘，便于独立确认结果
    @file_put_contents(dirname(__DIR__) . '/storage/logs/raf_page_report.json', $reportJson);

    // 首行给出一行结论，后面才是完整报告
    echo ($failures === [] ? 'RESULT: SUCCESS' : 'RESULT: FAILED') . PHP_EOL;
    echo $reportJson;

    exit($failures === [] ? 0 : 1);
} catch (Throwable $exception) {
    $errorJson = json_encode([
        'success' => false,
        'server_id' => $serverId,
        'message' => $exception->getMessage(),
        'class' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    @file_put_contents(dirname(__DIR__) . '/storage/logs/raf_page_report.json', $errorJson);

    echo 'RESULT: ERROR' . PHP_EOL;
    echo $errorJson;

    exit(2);
}
