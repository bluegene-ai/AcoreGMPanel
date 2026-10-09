<?php
/**
 * File: resources/views/components/pagination.php
 * Purpose: 分页条的唯一实现（服务端渲染；AJAX 列表用 panel.js 的 Panel.paginate()，标记与本文件一致）。
 *
 * 用法：$page / $pages / $base 必填；$total 与 $per_page 可选，给出来就多一行 "x–y / 总数"。
 * $base 是已经带好筛选条件与 server 的 URL（用 partials/list_url.php 的 $list_url 生成）。
 * 跳页只在页数较多时出现，且用 GET 提交到 $base，无 JS 也可用。
 */

if ($pages <= 1) {
    return;
}

$base = $base ?? ($_SERVER['PHP_SELF'] ?? '');
$page = max(1, (int) $page);
$pages = max(1, (int) $pages);
$window = 3;
$start = max(1, $page - $window);
$end = min($pages, $page + $window);
$join = (strpos($base, '?') !== false ? '&' : '?');
$pageUrl = static fn (int $target): string => $base . $join . 'page=' . $target;

$total = isset($total) && is_numeric($total) ? (int) $total : null;
$perPage = isset($per_page) && is_numeric($per_page) ? (int) $per_page : 0;
$showJump = $pages > 7;
?>
<nav class="pagination-bar" aria-label="<?= htmlspecialchars(__('app.pagination.label')) ?>">
  <ul class="pagination-list">
    <?php if ($page > 1): ?>
      <li><a href="<?= htmlspecialchars($pageUrl($page - 1)) ?>" class="pg prev"
             aria-label="<?= htmlspecialchars(__('app.pagination.previous')) ?>"
             title="<?= htmlspecialchars(__('app.pagination.previous')) ?>">«</a></li>
    <?php else: ?>
      <li><span class="pg prev disabled" aria-disabled="true">«</span></li>
    <?php endif; ?>

    <?php if ($start > 1): ?>
      <li><a class="pg<?= $page === 1 ? ' active' : '' ?>" href="<?= htmlspecialchars($pageUrl(1)) ?>"
             <?= $page === 1 ? 'aria-current="page"' : '' ?>>1</a></li>
      <?php if ($start > 2): ?><li><span class="pagination-gap" aria-hidden="true">…</span></li><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
      <li><a class="pg<?= $i === $page ? ' active' : '' ?>" href="<?= htmlspecialchars($pageUrl($i)) ?>"
             aria-label="<?= htmlspecialchars(__('app.pagination.page', ['page' => $i])) ?>"
             <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a></li>
    <?php endfor; ?>

    <?php if ($end < $pages): ?>
      <?php if ($end < $pages - 1): ?><li><span class="pagination-gap" aria-hidden="true">…</span></li><?php endif; ?>
      <li><a class="pg<?= $page === $pages ? ' active' : '' ?>" href="<?= htmlspecialchars($pageUrl($pages)) ?>"
             <?= $page === $pages ? 'aria-current="page"' : '' ?>><?= $pages ?></a></li>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
      <li><a href="<?= htmlspecialchars($pageUrl($page + 1)) ?>" class="pg next"
             aria-label="<?= htmlspecialchars(__('app.pagination.next')) ?>"
             title="<?= htmlspecialchars(__('app.pagination.next')) ?>">»</a></li>
    <?php else: ?>
      <li><span class="pg next disabled" aria-disabled="true">»</span></li>
    <?php endif; ?>
  </ul>

  <?php if ($total !== null): ?>
    <div class="pagination-meta muted small">
      <?php if ($perPage > 0): ?>
        <?= htmlspecialchars(__('app.pagination.range', [
            'from' => ($page - 1) * $perPage + 1,
            'to' => min($page * $perPage, $total),
            'total' => $total,
        ])) ?>
      <?php else: ?>
        <?= htmlspecialchars(__('app.pagination.total', ['total' => $total])) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($showJump): ?>
    <form class="pagination-jump" method="get" action="<?= htmlspecialchars(strtok($base, '?') ?: $base) ?>">
      <?php
        // 跳到某一页也要带上当前筛选与区：直接复用 $base 的查询串
        parse_str((string) parse_url($base, PHP_URL_QUERY), $jumpQuery);
        unset($jumpQuery['page']);
        foreach ($jumpQuery as $jumpKey => $jumpValue) {
            if (is_array($jumpValue)) {
                continue;
            }
            echo '<input type="hidden" name="' . htmlspecialchars((string) $jumpKey) . '" value="' . htmlspecialchars((string) $jumpValue) . '">';
        }
      ?>
      <label class="muted small" for="paginationJump"><?= htmlspecialchars(__('app.pagination.jump_label')) ?></label>
      <input type="number" id="paginationJump" name="page" min="1" max="<?= $pages ?>" value="<?= $page ?>">
      <button type="submit" class="btn btn-sm outline"><?= htmlspecialchars(__('app.pagination.jump_submit')) ?></button>
    </form>
  <?php endif; ?>
</nav>
