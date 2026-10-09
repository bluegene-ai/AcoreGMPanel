<?php
/**
 * File: app/Domain/ItemInventory/ItemInventoryLog.php
 * Purpose: 物品/库存模块的动作记录，统一落到审计表（module=item_inventory）。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\ItemInventory;

use Acme\Panel\Support\Audit;
use Throwable;

final class ItemInventoryLog
{
    /**
     * 事件名就是动作名；状态优先取 context.status，其次看 context.success，
     * 都没有时按名字里的 failed / degraded / error 判断。
     */
    public static function action(string $event, array $context = []): void
    {
        try {
            $event = $event !== '' ? $event : 'action';
            $status = $context['status'] ?? null;
            if (!is_string($status) || $status === '') {
                $status = match (true) {
                    array_key_exists('success', $context) => $context['success'] ? 'ok' : 'fail',
                    str_contains($event, 'failed'), str_contains($event, 'degraded'), str_contains($event, 'error') => 'fail',
                    default => 'ok',
                };
            }
            Audit::log('item_inventory', $event, '', $context + ['status' => $status]);
        } catch (Throwable $ignored) {
            // Logging must never break an inventory operation.
        }
    }

    public static function currentUser(): string
    {
        return \Acme\Panel\Support\Auth::user() ?? 'unknown';
    }
}
