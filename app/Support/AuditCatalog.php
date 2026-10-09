<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;

/**
 * File: app/Support/AuditCatalog.php
 * Purpose: 审计日志的模块/动作/渠道目录，供写入端合成摘要、读取端渲染标签。
 *
 * 目录来自 config/logs.php。写入端只需要单个标签，因此按需走 Lang::get
 * （缺翻译时按 key 末段 humanize）；只有页面用的 localizedModules() 才整表展开。
 */
final class AuditCatalog
{
    /** 动作标签缺失时用作兜底的英文形式（exec_sql → Exec sql）。 */
    private const HUMANIZE_SUFFIXES = ['label', 'hint', 'description', 'summary', 'name', 'title', 'text'];

    private static ?array $config = null;

    private static ?array $modules = null;

    private static ?array $channels = null;

    private static ?array $statuses = null;

    public static function config(): array
    {
        if (self::$config === null) {
            $loaded = Config::get('logs', []);
            self::$config = is_array($loaded) ? $loaded : [];
        }

        return self::$config;
    }

    public static function storage(?string $key = null, mixed $default = null): mixed
    {
        $storage = self::config()['storage'] ?? [];
        if (!is_array($storage)) {
            $storage = [];
        }
        if ($key === null) {
            return $storage;
        }

        return $storage[$key] ?? $default;
    }

    public static function limits(?string $key = null, mixed $default = null): mixed
    {
        $limits = self::config()['limits'] ?? [];
        if (!is_array($limits)) {
            $limits = [];
        }
        if ($key === null) {
            return $limits;
        }

        return $limits[$key] ?? $default;
    }

    public static function defaults(): array
    {
        $defaults = self::config()['defaults'] ?? [];

        return is_array($defaults) ? $defaults : [];
    }

    /** 时间范围预设：键 => strtotime 相对表达式（空串代表不限时间）。 */
    public static function ranges(): array
    {
        $ranges = self::config()['ranges'] ?? [];
        if (!is_array($ranges) || $ranges === []) {
            return ['24h' => '-24 hours', '7d' => '-7 days', '30d' => '-30 days', 'all' => ''];
        }

        return $ranges;
    }

    /** @return array<string, array{label:string, description:string, actions:array<string,string>}> */
    public static function localizedModules(): array
    {
        if (self::$modules !== null) {
            return self::$modules;
        }

        $raw = self::config()['modules'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $modules = [];
        foreach ($raw as $id => $meta) {
            $id = (string) $id;
            if ($id === '' || !is_array($meta)) {
                continue;
            }
            $actions = [];
            foreach (($meta['actions'] ?? []) as $actionId => $label) {
                $actions[(string) $actionId] = self::label($label, (string) $actionId);
            }
            $modules[$id] = [
                'label' => self::label($meta['label'] ?? null, $id),
                'description' => self::label($meta['description'] ?? null, ''),
                'actions' => $actions,
            ];
        }

        return self::$modules = $modules;
    }

    /** @return array<string, string> */
    public static function localizedChannels(): array
    {
        if (self::$channels !== null) {
            return self::$channels;
        }

        $raw = self::config()['channels'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $channels = [];
        foreach ($raw as $id => $label) {
            $channels[(string) $id] = self::label($label, (string) $id);
        }

        return self::$channels = $channels;
    }

    /** @return array<string, string> */
    public static function localizedStatuses(): array
    {
        if (self::$statuses !== null) {
            return self::$statuses;
        }

        $raw = self::config()['statuses'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $statuses = [];
        foreach ($raw as $id => $label) {
            $statuses[(string) $id] = self::label($label, (string) $id);
        }

        return self::$statuses = $statuses;
    }

    public static function moduleLabel(string $module): string
    {
        $modules = self::localizedModules();

        return $modules[$module]['label'] ?? $module;
    }

    public static function actionLabel(string $module, string $action): string
    {
        $modules = self::localizedModules();

        return $modules[$module]['actions'][$action] ?? ($action !== '' ? $action : '-');
    }

    public static function channelLabel(string $channel): string
    {
        $channels = self::localizedChannels();

        return $channels[$channel] ?? $channel;
    }

    public static function statusLabel(string $status): string
    {
        $statuses = self::localizedStatuses();

        return $statuses[$status] ?? $status;
    }

    /**
     * 合成单行摘要：调用方显式给了 detail.summary 就用它，否则拼接「动作标签 · 目标」。
     * 失败事件把错误信息补在后面，避免只看到"执行 SQL"却不知道失败原因。
     */
    public static function summarize(string $module, string $action, string $target, array $detail, string $status): string
    {
        $explicit = $detail['summary'] ?? null;
        if (is_string($explicit) && trim($explicit) !== '') {
            return self::clip(trim($explicit));
        }

        $parts = [self::actionLabel($module, $action)];
        $target = trim($target);
        if ($target !== '') {
            $parts[] = $target;
        }

        if ($status === 'fail') {
            $error = $detail['error'] ?? ($detail['message'] ?? null);
            if (is_string($error) && trim($error) !== '') {
                $parts[] = self::clip(trim($error), 120);
            }
        }

        return self::clip(implode(' · ', array_filter($parts, static fn ($p) => $p !== '')));
    }

    public static function clip(string $value, int $max = 255): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1) . '…';
    }

    /** 把 'lang:app.logs.x' 解析为当前语种文本；缺翻译时按 key 末段生成可读标签。 */
    private static function label(mixed $value, string $fallback): string
    {
        if (is_string($value) && str_starts_with($value, 'lang:')) {
            $key = substr($value, 5);
            $translated = Lang::get($key);
            if (!is_string($translated) || $translated === $key) {
                return self::humanize($key, $fallback);
            }

            return $translated;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $fallback;
    }

    private static function humanize(string $key, string $fallback): string
    {
        $parts = array_values(array_filter(explode('.', $key), static fn ($p) => $p !== ''));
        if ($parts === []) {
            return $fallback;
        }

        $last = (string) end($parts);
        $base = $last;
        if (in_array($last, self::HUMANIZE_SUFFIXES, true) && count($parts) >= 2) {
            $base = $parts[count($parts) - 2];
        }
        $base = trim(str_replace(['-', '_'], ' ', $base));
        if ($base === '') {
            return $fallback;
        }

        return mb_strtoupper(mb_substr($base, 0, 1)) . mb_substr($base, 1);
    }
}
