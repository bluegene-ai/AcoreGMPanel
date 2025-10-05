<?php
/**
 * File: resources/views/auth/login.php
 * Purpose: Provides functionality for the resources/views/auth module.
 */

 include __DIR__.'/../layouts/base_top.php'; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.auth.page_title')) ?></h1>
<?php if(!empty($error) && function_exists('flash_add')) { flash_add('error',$error); } ?>
<form method="post" class="login-form" style="max-width:360px">
  <div style="display:flex;flex-direction:column;gap:14px;">
    <label><?= htmlspecialchars(__('app.auth.username')) ?><input type="text" name="username" required></label>
    <label><?= htmlspecialchars(__('app.auth.password')) ?><input type="password" name="password" required></label>
    <button class="btn" type="submit"><?= htmlspecialchars(__('app.auth.submit')) ?></button>
  </div>
</form>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
