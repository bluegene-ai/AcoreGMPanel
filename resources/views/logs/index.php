<?php
/**
 * File: resources/views/logs/index.php
 * Purpose: Provides functionality for the resources/views/logs module.
 */

 $module='logs'; include __DIR__.'/../layouts/base_top.php'; ?>
<?php
  $defaultModule = $defaults['module'] ?? array_key_first($modules);
  $defaultModule = $defaultModule && isset($modules[$defaultModule]) ? $defaultModule : array_key_first($modules);
  $defaultTypes = $defaultModule ? ($modules[$defaultModule]['types'] ?? []) : [];
  $defaultType = $defaults['type'] ?? array_key_first($defaultTypes);
  if(!$defaultType && $defaultTypes){ $defaultType = array_key_first($defaultTypes); }
  $defaultLimit = $defaults['limit'] ?? 200;
?>
<h1 class="page-title"><?= htmlspecialchars(__('app.logs.page_title')) ?></h1>
<p class="muted" style="margin-top:-8px;margin-bottom:18px"><?= htmlspecialchars(__('app.logs.intro')) ?></p>
<form id="logsForm" class="logs-form">
  <label class="logs-field"><?= htmlspecialchars(__('app.logs.fields.module')) ?>
    <select name="module" id="logsModuleSelect">
      <?php foreach($modules as $id => $meta): ?>
        <option value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" <?= $id === $defaultModule ? 'selected' : '' ?>>
          <?= htmlspecialchars($meta['label'] ?? $id, ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="logs-field"><?= htmlspecialchars(__('app.logs.fields.type')) ?>
    <select name="type" id="logsTypeSelect">
      <?php foreach($defaultTypes as $typeId => $meta): ?>
        <option value="<?= htmlspecialchars($typeId, ENT_QUOTES, 'UTF-8') ?>" <?= $typeId === $defaultType ? 'selected' : '' ?>>
          <?= htmlspecialchars($meta['label'] ?? $typeId, ENT_QUOTES, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="logs-field logs-field--compact"><?= htmlspecialchars(__('app.logs.fields.limit')) ?>
    <input type="number" name="limit" id="logsLimitInput" value="<?= (int)$defaultLimit ?>" min="1" max="<?= (int)($defaults['max_limit'] ?? 500) ?>">
  </label>
  <div class="logs-actions">
    <button type="button" class="btn" id="btn-load-logs"><?= htmlspecialchars(__('app.logs.actions.load')) ?></button>
    <button type="button" class="btn outline" id="btn-auto-toggle" data-on="0"><?= htmlspecialchars(__('app.logs.actions.auto_refresh')) ?></button>
  </div>
</form>
<div class="logs-summary" id="logsSummaryBox"></div>
<div class="logs-output">
  <div class="logs-table-wrap">
    <table class="logs-table">
      <thead>
        <tr>
          <th style="width:160px"><?= htmlspecialchars(__('app.logs.table.headers.time')) ?></th>
          <th style="width:90px"><?= htmlspecialchars(__('app.logs.table.headers.server')) ?></th>
          <th style="width:120px"><?= htmlspecialchars(__('app.logs.table.headers.actor')) ?></th>
          <th><?= htmlspecialchars(__('app.logs.table.headers.summary')) ?></th>
        </tr>
      </thead>
      <tbody id="logsTableBody">
        <tr><td colspan="4" class="muted text-center"><?= htmlspecialchars(__('app.logs.table.loading')) ?></td></tr>
      </tbody>
    </table>
  </div>
  <details class="logs-raw" open>
    <summary><?= htmlspecialchars(__('app.logs.raw.title')) ?></summary>
    <pre id="logsOutput" class="logs-raw__box"><?= htmlspecialchars(__('app.logs.raw.empty')) ?></pre>
  </details>
</div>
<script>
window.LOGS_DATA = <?= json_encode([
  'modules' => $modules,
  'defaults' => $defaults,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>

