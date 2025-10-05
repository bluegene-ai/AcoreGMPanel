<?php
/**
 * File: resources/views/components/pagination.php
 * Purpose: Provides functionality for the resources/views/components module.
 */


if($pages<=1) return; $base = $base ?? ($_SERVER['PHP_SELF'] ?? '');
$window = 3; $start=max(1,$page-$window); $end=min($pages,$page+$window);
?>
<nav class="pagination-bar">
  <ul class="pagination-list">
  <?php $join = (strpos($base,'?')!==false?'&':'?'); ?>
  <?php if($page>1): ?><li><a href="<?= htmlspecialchars($base) . $join ?>page=<?= $page-1 ?>" class="pg prev" aria-label="<?= htmlspecialchars(__('app.pagination.previous')) ?>" title="<?= htmlspecialchars(__('app.pagination.previous')) ?>">«</a></li><?php endif; ?>
    <?php for($i=$start;$i<=$end;$i++): ?>
  <li><a class="pg <?= $i===$page?'active':'' ?>" href="<?= htmlspecialchars($base) . $join ?>page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  <?php if($page<$pages): ?><li><a href="<?= htmlspecialchars($base) . $join ?>page=<?= $page+1 ?>" class="pg next" aria-label="<?= htmlspecialchars(__('app.pagination.next')) ?>" title="<?= htmlspecialchars(__('app.pagination.next')) ?>">»</a></li><?php endif; ?>
  </ul>
</nav>
<style>.pagination-bar{margin:18px 0}.pagination-list{list-style:none;margin:0;padding:0;display:flex;gap:6px;flex-wrap:wrap}.pagination-list .pg{display:block;padding:6px 10px;background:#1b242c;border:1px solid #2a3742;border-radius:4px;font-size:13px;color:#d0d8de;text-decoration:none}.pagination-list .pg.active,.pagination-list .pg:hover{background:var(--c-accent);color:#0e1920;border-color:var(--c-accent)}</style>
