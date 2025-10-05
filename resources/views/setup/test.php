<?php
/**
 * File: resources/views/setup/test.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

 ob_start(); ?>
<h3><?= htmlspecialchars(__('app.setup.test.title', ['current' => 3, 'total' => 5])) ?></h3>
<div class="card">
  <?php foreach($results as $r): ?>
    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #21262d;padding:6px 4px;">
      <span><?= htmlspecialchars($r['name']) ?></span>
  <span class="badge <?= $r['ok']?'ok':'fail' ?>" title="<?= htmlspecialchars($r['msg']) ?>"><?= htmlspecialchars($r['ok'] ? __('app.setup.status.ok') : __('app.setup.status.fail')) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php if($allOk): ?>
  <div class="alert success"><?= htmlspecialchars(__('app.setup.test.success')) ?></div>
  <a class="btn primary" href="<?= url('/setup?step=4') ?>"><?= htmlspecialchars(__('app.setup.test.next_admin')) ?></a>
<?php else: ?>
  <div class="alert error"><?= htmlspecialchars(__('app.setup.test.failure')) ?></div>
  <a class="btn" href="<?= url('/setup?step=2') ?>"><?= htmlspecialchars(__('app.setup.test.back')) ?></a>
<?php endif; ?>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php'; ?>
