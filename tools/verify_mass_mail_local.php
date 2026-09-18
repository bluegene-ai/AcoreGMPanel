<?php

declare(strict_types=1);

/**
 * 群发管理校验：
 *   1. 物品名不再走外部网站，改由 world 库 / DBC 解析，且结果正确；
 *   2. 发件日志会把"物品名 ×数量"写进 items 字段；
 *   3. 界面具备反馈条、物品名解析、收件人统计、日志筛选等交互能力；
 *   4. 中英文语言键一一对应。
 *
 * 用法：php tools/verify_mass_mail.php [serverId]
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Support\GameNameResolver;
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

// ------------------------------------------------------------------
// 1. 物品名解析（本地）
// ------------------------------------------------------------------
$serviceFile = $root . '/app/Domain/MassMail/MassMailService.php';
$serviceSource = (string) file_get_contents($serviceFile);
$jsFile = $root . '/public/assets/js/modules/mass_mail.js';
$jsSource = (string) file_get_contents($jsFile);

/** 去掉 // 与 /* *\/ 注释后再做"不得出现"断言，避免注释里的历史说明误伤 */
$stripComments = static function (string $source): string {
    $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
    return (string) preg_replace('#^\s*//.*$#m', '', $source);
};

$serviceCode = $stripComments($serviceSource);
$jsCode = $stripComments($jsSource);

expect(
    !str_contains($serviceCode, 'nfuwow'),
    '群发服务不再请求外部网站',
    $checks,
    $failures
);
expect(
    str_contains($serviceCode, 'GameNameResolver'),
    '群发服务改用本地名称解析器',
    $checks,
    $failures
);
expect(
    !str_contains($jsCode, 'console.log'),
    'JS 不再只写 console',
    $checks,
    $failures
);
expect(
    method_exists(\Acme\Panel\Domain\MassMail\MassMailService::class, 'resolveItemNames'),
    '提供批量物品名解析方法',
    $checks,
    $failures
);
expect(
    method_exists(\Acme\Panel\Domain\MassMail\MassMailService::class, 'previewTargets'),
    '提供收件人预览方法',
    $checks,
    $failures
);

// 真实取几个物品 ID，确认解析出来的不是空
$knownBonusItems = [21841, 23720];
$resolved = GameNameResolver::resolveMany('item', $knownBonusItems);
expect(
    $resolved !== [],
    '物品名可从 world 库解析',
    $checks,
    $failures,
    ['requested' => $knownBonusItems, 'resolved' => $resolved]
);
foreach ($resolved as $id => $name) {
    expect(
        is_string($name) && trim($name) !== '',
        '物品 #' . $id . ' 名称为「' . $name . '」',
        $checks,
        $failures
    );
}
expect(
    GameNameResolver::resolveMany('item', [2147483647]) === [],
    '不存在的物品 ID 不伪造名称',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 2. 日志写入带名称的摘要
// ------------------------------------------------------------------
expect(
    str_contains($serviceSource, '$itemsDisplay = mb_substr(implode'),
    '日志 items 字段写入带名称的摘要',
    $checks,
    $failures
);
expect(
    str_contains($serviceSource, '×'),
    '摘要使用「名称 ×数量」格式',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 3. 界面与交互
// ------------------------------------------------------------------
$viewFile = $root . '/resources/views/mass_mail/index.php';
$viewSource = (string) file_get_contents($viewFile);
$cssSource = (string) file_get_contents($root . '/public/assets/css/modules/mass_mail.css');

expect(str_contains($viewSource, 'id="mmFeedback"'), '页面提供操作反馈条', $checks, $failures);
expect(str_contains($viewSource, 'id="mmRecipients"'), '页面提供收件人统计区', $checks, $failures);
expect(str_contains($viewSource, 'id="mmItemsSummary"'), '页面提供物品摘要区', $checks, $failures);
expect(str_contains($viewSource, 'id="mmItemsClear"'), '物品编辑器可一键清空', $checks, $failures);
expect(str_contains($viewSource, 'id="logFilter"'), '日志支持关键字筛选', $checks, $failures);
expect(str_contains($viewSource, 'item_name_label'), '物品编辑器有名称列', $checks, $failures);

expect(str_contains($jsSource, 'function toast(msg, type)'), 'JS 反馈带类型（成功/失败）', $checks, $failures);
expect(str_contains($jsSource, "'/api/items'"), 'JS 调用物品名解析接口', $checks, $failures);
expect(str_contains($jsSource, "'/api/targets'"), 'JS 调用收件人预览接口', $checks, $failures);
expect(str_contains($jsSource, 'applyLogFilter'), 'JS 实现日志筛选', $checks, $failures);
expect(str_contains($jsSource, 'validateSendForm'), 'JS 提交前做本地校验', $checks, $failures);
expect(str_contains($jsSource, 'formatGold'), '金币数量显示为金/银/铜', $checks, $failures);
expect(!str_contains($jsSource, 'alert('), 'JS 不再使用阻塞式 alert', $checks, $failures);

expect(str_contains($cssSource, '.mm-recipients'), '收件人统计有样式', $checks, $failures);
expect(str_contains($cssSource, '.mm-item-name'), '物品名称列有样式', $checks, $failures);
expect(str_contains($cssSource, '.massmail-items__row.is-duplicate'), '重复物品有高亮样式', $checks, $failures);

// 路由与控制器
$routes = (string) file_get_contents($root . '/routes/web.php');
expect(str_contains($routes, "'/mass-mail/api/items'"), '注册物品名解析路由', $checks, $failures);
expect(str_contains($routes, "'/mass-mail/api/targets'"), '注册收件人预览路由', $checks, $failures);

// ------------------------------------------------------------------
// 4. 语言键一致
// ------------------------------------------------------------------
$langZh = require $root . '/resources/lang/zh_CN/mass_mail.php';
$langEn = require $root . '/resources/lang/en/mass_mail.php';

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

$zhKeys = $flatten($langZh);
$enKeys = $flatten($langEn);
$missingInEn = array_values(array_diff(array_keys($zhKeys), array_keys($enKeys)));
$missingInZh = array_values(array_diff(array_keys($enKeys), array_keys($zhKeys)));

expect(
    $missingInEn === [],
    '英文语言文件不缺键',
    $checks,
    $failures,
    ['missing' => $missingInEn]
);
expect(
    $missingInZh === [],
    '中文语言文件不缺键',
    $checks,
    $failures,
    ['missing' => $missingInZh]
);

echo json_encode([
    'success' => $failures === [],
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
