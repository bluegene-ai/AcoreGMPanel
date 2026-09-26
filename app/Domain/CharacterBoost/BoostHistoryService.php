<?php
/**
 * File: app/Domain/CharacterBoost/BoostHistoryService.php
 * Purpose: 直升历史记录的唯一写入入口。
 * 任何执行直升的入口（直升管理页、前台兑换码、角色详情页）都通过这里落地历史。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\CharacterBoost;

class BoostHistoryService
{
    private int $serverId;
    private BoostLogRepository $logs;

    public function __construct(?int $serverId = null)
    {
        $this->serverId = $serverId ?? \Acme\Panel\Support\ServerContext::currentId();
        $this->logs = new BoostLogRepository($this->serverId);
    }

    /** @param array<int, string> $errors */
    public function record(
        string $characterName,
        int $targetLevel,
        bool $success,
        string $itemSummary = '',
        int $quantity = 0,
        int $moneyCopper = 0,
        array $errors = []
    ): void {
        try {
            $this->logs->record([
                'subject' => sprintf('直升 %s 至 %d 级', mb_substr($characterName, 0, 60), $targetLevel),
                'items' => $itemSummary !== '' ? $itemSummary : null,
                'quantity' => $quantity > 0 ? $quantity : null,
                'amount' => $moneyCopper > 0 ? $moneyCopper : null,
                'success' => $success,
                'recipients' => $success ? $characterName : ($characterName . '!'),
                'errors' => $errors,
            ]);
        } catch (\Throwable $exception) {
            // 历史写入失败不能影响直升本身
        }
    }
}
