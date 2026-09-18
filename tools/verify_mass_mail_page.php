<?php

declare(strict_types=1);

/**
 * 群发管理页面渲染冒烟测试：确认视图在真实控制器数据下能渲染出新的交互区域。
 *
 * 用法：php tools/verify_mass_mail_page.php [serverId]
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Http\Controllers\MassMail\MassMailController;
use Acme\Panel\Support\ServerContext;

Config::init($root . '/config');
Lang::init();

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/mass-mail';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ServerContext::set(isset($argv[1]) ? (int) $argv[1] : 1);

$checks = [];
$failures = [];

function expect(bool $condition, string $label, array &$checks, array &$failures, $detail = null): void
{
    $checks[] = ['name' => $label, 'status' => $condition ? 'passed' : 'failed', 'detail' => $detail];
    if (!$condition) {
        $failures[] = $label;
    }
}

try {
    $controller = new MassMailController();
    $request = new Request();
    $request->method = 'GET';
    $request->uri = '/mass-mail';
    $request->get = [];
    $request->post = [];
    $request->server = $_SERVER;

    $response = $controller->index($request);
    $content = new ReflectionProperty(Response::class, 'content');
    $content->setAccessible(true);
    $html = (string) $content->getValue($response);

    expect(strlen($html) > 5000, '整页渲染成功', $checks, $failures, ['bytes' => strlen($html)]);
    expect(str_contains($html, 'id="mmFeedback"'), '渲染出操作反馈条', $checks, $failures);
    expect(str_contains($html, 'id="mmRecipientsCount"'), '渲染出收件人统计', $checks, $failures);
    $nameColumnLabel = (string) Lang::get('app.mass_mail.index.sections.send.item_name_label');
    expect(
        $nameColumnLabel !== '' && str_contains($html, $nameColumnLabel),
        '渲染出物品名称列表头',
        $checks,
        $failures,
        ['label' => $nameColumnLabel]
    );
    expect(str_contains($html, 'data-name-unknown'), '物品名解析状态透传到前端', $checks, $failures);
    expect(str_contains($html, 'id="mmItemsClear"'), '渲染出清空按钮', $checks, $failures);
    expect(str_contains($html, 'id="logFilter"'), '渲染出日志筛选框', $checks, $failures);
    expect(str_contains($html, 'id="mmConfirmInput"'), '渲染出二次确认输入框', $checks, $failures);

    // 页面里不应该留下未翻译的 key
    preg_match_all('/app\.mass_mail\.[A-Za-z0-9_.]+/', $html, $leaks);
    $leaks = array_values(array_unique(array_filter($leaks[0], static function (string $key): bool {
        return !str_ends_with($key, '.');
    })));
    expect($leaks === [], '没有未翻译的语言 key', $checks, $failures, ['leaks' => $leaks]);

    // 日志表格支持筛选，因此空表也要有稳定结构
    expect(str_contains($html, 'id="massMailLogTable"'), '渲染出日志表格', $checks, $failures);

    echo json_encode([
        'success' => $failures === [],
        'failures' => $failures,
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    exit($failures === [] ? 0 : 1);
} catch (Throwable $exception) {
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'class' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(2);
}
