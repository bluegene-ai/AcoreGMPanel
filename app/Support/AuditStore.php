<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Database;
use PDO;
use Throwable;

/**
 * File: app/Support/AuditStore.php
 * Purpose: 审计日志的统一落库层（建表、写入、兜底落盘、按龄清理）。
 *
 * 表建在共用的 Eluna 库里，面板用 auth 连接跨库读写；若该库不可用则退回「连上哪个库就写哪个库」，
 * 仍然失败时只写一个兜底文件 storage/logs/<storage.fallback>，不会因为日志写不进去而影响业务请求。
 */
final class AuditStore
{
    public const TABLE = 'panel_audit_log';

    /** 结构版本：新增列时 +1，会让上一次的校验标记失效并触发一次补列。 */
    private const SCHEMA_VERSION = 1;

    private const COLUMNS = [
        'ts', 'channel', 'severity', 'status', 'module', 'action', 'target', 'summary',
        'actor', 'realm_index', 'realm_id', 'realm_name', 'method', 'uri', 'ip',
        'user_agent', 'duration_ms', 'detail',
    ];

    private static ?PDO $pdo = null;

    private static ?string $table = null;

    /** 单请求熔断：库不可达时后续写入直接走兜底，不重试连接。 */
    private static bool $disabled = false;

    private static bool $schemaReady = false;

    private static bool $writingFallback = false;

