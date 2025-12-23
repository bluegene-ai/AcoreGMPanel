<?php
/**
 * File: resources/views/layouts/base_top.php
 * Purpose: Provides functionality for the resources/views/layouts module.
 */

  $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
  $currentPath = parse_url($rawUri, PHP_URL_PATH) ?: '/';
  $basePathSetting = \Acme\Panel\Core\Config::get('app.base_path') ?? '';
  $basePathTrimmed = rtrim($basePathSetting, '/');
  if($basePathTrimmed !== '' && str_starts_with($currentPath, $basePathTrimmed)){
    $trimmedPath = substr($currentPath, strlen($basePathTrimmed));
    $currentPath = ($trimmedPath === false || $trimmedPath === '') ? '/' : $trimmedPath;
  }
  if($currentPath === '') $currentPath = '/';
  $navActive = function(string ...$prefixes) use ($currentPath){
    foreach($prefixes as $prefix){
      if($prefix === '/' && $currentPath === '/') return 'active';
      if($prefix !== '/' && str_starts_with($currentPath, $prefix)) return 'active';
    }
    return '';
  };
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(str_replace('_', '-', \Acme\Panel\Core\Lang::locale()), ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<title><?= isset($title) ? htmlspecialchars($title) : htmlspecialchars(__(
  'app.app.title_suffix'
)) ?></title>
<script>window.APP_BASE='<?= addslashes(\Acme\Panel\Core\Config::get('app.base_path')??'') ?>';</script>
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if(function_exists('asset')): ?>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php else: ?>
<link rel="stylesheet" href="/assets/css/app.css">
<?php endif; ?>
<?php

?>
</head>
<body <?= isset($module)?'data-module="'.htmlspecialchars($module).'"':'' ?>>
<?php if(!empty($_SESSION['flashes']['warn'])): ?>
<div class="alert alert-warning" style="margin:0;border-radius:0;background:#fff3cd;color:#664d03;padding:10px 16px;font-size:14px;border-bottom:1px solid #ffeeba;">
  <?php foreach($_SESSION['flashes']['warn'] as $w): ?>
    <div><?= htmlspecialchars($w,ENT_QUOTES,'UTF-8') ?></div>
  <?php endforeach; unset($_SESSION['flashes']['warn']); ?>
</div>
<?php endif; ?>
<div class="layout-grid">
<aside class="sidebar">
  <h2><?= htmlspecialchars(__('app.app.name')) ?></h2>
  <ul>
    <li><a href="<?= url('/') ?>" class="<?= $navActive('/') ?>"><?= htmlspecialchars(__('app.nav.home')) ?></a></li>
    <li><a href="<?= url('/account') ?>" class="<?= $navActive('/account') ?>"><?= htmlspecialchars(__('app.nav.account')) ?></a></li>
    <li><a href="<?= url('/character') ?>" class="<?= $navActive('/character') ?>"><?= htmlspecialchars(__('app.nav.character')) ?></a></li>
  <?php $itemActive = str_starts_with($currentPath, '/item-ownership') ? '' : $navActive('/item'); ?>
    <li><a href="<?= url('/item') ?>" class="<?= $itemActive ?>"><?= htmlspecialchars(__('app.nav.item')) ?></a></li>
    <li><a href="<?= url('/creature') ?>" class="<?= $navActive('/creature') ?>"><?= htmlspecialchars(__('app.nav.creature')) ?></a></li>
    <li><a href="<?= url('/quest') ?>" class="<?= $navActive('/quest') ?>"><?= htmlspecialchars(__('app.nav.quest')) ?></a></li>
    <li><a href="<?= url('/mail') ?>" class="<?= $navActive('/mail') ?>"><?= htmlspecialchars(__('app.nav.mail')) ?></a></li>
    <li><a href="<?= url('/mass-mail') ?>" class="<?= $navActive('/mass-mail') ?>"><?= htmlspecialchars(__('app.nav.mass_mail')) ?></a></li>
    <li><a href="<?= url('/bag') ?>" class="<?= $navActive('/bag','/bag-query') ?>"><?= htmlspecialchars(__('app.nav.bag')) ?></a></li>
    <li><a href="<?= url('/item-ownership') ?>" class="<?= $navActive('/item-ownership') ?>"><?= htmlspecialchars(__('app.nav.item_owner')) ?></a></li>
    <li><a href="<?= url('/soap') ?>" class="<?= $navActive('/soap') ?>"><?= htmlspecialchars(__('app.nav.soap')) ?></a></li>
    <li><a href="<?= url('/smart-ai') ?>" class="<?= $navActive('/smart-ai') ?>"><?= htmlspecialchars(__('app.nav.smart_ai')) ?></a></li>
    <li><a href="<?= url('/logs') ?>" class="<?= $navActive('/logs') ?>"><?= htmlspecialchars(__('app.nav.logs')) ?></a></li>
  </ul>
  <div class="sidebar-metrics" id="sidebar-metrics">
    <strong><?= htmlspecialchars(__('app.common.performance')) ?></strong>
    <span><?= htmlspecialchars(__('app.common.loading')) ?></span>
  </div>
</aside>
<main class="container">
  <?php include dirname(__DIR__).'/components/flash.php'; ?>
  <div class="flex between center" style="margin-bottom:12px">
    <div></div>
    <div class="flex center" style="gap:12px;flex-wrap:wrap">
      <?php $current_server = $current_server ?? (\Acme\Panel\Support\ServerContext::currentId()); $servers = \Acme\Panel\Support\ServerList::options(); include dirname(__DIR__).'/partials/server_switch.php'; ?>
      <?php
        $availableLocales = \Acme\Panel\Core\Lang::available();
        $activeLocale = \Acme\Panel\Core\Lang::locale();
      ?>
      <?php if (count($availableLocales) > 1): ?>
        <div class="language-switch" style="display:inline-flex;align-items:center;gap:6px">
          <label for="panelLanguageSelect" style="font-size:13px;color:#8ea2b2;margin-right:4px"><?= htmlspecialchars(__('app.common.language')) ?>:</label>
          <select id="panelLanguageSelect" style="min-width:130px;padding:4px 6px;">
            <?php foreach ($availableLocales as $localeCode): ?>
              <option value="<?= htmlspecialchars($localeCode) ?>" <?= $localeCode === $activeLocale ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.common.languages.' . $localeCode, [], $localeCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <?php
        $online = \Acme\Panel\Support\ServerStats::onlineCount($current_server);
        $totalChars = \Acme\Panel\Support\ServerStats::totalCharacters($current_server);
        $labelOnline = $online===null? '?' : $online;
        $labelTotal = $totalChars===null? '?' : $totalChars;
      ?>
      <span class="badge" style="background:#1e2d3a;color:#9ecbff;padding:4px 10px;border-radius:4px;font-size:12px;display:inline-flex;align-items:center;gap:10px" title="<?= htmlspecialchars(__('app.common.online_total_title')) ?>">
        <span style="display:inline-flex;align-items:center;gap:4px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#58a6ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <strong><?= htmlspecialchars($labelOnline) ?></strong>/<span><?= htmlspecialchars($labelTotal) ?></span>
        </span>
        <span style="font-size:11px;color:#6ea8ff"><?= htmlspecialchars(__('app.common.online_total_label')) ?></span>
      </span>
    </div>
  </div>

