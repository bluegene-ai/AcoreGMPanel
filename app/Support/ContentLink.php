<?php
/**
 * File: app/Support/ContentLink.php
 * Purpose: Builds deep links from a game content id to that content's management screen, so any list
 * that shows a raw entry can link into the editor instead of forcing the operator to copy the id.
 *
 * Both target screens are pure-GET pages that already open their editor from a query parameter, so a
 * deep link costs ZERO extra database work (/item?edit_id=<entry>, /quest?edit_id=<id>). The target
 * pages enforce their own `content.view`; callers decide whether to render a link at all.
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Url;

final class ContentLink
{
    /**
     * Editor query parameter names, keyed by content type: shared by the server-rendered markup and the
     * client module.
     */
    private const EDIT_PARAM = [
        'item' => 'edit_id',
        'quest' => 'edit_id',
    ];

    /** List screen each content type links back to. */
    private const BASE_PATH = [
        'item' => '/item',
        'quest' => '/quest',
    ];

    /**
     * Absolute (base-path aware) URL that opens $id in its editor on the current realm; null for an
     * unknown type or a non-positive id.
     */
    public static function url(string $type, int $id, ?int $serverId = null): ?string
    {
        $param = self::EDIT_PARAM[$type] ?? null;
        $path = self::BASE_PATH[$type] ?? null;
        if ($param === null || $path === null || $id <= 0) {
            return null;
        }

        $query = $path . '?' . http_build_query([$param => $id]);

        // these helpers come from bootstrap/helpers.php, which may not be loaded (CLI probes, early bootstrap failures)
        if (function_exists('url_with_server')) {
            return url_with_server($query, $serverId);
        }
        if (function_exists('url')) {
            return url($query);
        }

        return $query;
    }

    public static function supports(string $type): bool
    {
        return isset(self::EDIT_PARAM[$type], self::BASE_PATH[$type]);
    }

    /** Query parameter name used to deep-link this content type. */
    public static function editParam(string $type): ?string
    {
        return self::EDIT_PARAM[$type] ?? null;
    }
}