    /** 测试/CLI 用：清空连接与熔断状态。 */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$table = null;
        self::$disabled = false;
        self::$schemaReady = false;
        self::$writingFallback = false;
    }

    public static function disabled(): bool
    {
        return self::$disabled;
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        $storage = AuditCatalog::storage();

        return is_array($storage) ? $storage : [];
    }

    /**
     * 取日志库连接；不可用时返回 null（调用方落兜底文件）。
     */
    public static function pdo(): ?PDO
    {
        if (self::$disabled) {
            return null;
        }
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $connection = (string) (self::config()['connection'] ?? 'auth');
        try {
            $pdo = Database::connection($connection);
        } catch (Throwable $e) {
            try {
                $pdo = Database::auth();
            } catch (Throwable $e2) {
                self::$disabled = true;

                return null;
            }
        }

        self::$pdo = $pdo;

        return self::$pdo;
    }

    /**
     * 表名（可能带库名前缀）。优先建在配置的库里；该库不存在或无权限时退回当前连接的库。
     */
    public static function table(): ?string
    {
        if (self::$table !== null) {
            return self::$table === '' ? null : self::$table;
        }

        $pdo = self::pdo();
        if ($pdo === null) {
            return null;
        }

        $cfg = self::config();
        $database = trim((string) ($cfg['database'] ?? ''), "` \t\n\r\0\x0B");
        $table = trim((string) ($cfg['table'] ?? self::TABLE), "` \t\n\r\0\x0B");
        if ($table === '') {
            $table = self::TABLE;
        }

        $candidates = [];
        if ($database !== '') {
            $candidates[] = '`' . str_replace('`', '', $database) . '`.`' . $table . '`';
        }
        $candidates[] = '`' . $table . '`';

        foreach ($candidates as $candidate) {
            if (self::createTable($pdo, $candidate)) {
                self::$table = $candidate;
                if (!self::schemaMarkerFresh($candidate)) {
                    self::upgradeTable($pdo, $candidate);
                    self::writeSchemaMarker($candidate);
                }

                return $candidate;
            }
        }

        self::$disabled = true;
        self::$table = '';

        return null;
    }

    private static function createTable(PDO $pdo, string $table): bool
    {
        if (self::$schemaReady) {
            return true;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . $table . ' ('
                . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . '`ts` DATETIME(3) NOT NULL,'
                . "`channel` VARCHAR(16) NOT NULL DEFAULT 'audit',"
                . '`severity` TINYINT UNSIGNED NOT NULL DEFAULT 1,'
                . "`status` VARCHAR(12) NOT NULL DEFAULT 'ok',"
                . "`module` VARCHAR(48) NOT NULL DEFAULT '',"
                . "`action` VARCHAR(64) NOT NULL DEFAULT '',"
                . "`target` VARCHAR(191) NOT NULL DEFAULT '',"
                . "`summary` VARCHAR(255) NOT NULL DEFAULT '',"
                . "`actor` VARCHAR(64) NOT NULL DEFAULT '',"
                . '`realm_index` INT NOT NULL DEFAULT 0,'
                . '`realm_id` INT NOT NULL DEFAULT 0,'
                . "`realm_name` VARCHAR(64) NOT NULL DEFAULT '',"
                . "`method` VARCHAR(8) NOT NULL DEFAULT '',"
                . "`uri` VARCHAR(255) NOT NULL DEFAULT '',"
                . "`ip` VARCHAR(45) NOT NULL DEFAULT '',"
                . "`user_agent` VARCHAR(255) NOT NULL DEFAULT '',"
                . '`duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,'
                . '`detail` MEDIUMTEXT NULL,'
                . 'PRIMARY KEY (`id`),'
                . 'KEY `idx_ts` (`ts`),'
                . 'KEY `idx_realm_ts` (`realm_index`,`ts`),'
                . 'KEY `idx_channel_ts` (`channel`,`ts`),'
                . 'KEY `idx_module_action` (`module`,`action`),'
                . 'KEY `idx_actor` (`actor`),'
                . 'KEY `idx_status` (`status`),'
                . 'KEY `idx_severity` (`severity`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
            );
        } catch (Throwable $e) {
            return false;
        }

        self::$schemaReady = true;

        return true;
    }

    /**
     * 补齐结构升级中后加的列。
     *
     * 用一条 SHOW COLUMNS 比对列集合，只在确实缺列时才 ALTER；外层还有一次性校验标记，
     * 正常情况下每个部署只跑一遍。
     */
    private static function upgradeTable(PDO $pdo, string $table): void
    {
        $additions = [
            'channel' => "ADD COLUMN `channel` VARCHAR(16) NOT NULL DEFAULT 'audit' AFTER `ts`",
            'severity' => 'ADD COLUMN `severity` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `channel`',
            'status' => "ADD COLUMN `status` VARCHAR(12) NOT NULL DEFAULT 'ok' AFTER `severity`",
            'summary' => "ADD COLUMN `summary` VARCHAR(255) NOT NULL DEFAULT '' AFTER `target`",
            'actor' => "ADD COLUMN `actor` VARCHAR(64) NOT NULL DEFAULT '' AFTER `summary`",
            'realm_index' => 'ADD COLUMN `realm_index` INT NOT NULL DEFAULT 0 AFTER `actor`',
            'realm_id' => 'ADD COLUMN `realm_id` INT NOT NULL DEFAULT 0 AFTER `realm_index`',
            'realm_name' => "ADD COLUMN `realm_name` VARCHAR(64) NOT NULL DEFAULT '' AFTER `realm_id`",
            'method' => "ADD COLUMN `method` VARCHAR(8) NOT NULL DEFAULT '' AFTER `realm_name`",
            'uri' => "ADD COLUMN `uri` VARCHAR(255) NOT NULL DEFAULT '' AFTER `method`",
            'user_agent' => "ADD COLUMN `user_agent` VARCHAR(255) NOT NULL DEFAULT '' AFTER `ip`",
            'duration_ms' => 'ADD COLUMN `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `user_agent`',
        ];

        try {
            $existing = [];
            $rows = $pdo->query('SHOW COLUMNS FROM ' . $table);
            foreach (($rows === false ? [] : $rows->fetchAll(PDO::FETCH_ASSOC)) as $row) {
                $existing[strtolower((string) ($row['Field'] ?? ''))] = true;
            }
        } catch (Throwable $e) {
            return;
        }

        foreach ($additions as $column => $clause) {
            if (isset($existing[$column])) {
                continue;
            }
            try {
                $pdo->exec('ALTER TABLE ' . $table . ' ' . $clause);
            } catch (Throwable $e) {
                // 补列失败不阻断写入：缺列时 INSERT 会整体失败并走兜底文件。
                return;
            }
        }
    }

    private static function schemaMarkerFile(string $table): string
    {
        $dir = LogPath::rootDir() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'panel';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'audit-schema-' . md5($table) . '.ok';
    }

    private static function schemaMarkerFresh(string $table): bool
    {
        $file = self::schemaMarkerFile($table);
        if (!is_file($file)) {
            return false;
        }

        return trim((string) @file_get_contents($file)) === (string) self::SCHEMA_VERSION;
    }

    private static function writeSchemaMarker(string $table): void
    {
        @file_put_contents(self::schemaMarkerFile($table), (string) self::SCHEMA_VERSION, LOCK_EX);
    }

    /**
     * 写一行审计记录。返回 false 表示已落到兜底文件（业务调用方无需关心）。
     */
    public static function insert(array $row): bool
    {
        $table = self::table();
        $pdo = self::$pdo;

        if ($table === null || $pdo === null) {
            self::writeFallback($row);

            return false;
        }

        try {
            $columns = implode(',', array_map(static fn (string $c): string => '`' . $c . '`', self::COLUMNS));
            $placeholders = implode(',', array_map(static fn (string $c): string => ':' . $c, self::COLUMNS));
            $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $placeholders . ')');

            $params = [];
            foreach (self::COLUMNS as $column) {
                $params[':' . $column] = $row[$column] ?? null;
            }
            $stmt->execute($params);

            return true;
        } catch (Throwable $e) {
            self::writeFallback($row);

            return false;
        }
    }

    /**
     * 删除早于 $days 天的记录，返回删除行数。$days < 1 时不做任何事。
     */
    public static function prune(int $days): int
    {
        $days = max(0, $days);
        if ($days < 1) {
            return 0;
        }

        $table = self::table();
        $pdo = self::$pdo;
        if ($table === null || $pdo === null) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE `ts` < (NOW() - INTERVAL :days DAY)');
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function count(): int
    {
        $table = self::table();
        $pdo = self::$pdo;
        if ($table === null || $pdo === null) {
            return 0;
        }

        try {
            $total = $pdo->query('SELECT COUNT(*) FROM ' . $table);

            return $total === false ? 0 : (int) $total->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function fallbackFile(): string
    {
        $name = (string) (self::config()['fallback'] ?? 'audit-fallback.log');
        if ($name === '') {
            $name = 'audit-fallback.log';
        }

        return LogPath::logFile($name, true, 0777);
    }

    /**
     * 库不可用时的唯一兜底：一行一条 JSON，可回灌审计表。
     */
    private static function writeFallback(array $row): void
    {
        if (self::$writingFallback) {
            return;
        }
        self::$writingFallback = true;

        try {
            $payload = ['ts' => $row['ts'] ?? date('Y-m-d H:i:s')] + $row;
            unset($payload['detail'], $payload['user_agent']);
            if (isset($row['detail']) && $row['detail'] !== null) {
                $decoded = json_decode((string) $row['detail'], true);
                $payload['detail'] = is_array($decoded) ? $decoded : (string) $row['detail'];
            }
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            LogPath::appendTo(self::fallbackFile(), $json === false ? '{}' : $json, true, 0777);
        } catch (Throwable $e) {
            // 兜底都失败时宁可丢这一条，也不把异常抛回业务请求。
        } finally {
            self::$writingFallback = false;
        }
    }
}
