<?php

declare(strict_types=1);

/**
 * 直升管理模块化校验：
 *   1. 群发页与群发模块里不再有任何直升代码/权限；
 *   2. 直升管理页可渲染（执行表单、预览区、概览、历史），语言键齐全；
 *   3. 导航、路由、权限都指向直升模块；
 *   4. 预览与实际执行共用同一套守卫规则。
 *
 * 用法：php tools/verify_boost_admin.php [serverId]
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostAdminController;
use Acme\Panel\Support\ModuleAssets;
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
$_SERVER['REQUEST_URI'] = '/character-boost';
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

/** 去掉注释，避免文档里的历史说明误伤"不得出现"断言 */
function stripComments(string $source): string
{
    $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
    return (string) preg_replace('#^\s*//.*$#m', '', $source);
}

// ------------------------------------------------------------------
// 1. 群发模块彻底摘掉直升
// ------------------------------------------------------------------
$massMailFiles = [
    'view' => $root . '/resources/views/mass_mail/index.php',
    'js' => $root . '/public/assets/js/modules/mass_mail.js',
    'controller' => $root . '/app/Http/Controllers/MassMail/MassMailController.php',
];
foreach ($massMailFiles as $label => $file) {
    $source = stripComments((string) file_get_contents($file));
    expect(
        !str_contains($source, 'boost'),
        '群发 ' . $label . ' 不含直升残留',
        $checks,
        $failures
    );
}

$authConfig = (string) file_get_contents($root . '/config/auth.php');
expect(
    !str_contains($authConfig, "'boost' => 'Mass-mail-triggered"),
    '群发 boost 权限已移除',
    $checks,
    $failures
);

$routes = (string) file_get_contents($root . '/routes/web.php');
expect(
    !str_contains($routes, "'/mass-mail/api/boost'"),
    '群发直升路由已移除',
    $checks,
    $failures
);
expect(
    str_contains($routes, "'/character-boost/api/apply'"),
    '直升执行路由指向直升模块',
    $checks,
    $failures
);
expect(
    str_contains($routes, "get('/character-boost'"),
    '直升管理入口路由存在',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 2. 直升管理页渲染
// ------------------------------------------------------------------
try {
    $controller = new CharacterBoostAdminController();
    $request = new Request();
    $request->method = 'GET';
    $request->uri = '/character-boost';
    $request->get = [];
    $request->post = [];
    $request->server = $_SERVER;

    $response = $controller->index($request);
    $content = new ReflectionProperty(Response::class, 'content');
    $content->setAccessible(true);
    $html = (string) $content->getValue($response);

    expect(strlen($html) > 3000, '直升管理页渲染成功', $checks, $failures, ['bytes' => strlen($html)]);
    expect(str_contains($html, 'id="boostFeedback"'), '页面有操作反馈条', $checks, $failures);
    expect(str_contains($html, 'id="boostApplyForm"'), '页面有直升执行表单', $checks, $failures);
    expect(str_contains($html, 'id="boostApplyPreviewBox"'), '页面有发放内容预览区', $checks, $failures);
    expect(str_contains($html, 'id="boostHistoryBody"'), '页面有直升历史表', $checks, $failures);
    expect(str_contains($html, 'id="boostApplyTemplate"'), '页面有模板选择', $checks, $failures);
    expect(str_contains($html, 'character-boost/templates'), '页面链接到模板管理', $checks, $failures);
    expect(str_contains($html, 'character-boost/redeem-codes'), '页面链接到兑换码管理', $checks, $failures);

    preg_match_all('/app\.character_boost\.[A-Za-z0-9_.]+/', $html, $leaks);
    $leaks = array_values(array_unique(array_filter($leaks[0], static function (string $key): bool {
        return !str_ends_with($key, '.');
    })));
    expect($leaks === [], '没有未翻译的语言 key', $checks, $failures, ['leaks' => $leaks]);

    // 资源必须挂到 character_boost 模块上，否则 JS/CSS 不会加载
    expect(
        str_contains($html, 'data-module="character_boost"'),
        '页面挂载 character_boost 模块资源',
        $checks,
        $failures
    );
    expect(
        str_contains($html, 'character_boost.css'),
        '加载直升模块样式',
        $checks,
        $failures
    );
} catch (Throwable $exception) {
    expect(false, '直升管理页渲染成功', $checks, $failures, [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile() . ':' . $exception->getLine(),
    ]);
}

// JS 模块文件必须存在（否则前端交互全失效）
expect(
    is_file($root . '/public/assets/js/modules/character_boost.js'),
    '直升管理交互脚本存在',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 3. 导航与权限
// ------------------------------------------------------------------
$navItems = ModuleAssets::navigationItems();
$boostNav = null;
foreach ($navItems as $item) {
    if (($item['path'] ?? '') === '/character-boost') {
        $boostNav = $item;
        break;
    }
}
expect($boostNav !== null, '侧边栏有直升管理入口', $checks, $failures, ['nav' => $boostNav]);

if ($boostNav !== null) {
    $capability = $boostNav['capability'] ?? null;
    $capabilities = is_array($capability) ? $capability : [$capability];
    expect(
        in_array('boost.templates', $capabilities, true),
        '导航入口允许模板权限访问',
        $checks,
        $failures,
        ['capability' => $capability]
    );
}

expect(
    !str_contains($authConfig, "'mass_mail' => [") || !str_contains(
        substr($authConfig, (int) strpos($authConfig, "'mass_mail' => ["), 400),
        "'boost'"
    ),
    '群发权限清单不含 boost',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 4. 预览与执行共用守卫
// ------------------------------------------------------------------
$serviceSource = stripComments((string) file_get_contents($root . '/app/Domain/CharacterBoost/CharacterBoostService.php'));
expect(
    method_exists(\Acme\Panel\Domain\CharacterBoost\CharacterBoostService::class, 'previewByGuid'),
    '提供只读预览方法',
    $checks,
    $failures
);
expect(
    substr_count($serviceSource, 'resolveGuard(') >= 2,
    '预览与执行共用同一套守卫规则',
    $checks,
    $failures,
    ['resolveGuard_calls' => substr_count($serviceSource, 'resolveGuard(')]
);

// 语言键一致
$zh = require $root . '/resources/lang/zh_CN/character_boost.php';
$en = require $root . '/resources/lang/en/character_boost.php';
$flatten = static function (array $array, string $prefix = '') use (&$flatten): array {
    $out = [];
    foreach ($array as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += $flatten($value, $path);
        } else {
            $out[$path] = true;
        }
    }
    return $out;
};
$zhKeys = $flatten($zh);
$enKeys = $flatten($en);
expect(
    array_diff(array_keys($zhKeys), array_keys($enKeys)) === [],
    '英文语言文件不缺键',
    $checks,
    $failures,
    ['missing' => array_values(array_diff(array_keys($zhKeys), array_keys($enKeys)))]
);
expect(
    array_diff(array_keys($enKeys), array_keys($zhKeys)) === [],
    '中文语言文件不缺键',
    $checks,
    $failures,
    ['missing' => array_values(array_diff(array_keys($enKeys), array_keys($zhKeys)))]
);

echo json_encode([
    'success' => $failures === [],
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
