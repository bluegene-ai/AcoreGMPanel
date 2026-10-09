<?php
/**
 * File: resources/views/partials/list_url.php
 * Purpose: 列表页排序 / 分页链接的唯一 URL 拼装入口。
 *
 * 此前排序与分页各写一套：account 的排序用 url_with_server 但先剥掉 server，character 的
 * 排序完全不走 url_with_server（子路径部署下会丢当前区）。include 本文件后可用的两个闭包：
 *
 *   $list_url('/account', ['sort' => 'level_desc'])  保留当前筛选，覆盖指定键，带上当前区
 *   $sort_direction('level_desc')                   → 'is-asc' / 'is-desc' / ''
 *
 * 值为 null 或 '' 的覆盖项表示删除该键（排序三态循环的"取消排序"就是靠这个）。
 */

$list_url = static function (string $path, array $overrides = [], bool $keepServer = true): string {
    $query = $_GET;
    unset($query['page'], $query['server']);
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $base = $keepServer ? url_with_server($path) : url($path);
    $queryString = http_build_query($query);

    return $queryString === '' ? $base : $base . (str_contains($base, '?') ? '&' : '?') . $queryString;
};

$sort_direction = static function (?string $value): string {
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    if (str_ends_with($value, '_desc')) {
        return 'is-desc';
    }
    if (str_ends_with($value, '_asc')) {
        return 'is-asc';
    }

    return '';
};
