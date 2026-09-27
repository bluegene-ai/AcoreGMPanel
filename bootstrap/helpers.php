<?php
/**
 * File: bootstrap/helpers.php
 * Purpose: Provides functionality for the bootstrap module.
 * Functions:
 *   - url()
 *   - asset()
 *   - url_with_server()
 *   - flash_add()
 *   - flash_pull_all()
 *   - __()
 */

declare(strict_types=1);

use Acme\Panel\Core\ItemQuality;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Url;
use Acme\Panel\Support\ContentLink;
use Acme\Panel\Support\ServerContext;

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return Url::to($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('url_with_server')) {
    function url_with_server(string $path, ?int $serverId = null): string
    {
        $serverId = $serverId ?? ServerContext::currentId();

        if (strpos($path, 'server=') !== false) {
            return url($path);
        }

        $parts = parse_url($path) ?: [];
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['server'] = $serverId;

        $rebuilt = ($parts['path'] ?? '') . '?' . http_build_query($query);

        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return url($rebuilt);
    }
}

if (!function_exists('character_view_url')) {
    function character_view_url(int|string $guid, ?int $serverId = null): string
    {
        return url_with_server('/character/view?guid=' . (int) $guid, $serverId);
    }
}

if (!function_exists('account_view_url')) {
    function account_view_url(int|string $accountId, ?int $serverId = null): string
    {
        return url_with_server('/account/view?id=' . (int) $accountId, $serverId);
    }
}

if (!function_exists('character_link')) {
    function character_link(int|string $guid, ?string $label = null, ?int $serverId = null, string $fallbackPrefix = '#'): string
    {
        $guid = (int) $guid;
        $text = trim((string) ($label ?? ''));
        if ($text === '')
            $text = $fallbackPrefix . $guid;

        return '<a href="' . htmlspecialchars(character_view_url($guid, $serverId), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }
}

if (!function_exists('account_link')) {
    function account_link(int|string $accountId, ?string $label = null, ?int $serverId = null, string $fallbackPrefix = '#'): string
    {
        $accountId = (int) $accountId;
        $text = trim((string) ($label ?? ''));
        if ($text === '')
            $text = $fallbackPrefix . $accountId;

        return '<a href="' . htmlspecialchars(account_view_url($accountId, $serverId), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }
}

if (!function_exists('item_name_link')) {
    /**
     * 物品名 → 物品管理页的编辑入口，并按 item_template.quality 上色。
     *
     * 这是"物品名 = 可点的物品链接"的项目统一实现：任何列表要显示物品名都调这个函数，
     * 不要各自拼 class。配套的样式只有一处，在 public/assets/css/app-core.css 的
     * `.item-name-link` 块里（颜色一律来自 ItemQuality 的 item-quality-* 类）。
     *
     * - $linkable = false（调用方没有 content.view）时输出同色的纯文本，不给必然被拒的链接；
     * - $name 为空时退化成 "#entry"，至少不丢信息；
     * - $quality 为 null（自定义模板没有 Quality）时不着色，而不是猜一个"粗糙"；
     * - 悬停提示把"哪个品质"和"点开去哪儿"都写出来：颜色本身对色觉障碍者不可读。
     *
     * @param int|string $entry    物品 entry
     * @param string|null $name    item_template.name，空则显示 #entry
     * @param int|null $quality    item_template.quality（0..7），null = 未知
     * @param bool $linkable       是否渲染成链接（由调用方按 content.view 决定）
     * @param int|null $serverId   目标页区服；默认跟随当前区服
     */
    function item_name_link(
        int|string $entry,
        ?string $name = null,
        ?int $quality = null,
        bool $linkable = true,
        ?int $serverId = null
    ): string {
        $entry = (int) $entry;
        $label = trim((string) $name);
        if ($label === '') {
            $label = '#' . $entry;
        }

        $url = $linkable ? ContentLink::url('item', $entry, $serverId) : null;

        $title = [];
        if ($quality !== null) {
            $title[] = __('app.item.tooltip.quality', [
                'quality' => ItemQuality::label($quality, false),
                'value' => $quality,
            ]);
        }
        if ($url !== null) {
            $title[] = __('app.item.link.manage', ['id' => $entry]);
        }
        $titleAttr = $title === []
            ? ''
            : ' title="' . htmlspecialchars(implode(' · ', $title), ENT_QUOTES, 'UTF-8') . '"';

        $class = 'item-name-link' . ($quality !== null ? ' ' . ItemQuality::css($quality) : '');
        $inner = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        if ($url !== null) {
            return '<a class="' . $class . '"' . $titleAttr
                . ' href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $inner . '</a>';
        }

        return '<span class="' . $class . '"' . $titleAttr . '>' . $inner . '</span>';
    }
}

if (!function_exists('panel_hint')) {
    /**
     * 行内的 ⓘ 说明标记：可见内容只有一个圆点，真正的说明在 title 里。
     *
     * 渲染成可聚焦的 <span tabindex="0" role="note" aria-label="...">：键盘用户 Tab 到它时
     * 浏览器会弹出 title，读屏则直接念 aria-label。之前各处手写的
     * <span class="panel-hint" title="...">i</span> 不可聚焦，键盘用户完全拿不到这些说明，
     * 而面板里几乎所有关键概念的解释都塞在里面。
     *
     * @param string $text  完整说明（同时进 title 与 aria-label）
     * @param string $label 可见字符，默认 "i"
     */
    function panel_hint(string $text, string $label = 'i'): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return '<span class="panel-hint" tabindex="0" role="note" aria-label="' . $escaped . '"'
            . ' title="' . $escaped . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('flash_add')) {
    function flash_add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['flashes'][$type][] = $message;
    }
}

if (!function_exists('flash_pull_all')) {
    function flash_pull_all(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $all = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);

        return $all;
    }
}

if (!function_exists('flash_pull_type')) {
    function flash_pull_type(string $type): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $items = $_SESSION['flashes'][$type] ?? [];
        unset($_SESSION['flashes'][$type]);

        if (empty($_SESSION['flashes']) || !is_array($_SESSION['flashes'])) {
            unset($_SESSION['flashes']);
        }

        return is_array($items) ? $items : [];
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = [], ?string $default = null): string
    {
        return Lang::get($key, $replace, $default);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return '-';
        }

        if (is_numeric($value)) {
            $ts = (int)$value;
            if ($ts >= 1000000000) {
                return date($format, $ts);
            }
        }

        return (string)$value;
    }
}

if (!function_exists('format_money_gsc')) {
    function format_money_gsc($copper): string
    {
        $amount = is_numeric($copper) ? (int)$copper : 0;
        if ($amount < 0) {
            $amount = 0;
        }

        $gold = intdiv($amount, 10000);
        $silver = intdiv($amount % 10000, 100);
        $copperLeft = $amount % 100;
        return $gold . '金' . $silver . '银' . $copperLeft . '铜';
    }
}

