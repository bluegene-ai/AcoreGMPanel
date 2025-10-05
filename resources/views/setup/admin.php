<?php
/**
 * File: resources/views/setup/admin.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

 ob_start(); $a=$admin; ?>
<h3><?= htmlspecialchars(__('app.setup.admin.step_title', ['current' => 4, 'total' => 5])) ?></h3>
<form id="admin-form">
  <?= Acme\Panel\Support\Csrf::field() ?>
  <input type="hidden" name="action" value="admin_save">
  <div class="field">
    <label>
      <?= htmlspecialchars(__('app.setup.admin.fields.username')) ?>
      <input name="admin_user" value="<?= htmlspecialchars($a['username'] ?? 'admin') ?>" required>
    </label>
  </div>
  <div class="field">
    <label>
      <?= htmlspecialchars(__('app.setup.admin.fields.password')) ?>
      <input type="password" name="admin_pass" required>
    </label>
  </div>
  <div class="field">
    <label>
      <?= htmlspecialchars(__('app.setup.admin.fields.password_confirm')) ?>
      <input type="password" name="admin_pass2" required>
    </label>
  </div>
  <div class="setup-actions">
    <button class="btn primary" type="submit"><?= htmlspecialchars(__('app.setup.admin.submit')) ?></button>
    <a class="btn" href="<?= url('/setup?step=3') ?>"><?= htmlspecialchars(__('app.setup.admin.back')) ?></a>
  </div>
</form>
<script>
const f=document.getElementById('admin-form');
const fallbackMessage='<?= addslashes(__('app.setup.admin.save_failed')) ?>';
f.addEventListener('submit',e=>{ e.preventDefault(); const fd=new FormData(f); fetch('<?= url('/setup/post') ?>',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{ if(j.success){ location.href=j.redirect; } else alert(j.message || fallbackMessage); }).catch(()=>alert(fallbackMessage)); });
</script>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php'; ?>
