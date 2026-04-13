<?php
/**
 * File: resources/views/setup/finish.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

$page = is_array($setupPage ?? null) ? $setupPage : [];
$messages = is_array($page['messages'] ?? null) ? $page['messages'] : [];
$actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
?>
<h3><?= htmlspecialchars((string)($page['heading'] ?? __('app.setup.finish.step_title', ['current' => 5, 'total' => 5]))) ?></h3>
<?php if($success): ?>
  <div class="alert success"><?= htmlspecialchars((string)($messages['success'] ?? __('app.setup.finish.success'))) ?></div>
  <a class="btn primary" href="<?= url('/') ?>"><?= htmlspecialchars((string)($actions['enter_panel'] ?? __('app.setup.finish.enter_panel'))) ?></a>
<?php else: ?>
  <div class="alert error"><?= htmlspecialchars(str_replace(':errors', implode('; ', $errors), (string)($messages['failure'] ?? __('app.setup.finish.failure', ['errors' => implode('; ', $errors)])))) ?></div>
  <a class="btn" href="<?= url('/setup?step=4') ?>"><?= htmlspecialchars((string)($actions['back'] ?? __('app.setup.finish.back'))) ?></a>
<?php endif; ?>
