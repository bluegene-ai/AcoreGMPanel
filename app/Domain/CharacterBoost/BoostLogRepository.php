<?php
/**
 * File: app/Domain/CharacterBoost/BoostLogRepository.php
 * Purpose: 直升历史的读写（自带表结构，不再借用群发日志表）。
 *
 * 历史实现把直升记录写进群发的 panel_massmail_log（action='boost'），
 * 导致直升模块与群发模块互相耦合。现在直升拥有独立的 panel_boost_log 表：
 *   - 建表与迁移在本仓储内自愈（首次使用自动创建）；
 *   - 旧库里如果还留有 action='boost' 的行，会一次性搬过来，搬完打标记。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\CharacterBoost;

use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;

class BoostLogRepository extends MultiServerRepository
{
    private const TABLE = 'panel_boost_log';

    private const LEGACY_TABLE = 'panel_massmail_log';

    private ?bool $ready = null;

    /**
     * 最近直升记录（按时间倒序）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 20): array
    {
        $limit = max(1, min($limit, 200));

        if (!$this->ensureTable()) {
            return [];
        }

        try {
            $stmt = $this->characters()->prepare(
                'SELECT id, created_at, subject, items, quantity, amount, targets,'
                . ' success_count, fail_count, success, recipients, sample_errors'
                . ' FROM ' . $this->tableName() . ' WHERE server_id = :sid'
                . ' ORDER BY id DESC LIMIT :lim'
            );
            $stmt->bindValue(':sid', $this->serverId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * 写入一条直升记录。
     *
     * @param array{subject:string,items:?string,quantity:?int,amount:?int,success:bool,recipients:?string,errors:array<int,string>} $entry
     */
    public function record(array $entry): bool
    {
        if (!$this->ensureTable()) {
            return false;
        }

        $errors = is_array($entry['errors'] ?? null) ? $entry['errors'] : [];
        $sample = $errors !== [] ? implode(' | ', array_slice($errors, 0, 3)) : null;
        $ok = (bool) ($entry['success'] ?? false);

        try {
            $stmt = $this->characters()->prepare(
                'INSERT INTO ' . $this->tableName()
                . ' (server_id, subject, items, quantity, amount, targets, success_count, fail_count, success, recipients, sample_errors)'
                . ' VALUES (:sid, :subject, :items, :quantity, :amount, 1, :ok, :fail, :success, :recipients, :sample)'
            );
            $stmt->bindValue(':sid', $this->serverId, PDO::PARAM_INT);
            $stmt->bindValue(':subject', mb_substr((string) ($entry['subject'] ?? ''), 0, 120), PDO::PARAM_STR);
            $stmt->bindValue(':items', $entry['items'] !== null ? mb_substr((string) $entry['items'], 0, 500) : null, $entry['items'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':quantity', $entry['quantity'] !== null ? (int) $entry['quantity'] : null, $entry['quantity'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':amount', $entry['amount'] !== null ? (int) $entry['amount'] : null, $entry['amount'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':ok', $ok ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':fail', $ok ? 0 : 1, PDO::PARAM_INT);
            $stmt->bindValue(':success', $ok ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':recipients', $entry['recipients'] !== null ? mb_substr((string) $entry['recipients'], 0, 160) : null, $entry['recipients'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':sample', $sample, $sample !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // 表结构
    // ------------------------------------------------------------------

    private function tableName(): string
    {
        return $this->quoteIdentifier(self::TABLE);
    }

    /**
     * 标识符必须用反引号包裹。
     * PDO::quote() 给的是单引号（字符串字面量），用在表名上会直接 1064 语法错误。
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    private function ensureTable(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        try {
            $pdo = $this->characters();
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ' . $this->quoteIdentifier(self::TABLE) . ' ('
                . ' id INT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . ' server_id INT NOT NULL DEFAULT 0,'
                . ' created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . ' subject VARCHAR(120) NOT NULL,'
                . ' items TEXT NULL,'
                . ' quantity INT NULL,'
                . ' amount BIGINT NULL,'
                . ' targets INT NOT NULL DEFAULT 1,'
                . ' success_count INT NOT NULL DEFAULT 0,'
                . ' fail_count INT NOT NULL DEFAULT 0,'
                . ' success TINYINT(1) NOT NULL DEFAULT 0,'
                . ' recipients TEXT NULL,'
                . ' sample_errors TEXT NULL,'
                . ' PRIMARY KEY (id),'
                . ' KEY idx_server_created (server_id, created_at)'
                . ' ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $this->ready = true;
            $this->migrateLegacyRows();
        } catch (\Throwable $exception) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /**
     * 把旧版本写在群发日志表里的直升记录搬过来。
     *
     * 幂等实现：先数新表里已有多少行，只补搬"旧表行数 - 新表已搬行数"之后的部分。
     * 不额外建标记表，也就不需要往 auth 库塞新表。
     */
    private function migrateLegacyRows(): void
    {
        try {
            $pdo = $this->characters();

            $hasLegacy = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME = :table LIMIT 1'
            );
            $hasLegacy->execute([':table' => self::LEGACY_TABLE]);
            if ($hasLegacy->fetchColumn() === false) {
                return;
            }

            $legacyStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . $this->quoteIdentifier(self::LEGACY_TABLE) . " WHERE action = 'boost'"
            );
            $legacyStmt->execute();
            $legacyCount = (int) $legacyStmt->fetchColumn();
            if ($legacyCount <= 0) {
                return;
            }

            $migratedStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . $this->quoteIdentifier(self::TABLE)
                . ' WHERE server_id = :sid AND subject LIKE :marker'
            );
            $migratedStmt->execute([':sid' => $this->serverId, ':marker' => '%直升%']);
            $migratedCount = (int) $migratedStmt->fetchColumn();

            $missing = $legacyCount - $migratedCount;
            if ($missing <= 0) {
                return;
            }

            $pdo->exec(
                'INSERT INTO ' . $this->quoteIdentifier(self::TABLE)
                . ' (server_id, created_at, subject, items, quantity, amount, targets, success_count, fail_count, success, recipients, sample_errors)'
                . ' SELECT server_id, created_at, subject, items, quantity, amount, targets, success_count, fail_count, success, recipients, sample_errors'
                . ' FROM ' . $this->quoteIdentifier(self::LEGACY_TABLE) . " WHERE action = 'boost'"
                . ' ORDER BY id ASC LIMIT ' . (int) $missing
            );
        } catch (\Throwable $exception) {
            // 迁移失败不影响新表读写
        }
    }
}
