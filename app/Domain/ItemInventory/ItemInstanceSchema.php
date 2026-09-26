<?php
/**
 * File: app/Domain/ItemInventory/ItemInstanceSchema.php
 * Purpose: Probes the live `item_instance` schema once per request (INFORMATION_SCHEMA, cached in
 * process) so the inventory mutation service can build SQL that works across AzerothCore variants
 * without ever requiring a schema migration.
 *
 * Probing is mandatory: `guid` is a plain PRIMARY KEY with DEFAULT 0, not AUTO_INCREMENT, so a new
 * instance must allocate its own guid; `enchantments` is TEXT NOT NULL with no default, so a minimal
 * INSERT fails under STRICT_TRANS_TABLES; some forks spell metadata columns differently or omit them.
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\ItemInventory;

use PDO;
use Throwable;

final class ItemInstanceSchema
{
    public const TABLE = 'item_instance';

    /**
     * Instance-level metadata that stops being meaningful when an instance is re-pointed at a different
     * item template. `enchantments*` hold *slot* enchantments rather than item-type data, so they are
     * deliberately NOT cleared - clearing them would silently strip a player's enchants.
     */
    private const CLEARABLE = [
        'charges' => 0,
        'randomPropertyId' => 0,
        'randomPropertyId2' => 0,
    ];

    /** Columns whose live value must be carried over to a newly split instance. */
    private const STRUCTURAL = [
        'itemEntry',
        'count',
        'durability',
        'owner_guid',
        'owner',
        'creatorGuid',
        'giftCreatorGuid',
    ];

    private ?array $columns = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function hasColumn(string $column): bool
    {
        return isset($this->columnMap()[$column]);
    }

    /**
     * Residue columns present in this schema, in a stable order.
     * @return array<int,string>
     */
    public function clearableColumns(): array
    {
        $present = [];
        foreach (array_keys(self::CLEARABLE) as $column) {
            if ($this->hasColumn($column)) {
                $present[] = $column;
            }
        }

        return $present;
    }

    /** SET-clause literal for a residue column. */
    public function clearLiteral(string $column): string
    {
        return (string) (int) (self::CLEARABLE[$column] ?? 0);
    }

    /** Bound value for a residue column. */
    public function clearValue(string $column): int
    {
        return (int) (self::CLEARABLE[$column] ?? 0);
    }

    /**
     * True when the schema manages `guid` itself (AUTO_INCREMENT); on the dominant AzerothCore schema it
     * does not, so the caller must allocate a guid explicitly.
     */
    public function useDefaultGuid(): bool
    {
        $map = $this->columnMap();
        if (!isset($map['guid'])) {
            return false;
        }

        return $map['guid']['extra'] === 'auto_increment';
    }

    /**
     * INSERT column list for cloning an instance, plus the value source for each column: a bound parameter
     * or a SQL literal that satisfies NOT NULL.
     * Structural columns present in the schema are bound; every other NOT NULL column without a default
     * (e.g. the `enchantments` text column on live AzerothCore realms) gets a type-appropriate literal so
     * the statement cannot fail under STRICT_TRANS_TABLES.
     * @return array{columns:array<int,string>,bindings:array<string,string>,literals:array<string,string>}
     */
    public function insertPlan(): array
    {
        $map = $this->columnMap();
        if (!$map) {
            // probe failed: fall back to the minimal shape that the widest range of schemas accepts
            return [
                'columns' => ['itemEntry', 'count', 'durability'],
                'bindings' => ['itemEntry' => ':entry', 'count' => ':count', 'durability' => ':durability'],
                'literals' => [],
            ];
        }

        $columns = [];
        $bindings = [];
        $literals = [];

        // Identity column is handled by the caller (explicit guid or AUTO_INCREMENT).
        $skip = ['guid'];

        foreach (self::STRUCTURAL as $column) {
            if (!isset($map[$column]) || in_array($column, $skip, true)) {
                continue;
            }
            $columns[] = $column;
            $bindings[$column] = ':' . $this->parameterName($column);
        }

        foreach ($map as $column => $meta) {
            if (in_array($column, $skip, true) || in_array($column, $columns, true)) {
                continue;
            }
            if ($meta['nullable'] || $meta['hasDefault'] || $meta['extra'] === 'auto_increment') {
                continue;
            }
            $columns[] = $column;
            $literals[$column] = $this->literalFor($meta['type']);
        }

        return ['columns' => $columns, 'bindings' => $bindings, 'literals' => $literals];
    }

    /** Safe, portable parameter name for a column (avoids camelCase weirdness). */
    public function parameterName(string $column): string
    {
        return 'c_' . preg_replace('/[^A-Za-z0-9_]/', '_', $column);
    }

    /** A literal that satisfies a NOT NULL column of the given MySQL type: numeric → 0, everything else → ''. */
    private function literalFor(string $type): string
    {
        $type = strtolower($type);
        foreach (['int', 'decimal', 'float', 'double', 'bit', 'year'] as $numeric) {
            if (str_contains($type, $numeric)) {
                return '0';
            }
        }

        return "''";
    }

    /** @return array<string,array{name:string,nullable:bool,hasDefault:bool,type:string,extra:string}> */
    private function columnMap(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $this->columns = [];

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, EXTRA
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
                 ORDER BY ORDINAL_POSITION'
            );
            $stmt->execute([':t' => self::TABLE]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $name = (string) ($row['COLUMN_NAME'] ?? '');
                if ($name === '') {
                    continue;
                }
                $this->columns[$name] = [
                    'name' => $name,
                    'nullable' => strtoupper((string) ($row['IS_NULLABLE'] ?? 'NO')) === 'YES',
                    'hasDefault' => $row['COLUMN_DEFAULT'] !== null,
                    'type' => (string) ($row['DATA_TYPE'] ?? ''),
                    'extra' => strtolower((string) ($row['EXTRA'] ?? '')),
                ];
            }
        } catch (Throwable $e) {
            // Leave the map empty: callers fall back to the minimal SQL shape.
        }

        return $this->columns;
    }
}
