<?php

declare(strict_types=1);

/**
 * 账号管理 / 角色管理列表页交互与 UI 校验：
 *   1. 两个列表页使用同一套筛选面板（.list-filter），字段带标签且可重置；
 *   2. 账号行把低频操作收进"更多"菜单，但动作按钮仍然齐全、data-action 未变；
 *   3. 两页共享的样式类真的存在于随页面加载的 CSS 里；
 *   4. 中英文语言键一一对应。
 *
 * 用法：php tools/verify_list_pages.php [serverId]
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

$checks = [];
$failures = [];

function expect(bool $condition, string $label, array &$checks, array &$failures, $detail = null): void
{
    $checks[] = ['name' => $label, 'status' => $condition ? 'passed' : 'failed', 'detail' => $detail];
    if (!$condition) {
        $failures[] = $label;
    }
}

function renderPage(string $controllerClass, string $method, string $uri, array $get = []): ?string
{
    try {
        $controller = new $controllerClass();
        $request = new Request();
        $request->method = 'GET';
        $request->uri = $uri;
        $request->get = $get;
        $request->post = [];
        $request->server = $_SERVER;

        $response = $controller->{$method}($request);
        $content = new ReflectionProperty(Response::class, 'content');
        $content->setAccessible(true);
        return (string) $content->getValue($response);
    } catch (\Throwable $exception) {
        return null;
    }
}

/** 收集翻译后的文案，用来断言"标签确实渲染出来了" */
function langValues(string $file, array $path): array
{
    $root = dirname(__DIR__);
    $bundle = require $root . '/resources/lang/zh_CN/' . $file . '.php';
    $node = $bundle;
    foreach ($path as $segment) {
        if (!is_array($node) || !array_key_exists($segment, $node)) {
            return [];
        }
        $node = $node[$segment];
    }
    return is_array($node) ? $node : [];
}

// ------------------------------------------------------------------
// 1. 账号列表页
// ------------------------------------------------------------------
$_SERVER['REQUEST_URI'] = '/account';
$accountHtml = renderPage(AccountController::class, 'index', '/account', ['load_all' => '1']);
expect($accountHtml !== null, '账号列表页渲染成功', $checks, $failures);
$accountHtml = (string) $accountHtml;

if ($accountHtml !== '') {
    expect(str_contains($accountHtml, 'list-filter'), '账号页使用统一样式的筛选面板', $checks, $failures);
    expect(str_contains($accountHtml, 'list-filter__field'), '账号页筛选项带字段标签', $checks, $failures);
    expect(str_contains($accountHtml, 'list-filter__actions'), '账号页有独立操作行', $checks, $failures);

    $accountSearch = langValues('account', ['search']);
    expect(
        $accountSearch !== [] && str_contains($accountHtml, (string) ($accountSearch['clear'] ?? '')),
        '账号页有"重置条件"入口',
        $checks,
        $failures,
        ['label' => $accountSearch['clear'] ?? null]
    );
    expect(
        $accountSearch !== [] && str_contains($accountHtml, (string) ($accountSearch['value_label'] ?? '')),
        '账号页关键字输入有标签',
        $checks,
        $failures
    );

    expect(str_contains($accountHtml, 'row-action-bar'), '账号行使用统一操作条', $checks, $failures);
    expect(str_contains($accountHtml, 'row-menu'), '账号行提供"更多"菜单', $checks, $failures);

    // 动作按钮必须一个都不少（data-action 是 JS 的契约）
    foreach (['chars', 'gm', 'ban', 'unban', 'pass', 'email', 'rename', 'ip-accounts', 'kick', 'delete'] as $action) {
        expect(
            str_contains($accountHtml, 'data-action="' . $action . '"'),
            '账号操作仍然可用：' . $action,
            $checks,
            $failures
        );
    }
}

// ------------------------------------------------------------------
// 2. 角色列表页
// ------------------------------------------------------------------
$_SERVER['REQUEST_URI'] = '/character';
$characterHtml = renderPage(CharacterController::class, 'index', '/character', ['load_all' => '1']);
expect($characterHtml !== null, '角色列表页渲染成功', $checks, $failures);
$characterHtml = (string) $characterHtml;

