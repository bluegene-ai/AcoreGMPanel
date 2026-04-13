<?php

namespace Acme\Panel\Domain\CharacterBoost;

use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;

class BoostTemplateRepository extends MultiServerRepository
{
    private static bool $schemaEnsured = false;

    private array $itemNameCache = [];

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $pdo = $this->auth();

        // Templates
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS character_boost_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                realm_id INT UNSIGNED NOT NULL DEFAULT 1,
                name VARCHAR(100) NOT NULL,
                target_level SMALLINT UNSIGNED NOT NULL,
                money_gold INT UNSIGNED NOT NULL DEFAULT 0,
                require_account_level_match TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY character_boost_templates_realm_name_unique (realm_id, name),
                KEY idx_realm (realm_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Template items
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS character_boost_template_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                item_entry INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uniq_template_item (template_id, item_entry),
                KEY idx_template (template_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Class rewards
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS character_boost_template_class_rewards (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                tier VARCHAR(10) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uniq_template_tier (template_id, tier),
                KEY idx_template (template_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Redeem codes (可选：用于公开兑换码流程；此处仅保证表结构存在)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS character_boost_redeem_codes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(16) NOT NULL,
                used_at TIMESTAMP NULL DEFAULT NULL,
                used_realm_id SMALLINT UNSIGNED NULL DEFAULT NULL,
                used_character_name VARCHAR(64) NULL DEFAULT NULL,
                used_ip VARCHAR(45) NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uniq_code (code),
                KEY idx_template_used (template_id, used_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::$schemaEnsured = true;
    }

    public function authPdo(): PDO
    {
        $this->ensureSchema();
        return $this->auth();
    }

    public function lockRedeemCode(string $code): ?array
    {
        $this->ensureSchema();
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $pdo = $this->auth();
        $st = $pdo->prepare('SELECT id, template_id, code, used_at, used_realm_id, used_character_name, used_ip FROM character_boost_redeem_codes WHERE code=:c LIMIT 1 FOR UPDATE');
        $st->execute([':c' => $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['template_id'] = (int) $row['template_id'];
        $row['code'] = (string) $row['code'];
        $row['used_realm_id'] = $row['used_realm_id'] === null ? null : (int) $row['used_realm_id'];
        $row['used_character_name'] = $row['used_character_name'] === null ? null : (string) $row['used_character_name'];
        $row['used_ip'] = $row['used_ip'] === null ? null : (string) $row['used_ip'];

        return $row;
    }

    public function markRedeemCodeUsed(int $id, int $realmId, string $characterName, string $ip): bool
    {
        $this->ensureSchema();
        if ($id <= 0) {
            return false;
        }

        $pdo = $this->auth();
        $st = $pdo->prepare('UPDATE character_boost_redeem_codes SET used_at=NOW(), used_realm_id=:rid, used_character_name=:n, used_ip=:ip, updated_at=NOW() WHERE id=:id AND used_at IS NULL');
        $st->execute([
            ':rid' => $realmId,
            ':n' => $characterName,
            ':ip' => $ip,
            ':id' => $id,
        ]);

        return $st->rowCount() > 0;
    }

    /**
     * @return array<int,string> Generated codes
     */
    public function generateRedeemCodes(int $templateId, int $count): array
    {
        $this->ensureSchema();

        $templateId = (int) $templateId;
        $count = (int) $count;
        if ($templateId <= 0 || $count <= 0) {
            return [];
        }

        $pdo = $this->auth();
        $out = [];

        $insert = $pdo->prepare('INSERT INTO character_boost_redeem_codes(template_id, code, created_at, updated_at) VALUES(:tid, :code, NOW(), NOW())');

        // Avoid infinite loops if the table is full of collisions (extremely unlikely).
        $maxAttempts = max($count * 20, 50);
        $attempts = 0;

        while (count($out) < $count && $attempts < $maxAttempts) {
            $attempts++;

            $code = self::randomRedeemCode(16);

            try {
                $insert->execute([':tid' => $templateId, ':code' => $code]);
                $out[] = $code;
            } catch (\Throwable $e) {
                // Likely duplicate code due to UNIQUE constraint; retry.
            }
        }

        return $out;
    }

    private static function randomRedeemCode(int $len = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $alphaLen = strlen($alphabet);
        $bytes = random_bytes($len);

        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % $alphaLen];
        }
        return $out;
    }

    public function listForRealm(int $realmId): array
    {
        $this->ensureSchema();
        $pdo = $this->auth();

        $st = $pdo->prepare('SELECT id, realm_id, name, target_level, money_gold, require_account_level_match FROM character_boost_templates WHERE realm_id=:rid ORDER BY name ASC, id ASC');
        $st->execute([':rid' => $realmId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['realm_id'] = (int) $r['realm_id'];
            $r['target_level'] = (int) $r['target_level'];
            $r['money_gold'] = (int) $r['money_gold'];
            $r['require_account_level_match'] = (int) $r['require_account_level_match'];
        }
        unset($r);

        return $rows;
    }

    public function listForRealmWithRewards(int $realmId): array
    {
        $templates = $this->listForRealm($realmId);
        if (!$templates) {
            return [];
        }

        $templateIds = [];
        foreach ($templates as $t) {
            $id = (int) ($t['id'] ?? 0);
            if ($id > 0) {
                $templateIds[] = $id;
            }
        }
        $templateIds = array_values(array_unique($templateIds));
        if (!$templateIds) {
            return $templates;
        }

        $pdo = $this->auth();
        $in = implode(',', array_fill(0, count($templateIds), '?'));

        $itemsByTemplate = [];
        $allEntries = [];
        try {
            $st = $pdo->prepare('SELECT template_id, item_entry, quantity FROM character_boost_template_items WHERE template_id IN (' . $in . ') ORDER BY template_id ASC, item_entry ASC');
            foreach ($templateIds as $i => $tid) {
                $st->bindValue($i + 1, $tid, PDO::PARAM_INT);
            }
            $st->execute();
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $tid = (int) ($r['template_id'] ?? 0);
                $entry = (int) ($r['item_entry'] ?? 0);
                $qty = (int) ($r['quantity'] ?? 0);
                if ($tid <= 0 || $entry <= 0 || $qty <= 0) {
                    continue;
                }
                $itemsByTemplate[$tid][] = [
                    'item_entry' => $entry,
                    'quantity' => $qty,
                ];
                $allEntries[] = $entry;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $tiersByTemplate = [];
        try {
            $st = $pdo->prepare('SELECT template_id, tier FROM character_boost_template_class_rewards WHERE template_id IN (' . $in . ') ORDER BY template_id ASC, tier ASC');
            foreach ($templateIds as $i => $tid) {
                $st->bindValue($i + 1, $tid, PDO::PARAM_INT);
            }
            $st->execute();
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $tid = (int) ($r['template_id'] ?? 0);
                $tier = strtolower(trim((string) ($r['tier'] ?? '')));
                if ($tid <= 0 || $tier === '') {
                    continue;
                }
                $tiersByTemplate[$tid][] = $tier;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $entryToName = $this->resolveItemNames(array_values(array_unique(array_filter(array_map('intval', $allEntries), static fn ($v) => $v > 0))));

        foreach ($templates as &$tpl) {
            $tid = (int) ($tpl['id'] ?? 0);
            $items = $itemsByTemplate[$tid] ?? [];
            foreach ($items as &$it) {
                $entry = (int) ($it['item_entry'] ?? 0);
                $it['item_name'] = (string) ($entryToName[$entry] ?? ('#' . (string) $entry));
            }
            unset($it);

            $tpl['items'] = $items;
            $tpl['class_rewards'] = $tiersByTemplate[$tid] ?? [];
        }
        unset($tpl);

        return $templates;
    }

    /**
     * @param array<int,int> $entries
     * @return array<int,string>
     */
    private function resolveItemNames(array $entries): array
    {
        $entries = array_values(array_unique(array_filter(array_map('intval', $entries), static fn ($v) => $v > 0)));
        if (!$entries) {
            return [];
        }

        $out = [];
        $remain = [];
        foreach ($entries as $e) {
            if (isset($this->itemNameCache[$e])) {
                $out[$e] = $this->itemNameCache[$e];
            } else {
                $remain[] = $e;
            }
        }
        if (!$remain) {
            return $out;
        }

        $world = null;
        try {
            $world = $this->world();
        } catch (\Throwable $e) {
            return $out;
        }

        try {
            $in = implode(',', array_fill(0, count($remain), '?'));
            $sql = 'SELECT i.entry, COALESCE(li.name_loc4, li.name_loc8, li.name_loc6, li.name_loc5, i.name) AS name_cn '
                . 'FROM item_template i '
                . 'LEFT JOIN locales_item li ON li.entry=i.entry '
                . 'WHERE i.entry IN (' . $in . ')';
            $st = $world->prepare($sql);
            foreach ($remain as $i => $v) {
                $st->bindValue($i + 1, $v, PDO::PARAM_INT);
            }
            $st->execute();
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) ($r['entry'] ?? 0);
                $nm = trim((string) ($r['name_cn'] ?? ''));
                if ($id > 0 && $nm !== '') {
                    $out[$id] = $nm;
                    $this->itemNameCache[$id] = $nm;
                }
            }
        } catch (\Throwable $e) {
            // Fallback without locales_item
            try {
                $in = implode(',', array_fill(0, count($remain), '?'));
                $sql = 'SELECT entry, name FROM item_template WHERE entry IN (' . $in . ')';
                $st = $world->prepare($sql);
                foreach ($remain as $i => $v) {
                    $st->bindValue($i + 1, $v, PDO::PARAM_INT);
                }
                $st->execute();
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int) ($r['entry'] ?? 0);
                    $nm = trim((string) ($r['name'] ?? ''));
                    if ($id > 0 && $nm !== '') {
                        $out[$id] = $nm;
                        $this->itemNameCache[$id] = $nm;
                    }
                }
            } catch (\Throwable $e2) {
                // ignore
            }
        }

        return $out;
    }

    public function redeemCodeStatsForRealm(int $realmId, ?int $templateId = null): array
    {
        $this->ensureSchema();

        $realmId = max(1, (int) $realmId);
        $templateId = $templateId !== null ? (int) $templateId : null;

        $pdo = $this->auth();

        $sql = 'SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN rc.used_at IS NULL THEN 1 ELSE 0 END) AS unused,
                    SUM(CASE WHEN rc.used_at IS NOT NULL THEN 1 ELSE 0 END) AS used
                FROM character_boost_redeem_codes rc
                INNER JOIN character_boost_templates t ON t.id = rc.template_id
                WHERE t.realm_id = :rid';

        $params = [':rid' => $realmId];
        if ($templateId !== null && $templateId > 0) {
            $sql .= ' AND rc.template_id = :tid';
            $params[':tid'] = $templateId;
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unused' => (int) ($row['unused'] ?? 0),
            'used' => (int) ($row['used'] ?? 0),
        ];
    }

    public function listRedeemCodesForRealm(int $realmId, ?int $templateId, ?bool $unusedOnly, int $page, int $perPage, string $sort = 'id', string $dir = 'desc'): array
    {
        $this->ensureSchema();

        $realmId = max(1, (int) $realmId);
        $templateId = $templateId !== null ? (int) $templateId : null;
        $page = max(1, (int) $page);
        $perPage = max(1, min(200, (int) $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = strtolower(trim($sort));
        if ($sort !== 'id') {
            $sort = 'id';
        }

        $dir = strtolower(trim($dir));
        $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';

        $pdo = $this->auth();

        $where = 'WHERE t.realm_id = :rid';
        $params = [':rid' => $realmId];

        if ($templateId !== null && $templateId > 0) {
            $where .= ' AND rc.template_id = :tid';
            $params[':tid'] = $templateId;
        }
        if ($unusedOnly === true) {
            $where .= ' AND rc.used_at IS NULL';
        }

        $cntSql = 'SELECT COUNT(*)
                  FROM character_boost_redeem_codes rc
                  INNER JOIN character_boost_templates t ON t.id = rc.template_id
                  ' . $where;
        $cnt = $pdo->prepare($cntSql);
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $sql = 'SELECT
                    rc.id,
                    rc.template_id,
                    t.name AS template_name,
                    rc.code,
                    rc.used_at,
                    rc.used_realm_id,
                    rc.used_character_name,
                    rc.used_ip,
                    rc.created_at
                FROM character_boost_redeem_codes rc
                INNER JOIN character_boost_templates t ON t.id = rc.template_id
                ' . $where . '
            ORDER BY rc.id ' . $orderDir . '
                LIMIT :lim OFFSET :off';

        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, PDO::PARAM_INT);
        }
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();

        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$r) {
            $r['id'] = (int) ($r['id'] ?? 0);
            $r['template_id'] = (int) ($r['template_id'] ?? 0);
            $r['template_name'] = (string) ($r['template_name'] ?? '');
            $r['code'] = (string) ($r['code'] ?? '');
            $r['used_realm_id'] = $r['used_realm_id'] === null ? null : (int) $r['used_realm_id'];
            $r['used_character_name'] = $r['used_character_name'] === null ? null : (string) $r['used_character_name'];
            $r['used_ip'] = $r['used_ip'] === null ? null : (string) $r['used_ip'];
            $r['created_at'] = $r['created_at'] === null ? null : (string) $r['created_at'];
            $r['used_at'] = $r['used_at'] === null ? null : (string) $r['used_at'];
        }
        unset($r);

        $pages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        if ($pages < 1) {
            $pages = 1;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'total' => $total,
            'items' => $items,
        ];
    }

    public function deleteUnusedRedeemCode(int $realmId, int $id): bool
    {
        $this->ensureSchema();
        $realmId = max(1, (int) $realmId);
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $pdo = $this->auth();
        $sql = 'DELETE rc
                FROM character_boost_redeem_codes rc
                INNER JOIN character_boost_templates t ON t.id = rc.template_id
                WHERE rc.id = :id AND rc.used_at IS NULL AND t.realm_id = :rid';
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $id, ':rid' => $realmId]);
        return $st->rowCount() > 0;
    }

