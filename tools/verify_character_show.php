<?php

declare(strict_types=1);

/**
 * 角色详情页渲染冒烟测试（用真实游戏对象 ID 渲染，校验输出的是文本而不是 code）。
 *
 * 用法：php tools/verify_character_show.php
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\View;
use Acme\Panel\Support\GameNameResolver;
use Acme\Panel\Support\ServerContext;

Config::init($root . '/config');
Lang::init();

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION = ['panel_logged_in' => true, 'panel_user' => 'cli-verifier', 'panel_capabilities' => ['*']];
ServerContext::set(isset($argv[1]) ? (int) $argv[1] : 1);

$failures = [];
$checks = [];

function expect(bool $condition, string $label, array &$checks, array &$failures, $detail = null): void
{
    $checks[] = ['name' => $label, 'status' => $condition ? 'passed' : 'failed', 'detail' => $detail];
    if (!$condition) {
        $failures[] = $label;
    }
}

$questIds = [1, 2, 5];
$factionIds = [72, 67, 469];
$spellIds = [133, 116];
$skillIds = [6, 8];
$achievementIds = [6, 2144];
$itemIds = [21841];
$names = [
    'quest' => GameNameResolver::resolveMany('quest', $questIds),
    'faction' => GameNameResolver::resolveMany('faction', $factionIds),
    'spell' => GameNameResolver::resolveMany('spell', $spellIds),
    'skill' => GameNameResolver::resolveMany('skill', $skillIds),
    'achievement' => GameNameResolver::resolveMany('achievement', $achievementIds),
    'item' => GameNameResolver::resolveMany('item', $itemIds),
];

$skills = [];
foreach ($skillIds as $id) {
    $skills[] = ['skill' => $id, 'value' => 450, 'max' => 450];
}
$spells = [];
foreach ($spellIds as $id) {
    $spells[] = ['spell' => $id, 'active' => 1, 'disabled' => 0];
}
$reps = [];
foreach ($factionIds as $id) {
    $reps[] = ['faction' => $id, 'standing' => 42999, 'flags' => 0x01];
}
$quests = [
    'regular' => array_map(static fn (int $id): array => [
        'quest' => $id, 'status' => 1, 'timer' => 0,
        'mobcount1' => 0, 'mobcount2' => 0, 'mobcount3' => 0, 'mobcount4' => 0,
        'itemcount1' => 0, 'itemcount2' => 0, 'itemcount3' => 0, 'itemcount4' => 0,
    ], $questIds),
    'daily' => array_map(static fn (int $id): array => ['quest' => $id], []),
    'weekly' => [],
];
$achievements = [
    'unlocks' => array_map(static fn (int $id): array => ['achievement' => $id, 'date' => time()], $achievementIds),
    'progress' => [['criteria' => 12345, 'counter' => 3, 'date' => time()]],
];
$auras = [['spell' => 133, 'caster_guid' => 1, 'item_guid' => 0, 'effect_mask' => 1, 'amount0' => 1, 'amount1' => 0, 'amount2' => 0, 'remaincharges' => 0, 'maxduration' => 0, 'remaintime' => 0]];
$cooldowns = [
    ['spellid' => 133, 'itemid' => 21841, 'time' => time(), 'category' => 0],
];

$summary = [
    'guid' => 1, 'name' => 'RenderProbe', 'account' => 1, 'account_username' => 'probe',
    'level' => 80, 'class' => 8, 'race' => 1, 'gender' => 1, 'online' => 0, 'map' => 0, 'zone' => 1519,
    'position_x' => 1.0, 'position_y' => 2.0, 'position_z' => 3.0, 'money' => 100,
    'logout_time' => time(), 'homebind' => null, 'gmlevel' => 0, 'ban' => null,
];

$html = View::make('character.show', [
    'summary' => $summary,
    'inventory' => [],
    'skills' => $skills,
    'spells' => $spells,
    'reputations' => $reps,
    'quests' => $quests,
    'auras' => $auras,
    'cooldowns' => $cooldowns,
    'achievements' => $achievements,
    'game_names' => $names,
    'mail_count' => 0,
    'boost_templates' => [],
    'error' => null,
    '__pageCapabilities' => ['details' => true, 'ban' => true, 'delete' => true, 'level' => true, 'gold' => true, 'teleport' => true, 'reset' => true, 'boost' => true, 'boost_templates' => true, 'boost_codes' => true],
]);

expect(str_contains($html, 'char-name-cell'), '渲染出名称单元格', $checks, $failures, ['bytes' => strlen($html)]);

foreach ($names as $type => $map) {
    foreach ($map as $id => $name) {
        $escaped = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        expect(
            str_contains($html, $escaped),
            $type . ' #' . $id . ' 渲染为「' . $name . '」',
            $checks,
            $failures
        );
    }
}

expect(str_contains($html, '崇拜'), '声望等级渲染为「崇拜」', $checks, $failures);
expect(!str_contains($html, 'js-nfuwow'), '输出中不含待补名占位', $checks, $failures);
// 未映射的成就条件仍要显示原始 ID，不能凭空丢掉
expect(str_contains($html, '12345'), '未映射的成就条件仍显示原始 ID', $checks, $failures);

// 性别：0=男 1=女，必须显示文案而不是数字
$genderLabel = \Acme\Panel\Support\GameMaps::genderName(1);
expect(
    $genderLabel !== '' && str_contains($html, $genderLabel),
    '性别渲染为「' . $genderLabel . '」',
    $checks,
    $failures,
    ['label' => $genderLabel]
);

// 冷却表：法术列与物品列都必须显示名称（此前直接输出原始 ID）
// 锚点用表头组合，避免命中"重置冷却"按钮文字里的"冷却"
$cooldownHeader = (string) Lang::get('app.character.show.cooldowns.spell');
$cooldownHeader .= '</th>';
$cooldownsSectionPos = $cooldownHeader !== '</th>' ? strpos($html, $cooldownHeader) : false;
$cooldownBlock = $cooldownsSectionPos === false
    ? ''
    : substr($html, $cooldownsSectionPos, 1500);

expect($cooldownBlock !== '', '定位到冷却区域', $checks, $failures, ['anchor' => $cooldownHeader]);
if ($cooldownBlock !== '') {
    $cooldownSpellName = $names['spell'][133] ?? null;
    $cooldownItemName = $names['item'][21841] ?? null;

    expect(
        $cooldownSpellName !== null && str_contains($cooldownBlock, $cooldownSpellName),
        '冷却表法术列显示名称「' . $cooldownSpellName . '」',
        $checks,
        $failures,
        ['name' => $cooldownSpellName]
    );
    expect(
        $cooldownItemName !== null && str_contains($cooldownBlock, $cooldownItemName),
        '冷却表物品列显示名称「' . $cooldownItemName . '」',
        $checks,
        $failures,
        ['name' => $cooldownItemName]
    );
}

echo json_encode([
    'success' => $failures === [],
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
