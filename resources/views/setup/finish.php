<?php
/**
 * File: resources/views/setup/finish.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

 ob_start(); ?>
<h3><?= htmlspecialchars(__('app.setup.finish.step_title', ['current' => 5, 'total' => 5])) ?></h3>
<?php if($success): ?>
  <div class="alert success"><?= htmlspecialchars(__('app.setup.finish.success')) ?></div>
  <a class="btn primary" href="<?= url('/') ?>"><?= htmlspecialchars(__('app.setup.finish.enter_panel')) ?></a>
<?php else: ?>
  <div class="alert error"><?= htmlspecialchars(__('app.setup.finish.failure', ['errors' => implode('; ', $errors)])) ?></div>
  <a class="btn" href="<?= url('/setup?step=4') ?>"><?= htmlspecialchars(__('app.setup.finish.back')) ?></a>
<?php endif; ?>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php'; ?>