if ($characterHtml !== '') {
    expect(str_contains($characterHtml, 'list-filter'), '角色页使用同一套筛选面板', $checks, $failures);
    expect(str_contains($characterHtml, 'list-filter__field'), '角色页筛选项带字段标签', $checks, $failures);
    expect(str_contains($characterHtml, 'list-filter__range'), '角色页等级区间合并成一个字段', $checks, $failures);

    $characterSearch = langValues('character', ['index', 'search']);
    expect(
        $characterSearch !== [] && str_contains($characterHtml, (string) ($characterSearch['clear'] ?? '')),
        '角色页有"重置条件"入口',
        $checks,
        $failures,
        ['label' => $characterSearch['clear'] ?? null]
    );
    expect(
        $characterSearch !== [] && str_contains($characterHtml, (string) ($characterSearch['level_label'] ?? '')),
        '角色页等级区间有标签',
        $checks,
        $failures
    );
    expect(str_contains($characterHtml, 'row-action-bar'), '角色行使用同一操作条', $checks, $failures);
}

// ------------------------------------------------------------------
// 3. 共享样式（必须放在两页都会加载的 app-core.css 里）
// ------------------------------------------------------------------
$sharedCss = (string) file_get_contents($root . '/public/assets/css/app-core.css');
foreach (['.list-filter', '.list-filter__grid', '.list-filter__actions', '.row-action-bar', '.row-menu', '.row-menu__list'] as $selector) {
    expect(
        str_contains($sharedCss, $selector),
        'app-core.css 含共享样式：' . $selector,
        $checks,
        $failures
    );
}

// ------------------------------------------------------------------
// 3b. 回归：旧版横向工具条样式会把纵向 flex 的输入框压成窄高方块
// ------------------------------------------------------------------
$legacySelectors = [
    '.account-search{',
    '.account-search__row',
    '.account-search__inline-field',
    '.account-search__exclude-input',
    '.character-search{',
    '.character-search__row',
    '.character-search__guid',
    '.character-search__level',
    '.character-search__actions',
];
$staleSelectors = [];
foreach ($legacySelectors as $selector) {
    if (str_contains($sharedCss, $selector)) {
        $staleSelectors[] = $selector;
    }
}
expect(
    $staleSelectors === [],
    'app-core.css 已清理旧版横向搜索条样式',
    $checks,
    $failures,
    ['stale' => $staleSelectors]
);

// 视图里不能再挂旧类名，否则又会把 list-filter 的纵向布局带偏
foreach (['account' => $accountHtml, 'character' => $characterHtml] as $label => $html) {
    expect(
        !str_contains($html, 'class="account-search') && !str_contains($html, 'class="character-search'),
        $label . ' 页不再使用旧版搜索条类名',
        $checks,
        $failures
    );
}

