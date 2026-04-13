<?php
/**
 * File: resources/views/auth/login.php
 * Purpose: Provides functionality for the resources/views/auth module.
 */
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<?php if(!empty($error) && function_exists('flash_add')) { flash_add('error',$error); } ?>
<form method="post" class="login-form">
  <div class="login-form__fields">
    <label class="login-form__field"><?= htmlspecialchars(__('app.auth.username')) ?><input type="text" name="username" required></label>
    <label class="login-form__field"><?= htmlspecialchars(__('app.auth.password')) ?><input type="password" name="password" required></label>
    <button class="btn" type="submit"><?= htmlspecialchars(__('app.auth.submit')) ?></button>
  </div>
</form>
