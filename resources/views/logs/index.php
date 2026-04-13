<?php
/**
 * File: resources/views/logs/index.php
 * Purpose: Provides functionality for the resources/views/logs module.
 */

?>
<?php
  $defaultModule = $defaults['module'] ?? array_key_first($modules);
  $defaultModule = $defaultModule && isset($modules[$defaultModule]) ? $defaultModule : array_key_first($modules);
  $defaultTypes = $defaultModule ? ($modules[$defaultModule]['types'] ?? []) : [];
  $defaultType = $defaults['type'] ?? array_key_first($defaultTypes);
  if(!$defaultType && $defaultTypes){ $defaultType = array_key_first($defaultTypes); }
  $defaultLimit = $defaults['limit'] ?? 200;
    $logsCapabilities = $__pageCapabilities ?? [
      'catalog' => $__can('logs.catalog'),
      'read' => $__can('logs.read'),
    ];
    $__pageCapabilities = $logsCapabilities;
    $capabilityNotice = $logsCapabilities['read'] ? null : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
  <?php include __DIR__.'/../components/capability_notice.php'; ?>
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
    <?php if($logsCapabilities['read']): ?>
    <button type="button" class="btn" id="btn-load-logs"><?= htmlspecialchars(__('app.logs.actions.load')) ?></button>
    <button type="button" class="btn outline" id="btn-auto-toggle" data-on="0"><?= htmlspecialchars(__('app.logs.actions.auto_refresh')) ?></button>
    <?php endif; ?>
  </div>
</form>
<div class="logs-summary" id="logsSummaryBox"></div>
<div class="logs-output">
  <div class="logs-table-wrap">
    <table class="logs-table">
      <thead>
        <tr>
          <th class="logs-col-time"><?= htmlspecialchars(__('app.logs.table.headers.time')) ?></th>
          <th class="logs-col-server"><?= htmlspecialchars(__('app.logs.table.headers.server')) ?></th>
          <th class="logs-col-actor"><?= htmlspecialchars(__('app.logs.table.headers.actor')) ?></th>
          <th><?= htmlspecialchars(__('app.logs.table.headers.summary')) ?></th>
        </tr>
      </thead>
      <tbody id="logsTableBody">
        <?php if($logsCapabilities['read']): ?>
        <tr><td colspan="4" class="muted text-center"><?= htmlspecialchars(__('app.logs.table.loading')) ?></td></tr>
        <?php else: ?>
        <tr><td colspan="4" class="muted text-center"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <details class="logs-raw" open>
    <summary><?= htmlspecialchars(__('app.logs.raw.title')) ?></summary>
    <pre id="logsOutput" class="logs-raw__box"><?= htmlspecialchars($logsCapabilities['read'] ? __('app.logs.raw.empty') : __('app.common.capabilities.section_hidden', ['section' => __('app.logs.raw.title')])) ?></pre>
  </details>
</div>
<script type="application/json" data-panel-json data-global="LOGS_DATA"><?= json_encode([
  'modules' => $modules,
  'defaults' => $defaults,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

