<?php
/**
 * File: resources/views/logs/index.php
 * Purpose: 审计日志台：筛选、分页、明细与导出，数据全部来自 panel_audit_log。
 */

$catalog = is_array($catalog ?? null) ? $catalog : [];
$filters = is_array($filters ?? null) ? $filters : [];
$limits = is_array($limits ?? null) ? $limits : [];
$storageReady = (bool) ($storage_ready ?? true);
$total = (int) ($total ?? 0);

$modules = is_array($catalog['modules'] ?? null) ? $catalog['modules'] : [];
$actions = is_array($catalog['actions'] ?? null) ? $catalog['actions'] : [];
$actors = is_array($catalog['actors'] ?? null) ? $catalog['actors'] : [];
$realms = is_array($catalog['realms'] ?? null) ? $catalog['realms'] : [];
$channels = is_array($catalog['channels'] ?? null) ? $catalog['channels'] : [];
$statuses = is_array($catalog['statuses'] ?? null) ? $catalog['statuses'] : [];

$logsCapabilities = $__pageCapabilities ?? [
    'catalog' => $__can('logs.catalog'),
    'read' => $__can('logs.read'),
    'purge' => $__can('logs.purge'),
];
$logsCapabilities += ['catalog' => false, 'read' => false, 'purge' => false];
$__pageCapabilities = $logsCapabilities;
$capabilityNotice = $logsCapabilities['read'] ? null : __('app.common.capabilities.page_limited');
$__pageHeader['intro_hint'] = __('app.logs.intro');

$selectedModule = (string) ($filters['module'] ?? '');
$selectedAction = (string) ($filters['action'] ?? '');
$rangeLabels = [
    '1h' => __('app.logs.ranges.1h'),
    '24h' => __('app.logs.ranges.24h'),
    '7d' => __('app.logs.ranges.7d'),
    '30d' => __('app.logs.ranges.30d'),
    '90d' => __('app.logs.ranges.90d'),
    'all' => __('app.logs.ranges.all'),
    'custom' => __('app.logs.ranges.custom'),
];
$perPageOptions = array_values(array_unique(array_filter([
    (int) ($limits['per_page'] ?? 50),
    25, 50, 100,
    (int) ($limits['max_per_page'] ?? 200),
])));
sort($perPageOptions);
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<?php include __DIR__.'/../components/capability_notice.php'; ?>

<?php if(!$storageReady): ?>
  <div class="panel-flash panel-flash--warning panel-flash--inline is-visible"><?= htmlspecialchars(__('app.logs.storage_unavailable')) ?></div>
<?php endif; ?>

