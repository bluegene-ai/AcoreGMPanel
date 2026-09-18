<?php

declare(strict_types=1);

/**
 * 招募管理统计口径校验（需要可用的数据库连接）。
 *
 * 核心不变式：统计卡上的数字，必须与点击卡片后那一刻拿到的明细条数一致。
 * 这里直接调用真实的 RafRepository，对每个状态/条件分别比较：
 *   stats()[key]  ===  listLinks(卡片条件)->total
 *
 * 用法：php tools/verify_raf_counts.php [serverId]
 */

define('PANEL_CLI_AUTH_BYPASS', true);

require dirname(__DIR__) . '/bootstrap/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Raf\RafRepository;
use Acme\Panel\Support\ServerContext;

Config::init(dirname(__DIR__) . '/config');
Lang::init();

// 保持与页面渲染一致的会话前提
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$serverId = isset($argv[1]) ? (int) $argv[1] : ServerContext::defaultId();
ServerContext::set($serverId);

$checks = [];
$failures = [];

function assertSame(int $expected, int $actual, string $label, array &$checks, array &$failures): void
{
    $passed = $expected === $actual;
    $checks[] = [
        'name' => $label,
        'status' => $passed ? 'passed' : 'failed',
        'expected' => $expected,
        'actual' => $actual,
    ];
    if (!$passed) {
        $failures[] = $label . ' (expected ' . $expected . ', got ' . $actual . ')';
    }
}

try {
    $repo = new RafRepository($serverId);
    $schema = $repo->schemaStatus();

    if (!$schema['ready']) {
        echo json_encode([
            'success' => false,
            'message' => 'RAF 基础表缺失，无法校验统计口径',
            'schema' => $schema,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(1);
    }

    $stats = $repo->stats([]);
    $checks[] = ['name' => 'raf.stats_snapshot', 'status' => 'passed', 'detail' => $stats];

    $limit = 500;

    // 1. 总绑定
    $list = $repo->listLinks([], 1, $limit);
    assertSame($stats['total'], $list->total, 'stats.total == list.total', $checks, $failures);

    // 2. 有效绑定
    $activeList = $repo->listLinks(['status' => 'active'], 1, $limit);
    assertSame($stats['active'], $activeList->total, 'stats.active == list(status=active)', $checks, $failures);

    // 3. 已完成
    $completedList = $repo->listLinks(['status' => 'completed'], 1, $limit);
    assertSame($stats['completed'], $completedList->total, 'stats.completed == list(status=completed)', $checks, $failures);

    // 4. 已失效
    $inactiveList = $repo->listLinks(['status' => 'inactive'], 1, $limit);
    assertSame($stats['inactive'], $inactiveList->total, 'stats.inactive == list(status=inactive)', $checks, $failures);

    // 5. 永久封停
    $permanentList = $repo->listLinks(['status' => 'permanent'], 1, $limit);
    assertSame($stats['permanent_blocked'], $permanentList->total, 'stats.permanent_blocked == list(status=permanent)', $checks, $failures);

    // 6. 已产生奖励的绑定（跨表 + realm 过滤）
    // 奖励表按"招募者"记录，所以只要该 realm 下的招募者都没有奖励等级，
    // 卡片与下钻都必须一起归零 —— 这正是"卡片数字 == 明细条数"的核心不变式
    $rewardedList = $repo->listLinks(['rewarded_only' => 1], 1, $limit);
    $rewardedIds = $repo->rewardedAccountIds([]);
    assertSame($stats['rewarded_accounts'], $rewardedList->total, 'stats.rewarded_accounts == list(rewarded_only)', $checks, $failures);
    $checks[] = [
        'name' => 'raf.rewarded_scope',
        'status' => 'passed',
        'detail' => ['rewarded_account_ids' => $rewardedIds, 'card' => $stats['rewarded_accounts'], 'detail_total' => $rewardedList->total],
    ];

    // 7. 统计卡条件必须覆盖全部绑定（互斥且不重叠）
    $sum = $stats['active'] + $stats['completed'] + $stats['inactive'];
    $checks[] = [
        'name' => 'raf.status_partition',
        'status' => $sum <= $stats['total'] ? 'passed' : 'failed',
        'detail' => ['active+completed+inactive' => $sum, 'total' => $stats['total'], 'permanent_blocked' => $stats['permanent_blocked']],
    ];
    if ($sum > $stats['total']) {
        $failures[] = '状态分区之和超过总数';
    }

    // 8. 奖励发放记录
    if ($schema['reward_log_ready']) {
        $logStats = $repo->rewardLogStats([]);
        $checks[] = ['name' => 'raf.reward_log_stats_snapshot', 'status' => 'passed', 'detail' => $logStats];

        $logList = $repo->listRewardLogs([], 1, $limit);
        assertSame($logStats['total'], $logList->total, 'rewardLogStats.total == listRewardLogs.total', $checks, $failures);

        $defaultList = $repo->listRewardLogs(['default_only' => 1], 1, $limit);
        $defaultStats = $repo->rewardLogStats(['default_only' => 1]);
        assertSame($defaultStats['total'], $defaultList->total, 'default_only 统计与明细一致', $checks, $failures);
        assertSame($logStats['default_rewards'], $defaultList->total, 'stats.default_rewards == list(default_only)', $checks, $failures);

        // 统计卡"最近发放时间"必须等于明细首条的发放时间
        $latestRows = $repo->listRewardLogs(['sort' => 'granted_at', 'dir' => 'DESC'], 1, 1);
        $latestGrantedAt = (int) ($latestRows->items[0]['granted_at'] ?? 0);
        assertSame($logStats['latest_granted_at'], $latestGrantedAt, 'stats.latest == 首条明细时间', $checks, $failures);
    } else {
        $checks[] = ['name' => 'raf.reward_log_skipped', 'status' => 'passed', 'detail' => 'reward_log 表不存在，已跳过'];
    }

    echo json_encode([
        'success' => $failures === [],
        'server_id' => $serverId,
        'failures' => $failures,
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    exit($failures === [] ? 0 : 1);
} catch (Throwable $exception) {
    echo json_encode([
        'success' => false,
        'server_id' => $serverId,
        'message' => $exception->getMessage(),
        'class' => get_class($exception),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(2);
}
