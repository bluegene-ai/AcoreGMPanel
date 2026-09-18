<?php

declare(strict_types=1);

/**
 * 把账号 / 角色列表页的筛选面板 HTML 片段导出到 storage/tmp/，便于人工核对结构。
 * 用法：php tools/dump_filter_panel.php [serverId]
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Http\Controllers\AccountController;
use Acme\Panel\Http\Controllers\Character\CharacterController;
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
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ServerContext::set(isset($argv[1]) ? (int) $argv[1] : 1);

$render = static function (string $class, string $uri) use ($root): string {
    $_SERVER['REQUEST_URI'] = $uri;
    $controller = new $class();
    $request = new Request();
    $request->method = 'GET';
    $request->uri = $uri;
    $request->get = [];
    $request->post = [];
    $request->server = $_SERVER;

    $response = $controller->index($request);
    $property = new ReflectionProperty(Response::class, 'content');
    $property->setAccessible(true);
    $html = (string) $property->getValue($response);

    $start = strpos($html, '<form class="list-filter"');
    $end = strpos($html, '</form>', $start);

    return $start === false ? '(panel not found)' : substr($html, $start, $end - $start + 7);
};

$dir = $root . '/storage/tmp';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

$panels = [
    'account-filter-panel.html' => $render(AccountController::class, '/account'),
    'character-filter-panel.html' => $render(CharacterController::class, '/character'),
];

foreach ($panels as $file => $markup) {
    file_put_contents($dir . '/' . $file, $markup);
    echo $file . ': ' . strlen($markup) . " bytes\n";
}
