<?php
/**
 * File: resources/views/setup/layout.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

use Acme\Panel\Support\ModuleAssets;

$__layoutHead = is_array($__layoutHead ?? null) ? $__layoutHead : [];
$__setupLayout = is_array($__setupLayout ?? null) ? $__setupLayout : [];
$localeCode = (string)($__setupLayout['localeCode'] ?? str_replace('_', '-', \Acme\Panel\Core\Lang::locale()));
$layoutPage = is_array($__setupLayout['page'] ?? null) ? $__setupLayout['page'] : [];
$currentStep = (int)($layoutPage['current_step'] ?? ($__setupLayout['currentStep'] ?? 1));
$steps = is_array($layoutPage['steps'] ?? null) ? $layoutPage['steps'] : [];
$warningBannerClass = 'setup-alert-banner';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($localeCode) ?>">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars((string)($__layoutHead['title'] ?? __('app.setup.layout.page_title'))) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if((string)($__layoutHead['description'] ?? '') !== ''): ?>
<meta name="description" content="<?= htmlspecialchars((string)$__layoutHead['description'], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if((string)($__layoutHead['keywords'] ?? '') !== ''): ?>
<meta name="keywords" content="<?= htmlspecialchars((string)$__layoutHead['keywords'], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if((string)($__layoutHead['canonical'] ?? '') !== ''): ?>
<link rel="canonical" href="<?= htmlspecialchars((string)$__layoutHead['canonical'], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= ModuleAssets::assetPath('css/app-core.css') ?>">
<link rel="stylesheet" href="<?= ModuleAssets::assetPath('css/setup.css') ?>">
</head>
<body class="setup-body">
<?php include dirname(__DIR__) . '/components/warning_banner.php'; ?>
  <div class="setup-shell">
    <header class="setup-header">
      <div>
        <h1 class="setup-header__title"><?= htmlspecialchars((string)($layoutPage['page_title'] ?? __('app.setup.layout.page_title'))) ?></h1>
        <p class="setup-body-copy"><?= htmlspecialchars((string)($layoutPage['intro'] ?? __('app.setup.layout.intro'))) ?></p>
      </div>
      <nav class="setup-stepper" aria-label="<?= htmlspecialchars((string)($layoutPage['stepper_label'] ?? __('app.setup.layout.stepper_label'))) ?>">
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
  <script src="<?= ModuleAssets::assetPath('js/setup.js') ?>"></script>
</body>
</html>
