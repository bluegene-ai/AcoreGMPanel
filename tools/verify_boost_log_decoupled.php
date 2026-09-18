<?php

declare(strict_types=1);

/**
 * 直升日志解耦校验：
 *   1. 直升拥有独立的 panel_boost_log 表（首次使用自愈建表）；
 *   2. 写入 → 读回一致，且 server_id 隔离；
 *   3. 群发模块里不再有任何直升代码（类、常量、方法、语言键）；
 *   4. 直升历史只读写新表，不碰 panel_massmail_log。
 *
 * 注意：本工具会往 panel_boost_log 写一条 server_id=0 的探针记录，随后删除。
 *
 * 用法：php tools/verify_boost_log_decoupled.php
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Database;
use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\CharacterBoost\BoostHistoryService;
use Acme\Panel\Domain\CharacterBoost\BoostLogRepository;
use Acme\Panel\Support\ServerContext;

Config::init($root . '/config');
Lang::init();

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$_SESSION = ['panel_logged_in' => true, 'panel_user' => 'cli-verifier', 'panel_capabilities' => ['*']];

$serverId = isset($argv[1]) ? (int) $argv[1] : 0;
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

function stripComments(string $source): string
{
    $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
    return (string) preg_replace('#^\s*//.*$#m', '', $source);
}

// ------------------------------------------------------------------
// 1. 写路径不再经过群发
// ------------------------------------------------------------------
$massMailService = stripComments((string) file_get_contents($root . '/app/Domain/MassMail/MassMailService.php'));
foreach ([
    'boostCharacter',
    'BOOST_CLASS_ITEMS',
    'BOOST_GOLD_COPPER',
    'logBoost',
    'boostClassLabel',
    'resolveClassKeyByName',
    'buildBoostItems',
    "action','boost",
    "'boost',",
] as $needle) {
    expect(
        !str_contains($massMailService, $needle),
        '群发服务已无直升代码：' . $needle,
        $checks,
        $failures
    );
}

$massMailLangZh = (string) file_get_contents($root . '/resources/lang/zh_CN/mass_mail.php');
$massMailLangEn = (string) file_get_contents($root . '/resources/lang/en/mass_mail.php');
expect(!str_contains($massMailLangZh, "'boost'"), '群发中文语言包已无直升键', $checks, $failures);
expect(!str_contains($massMailLangEn, "'boost'"), '群发英文语言包已无直升键', $checks, $failures);

// 直升服务是历史记录的唯一写入点
$boostService = (string) file_get_contents($root . '/app/Domain/CharacterBoost/CharacterBoostService.php');
expect(
    str_contains($boostService, 'recordHistory('),
    '直升服务在成功后写历史',
    $checks,
    $failures
);
expect(
    str_contains($boostService, 'BoostHistoryService'),
    '直升服务通过 BoostHistoryService 写历史',
    $checks,
    $failures
);

// BoostLogRepository 不再读群发表（只允许出现在迁移逻辑里）
$boostLogRepo = (string) file_get_contents($root . '/app/Domain/CharacterBoost/BoostLogRepository.php');
expect(
    str_contains($boostLogRepo, "const TABLE = 'panel_boost_log'"),
    '直升日志使用独立表 panel_boost_log',
    $checks,
    $failures
);
$readingLegacy = preg_match('/SELECT[^;]*panel_massmail_log/i', $boostLogRepo) === 1;
expect(
    !$readingLegacy,
    '直升日志读取路径不查群发表',
    $checks,
    $failures
);

// ------------------------------------------------------------------
// 2. 真实读写：建表 → 写入 → 读回 → 清理
// ------------------------------------------------------------------
$probeName = 'BoostLogProbe' . substr((string) time(), -6);

try {
    $pdo = Database::forServer($serverId, 'characters');
} catch (\Throwable $exception) {
    expect(false, '连接 characters 库', $checks, $failures, ['message' => $exception->getMessage()]);
    $pdo = null;
}

if ($pdo !== null) {
    $history = new BoostHistoryService($serverId);
    $history->record($probeName, 80, true, '21841:3,23720:1', 2, 5000000);

    $tableExists = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        . ' AND TABLE_NAME = :table LIMIT 1'
    );
    $tableExists->execute([':table' => 'panel_boost_log']);
    expect($tableExists->fetchColumn() !== false, 'panel_boost_log 已自动建表', $checks, $failures);

    $repo = new BoostLogRepository($serverId);
    $rows = $repo->recent(20);
    $found = null;
    foreach ($rows as $row) {
        if (str_contains((string) ($row['recipients'] ?? ''), $probeName)) {
            $found = $row;
            break;
        }
    }

    expect($found !== null, '写入的直升记录可以读回', $checks, $failures, ['rows' => count($rows)]);
    if ($found !== null) {
        expect((int) ($found['success'] ?? 0) === 1, '记录 success=1', $checks, $failures, $found);
        expect((string) ($found['items'] ?? '') === '21841:3,23720:1', '记录保留物品摘要', $checks, $failures);
        expect((int) ($found['amount'] ?? 0) === 5000000, '记录保留金币（铜）', $checks, $failures);
        expect(str_contains((string) ($found['subject'] ?? ''), (string) 80), '记录含目标等级', $checks, $failures);
    }

    // server_id 隔离
    $otherServer = $serverId === 0 ? 1 : 0;
    $otherRows = (new BoostLogRepository($otherServer))->recent(20);
    $leaked = false;
    foreach ($otherRows as $row) {
        if (str_contains((string) ($row['recipients'] ?? ''), $probeName)) {
            $leaked = true;
            break;
        }
    }
    expect(!$leaked, 'hero 记录按 server_id 隔离', $checks, $failures);

    // 失败记录也要落地
    $history->record($probeName . 'Fail', 80, false, '', 0, 0, ['模板无效']);
    $failRows = (new BoostLogRepository($serverId))->recent(20);
    $failFound = null;
    foreach ($failRows as $row) {
        if (str_contains((string) ($row['recipients'] ?? ''), $probeName . 'Fail')) {
            $failFound = $row;
            break;
        }
    }
    expect($failFound !== null, '失败记录也会落地', $checks, $failures);
    if ($failFound !== null) {
        expect((int) ($failFound['success'] ?? 1) === 0, '失败记录 success=0', $checks, $failures);
        expect(str_contains((string) ($failFound['recipients'] ?? ''), '!'), '失败记录带 ! 标记', $checks, $failures);
        expect(str_contains((string) ($failFound['sample_errors'] ?? ''), '模板无效'), '失败记录保留错误样例', $checks, $failures);
    }

    // 清理探针数据
    try {
        $cleanup = $pdo->prepare('DELETE FROM panel_boost_log WHERE recipients LIKE :probe');
        $cleanup->execute([':probe' => '%' . $probeName . '%']);
        $checks[] = ['name' => '探针数据清理', 'status' => 'passed', 'detail' => $cleanup->rowCount() . ' rows'];
    } catch (\Throwable $exception) {
        $checks[] = ['name' => '探针数据清理', 'status' => 'failed', 'detail' => $exception->getMessage()];
        $failures[] = '探针数据清理';
    }
}

echo json_encode([
    'success' => $failures === [],
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