<form id="logsFilters" class="logs-filters" autocomplete="off">
  <div class="logs-field logs-field--wide">
    <label for="logsKeyword"><?= htmlspecialchars(__('app.logs.fields.keyword')) ?></label>
    <input type="search" id="logsKeyword" name="keyword" value="<?= htmlspecialchars((string) ($filters['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
           placeholder="<?= htmlspecialchars(__('app.logs.fields.keyword_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <div class="logs-field">
    <label for="logsChannel"><?= htmlspecialchars(__('app.logs.fields.channel')) ?></label>
    <select id="logsChannel" name="channel">
      <option value="all"><?= htmlspecialchars(__('app.logs.filters.all')) ?></option>
      <?php foreach($channels as $id => $label): ?>
        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['channel'] ?? '') === (string) $id ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field">
    <label for="logsModule"><?= htmlspecialchars(__('app.logs.fields.module')) ?></label>
    <select id="logsModule" name="module">
      <option value="all"><?= htmlspecialchars(__('app.logs.filters.all')) ?></option>
      <?php foreach($modules as $module): ?>
        <?php $moduleId = (string) ($module['id'] ?? ''); if($moduleId === '') continue; ?>
        <option value="<?= htmlspecialchars($moduleId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedModule === $moduleId ? 'selected' : '' ?>>
          <?= htmlspecialchars((string) ($module['label'] ?? $moduleId), ENT_QUOTES, 'UTF-8') ?><?= ((int) ($module['count'] ?? 0)) > 0 ? ' (' . (int) $module['count'] . ')' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field">
    <label for="logsAction"><?= htmlspecialchars(__('app.logs.fields.action')) ?></label>
    <select id="logsAction" name="action">
      <option value="all"><?= htmlspecialchars(__('app.logs.filters.all')) ?></option>
      <?php foreach(($actions[$selectedModule] ?? []) as $action): ?>
        <?php $actionId = (string) ($action['id'] ?? ''); if($actionId === '') continue; ?>
        <option value="<?= htmlspecialchars($actionId, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAction === $actionId ? 'selected' : '' ?>>
          <?= htmlspecialchars((string) ($action['label'] ?? $actionId), ENT_QUOTES, 'UTF-8') ?><?= ((int) ($action['count'] ?? 0)) > 0 ? ' (' . (int) $action['count'] . ')' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field">
    <label for="logsActor"><?= htmlspecialchars(__('app.logs.fields.actor')) ?></label>
    <select id="logsActor" name="actor">
      <option value="all"><?= htmlspecialchars(__('app.logs.filters.all')) ?></option>
      <?php foreach($actors as $actor): ?>
        <?php $actorId = (string) ($actor['id'] ?? ''); if($actorId === '') continue; ?>
        <option value="<?= htmlspecialchars($actorId, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['actor'] ?? '') === $actorId ? 'selected' : '' ?>>
          <?= htmlspecialchars($actorId, ENT_QUOTES, 'UTF-8') ?> (<?= (int) ($actor['count'] ?? 0) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field">
    <label for="logsStatus"><?= htmlspecialchars(__('app.logs.fields.status')) ?></label>
    <select id="logsStatus" name="status">
      <option value="all"><?= htmlspecialchars(__('app.logs.filters.all')) ?></option>
      <?php foreach($statuses as $id => $label): ?>
        <option value="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['status'] ?? '') === (string) $id ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field">
    <label for="logsRealm"><?= htmlspecialchars(__('app.logs.fields.realm')) ?></label>
    <select id="logsRealm" name="realm">
      <option value="all"><?= htmlspecialchars(__('app.logs.filters.all_realms')) ?></option>
      <?php foreach($realms as $realm): ?>
        <?php $realmId = (string) ($realm['id'] ?? ''); if($realmId === '') continue; ?>
        <option value="<?= htmlspecialchars($realmId, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['realm'] ?? '') === $realmId ? 'selected' : '' ?>>
          <?= htmlspecialchars((string) ($realm['label'] ?? ('#' . $realmId)), ENT_QUOTES, 'UTF-8') ?> (<?= (int) ($realm['count'] ?? 0) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field">
    <label for="logsRange"><?= htmlspecialchars(__('app.logs.fields.range')) ?></label>
    <select id="logsRange" name="range">
      <?php foreach($rangeLabels as $rangeId => $rangeLabel): ?>
        <option value="<?= htmlspecialchars($rangeId, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($filters['range'] ?? '') === $rangeId ? 'selected' : '' ?>><?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-field logs-field--range" id="logsCustomRange" hidden>
    <label for="logsFrom"><?= htmlspecialchars(__('app.logs.fields.from')) ?></label>
    <input type="datetime-local" id="logsFrom" name="from" value="<?= htmlspecialchars(str_replace(' ', 'T', (string) ($filters['from'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
  </div>
  <div class="logs-field logs-field--range" id="logsCustomRangeTo" hidden>
    <label for="logsTo"><?= htmlspecialchars(__('app.logs.fields.to')) ?></label>
    <input type="datetime-local" id="logsTo" name="to" value="<?= htmlspecialchars(str_replace(' ', 'T', (string) ($filters['to'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <div class="logs-field logs-field--compact">
    <label for="logsPerPage"><?= htmlspecialchars(__('app.logs.fields.per_page')) ?></label>
    <select id="logsPerPage" name="per_page">
      <?php foreach($perPageOptions as $option): ?>
        <option value="<?= (int) $option ?>" <?= (int) ($filters['per_page'] ?? 0) === (int) $option ? 'selected' : '' ?>><?= (int) $option ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="logs-actions">
    <?php if($logsCapabilities['read']): ?>
      <button type="submit" class="btn" id="btn-logs-search"><?= htmlspecialchars(__('app.logs.actions.search')) ?></button>
      <button type="button" class="btn outline" id="btn-logs-reset"><?= htmlspecialchars(__('app.logs.actions.reset')) ?></button>
      <button type="button" class="btn outline" id="btn-auto-toggle" data-on="0"><?= htmlspecialchars(__('app.logs.actions.auto_refresh')) ?></button>
      <a class="btn outline" id="logsExport" href="<?= htmlspecialchars(url('/logs/export'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('app.logs.actions.export')) ?></a>
      <?php if($logsCapabilities['purge']): ?>
        <details class="logs-purge">
          <summary class="btn outline"><?= htmlspecialchars(__('app.logs.actions.purge')) ?></summary>
          <div class="logs-purge__panel">
            <p class="muted small"><?= htmlspecialchars(__('app.logs.purge.hint')) ?></p>
            <label class="logs-purge__field" for="logsPurgeDays"><?= htmlspecialchars(__('app.logs.purge.days')) ?>
              <input type="number" id="logsPurgeDays" min="1" max="3650" value="<?= (int) ($limits['retention_days'] ?? 180) ?>">
            </label>
            <button type="button" class="btn danger" id="btn-logs-purge"><?= htmlspecialchars(__('app.logs.purge.confirm')) ?></button>
          </div>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</form>

<div class="logs-summary" id="logsSummaryBox"></div>

<div class="logs-layout">
  <section class="logs-main">
    <div class="logs-table-wrap">
      <table class="table table--logs">
        <thead>
          <tr>
            <th scope="col" class="logs-col-time"><?= htmlspecialchars(__('app.logs.table.headers.time')) ?></th>
            <th scope="col" class="logs-col-realm"><?= htmlspecialchars(__('app.logs.table.headers.realm')) ?></th>
            <th scope="col" class="logs-col-actor"><?= htmlspecialchars(__('app.logs.table.headers.actor')) ?></th>
            <th scope="col" class="logs-col-channel"><?= htmlspecialchars(__('app.logs.table.headers.channel')) ?></th>
            <th scope="col" class="logs-col-module"><?= htmlspecialchars(__('app.logs.table.headers.module')) ?></th>
            <th scope="col" class="logs-col-status"><?= htmlspecialchars(__('app.logs.table.headers.status')) ?></th>
            <th scope="col" class="logs-col-target"><?= htmlspecialchars(__('app.logs.table.headers.target')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.logs.table.headers.summary')) ?></th>
          </tr>
        </thead>
        <tbody id="logsTableBody">
          <?php if($logsCapabilities['read']): ?>
            <tr><td colspan="8" class="muted text-center"><?= htmlspecialchars(__('app.logs.table.loading')) ?></td></tr>
          <?php else: ?>
            <tr><td colspan="8" class="muted text-center"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <nav class="logs-pager" id="logsPager" aria-label="<?= htmlspecialchars(__('app.pagination.label')) ?>"></nav>
  </section>

  <aside class="logs-detail" id="logsDetail" hidden>
    <header class="logs-detail__head">
      <h3 class="logs-detail__title"><?= htmlspecialchars(__('app.logs.detail.title')) ?></h3>
      <button type="button" class="btn-sm btn outline" id="btn-logs-detail-close"><?= htmlspecialchars(__('app.logs.detail.close')) ?></button>
    </header>
    <div class="logs-detail__body" id="logsDetailBody"></div>
  </aside>
</div>

<script type="application/json" data-panel-json data-global="LOGS_DATA"><?= json_encode([
  'catalog' => $catalog,
  'filters' => $filters,
  'limits' => $limits,
  'storage_ready' => $storageReady,
  'total' => $total,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
