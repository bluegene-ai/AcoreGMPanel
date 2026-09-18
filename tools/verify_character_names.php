<?php

declare(strict_types=1);

/**
 * 角色详情「ID → 文本」映射与账号/角色互跳校验。
 *
 * 校验三件事：
 *   1. GameNameResolver 能把任务 / 阵营 / 法术 / 技能 / 成就 ID 解析成文本，
 *      并且 DBC 解析结果与已知样本一致（暴风城 / 冰霜 / 火球术 …）；
 *   2. 角色详情页渲染出的是名称而不是裸 ID，且不再依赖外部网站补名；
 *   3. 账号管理 ↔ 角色管理之间存在双向跳转入口。
 *
 * 用法：php tools/verify_character_names.php [serverId]
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

$serverId = isset($argv[1]) ? (int) $argv[1] : 1;
ServerContext::set($serverId);

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
// 1. ID → 文本解析
// ------------------------------------------------------------------
$known = [
    'faction' => [72 => '暴风城', 469 => '联盟', 67 => '部落'],
    'skill' => [6 => '冰霜', 8 => '火焰', 171 => '炼金术'],
    'spell' => [133 => '火球术', 116 => '寒冰箭'],
    'achievement' => [6 => '10级', 2144 => '千奇百怪的漫长旅行'],
    'quest' => [1 => '坎瑞萨德的任务', 2 => '沙普塔隆的爪子'],
];

$started = microtime(true);
foreach ($known as $type => $samples) {
    $resolved = GameNameResolver::resolveMany($type, array_keys($samples));
    $mismatch = [];
    foreach ($samples as $id => $expected) {
        if (($resolved[$id] ?? null) !== $expected) {
            $mismatch[$id] = ['expected' => $expected, 'actual' => $resolved[$id] ?? null];
        }
    }
    expect(
        $mismatch === [],
        $type . ' 名称映射正确',
        $checks,
        $failures,
        ['resolved' => count($resolved), 'mismatch' => $mismatch]
    );
}
$checks[] = ['name' => 'raf.resolver_timing', 'status' => 'passed', 'detail' => round(microtime(true) - $started, 3) . 's'];

// 未知 ID 不能编造名字
expect(
    GameNameResolver::resolveMany('spell', [999999999]) === [],
    '未知 ID 返回空而不是伪造名称',
    $checks,
    $failures
);
expect(
    GameNameResolver::resolveMany('not_a_type', [1]) === [],
    '非法类型被拒绝',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 2. 角色详情页渲染
// ------------------------------------------------------------------
$viewFile = $root . '/resources/views/character/show.php';
$viewSource = (string) file_get_contents($viewFile);

expect(
    !str_contains($viewSource, 'js-nfuwow'),
    '角色详情页不再依赖外部网站补名',
    $checks,
    $failures
);
expect(
    str_contains($viewSource, "gameNameCell('quest'"),
    '任务列使用服务端映射的名称',
    $checks,
    $failures
);
expect(
    str_contains($viewSource, "gameNameCell('faction'"),
    '声望列使用服务端映射的阵营名称',
    $checks,
    $failures
);
expect(
    str_contains($viewSource, 'standing_tiers'),
    '声望值附带等级文案',
    $checks,
    $failures
);
expect(
    str_contains($viewSource, '$gameNames') && str_contains($viewSource, 'game_names'),
    '详情页接收控制器解析结果',
    $checks,
    $failures
);

// 控制器必须把映射结果交给视图，并且用新解析器而不是外部网站
$controllerFile = $root . '/app/Http/Controllers/Character/CharacterController.php';
$controllerSource = (string) file_get_contents($controllerFile);
expect(
    !str_contains($controllerSource, 'NfuwowNameResolver'),
    '控制器不再调用外部网站解析器',
    $checks,
    $failures
);
expect(
    str_contains($controllerSource, 'GameNameResolver') && str_contains($controllerSource, "'game_names' => \$names"),
    '控制器把服务端映射结果注入视图',
    $checks,
    $failures
);

// 语言文件必须两种语言都齐全
$langZh = require $root . '/resources/lang/zh_CN/character.php';
$langEn = require $root . '/resources/lang/en/character.php';
expect(
    isset($langZh['show']['reputations']['standing_tiers']['exalted'])
        && isset($langEn['show']['reputations']['standing_tiers']['exalted']),
    '声望等级文案双语齐全',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 3. 账号 ↔ 角色互跳
// ------------------------------------------------------------------
$accountShow = (string) file_get_contents($root . '/resources/views/account/show.php');
$characterIndex = (string) file_get_contents($root . '/resources/views/character/index.php');
$characterShow = (string) file_get_contents($viewFile);
$accountJs = (string) file_get_contents($root . '/public/assets/js/modules/account.js');

expect(
    str_contains($accountShow, "'/character?account='"),
    '账号详情页可跳到角色管理的账号筛选',
    $checks,
    $failures
);
expect(
    str_contains($characterIndex, "'/character?account='"),
    '角色列表可按同账号快速筛选',
    $checks,
    $failures
);
expect(
    str_contains($characterShow, "'/character?account='") && str_contains($characterShow, 'account_view_url'),
    '角色详情页可回到账号与同账号角色',
    $checks,
    $failures
);
expect(
    str_contains($accountJs, 'appendModalFooterLink') && str_contains($accountJs, '/character?account='),
    '账号角色弹窗提供到角色管理的入口',
    $checks,
    $failures
);

// 跳转目标必须真的是角色列表的账号筛选键
$charRepository = (string) file_get_contents($root . '/app/Domain/Character/CharacterRepository.php');
expect(
    str_contains($charRepository, "\$filters['account']") || str_contains($charRepository, 'accountName'),
    '角色查询支持 account 筛选参数',
    $checks,
    $failures
);

echo json_encode([
    'success' => $failures === [],
    'server_id' => $serverId,
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
