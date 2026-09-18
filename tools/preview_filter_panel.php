<?php

declare(strict_types=1);

/**
 * 生成"账号管理 / 角色管理筛选面板"的离线预览页，用于真实浏览器渲染核对。
 *
 * 把 app-core.css 与两个模块样式内联进 HTML，再套上真实的布局骨架
 * （.layout-shell-bg / .layout-grid / .sidebar--shell / .app-shell-panel），
 * 这样面板拿到的可用宽度与线上页面一致。
 *
 * 用法：
 *   php tools/preview_filter_panel.php            # 写到 storage/tmp/filter-preview.html
 *   php tools/preview_filter_panel.php account    # 只渲染账号面板
 *   php tools/preview_filter_panel.php character  # 只渲染角色面板
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
    'panel_user' => 'cli-preview',
    'panel_capabilities' => ['*'],
];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

ServerContext::set(isset($argv[2]) ? (int) $argv[2] : 1);

/** 渲染一个列表页，并截出筛选面板那一块 */
$renderPanel = static function (string $controllerClass, string $uri): string {
    $_SERVER['REQUEST_URI'] = $uri;

    $controller = new $controllerClass();
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
    if ($start === false) {
        return '<p>panel not found</p>';
    }
    $end = strpos($html, '</form>', $start);

    return substr($html, $start, $end - $start + 7);
};

$which = strtolower((string) ($argv[1] ?? 'all'));
$sections = [];

if ($which === 'all' || $which === 'account') {
    $sections[] = ['title' => '账号管理', 'html' => $renderPanel(AccountController::class, '/account')];
}
if ($which === 'all' || $which === 'character') {
    $sections[] = ['title' => '角色管理', 'html' => $renderPanel(CharacterController::class, '/character')];
}
if ($sections === []) {
    fwrite(STDERR, "unknown section: {$which}\n");
    exit(2);
}

$css = '';
foreach (['css/app-core.css', 'css/modules/account.css', 'css/modules/character.css'] as $relative) {
    $path = $root . '/public/assets/' . $relative;
    if (!is_file($path)) {
        continue;
    }
    $css .= "/* ==== {$relative} ==== */\n" . file_get_contents($path) . "\n";
}

$body = '';
foreach ($sections as $section) {
    $body .= '        <h2 class="preview-heading">' . htmlspecialchars($section['title']) . "</h2>\n"
        . $section['html'] . "\n";
}

$html = <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>筛选面板预览</title>
<style>
{$css}
/* ==== 仅预览用：让骨架紧凑一些，不影响真实样式判定 ==== */
html,body{margin:0;padding:0;background:#060d15;}
.preview-heading{margin:22px 0 10px;font-size:15px;color:#93b6dd;font-weight:600;letter-spacing:.04em;}
.preview-heading:first-child{margin-top:0;}
</style>
</head>
<body>
<div class="layout-shell-bg">
  <div class="layout-grid layout-grid--shell">
    <aside class="sidebar--shell">
      <div class="sidebar__brand"><strong>AGMP</strong></div>
      <ul>
        <li><a href="#">首页</a></li>
        <li><a href="#">账号管理</a></li>
        <li><a class="active" href="#">角色管理</a></li>
        <li><a href="#">直升管理</a></li>
        <li><a href="#">招募管理</a></li>
      </ul>
    </aside>
    <main class="container app-shell-main">
      <div class="app-shell-panel">
        <div class="layout-topbar app-shell-toolbar">
          <div class="layout-topbar__spacer"></div>
          <div class="layout-topbar__actions"><span class="muted small">服务器: 80-女王的复仇 · 语言: 简体中文 · 15 / 3467 在线/总数</span></div>
        </div>
{$body}
      </div>
    </main>
  </div>
</div>
<pre id="measure-out" style="margin:18px 0 0;padding:10px 12px;background:#000;color:#7fd7a0;font:12px/1.5 Consolas,monospace;white-space:pre;border-radius:8px;"></pre>
</body>
</html>
HTML;

// 量尺寸的脚本必须放在 <head>：面板标记里带 <select>/<table> 等，
// 放在 body 末尾时会被 HTML 解析器吞进不可执行上下文。放 head + DOMContentLoaded 最稳。
$measureScript = <<<'JS'
<script>
// 量一量真实布局，结果写进 #measure-out，截图即可读数（同时兼容 --dump-dom）。
(function () {
  function run() {
    var pre = document.getElementById('measure-out');
    if (!pre) return;
    try {
      var lines = ['viewport=' + document.documentElement.clientWidth];
      Array.prototype.forEach.call(document.querySelectorAll('.list-filter'), function (form, index) {
        var grid = form.querySelector('.list-filter__grid');
        lines.push('panel#' + index
          + ' form=' + Math.round(form.getBoundingClientRect().width)
          + ' grid=' + Math.round(grid.getBoundingClientRect().width));
        var rows = {};
        Array.prototype.forEach.call(grid.querySelectorAll('.list-filter__field'), function (field) {
          var control = field.querySelector('input, select, .list-filter__range');
          var top = Math.round(field.getBoundingClientRect().top);
          rows[top] = (rows[top] || 0) + 1;
          var label = field.querySelector('span');
          lines.push('  ' + (label ? label.textContent.trim() : '?')
            + ' field=' + Math.round(field.getBoundingClientRect().width)
            + ' ctl=' + (control ? Math.round(control.getBoundingClientRect().width) : '-'));
        });
        var tops = Object.keys(rows);
        lines.push('  rows=' + tops.length + ' perRow='
          + tops.map(function (k) { return rows[k]; }).join(','));
      });
      pre.textContent = lines.join('\n');
    } catch (error) {
      pre.textContent = 'MEASURE ERROR: ' + error;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
</script>
JS;

$html = str_replace('</head>', $measureScript . "\n</head>", $html);

$dir = $root . '/storage/tmp';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

$target = $dir . '/filter-preview.html';
file_put_contents($target, $html);

echo $target . "\n";
echo 'bytes: ' . strlen($html) . "\n";