// 两个模块 CSS 里也不能再残留窄宽度规则（宽度交给栅格修饰符）
$accountModuleCss = (string) file_get_contents($root . '/public/assets/css/modules/account.css');
$characterModuleCss = (string) file_get_contents($root . '/public/assets/css/modules/character.css');
expect(
    !str_contains($accountModuleCss, 'account-search__') && !str_contains($characterModuleCss, 'character-search__'),
    '模块样式表不再残留旧版字段宽度规则',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 3c. 新面板结构：底部状态/动作行，且"请输入查询条件"在表单内部
// ------------------------------------------------------------------
foreach (['account' => $accountHtml, 'character' => $characterHtml] as $label => $html) {
    expect(str_contains($html, 'list-filter__footer'), $label . ' 页有面板底部行', $checks, $failures);
    expect(str_contains($html, 'list-filter__status'), $label . ' 页有状态/反馈槽位', $checks, $failures);
}

// 空状态提示必须落在 <form> 内（旧版渲染在按钮下方，视觉上像第三条工具栏）
$_SERVER['REQUEST_URI'] = '/account';
$accountEmptyHtml = (string) renderPage(AccountController::class, 'index', '/account', []);
$_SERVER['REQUEST_URI'] = '/character';
$characterEmptyHtml = (string) renderPage(CharacterController::class, 'index', '/character', []);

$emptyCases = [
    'account' => [$accountEmptyHtml, (string) (langValues('account', ['feedback'])['enter_search'] ?? '__none__')],
    'character' => [$characterEmptyHtml, (string) (langValues('character', ['index', 'feedback'])['enter_search'] ?? '__none__')],
];
foreach ($emptyCases as $label => [$html, $hintText]) {
    $formEnd = strpos($html, '</form>');
    $hintPos = strpos($html, $hintText);
    expect(
        $hintPos !== false && $formEnd !== false && $hintPos < $formEnd,
        $label . ' 页空状态提示位于筛选面板内',
        $checks,
        $failures,
        ['hint_pos' => $hintPos, 'form_end' => $formEnd]
    );
}

// 面板用到的栅格修饰符必须有定义
preg_match_all('/list-filter__field--[a-z0-9-]+/', $accountHtml . $characterHtml, $modifierHits);
$modifiers = array_values(array_unique($modifierHits[0]));
$undefinedModifiers = [];
foreach ($modifiers as $modifier) {
    if (!str_contains($sharedCss, '.' . $modifier)) {
        $undefinedModifiers[] = $modifier;
    }
}
expect(
    $modifiers !== [] && $undefinedModifiers === [],
    '面板栅格修饰符均有样式定义',
    $checks,
    $failures,
    ['used' => $modifiers, 'undefined' => $undefinedModifiers]
);

// 回归：两页都必须用"内容自适应单行"筛选条。
// 等分栅格会把"角色名/等级"这类短字段拉到 ~350px，正是之前被吐槽的原因。
foreach (['account' => $accountHtml, 'character' => $characterHtml] as $label => $html) {
    expect(
        str_contains($html, 'list-filter__grid list-filter__grid--fit')
            || str_contains($html, 'list-filter__grid--fit'),
        $label . ' 页使用内容自适应单行筛选条',
        $checks,
        $failures
    );
}

// 每个字段都要有宽度预设（--lfw / --lfw-min），否则会退化成默认宽
$fitFieldsUsed = array_values(array_filter($modifiers, static fn (string $m): bool => str_starts_with($m, 'list-filter__field--fit-')));
expect(
    count($fitFieldsUsed) >= 5,
    '筛选字段都挂了宽度预设',
    $checks,
    $failures,
    ['used' => $fitFieldsUsed]
);

$fitPresetsMissing = [];
foreach ($fitFieldsUsed as $modifier) {
    // 形如 .list-filter__field--fit-name{--lfw:...;--lfw-min:...;}
    $pattern = '/' . preg_quote('.' . $modifier, '/') . '\{([^}]*)\}/';
    if (!preg_match($pattern, $sharedCss, $preset) || !str_contains($preset[1], '--lfw') || !str_contains($preset[1], '--lfw-min')) {
        $fitPresetsMissing[] = $modifier;
    }
}
expect(
    $fitPresetsMissing === [],
    '宽度预设同时给出理想值与压缩下限',
    $checks,
    $failures,
    ['missing' => $fitPresetsMissing]
);

// 角色页脚本用的是 panel-flash--danger，必须有对应样式
expect(
    str_contains($sharedCss, '.panel-flash--danger'),
    'panel-flash--danger 别名有样式',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 3d. 渲染结果里不允许残留未翻译 key
// ------------------------------------------------------------------
foreach (['account' => $accountHtml, 'character' => $characterHtml] as $label => $html) {
    preg_match_all('/app\.(?:account|character)\.[A-Za-z0-9_.]+/', $html, $leaks);
    $leaked = array_values(array_unique(array_filter(
        $leaks[0],
        static fn (string $key): bool => !str_ends_with($key, '.')
    )));
    expect($leaked === [], $label . ' 页没有残留未翻译 key', $checks, $failures, ['leaks' => $leaked]);
}

// ------------------------------------------------------------------
// 3e. 两个列表页视图必须存在（面板结构就靠它们）
// ------------------------------------------------------------------
expect(
    is_file($root . '/resources/views/account/index.php')
        && is_file($root . '/resources/views/character/index.php'),
    '两个列表页视图存在',
    $checks,
    $failures
);

// 用真实模块解析确认两页都会加载 app-core.css
$accountStyles = implode(' ', \Acme\Panel\Support\ModuleAssets::stylesheetUrlsForPage('account'));
$characterStyles = implode(' ', \Acme\Panel\Support\ModuleAssets::stylesheetUrlsForPage('character'));
expect(
    str_contains($accountStyles, 'app-core.css') && str_contains($characterStyles, 'app-core.css'),
    '两个列表页都加载 app-core.css',
    $checks,
    $failures,
    ['account' => $accountStyles, 'character' => $characterStyles]
);

// ------------------------------------------------------------------
// 4. 语言键一致
// ------------------------------------------------------------------
foreach (['account', 'character'] as $file) {
    $zh = require $root . '/resources/lang/zh_CN/' . $file . '.php';
    $en = require $root . '/resources/lang/en/' . $file . '.php';
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
    $zhKeys = array_keys($flatten($zh));
    $enKeys = array_keys($flatten($en));

    // 只关心本次新增/改动的键是否双语齐全（历史遗留缺键不作为失败条件）
    $touched = array_values(array_filter($zhKeys, static function (string $key): bool {
        return str_contains($key, '.search.') || $key === 'actions.more';
    }));
    $missing = array_values(array_diff($touched, $enKeys));
    expect($missing === [], $file . ' 新增的筛选/操作文案英文齐全', $checks, $failures, ['missing' => $missing]);
}

echo json_encode([
    'success' => $failures === [],
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
