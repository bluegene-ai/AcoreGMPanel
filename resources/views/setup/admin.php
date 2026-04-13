<?php
/**
 * File: resources/views/setup/admin.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

$a=$admin;
$page = is_array($setupPage ?? null) ? $setupPage : [];
$fields = is_array($page['fields'] ?? null) ? $page['fields'] : [];
$actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
?>
<h3><?= htmlspecialchars((string)($page['heading'] ?? __('app.setup.admin.step_title', ['current' => 4, 'total' => 5]))) ?></h3>
<form id="admin-form" method="post" action="<?= url('/setup/post') ?>" data-submit-fail="<?= htmlspecialchars((string)($page['submit_fail'] ?? __('app.setup.admin.save_failed'))) ?>">
  <?= Acme\Panel\Support\Csrf::field() ?>
  <input type="hidden" name="action" value="admin_save">
  <div class="field">
    <label>
      <?= htmlspecialchars((string)($fields['username'] ?? __('app.setup.admin.fields.username'))) ?>
      <input name="admin_user" value="<?= htmlspecialchars($a['username'] ?? 'admin') ?>" required>
    </label>
  </div>
  <div class="field">
    <label>
      <?= htmlspecialchars((string)($fields['password'] ?? __('app.setup.admin.fields.password'))) ?>
      <input type="password" name="admin_pass" required>
    </label>
  </div>
  <div class="field">
    <label>
      <?= htmlspecialchars((string)($fields['password_confirm'] ?? __('app.setup.admin.fields.password_confirm'))) ?>
      <input type="password" name="admin_pass2" required>
    </label>
  </div>
  <div class="setup-actions">
    <button class="btn primary" type="submit"><?= htmlspecialchars((string)($actions['submit'] ?? __('app.setup.admin.submit'))) ?></button>
    <a class="btn" href="<?= url('/setup?step=3') ?>"><?= htmlspecialchars((string)($actions['back'] ?? __('app.setup.admin.back'))) ?></a>
  </div>
</form>