    public function purgeUnusedRedeemCodes(int $realmId, ?int $templateId = null): int
    {
        $this->ensureSchema();
        $realmId = max(1, (int) $realmId);
        $templateId = $templateId !== null ? (int) $templateId : null;

        $pdo = $this->auth();
        $sql = 'DELETE rc
                FROM character_boost_redeem_codes rc
                INNER JOIN character_boost_templates t ON t.id = rc.template_id
                WHERE rc.used_at IS NULL AND t.realm_id = :rid';
        $params = [':rid' => $realmId];
        if ($templateId !== null && $templateId > 0) {
            $sql .= ' AND rc.template_id = :tid';
            $params[':tid'] = $templateId;
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->rowCount();
    }

    public function findForRealm(int $realmId, int $templateId): ?array
    {
        $this->ensureSchema();
        $pdo = $this->auth();

        $st = $pdo->prepare('SELECT id, realm_id, name, target_level, money_gold, require_account_level_match FROM character_boost_templates WHERE realm_id=:rid AND id=:id LIMIT 1');
        $st->execute([':rid' => $realmId, ':id' => $templateId]);
        $tpl = $st->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            return null;
        }

        $tpl['id'] = (int) $tpl['id'];
        $tpl['realm_id'] = (int) $tpl['realm_id'];
        $tpl['target_level'] = (int) $tpl['target_level'];
        $tpl['money_gold'] = (int) $tpl['money_gold'];
        $tpl['require_account_level_match'] = (int) $tpl['require_account_level_match'];

        $st = $pdo->prepare('SELECT item_entry, quantity FROM character_boost_template_items WHERE template_id=:tid ORDER BY item_entry ASC');
        $st->execute([':tid' => $tpl['id']]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$it) {
            $it['item_entry'] = (int) $it['item_entry'];
            $it['quantity'] = (int) $it['quantity'];
        }
        unset($it);

        $st = $pdo->prepare('SELECT tier FROM character_boost_template_class_rewards WHERE template_id=:tid ORDER BY tier ASC');
        $st->execute([':tid' => $tpl['id']]);
        $rewards = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rewards as &$rw) {
            $rw['tier'] = (string) $rw['tier'];
        }
        unset($rw);

