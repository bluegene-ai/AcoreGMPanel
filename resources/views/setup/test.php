<?php
/**
 * File: resources/views/setup/test.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

$page = is_array($setupPage ?? null) ? $setupPage : [];
$messages = is_array($page['messages'] ?? null) ? $page['messages'] : [];
$actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
$status = is_array($page['status'] ?? null) ? $page['status'] : [];
$authResult = is_array($authResult ?? null) ? $authResult : null;
$realmGroups = is_array($realmGroups ?? null) ? $realmGroups : [];
?>
<h3><?= htmlspecialchars((string)($page['heading'] ?? __('app.setup.test.title', ['current' => 3, 'total' => 5]))) ?></h3>
<?php if ($authResult): ?>
  <div class="setup-test-card">
    <div class="setup-test-row">
      <span><?= htmlspecialchars((string)($authResult['label'] ?? $authResult['name'] ?? 'Auth DB')) ?></span>
      <span class="badge <?= !empty($authResult['ok']) ? 'ok' : 'fail' ?>" title="<?= htmlspecialchars((string)($authResult['msg'] ?? '')) ?>"><?= htmlspecialchars(!empty($authResult['ok']) ? ($status['ok'] ?? __('app.setup.status.ok')) : ($status['fail'] ?? __('app.setup.status.fail'))) ?></span>
    </div>
  </div>
<?php endif; ?>
<?php foreach ($realmGroups as $group): ?>
  <?php
    $groupLabel = (string)($group['label'] ?? '');
    $groupResults = is_array($group['results'] ?? null) ? $group['results'] : [];
    $soapWarning = trim((string)($group['soap_warning'] ?? ''));
  ?>
  <section class="setup-test-card setup-test-card--group">
    <div class="setup-section__header setup-section__header--compact">
      <div>
        <h4 class="setup-test-group__title"><?= htmlspecialchars($groupLabel) ?></h4>
      </div>
      <span class="badge <?= !empty($group['ok']) ? 'ok' : 'fail' ?>"><?= htmlspecialchars(!empty($group['ok']) ? ($messages['group_ok'] ?? __('app.setup.test.group_ok')) : ($messages['group_fail'] ?? __('app.setup.test.group_fail'))) ?></span>
    </div>
    <?php foreach ($groupResults as $result): ?>
      <div class="setup-test-row">
        <span><?= htmlspecialchars((string)($result['label'] ?? $result['name'] ?? '')) ?></span>
        <span class="badge <?= !empty($result['ok']) ? 'ok' : 'fail' ?>" title="<?= htmlspecialchars((string)($result['msg'] ?? '')) ?>"><?= htmlspecialchars(!empty($result['ok']) ? ($status['ok'] ?? __('app.setup.status.ok')) : ($status['fail'] ?? __('app.setup.status.fail'))) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if ($soapWarning !== ''): ?>
      <div class="alert warn setup-test-warning"><?= htmlspecialchars($soapWarning) ?></div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php if($allOk): ?>
  <div class="alert success"><?= htmlspecialchars((string)($messages['success'] ?? __('app.setup.test.success'))) ?></div>
  <a class="btn primary" href="<?= url('/setup?step=4') ?>"><?= htmlspecialchars((string)($actions['next'] ?? __('app.setup.test.next_admin'))) ?></a>
<?php else: ?>
  <div class="alert error"><?= htmlspecialchars((string)($messages['failure'] ?? __('app.setup.test.failure'))) ?></div>
  <a class="btn" href="<?= url('/setup?step=2') ?>"><?= htmlspecialchars((string)($actions['back'] ?? __('app.setup.test.back'))) ?></a>
<?php endif; ?>
