<?php
/**
 * File: app/Domain/Auctionator/AuctionatorRepository.php
 * Purpose: Read the mod-auctionator state and edit its three policy tables.
 *
 * Tables involved (see the module's conf for the semantics):
 *   world      mod_auctionator_itemclass_config (class, subclass, bonding, max_count, stack_count)
 *   world      mod_auctionator_disabled_items   (item)
 *   world      mod_auctionator_gm_list          (item, price, stack, hours, house, owner, enabled)
 *   world      mod_auctionator_item_class       (class, subclass, name)  [labels only]
 *   characters mod_auctionator_market_price     (entry, scan_datetime, average_price, count, ...)
 *   characters auctionhouse / item_instance / mail                       [dashboard counters]
 *
 * Classes:
 *   - AuctionatorRepository
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Auctionator;

use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;
use Throwable;

final class AuctionatorRepository extends MultiServerRepository
{
    /** @var string[] */
    private array $warnings = [];

    /** @return string[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    // ------------------------------------------------------------------ dashboard reads

    /**
     * Bot/player listing counters for the dashboard.
     *
     * @return array{ok: bool, total: int, bot: int, player: int, bid_only: int, with_buyout: int,
     *               distinct_items: int, min_id: int, max_id: int, min_expire: int, max_expire: int,
     *               by_house: array<int, int>, bot_mail: int}
     */
    public function listingStats(int $botGuid): array
    {
        $stats = [
            'ok' => false, 'total' => 0, 'bot' => 0, 'player' => 0, 'bid_only' => 0,
            'with_buyout' => 0, 'distinct_items' => 0, 'min_id' => 0, 'max_id' => 0,
            'min_expire' => 0, 'max_expire' => 0, 'by_house' => [], 'bot_mail' => 0,
        ];

        $row = $this->tryOne(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(itemowner = :bot_a), 0) AS bot,
                    COALESCE(SUM(itemowner <> :bot_b), 0) AS player,
                    COALESCE(SUM(buyoutprice = 0), 0) AS bid_only,
                    COALESCE(SUM(buyoutprice > 0), 0) AS with_buyout,
                    COALESCE(MIN(id), 0) AS min_id,
                    COALESCE(MAX(id), 0) AS max_id,
                    COALESCE(MIN(time), 0) AS min_expire,
                    COALESCE(MAX(time), 0) AS max_expire
               FROM auctionhouse',
            [':bot_a' => $botGuid, ':bot_b' => $botGuid],
            $this->characters()
        );

        if ($row === null) {
            return $stats;
        }

        $stats['ok'] = true;
        foreach (['total', 'bot', 'player', 'bid_only', 'with_buyout', 'min_id', 'max_id', 'min_expire', 'max_expire'] as $key) {
            $stats[$key] = (int) ($row[$key] ?? 0);
        }

        foreach ($this->tryAll('SELECT houseId, COUNT(*) AS n FROM auctionhouse GROUP BY houseId', [], $this->characters()) as $houseRow) {
            $stats['by_house'][(int) $houseRow['houseId']] = (int) $houseRow['n'];
        }

        $distinct = $this->tryOne(
            'SELECT COUNT(DISTINCT ii.itemEntry) AS n
               FROM auctionhouse ah
               JOIN item_instance ii ON ii.guid = ah.itemguid',
            [],
            $this->characters()
        );
        $stats['distinct_items'] = (int) ($distinct['n'] ?? 0);

        if ($botGuid > 0) {
            $mail = $this->tryOne('SELECT COUNT(*) AS n FROM mail WHERE receiver = :guid', [':guid' => $botGuid], $this->characters());
            $stats['bot_mail'] = (int) ($mail['n'] ?? 0);
        }

        return $stats;
    }

    /**
     * @return array{ok: bool, error: string, rows: int, distinct_items: int, fresh_items: int,
     *               newest: string, oldest: string, sources: array<string, int>}
     */
    public function marketStats(int $maxAgeDays): array
    {
        $stats = ['ok' => false, 'error' => '', 'rows' => 0, 'distinct_items' => 0, 'fresh_items' => 0, 'newest' => '', 'oldest' => '', 'sources' => []];

        $table = $this->hasTable('mod_auctionator_market_price', $this->characters());
        if ($table !== true) {
            $stats['error'] = 'missing_table';

            return $stats;
        }

        $row = $this->tryOne(
            'SELECT COUNT(*) AS rows_total,
                    COUNT(DISTINCT entry) AS items,
                    COALESCE(DATE_FORMAT(MAX(scan_datetime), "%Y-%m-%d %H:%i"), "") AS newest,
                    COALESCE(DATE_FORMAT(MIN(scan_datetime), "%Y-%m-%d %H:%i"), "") AS oldest
               FROM mod_auctionator_market_price',
            [],
            $this->characters()
        );

        if ($row === null) {
            $stats['error'] = 'unreadable';

            return $stats;
        }

        $stats['ok'] = true;
        $stats['rows'] = (int) ($row['rows_total'] ?? 0);
        $stats['distinct_items'] = (int) ($row['items'] ?? 0);
        $stats['newest'] = (string) ($row['newest'] ?? '');
        $stats['oldest'] = (string) ($row['oldest'] ?? '');

        if ($maxAgeDays > 0) {
            $fresh = $this->tryOne(
                'SELECT COUNT(DISTINCT entry) AS n
                   FROM mod_auctionator_market_price
                  WHERE TIMESTAMPDIFF(DAY, scan_datetime, NOW()) <= :days',
                [':days' => $maxAgeDays],
                $this->characters()
            );
            $stats['fresh_items'] = (int) ($fresh['n'] ?? 0);
        } else {
            $stats['fresh_items'] = $stats['distinct_items'];
        }

        foreach ($this->tryAll(
            'SELECT COALESCE(NULLIF(source, ""), "(empty)") AS src, COUNT(*) AS n
               FROM mod_auctionator_market_price GROUP BY src ORDER BY n DESC LIMIT 8',
            [],
            $this->characters()
        ) as $sourceRow) {
            $stats['sources'][(string) $sourceRow['src']] = (int) $sourceRow['n'];
        }

        return $stats;
    }

    // ------------------------------------------------------------------ policy reads

    /**
     * @return array{disabled: array<int, array<string, mixed>>, itemclass: array<int, array<string, mixed>>,
     *               gm_list: array<int, array<string, mixed>>, tables: array<string, bool>}
     */
    public function policyRows(int $limit, int $disabledLimit, int $itemclassLimit, int $gmListLimit, int $disabledFrom = 0): array
    {
        $world = $this->world();

        // The blacklist can hold thousands of rows, so it is paged by item id instead of
        // being dumped in one go (`disabled_from` comes from the page query string).
        $disabledRows = $this->tryAll(
            'SELECT item FROM mod_auctionator_disabled_items WHERE item >= :from ORDER BY item ASC LIMIT ' . max(1, $disabledLimit),
            [':from' => max(0, $disabledFrom)],
            $world
        );

        $itemclassRows = $this->tryAll(
            'SELECT class, subclass, bonding, max_count, stack_count
               FROM mod_auctionator_itemclass_config
              ORDER BY class ASC, subclass ASC LIMIT ' . max(1, $itemclassLimit),
            [],
            $world
        );

        $gmRows = $this->tryAll(
            'SELECT item, price, stack, hours, house, owner, enabled
               FROM mod_auctionator_gm_list
              ORDER BY house ASC, item ASC LIMIT ' . max(1, $gmListLimit),
            [],
            $world
        );

        $entries = [];
        foreach ($disabledRows as $row) {
            $entries[] = (int) $row['item'];
        }
        foreach ($gmRows as $row) {
            $entries[] = (int) $row['item'];
        }

        $names = $this->itemNames($entries);
        $classNames = $this->classNames();

        $disabled = [];
        foreach ($disabledRows as $row) {
            $entry = (int) $row['item'];
            $disabled[] = ['item' => $entry, 'name' => $names[$entry] ?? ''];
        }

        $itemclass = [];
        foreach ($itemclassRows as $row) {
            $class = (int) $row['class'];
            $subclass = (int) $row['subclass'];
            $itemclass[] = [
                'class' => $class,
                'subclass' => $subclass,
                'class_label' => $classNames['class'][$class] ?? ('#' . $class),
                'subclass_label' => $classNames['subclass'][$class . ':' . $subclass] ?? ('#' . $subclass),
                'bonding' => (int) $row['bonding'],
                'max_count' => (int) $row['max_count'],
                'stack_count' => (int) $row['stack_count'],
            ];
        }

        $gmList = [];
        foreach ($gmRows as $row) {
            $entry = (int) $row['item'];
            $gmList[] = [
                'item' => $entry,
                'name' => $names[$entry] ?? '',
                'price' => (int) $row['price'],
                'stack' => (int) $row['stack'],
                'hours' => (int) $row['hours'],
                'house' => (int) $row['house'],
                'owner' => (int) $row['owner'],
                'enabled' => (int) $row['enabled'],
            ];
        }

        return [
            'disabled' => $disabled,
            'itemclass' => $itemclass,
            'gm_list' => $gmList,
            'totals' => $this->policyTotals($world),
            'disabled_from' => max(0, $disabledFrom),
            'tables' => [
                'disabled_items' => $this->hasTable('mod_auctionator_disabled_items', $world) === true,
                'itemclass_config' => $this->hasTable('mod_auctionator_itemclass_config', $world) === true,
                'gm_list' => $this->hasTable('mod_auctionator_gm_list', $world) === true,
                'market_price' => $this->hasTable('mod_auctionator_market_price', $this->characters()) === true,
            ],
        ];
    }

    /**
     * @param int[] $entries
     * @return array<int, string>
     */
    public function itemNames(array $entries): array
    {
        $entries = array_values(array_unique(array_filter(array_map('intval', $entries), static fn (int $id): bool => $id > 0)));
        if ($entries === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entries), '?'));
        $rows = $this->tryAll(
            'SELECT entry, name FROM item_template WHERE entry IN (' . $placeholders . ')',
            $entries,
            $this->world()
        );

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['entry']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * Item class/subclass labels from the module's own lookup table (mod_auctionator_item_class).
     *
     * @return array{class: array<int, string>, subclass: array<string, string>}
     */
    public function classNames(): array
    {
        $rows = $this->tryAll('SELECT class, subclass, name FROM mod_auctionator_item_class', [], $this->world());

        $classes = [];
        $subclasses = [];
        foreach ($rows as $row) {
            $class = (int) $row['class'];
            $name = (string) $row['name'];
            if ($row['subclass'] === null) {
                $classes[$class] = $name;
                continue;
            }

            $subclasses[$class . ':' . (int) $row['subclass']] = $name;
        }

        return ['class' => $classes, 'subclass' => $subclasses];
    }

    // ------------------------------------------------------------------ policy writes

    public function addDisabledItem(int $item): bool
    {
        $statement = $this->world()->prepare('INSERT IGNORE INTO mod_auctionator_disabled_items (item) VALUES (:item)');

        return $statement->execute([':item' => $item]);
    }

    public function deleteDisabledItem(int $item): bool
    {
        $statement = $this->world()->prepare('DELETE FROM mod_auctionator_disabled_items WHERE item = :item');
        $statement->execute([':item' => $item]);

        return $statement->rowCount() > 0;
    }

    public function saveItemclassRow(int $class, int $subclass, int $bonding, int $maxCount, int $stackCount): void
    {
        $statement = $this->world()->prepare(
            'INSERT INTO mod_auctionator_itemclass_config (class, subclass, bonding, max_count, stack_count)
             VALUES (:class, :subclass, :bonding, :max_count, :stack_count)
             ON DUPLICATE KEY UPDATE bonding = VALUES(bonding), max_count = VALUES(max_count), stack_count = VALUES(stack_count)'
        );

        $statement->execute([
            ':class' => $class,
            ':subclass' => $subclass,
            ':bonding' => $bonding,
            ':max_count' => $maxCount,
            ':stack_count' => $stackCount,
        ]);
    }

    public function deleteItemclassRow(int $class, int $subclass): bool
    {
        $statement = $this->world()->prepare('DELETE FROM mod_auctionator_itemclass_config WHERE class = :class AND subclass = :subclass');
        $statement->execute([':class' => $class, ':subclass' => $subclass]);

        return $statement->rowCount() > 0;
    }

    public function saveGmListRow(int $item, int $price, int $stack, int $hours, int $house, int $owner, int $enabled): void
    {
        $statement = $this->world()->prepare(
            'INSERT INTO mod_auctionator_gm_list (item, price, stack, hours, house, owner, enabled)
             VALUES (:item, :price, :stack, :hours, :house, :owner, :enabled)
             ON DUPLICATE KEY UPDATE price = VALUES(price), stack = VALUES(stack), hours = VALUES(hours),
                                     house = VALUES(house), owner = VALUES(owner), enabled = VALUES(enabled)'
        );

        $statement->execute([
            ':item' => $item,
            ':price' => $price,
            ':stack' => $stack,
            ':hours' => $hours,
            ':house' => $house,
            ':owner' => $owner,
            ':enabled' => $enabled,
        ]);
    }

    public function toggleGmListRow(int $item, bool $enabled): bool
    {
        $statement = $this->world()->prepare('UPDATE mod_auctionator_gm_list SET enabled = :enabled WHERE item = :item');
        $statement->execute([':enabled' => $enabled ? 1 : 0, ':item' => $item]);

        return $statement->rowCount() > 0;
    }

    public function deleteGmListRow(int $item): bool
    {
        $statement = $this->world()->prepare('DELETE FROM mod_auctionator_gm_list WHERE item = :item');
        $statement->execute([':item' => $item]);

        return $statement->rowCount() > 0;
    }

    /**
     * Row counts of the three policy tables (the page shows them next to the limited lists).
     *
     * @return array{disabled: int, itemclass: int, gm_list: int, gm_list_enabled: int}
     */
    private function policyTotals(PDO $world): array
    {
        $totals = ['disabled' => 0, 'itemclass' => 0, 'gm_list' => 0, 'gm_list_enabled' => 0];

        $row = $this->tryOne(
            'SELECT (SELECT COUNT(*) FROM mod_auctionator_disabled_items) AS disabled,
                    (SELECT COUNT(*) FROM mod_auctionator_itemclass_config) AS itemclass,
                    (SELECT COUNT(*) FROM mod_auctionator_gm_list) AS gm_list,
                    (SELECT COUNT(*) FROM mod_auctionator_gm_list WHERE enabled = 1) AS gm_list_enabled',
            [],
            $world
        );

        if ($row === null) {
            return $totals;
        }

        foreach (array_keys($totals) as $key) {
            $totals[$key] = (int) ($row[$key] ?? 0);
        }

        return $totals;
    }

    /**
     * Item existence check for the GM add command (".auctionator add" aborts on unknown ids).
     */
    public function itemExists(int $item): bool
    {
        $row = $this->tryOne('SELECT entry, name FROM item_template WHERE entry = :entry', [':entry' => $item], $this->world());

        return $row !== null;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * 本区**到底装没装** mod-auctionator：以模块自己的表（world 库 mod_auctionator_disabled_items）
     * 为唯一证据。多区面板里这是"能不能管这个区的机器人"的判据，比手写白名单可靠——
     * 装了模块的区自动就能管，没装的区也不会被误判成"页面故障"。
     *
     * @return array{deployed: bool, reason: string} reason ∈ deployed|not_deployed|db_unreachable
     */
    public function deploymentProbe(): array
    {
        try {
            $world = $this->world();
        } catch (Throwable) {
            return ['deployed' => false, 'reason' => 'db_unreachable'];
        }

        $has = $this->hasTable('mod_auctionator_disabled_items', $world);
        if ($has === null) {
            return ['deployed' => false, 'reason' => 'db_unreachable'];
        }

        return ['deployed' => $has, 'reason' => $has ? 'deployed' : 'not_deployed'];
    }

    private function hasTable(string $table, PDO $pdo): ?bool
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute([':table' => $table]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return $row !== false && (int) ($row['n'] ?? 0) > 0;
        } catch (Throwable $exception) {
            $this->warn('hasTable(' . $table . '): ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function tryOne(string $sql, array $params, PDO $pdo): ?array
    {
        try {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            return $row === false ? [] : $row;
        } catch (Throwable $exception) {
            $this->warn(preg_replace('/\s+/', ' ', substr($sql, 0, 90)) . ' -> ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function tryAll(string $sql, array $params, PDO $pdo): array
    {
        try {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable $exception) {
            $this->warn(preg_replace('/\s+/', ' ', substr($sql, 0, 90)) . ' -> ' . $exception->getMessage());

            return [];
        }
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;

        try {
            $directory = dirname(__DIR__, 3) . '/storage/logs';
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            @file_put_contents(
                $directory . '/auctionator_repository_warnings.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
                FILE_APPEND
            );
        } catch (Throwable) {
            // Logging must never break the page.
        }
    }
}