        $tpl['items'] = $items;
        $tpl['class_rewards'] = $rewards;

        return $tpl;
    }

    public function createTemplate(int $realmId, string $name, int $targetLevel, int $moneyGold, bool $requireAccountLevelMatch): int
    {
        $this->ensureSchema();

        $realmId = max(1, (int) $realmId);
        $name = trim($name);
        $targetLevel = (int) $targetLevel;
        $moneyGold = max(0, (int) $moneyGold);

        if ($name === '') {
            return 0;
        }
        if ($targetLevel < 1 || $targetLevel > 255) {
            return 0;
        }

        $pdo = $this->auth();
        $st = $pdo->prepare('INSERT INTO character_boost_templates(realm_id,name,target_level,money_gold,require_account_level_match,created_at,updated_at) VALUES(:rid,:name,:lvl,:gold,:req,NOW(),NOW())');
        $st->execute([
            ':rid' => $realmId,
            ':name' => $name,
            ':lvl' => $targetLevel,
            ':gold' => $moneyGold,
            ':req' => $requireAccountLevelMatch ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function updateTemplate(int $realmId, int $templateId, string $name, int $targetLevel, int $moneyGold, bool $requireAccountLevelMatch): bool
    {
        $this->ensureSchema();

        $realmId = max(1, (int) $realmId);
        $templateId = (int) $templateId;
        $name = trim($name);
        $targetLevel = (int) $targetLevel;
        $moneyGold = max(0, (int) $moneyGold);

        if ($templateId <= 0 || $name === '' || $targetLevel < 1 || $targetLevel > 255) {
            return false;
        }

        $pdo = $this->auth();
        $st = $pdo->prepare('UPDATE character_boost_templates SET name=:name, target_level=:lvl, money_gold=:gold, require_account_level_match=:req, updated_at=NOW() WHERE id=:id AND realm_id=:rid');
        $st->execute([
            ':name' => $name,
            ':lvl' => $targetLevel,
            ':gold' => $moneyGold,
            ':req' => $requireAccountLevelMatch ? 1 : 0,
            ':id' => $templateId,
            ':rid' => $realmId,
        ]);

        return $st->rowCount() > 0;
    }

    public function deleteTemplate(int $realmId, int $templateId): bool
    {
        $this->ensureSchema();

        $realmId = max(1, (int) $realmId);
        $templateId = (int) $templateId;
        if ($templateId <= 0) {
            return false;
        }

        $pdo = $this->auth();

        // Manual cascade
        $st = $pdo->prepare('DELETE FROM character_boost_template_items WHERE template_id=:id');
        $st->execute([':id' => $templateId]);

        $st = $pdo->prepare('DELETE FROM character_boost_template_class_rewards WHERE template_id=:id');
        $st->execute([':id' => $templateId]);

        $st = $pdo->prepare('DELETE FROM character_boost_redeem_codes WHERE template_id=:id AND used_at IS NULL');
        $st->execute([':id' => $templateId]);

        $st = $pdo->prepare('DELETE FROM character_boost_templates WHERE id=:id AND realm_id=:rid');
        $st->execute([':id' => $templateId, ':rid' => $realmId]);

        return $st->rowCount() > 0;
    }

    public function replaceTemplateItems(int $templateId, array $items): void
    {
        $this->ensureSchema();

        $templateId = (int) $templateId;
        if ($templateId <= 0) {
            return;
        }

        $pdo = $this->auth();
        $pdo->prepare('DELETE FROM character_boost_template_items WHERE template_id=:id')->execute([':id' => $templateId]);

        $bucket = [];
        foreach ($items as $it) {
            $entry = (int) ($it['item_entry'] ?? $it['entry'] ?? 0);
            $qty = (int) ($it['quantity'] ?? $it['qty'] ?? 0);
            if ($entry <= 0 || $qty <= 0) {
                continue;
            }
            $bucket[$entry] = ($bucket[$entry] ?? 0) + $qty;
        }

        if (!$bucket) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO character_boost_template_items(template_id,item_entry,quantity,created_at,updated_at) VALUES(:tid,:entry,:qty,NOW(),NOW())');
        foreach ($bucket as $entry => $qty) {
            $ins->execute([':tid' => $templateId, ':entry' => (int) $entry, ':qty' => (int) $qty]);
        }
    }

    public function replaceTemplateClassRewards(int $templateId, array $tiers): void
    {
        $this->ensureSchema();

        $templateId = (int) $templateId;
        if ($templateId <= 0) {
            return;
        }

        $pdo = $this->auth();
        $pdo->prepare('DELETE FROM character_boost_template_class_rewards WHERE template_id=:id')->execute([':id' => $templateId]);

        $clean = [];
        foreach ($tiers as $tier) {
            $t = strtolower(trim((string) $tier));
            if ($t === '') {
                continue;
            }
            $clean[$t] = true;
        }
        if (!$clean) {
            return;
        }

        $ins = $pdo->prepare('INSERT INTO character_boost_template_class_rewards(template_id,tier,created_at,updated_at) VALUES(:tid,:tier,NOW(),NOW())');
        foreach (array_keys($clean) as $t) {
            $ins->execute([':tid' => $templateId, ':tier' => $t]);
        }
    }
}
