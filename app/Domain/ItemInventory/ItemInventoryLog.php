<?php
/**
 * File: app/Domain/ItemInventory/ItemInventoryLog.php
 * Purpose: Single append-only action log for the unified item/inventory module,
 *          replacing the two duplicate log writers that used to live inside
 *          BagQueryRepository and ItemOwnershipRepository.
 *
 * Writes to storage/logs/item_inventory_actions.log, which the Logs module
 * exposes through config/logs.php.
 *
 * Classes:
 *   - ItemInventoryLog
 * Functions:
 *   - action()
 *   - logFile()
 *   - currentUser()
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\ItemInventory;

use Acme\Panel\Support\ClientIp;
use Acme\Panel\Support\LogPath;
use Throwable;

final class ItemInventoryLog
{
    public const FILE = 'item_inventory_actions.log';

    /**
     * @param array<string,mixed> $context
     */
    public static function action(string $event, array $context = []): void
    {
        try {
            $data = $context + [
                'admin' => self::currentUser(),
                'server' => $context['server'] ?? null,
                'ip' => ClientIp::resolve($_SERVER),
                'time_iso' => date('c'),
            ];
            $data = array_filter($data, static fn ($value): bool => $value !== null);

            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $line = sprintf('[%s] item_inventory.%s %s', date('Y-m-d H:i:s'), $event, $json ?: '{}');

            LogPath::appendTo(self::logFile(), $line, true, 0777);
        } catch (Throwable $ignored) {
            // Logging must never break an inventory operation.
        }
    }

    public static function logFile(): string
    {
        return LogPath::logsDir(true, 0777) . DIRECTORY_SEPARATOR . self::FILE;
    }

    public static function currentUser(): string
    {
        $candidates = [$_SESSION['panel_user'] ?? null, $_SESSION['admin_user'] ?? null, $_SESSION['username'] ?? null];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return 'unknown';
    }
}
