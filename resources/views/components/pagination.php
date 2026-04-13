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
