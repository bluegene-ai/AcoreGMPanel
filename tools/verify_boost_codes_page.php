<?php

declare(strict_types=1);

/**
 * 直升兑换码管理页（/character-boost/redeem-codes）校验：
 *   1. 页面可渲染，且"生成 / 管理"两大区块的关键节点齐全；
 *   2. 视图里的语言 key 全部已翻译（不泄漏 app.character_boost.* 原文）；
 *   3. JS 模块用到的翻译路径在浏览器实际拿到的 locale 树里都能命中；
 *   4. 页面与脚本用到的 cb-* 样式类都在 character_boost.css 里定义；
 *   5. 中英文语言文件键位一致。
 *
 * 用法：php tools/verify_boost_codes_page.php [serverId]
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostRedeemCodeAdminController;
use Acme\Panel\Support\PanelLocale;
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
$_SERVER['REQUEST_URI'] = '/character-boost/redeem-codes';
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

// ------------------------------------------------------------------
// 1. 渲染页面
// ------------------------------------------------------------------
$html = '';

try {
    $controller = new CharacterBoostRedeemCodeAdminController();
    $request = new Request();
    $request->method = 'GET';
    $request->uri = '/character-boost/redeem-codes';
    $request->get = [];
    $request->post = [];
    $request->server = $_SERVER;

    $response = $controller->index($request);
    $property = new ReflectionProperty(Response::class, 'content');
    $property->setAccessible(true);
    $html = (string) $property->getValue($response);

    expect(strlen($html) > 3000, '兑换码管理页渲染成功', $checks, $failures, ['bytes' => strlen($html)]);
} catch (Throwable $exception) {
    expect(false, '兑换码管理页渲染成功', $checks, $failures, [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile() . ':' . $exception->getLine(),
    ]);
}

$requiredIds = [
    'boostCodesApp',
    'boostCodesForm',
    'boostCodesTemplate',
    'boostCodesCount',
    'boostCodesQuickSet',
    'boostCodesDownload',
    'boostCodesSubmit',
    'boostCodesResultPanel',
    'boostCodesOutput',
    'boostCodesCopy',
    'boostCodesResultCollapse',
    'boostCodesResultCount',
    'boostCodesManageForm',
    'boostCodesManageTemplate',
    'boostCodesManageStatus',
    'boostCodesManageSearch',
    'boostCodesManagePerPage',
    'boostCodesStatTotal',
    'boostCodesStatUnused',
    'boostCodesStatUsed',
    'boostCodesManageTbody',
    'boostCodesSortId',
    'boostCodesPagePrev',
    'boostCodesPageNext',
    'boostCodesPageInfo',
    'boostCodesManageRefresh',
    'boostCodesManagePurgeUnused',
];

$missingIds = [];
foreach ($requiredIds as $id) {
    if (!str_contains($html, 'id="' . $id . '"')) {
        $missingIds[] = $id;
    }
}
expect($missingIds === [], '页面关键节点齐全', $checks, $failures, ['missing' => $missingIds]);

// 隐藏的 unused_only 复选框必须保留（apiList 依赖它）
expect(
    str_contains($html, 'id="boostCodesManageUnusedOnly"'),
    '保留 unused_only 兼容字段',
    $checks,
    $failures
);

// 结果区默认收起，避免空文本框占位
expect(
    (bool) preg_match('/id="boostCodesResultPanel"[^>]*\bhidden\b/', $html),
    '生成结果区默认收起',
    $checks,
    $failures
);

// 资源挂载：脚本用页面级模块，样式用 character_boost 包
expect(str_contains($html, 'data-module="character_boost_codes"'), '页面挂载兑换码脚本模块', $checks, $failures);
expect(str_contains($html, 'character_boost.css'), '页面加载直升模块样式', $checks, $failures);

// ------------------------------------------------------------------
// 2. 视图语言 key 全部已翻译
// ------------------------------------------------------------------
$viewPath = $root . '/resources/views/character_boost/codes.php';
expect(is_file($viewPath), '兑换码视图存在', $checks, $failures, ['path' => $viewPath]);

$viewKeys = [];
if (is_file($viewPath)) {
    $viewSource = (string) file_get_contents($viewPath);
    preg_match_all('/__\(\s*[\'"]([^\'"]+)[\'"]/', $viewSource, $matches);
    $viewKeys = array_values(array_unique(array_filter(
        $matches[1],
        static fn (string $key): bool => str_starts_with($key, 'app.')
    )));
}

$untranslated = [];
foreach ($viewKeys as $key) {
    $value = Lang::get($key);
    if ($value === $key || str_starts_with($value, 'app.')) {
        $untranslated[] = $key;
    }
}
expect($untranslated === [], '视图语言 key 均已翻译', $checks, $failures, ['untranslated' => $untranslated]);
expect(count($viewKeys) > 30, '视图使用了足够的语言 key', $checks, $failures, ['count' => count($viewKeys)]);

// 渲染结果里不允许残留未翻译的 app.* 原文
preg_match_all('/app\.(?:character_boost|common)\.[A-Za-z0-9_.]+/', $html, $leaks);
$leaked = array_values(array_unique(array_filter(
    $leaks[0],
    static fn (string $key): bool => !str_ends_with($key, '.')
)));
expect($leaked === [], '页面没有残留未翻译 key', $checks, $failures, ['leaks' => $leaked]);

// ------------------------------------------------------------------
// 3. JS 翻译路径在浏览器 locale 树里能命中
// ------------------------------------------------------------------
$jsPath = $root . '/public/assets/js/modules/character_boost_codes.js';
expect(is_file($jsPath), '兑换码交互脚本存在', $checks, $failures, ['path' => $jsPath]);

$cssPath = $root . '/public/assets/css/modules/character_boost.css';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

if (is_file($jsPath)) {
    $jsSource = (string) file_get_contents($jsPath);

    preg_match_all("/translate\(\s*'([^']+)'/", $jsSource, $jsMatches);
    $jsKeys = array_values(array_unique($jsMatches[1]));

    // 浏览器侧 locale：PanelLocale::jsLocaleForPage 生成的树 + panel.js 的回退顺序
    $locale = PanelLocale::jsLocaleForPage('character_boost_codes');
    $moduleTree = $locale['modules']['character_boost'] ?? [];

    $resolvePath = static function (array $tree, array $segments) {
        $node = $tree;
        foreach ($segments as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }
        return is_string($node) ? $node : null;
    };

    $missingJsKeys = [];
    foreach ($jsKeys as $key) {
        $segments = explode('.', $key);
        $candidates = [
            $resolvePath($moduleTree, $segments),
            $resolvePath($locale['common']['modules']['character_boost'] ?? [], $segments),
            $resolvePath($locale['common']['api'] ?? [], $segments),
            $resolvePath($locale['common'] ?? [], $segments),
        ];

        if (!array_filter($candidates, static fn ($value): bool => is_string($value) && $value !== '')) {
            $missingJsKeys[] = $key;
        }
    }

    expect($missingJsKeys === [], 'JS 翻译路径全部命中', $checks, $failures, ['missing' => $missingJsKeys]);
    expect(count($jsKeys) > 10, 'JS 使用了足够的翻译键', $checks, $failures, ['count' => count($jsKeys)]);

    // 两个表单都必须阻止默认提交，否则回车会整页刷新
    expect(
        substr_count($jsSource, 'event.preventDefault()') >= 2,
        '生成与管理表单都阻止默认提交',
        $checks,
        $failures,
        ['preventDefault_calls' => substr_count($jsSource, 'event.preventDefault()')]
    );
    expect(
        str_contains($jsSource, 'manageForm.addEventListener(\'submit\''),
        '管理筛选表单有 submit 守卫',
        $checks,
        $failures
    );

    // 状态下拉必须把三态 status 上送（unused_only 仅为旧接口兼容），否则"已使用"等价于"全部"
    expect(
        str_contains($jsSource, "d2.set('status', status)"),
        '管理筛选按状态三态上送 status',
        $checks,
        $failures
    );

    // 结果区收起时生成卡片要占满整行（否则右侧留空列）
    expect(
        str_contains($jsSource, 'cb-codes__top--single') && str_contains($css, '.cb-codes__top--single'),
        '结果区收起时顶部单列布局',
        $checks,
        $failures
    );
}

// ------------------------------------------------------------------
// 4. cb-* 样式类已定义
// ------------------------------------------------------------------
$collectClasses = static function (string $source): array {
    preg_match_all('/\bcb-[A-Za-z0-9_-]+/', $source, $hits);
    return array_values(array_unique($hits[0]));
};

$usedClasses = array_values(array_unique(array_merge(
    $collectClasses($html),
    is_file($jsPath) ? $collectClasses((string) file_get_contents($jsPath)) : []
)));

$undefinedClasses = [];
foreach ($usedClasses as $class) {
    if (!str_contains($css, '.' . $class)) {
        $undefinedClasses[] = $class;
    }
}
expect($undefinedClasses === [], 'cb-* 样式类均有定义', $checks, $failures, ['undefined' => $undefinedClasses]);

// 新增的兑换码页布局类必须在样式表里（防止半成品样式）
$layoutClasses = [
    'cb-codes__top',
    'cb-codes__grid',
    'cb-codes__filters',
    'cb-codes__filter-grid',
    'cb-codes__footer',
    'cb-card__head',
    'cb-card__title',
    'cb-card__tools',
    'cb-chip',
    'cb-quick',
    'cb-stat-row',
    'cb-stat-card',
    'cb-badge--used',
    'cb-badge--unused',
    'cb-output--result',
    'cb-nowrap',
    'cb-help--flush',
];
$missingLayout = [];
foreach ($layoutClasses as $class) {
    if (!str_contains($css, '.' . $class)) {
        $missingLayout[] = $class;
    }
}
expect($missingLayout === [], '兑换码页布局样式齐全', $checks, $failures, ['missing' => $missingLayout]);

// ------------------------------------------------------------------
// 5. 中英文键位一致
// ------------------------------------------------------------------
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

$zhKeys = $flatten(require $root . '/resources/lang/zh_CN/character_boost.php');
$enKeys = $flatten(require $root . '/resources/lang/en/character_boost.php');

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
