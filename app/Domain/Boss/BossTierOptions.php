<?php
/**
 * File: app/Domain/Boss/BossTierOptions.php
 * Purpose: Builds the difficulty tier option list and estimated boss health
 *          from config/boss.php for the Boss activity module.
 * Classes:
 *   - BossTierOptions
 * Functions:
 *   - entries()
 *   - labels()
 *   - defaultEntry()
 *   - resolveEntry()
 *   - tierHealthModifier()
 *   - estimateHp()
 *   - toScaled()
 *   - toDisplay()
 *   - formatHp()
 *   - scale()
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;

final class BossTierOptions
{
    /**
     * config/boss.php 的 tiers 定义，键为 entry（已规整为 int）。
     */
    public static function entries(): array
    {
        $configured = Config::get('boss.tiers', []);
        if (!is_array($configured))
            return [];

        $entries = [];

        foreach ($configured as $entry => $tier) {
            $entryId = (int) $entry;
            if ($entryId <= 0 || !is_array($tier))
                continue;

            $entries[$entryId] = [
                'key' => (string) ($tier['key'] ?? ''),
                'health_modifier' => (float) ($tier['health_modifier'] ?? 0),
                'damage_modifier' => (float) ($tier['damage_modifier'] ?? 0),
            ];
        }

        return $entries;
    }

    /**
     * entry => 本地化档位名（视图下拉框只需要这个）。
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::entries() as $entry => $tier) {
            $key = (string) ($tier['key'] ?? '');
            $labels[$entry] = $key !== ''
                ? (string) __('app.boss.tiers.labels.' . $key, [], (string) $entry)
                : (string) $entry;
        }

        return $labels;
    }

    public static function defaultEntry(): int
    {
        $configured = (int) Config::get('boss.default_tier_entry', 0);
        $entries = self::entries();

        if ($configured > 0 && array_key_exists($configured, $entries))
            return $configured;

        $fallback = (int) Config::get('boss.defaults.boss_entry', 0);
        if ($fallback > 0 && array_key_exists($fallback, $entries))
            return $fallback;

        $first = array_key_first($entries);

        return $first === null ? 0 : (int) $first;
    }

    /**
     * 只接受 tiers 里的 entry，其余一律回落到 default_tier_entry。
     */
    public static function resolveEntry(mixed $value): int
    {
        $entries = self::entries();
        $entry = (int) $value;

        if ($entry > 0 && array_key_exists($entry, $entries))
            return $entry;

        return self::defaultEntry();
    }

    public static function tierHealthModifier(int $entry): float
    {
        $entries = self::entries();

        return (float) ($entries[$entry]['health_modifier'] ?? 0.0);
    }

    /**
     * 预估血量 = round(tier_base_hp × HealthModifier × (health_multiplier_scaled / 100))。
     */
    public static function estimateHp(int $entry, int $healthMultiplierScaled): int
    {
        $modifier = self::tierHealthModifier($entry);
        if ($modifier <= 0.0)
            return 0;

        $scale = self::scale();
        $multiplier = max(0, $healthMultiplierScaled) / $scale;

        return (int) round(
            (float) Config::get('boss.tier_base_hp', 13945) * $modifier * $multiplier
        );
    }

    /**
     * 把视图里的 boss_health_multiplier 显示值（如 1500.00）换算成存储缩放值（150000）。
     */
    public static function toScaled(mixed $displayValue): int
    {
        if (!is_numeric($displayValue))
            return (int) Config::get('boss.defaults.boss_health_multiplier_scaled', 2000);

        return (int) round((float) $displayValue * self::scale());
    }

    /**
     * 存储缩放值（150000）→ 显示值（'1500.00'）。
     */
    public static function toDisplay(int $scaledValue): string
    {
        return number_format($scaledValue / self::scale(), 2, '.', '');
    }

    public static function formatHp(int $value): string
    {
        return number_format($value, 0, '.', ',');
    }

    private static function scale(): int
    {
        return max(1, (int) Config::get('boss.decimal_scale', 100));
    }
}
