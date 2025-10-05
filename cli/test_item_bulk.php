<?php
session_id('bulkcli');
session_start();

define('PANEL_CLI_AUTH_BYPASS', true);

require __DIR__ . '/../bootstrap/autoload.php';
require __DIR__ . '/../bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Domain\ItemOwnership\ItemOwnershipRepository;
use PDO;

Config::init(__DIR__ . '/../config');
Lang::init();

if (!ServerContext::currentId()) {
    ServerContext::set(0);
}

$repo = new class extends ItemOwnershipRepository {
    public function debugRows(array $ids): array
    {
        $ids = $this->normalize($ids);
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT ii.guid, ii.count, ii.durability, ii.itemEntry, ci.guid AS character_guid'
            . ' FROM item_instance ii'
            . ' JOIN character_inventory ci ON ci.item = ii.guid'
            . ' WHERE ii.guid IN (' . $placeholders . ') ORDER BY ii.guid ASC';
        $stmt = $this->characters()->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalize(array $ids): array
    {
        $filtered = [];
        foreach ($ids as $id) {
            $v = (int) $id;
            if ($v > 0) {
                $filtered[$v] = $v;
            }
        }
        return array_values($filtered);
    }
};
$action = $argv[1] ?? 'replace';
$idsArg = $argv[2] ?? '';
$newEntry = isset($argv[3]) ? (int)$argv[3] : 0;
$rawIds = false;

if (str_starts_with($idsArg, 'raw:')) {
    $rawIds = true;
    $idsArg = substr($idsArg, 4);
}

$ids = array_values(array_filter(array_map('intval', explode(',', $idsArg)), fn($v) => $v > 0));
$payloadIds = $rawIds ? $idsArg : $ids;

if (!$rawIds && !$ids) {
    fwrite(STDERR, "Provide comma separated instance ids as second argument\n");
    exit(1);
}

if ($action === 'debug') {
    $rows = $repo->debugRows($ids);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

if ($action === 'delete') {
    $result = $repo->bulkDelete($payloadIds);
} else {
    if ($newEntry <= 0) {
        fwrite(STDERR, "Provide new entry as third argument for replace action\n");
        exit(1);
    }
    $result = $repo->bulkReplace($payloadIds, $newEntry);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
