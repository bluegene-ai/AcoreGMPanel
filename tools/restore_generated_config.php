<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$reportsDir = $root . DIRECTORY_SEPARATOR . 'test_reports';
$generatedDir = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'generated';

function writeJson(array $payload, int $exitCode = 0): never
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
}

function usage(): never
{
    writeJson([
        'success' => false,
        'error' => 'usage',
        'message' => 'Usage: php tools/restore_generated_config.php [--latest|report-name|path-to-backup_generated]',
    ], 1);
}

function removeTree(string $dir): void
{
    if (!is_dir($dir))
        return;

    $items = scandir($dir);
    if (!is_array($items))
        return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..')
            continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            removeTree($path);
            @rmdir($path);
            continue;
        }

        @unlink($path);
    }
}

function copyTree(string $source, string $target): array
{
    $copied = [];
    $items = scandir($source);
    if (!is_array($items))
        return $copied;

    if (!is_dir($target) && !@mkdir($target, 0777, true) && !is_dir($target))
        throw new RuntimeException('无法创建目标目录：' . $target);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..')
            continue;

        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $target . DIRECTORY_SEPARATOR . $item;

        if (is_dir($src) && !is_link($src)) {
            $copied = array_merge($copied, copyTree($src, $dst));
            continue;
        }

        if (!@copy($src, $dst))
            throw new RuntimeException('复制失败：' . $item);

        $copied[] = str_replace('\\', '/', substr($dst, strlen(dirname($target)) + 1));
    }

    return $copied;
}

function latestBackupDir(string $reportsDir): ?string
{
    if (!is_dir($reportsDir))
        return null;

    $candidates = glob($reportsDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'backup_generated', GLOB_ONLYDIR);
    if (!is_array($candidates) || $candidates === [])
        return null;

    usort($candidates, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return $candidates[0] ?? null;
}

function resolveSource(string $arg, string $reportsDir): ?string
{
    if ($arg === '--latest' || $arg === '')
        return latestBackupDir($reportsDir);

    if (is_dir($arg))
        return realpath($arg) ?: $arg;

    $reportDir = $reportsDir . DIRECTORY_SEPARATOR . $arg;
    if (is_dir($reportDir . DIRECTORY_SEPARATOR . 'backup_generated'))
        return $reportDir . DIRECTORY_SEPARATOR . 'backup_generated';

    return null;
}

function summarizeServers(string $generatedDir): array
{
    $serversFile = $generatedDir . DIRECTORY_SEPARATOR . 'servers.php';
    if (!is_file($serversFile))
        return [];

    $config = require $serversFile;
    $servers = $config['servers'] ?? [];
    if (!is_array($servers))
        return [];

    $summary = [];
    foreach ($servers as $index => $server) {
        if (!is_array($server))
            continue;

        $summary[] = [
            'server_id' => (int) $index,
            'realm_id' => (int) ($server['realm_id'] ?? 0),
            'name' => (string) ($server['name'] ?? ''),
            'world_db' => (string) (($server['world']['database'] ?? '') ?: ''),
            'characters_db' => (string) (($server['characters']['database'] ?? '') ?: ''),
            'soap_port' => (int) ($server['port'] ?? (($server['soap']['port'] ?? 0))),
        ];
    }

    return $summary;
}

$arg = $argv[1] ?? '--latest';
if (in_array($arg, ['-h', '--help'], true))
    usage();

$sourceDir = resolveSource($arg, $reportsDir);
if ($sourceDir === null || !is_dir($sourceDir)) {
    writeJson([
        'success' => false,
        'error' => 'missing_source',
        'message' => '未找到 backup_generated 来源目录。',
        'requested' => $arg,
    ], 2);
}

$sourceRealPath = realpath($sourceDir);
if ($sourceRealPath === false) {
    writeJson([
        'success' => false,
        'error' => 'invalid_source',
        'message' => '来源目录不可解析。',
        'requested' => $sourceDir,
    ], 2);
}

if (!str_starts_with(str_replace('\\', '/', $sourceRealPath), str_replace('\\', '/', $root . DIRECTORY_SEPARATOR))) {
    writeJson([
        'success' => false,
        'error' => 'unsafe_source',
        'message' => '来源目录必须位于当前 AGMP 工作区内。',
        'requested' => $sourceRealPath,
    ], 2);
}

try {
    if (!is_dir($generatedDir) && !@mkdir($generatedDir, 0777, true) && !is_dir($generatedDir))
        throw new RuntimeException('无法创建 generated 目录。');

    removeTree($generatedDir);
    $copied = copyTree($sourceRealPath, $generatedDir);
    if (!in_array('.gitkeep', $copied, true) && !is_file($generatedDir . DIRECTORY_SEPARATOR . '.gitkeep'))
        @file_put_contents($generatedDir . DIRECTORY_SEPARATOR . '.gitkeep', '');

    writeJson([
        'success' => true,
        'source' => str_replace('\\', '/', substr($sourceRealPath, strlen($root) + 1)),
        'target' => 'config/generated',
        'copied_files' => $copied,
        'servers' => summarizeServers($generatedDir),
    ]);
} catch (Throwable $e) {
    writeJson([
        'success' => false,
        'error' => 'restore_failed',
        'message' => $e->getMessage(),
    ], 3);
}