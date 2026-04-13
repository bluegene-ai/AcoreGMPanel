<?php
/**
 * File: resources/views/layouts/base_top.php
 * Purpose: Provides functionality for the resources/views/layouts module.
 */

use Acme\Panel\Support\ModuleAssets;

  $__layoutHead = is_array($__layoutHead ?? null) ? $__layoutHead : [];
  $__layoutCurrentPath = is_string($__layoutCurrentPath ?? null) ? $__layoutCurrentPath : '/';
  $__layoutBodyAttributes = is_array($__layoutBodyAttributes ?? null) ? $__layoutBodyAttributes : [];
  $__layoutStyleAssetUrls = is_array($__layoutStyleAssetUrls ?? null) ? $__layoutStyleAssetUrls : [];
  $__layoutNavigationItems = is_array($__layoutNavigationItems ?? null) ? $__layoutNavigationItems : ModuleAssets::navigationItems();
  $__layoutServerSwitch = is_array($__layoutServerSwitch ?? null) ? $__layoutServerSwitch : ['current_server' => 0, 'servers' => []];
  $__layoutLocales = is_array($__layoutLocales ?? null) ? $__layoutLocales : ['available' => [], 'active' => ''];
  $__layoutServerStats = is_array($__layoutServerStats ?? null) ? $__layoutServerStats : ['online_label' => '?', 'total_label' => '?'];

  $__bodyAttributes = [];
  foreach ($__layoutBodyAttributes as $__attributeName => $__attributeValue) {
    if (!is_string($__attributeName) || $__attributeName === '') {
      continue;
    }

    $__bodyAttributes[] = $__attributeName . '="' . htmlspecialchars((string) $__attributeValue, ENT_QUOTES, 'UTF-8') . '"';
  }
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(str_replace('_', '-', \Acme\Panel\Core\Lang::locale()), ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars((string)($__layoutHead['title'] ?? __('app.app.title_suffix'))) ?></title>
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
<?php foreach($__layoutStyleAssetUrls as $__styleAssetUrl): ?>
<link rel="stylesheet" href="<?= $__styleAssetUrl ?>">
<?php endforeach; ?>
</head>
<body<?= $__bodyAttributes ? ' ' . implode(' ', $__bodyAttributes) : '' ?>>
<div class="layout-shell-bg">
<?php $warningBannerClass = 'layout-warning-banner'; ?>
<?php include dirname(__DIR__) . '/components/warning_banner.php'; ?>
<div class="layout-grid layout-grid--shell">
<aside class="sidebar sidebar--shell">
  <div class="sidebar__brand">
    <h2><?= htmlspecialchars(__('app.app.name')) ?></h2>
    <p class="sidebar__intro"><?= htmlspecialchars(__('app.app.title_suffix')) ?></p>
  </div>
  <ul>
    <?php foreach($__layoutNavigationItems as $__navItem): ?>
      <?php
        $__layoutIsAuthenticated = \Acme\Panel\Support\Auth::check();
        $__navCapability = $__navItem['capability'] ?? null;
        if ($__layoutIsAuthenticated && is_string($__navCapability) && $__navCapability !== '' && !$__can($__navCapability)) {
          continue;
        }
        if ($__layoutIsAuthenticated && is_array($__navCapability) && $__navCapability !== [] && !$__canAny($__navCapability)) {
          continue;
        }
        $__navPath = (string)($__navItem['path'] ?? '/');
        $__navPrefixes = $__navItem['activePrefixes'] ?? [$__navPath];
        $__navClass = ModuleAssets::pathMatches($__layoutCurrentPath, is_array($__navPrefixes) ? $__navPrefixes : [$__navPath])
          ? 'active'
          : '';
      ?>
    <li><a href="<?= url($__navPath) ?>" class="<?= $__navClass ?>"><?= htmlspecialchars(__(($__navItem['label'] ?? 'app.nav.home'))) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-metrics" id="sidebar-metrics">
    <strong><?= htmlspecialchars(__('app.common.performance')) ?></strong>
    <span><?= htmlspecialchars(__('app.common.loading')) ?></span>
  </div>
</aside>
<main class="container app-shell-main">
  <div class="app-shell-panel">
  <?php include dirname(__DIR__).'/components/flash.php'; ?>
  <div class="layout-topbar app-shell-toolbar">
    <div class="layout-topbar__spacer"></div>
    <div class="layout-topbar__actions">
      <?php $current_server = $__layoutServerSwitch['current_server'] ?? 0; $servers = $__layoutServerSwitch['servers'] ?? []; include dirname(__DIR__).'/partials/server_switch.php'; ?>
      <?php if (count($__layoutLocales['available'] ?? []) > 1): ?>
        <div class="language-switch">
          <label for="panelLanguageSelect" class="language-switch__label"><?= htmlspecialchars(__('app.common.language')) ?>:</label>
          <select id="panelLanguageSelect" class="language-switch__select">
            <?php foreach (($__layoutLocales['available'] ?? []) as $localeCode): ?>
              <option value="<?= htmlspecialchars($localeCode) ?>" <?= $localeCode === ($__layoutLocales['active'] ?? '') ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.common.languages.' . $localeCode, [], $localeCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <span class="badge header-online-badge" title="<?= htmlspecialchars(__('app.common.online_total_title')) ?>">
        <span class="header-online-badge__count">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#58a6ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <strong><?= htmlspecialchars((string)($__layoutServerStats['online_label'] ?? '?')) ?></strong>/<span><?= htmlspecialchars((string)($__layoutServerStats['total_label'] ?? '?')) ?></span>
        </span>
        <span class="header-online-badge__label"><?= htmlspecialchars(__('app.common.online_total_label')) ?></span>
      </span>
    </div>
  </div>

