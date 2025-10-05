<?php
/**
 * File: resources/views/setup/layout.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

  ?>
<?php
$localeCode = isset($currentLocale) && $currentLocale !== '' ? str_replace('_','-', $currentLocale) : str_replace('_','-', \Acme\Panel\Core\Lang::locale());
$steps = [];
for($i=1;$i<=5;$i++){
  $steps[$i] = __('app.setup.layout.step_titles.' . $i, [], 'Step ' . $i);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($localeCode) ?>">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(__('app.setup.layout.page_title')) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if(function_exists('asset')): ?>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/setup.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/setup.css">
<?php endif; ?>
</head>
<?php $currentStep = (int)($_GET['step'] ?? 1); ?>
<body class="setup-body">
<?php if(!empty($_SESSION['flashes']['warn'])): ?>
  <div class="alert warn" style="width:100%;max-width:1100px;margin-bottom:16px;">
    <?php foreach($_SESSION['flashes']['warn'] as $w): ?>
      <div><?= htmlspecialchars($w,ENT_QUOTES,'UTF-8') ?></div>
    <?php endforeach; unset($_SESSION['flashes']['warn']); ?>
  </div>
<?php endif; ?>
  <div class="setup-shell">
<script>window.APP_BASE='<?= addslashes(\Acme\Panel\Core\Config::get('app.base_path')??'') ?>';</script>
    <header class="setup-header">
      <div>
        <h1 class="setup-header__title"><?= htmlspecialchars(__('app.setup.layout.page_title')) ?></h1>
        <p class="setup-body-copy"><?= htmlspecialchars(__('app.setup.layout.intro')) ?></p>
      </div>
      <nav class="setup-stepper" aria-label="<?= htmlspecialchars(__('app.setup.layout.stepper_label')) ?>">
        <?php foreach($steps as $stepIndex => $label): ?>
          <span class="setup-stepper__item <?= $stepIndex === $currentStep ? 'active' : '' ?>">
            <span class="setup-stepper__dot"><?= $stepIndex ?></span>
            <span><?= htmlspecialchars($label) ?></span>
          </span>
        <?php endforeach; ?>
      </nav>
    </header>
    <?= $content ?>
  </div>
</body>
</html>
