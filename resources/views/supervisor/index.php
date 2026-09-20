<?php
/**
 * File: resources/views/supervisor/index.php
 * Purpose: State card + controls for acore_supervisor.exe (worldserver / authserver watchdog).
 */

$state = is_array($state ?? null) ? $state : [];
$services = is_array($state['services'] ?? null) ? $state['services'] : [];
$supervisor = is_array($state['supervisor'] ?? null) ? $state['supervisor'] : [];
$paths = is_array($state['paths'] ?? null) ? $state['paths'] : [];
$logLines = is_array($log_lines ?? null) ? $log_lines : [];

$capabilities = $__pageCapabilities ?? [
    'view' => $__can('supervisor.view'),
    'control' => $__can('supervisor.control'),
];
$__pageCapabilities = $capabilities;
$capabilityNotice = ($capabilities['control'] ?? false) ? null : __('app.common.capabilities.read_only');

$supervisorRunning = (bool) ($state['running'] ?? false);
$toneClass = static function (string $tone): string {
    return 'sv-badge sv-badge--' . preg_replace('/[^a-z]/', '', strtolower($tone));
};
$formatDuration = static function (?int $seconds): string {
    if ($seconds === null || $seconds < 0) {
        return '--';
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($days > 0) {
        return sprintf('%dd %02dh%02dm', $days, $hours, $minutes);
    }
    if ($hours > 0) {
        return sprintf('%dh%02dm%02ds', $hours, $minutes, $secs);
    }
    return sprintf('%dm%02ds', $minutes, $secs);
};
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="sv-toolbar">
  <div class="sv-toolbar__status">
    <span class="<?= $toneClass($supervisorRunning ? 'ok' : 'error') ?>">
      <?= htmlspecialchars($supervisorRunning
          ? __('app.supervisor.supervisor.running')
          : __('app.supervisor.supervisor.not_running')) ?>
    </span>
    <span class="sv-muted">
      <?= htmlspecialchars((string) ($state['reason_label'] ?? '')) ?>
      <?php if (($state['status_age_seconds'] ?? null) !== null): ?>
        · <?= htmlspecialchars(__('app.supervisor.supervisor.status_age', ['seconds' => (int) $state['status_age_seconds']])) ?>
      <?php endif; ?>
    </span>
  </div>
  <div class="sv-toolbar__actions">
    <?php if (($capabilities['control'] ?? false) && $supervisorRunning): ?>
      <button type="button" class="btn outline" data-sv-action="restart" data-sv-target="all">
        <?= htmlspecialchars(__('app.supervisor.actions.restart_all')) ?>
      </button>
      <button type="button" class="btn outline danger" data-sv-action="stop" data-sv-target="all"
              data-sv-confirm="<?= htmlspecialchars(__('app.supervisor.confirm.stop_all'), ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars(__('app.supervisor.actions.stop_all')) ?>
      </button>
    <?php endif; ?>
    <?php if (($capabilities['control'] ?? false) && !$supervisorRunning && ($state['can_start'] ?? false)): ?>
      <button type="button" class="btn" data-sv-action="start_supervisor" data-sv-target="all">
        <?= htmlspecialchars(__('app.supervisor.actions.start_supervisor')) ?>
      </button>
    <?php endif; ?>
    <button type="button" class="btn ghost" id="sv-refresh"><?= htmlspecialchars(__('app.supervisor.actions.refresh')) ?></button>
    <label class="sv-autorefresh">
      <input type="checkbox" id="sv-autorefresh" <?= ((int) ($state['poll_seconds'] ?? 0) > 0) ? 'checked' : '' ?>>
      <?= htmlspecialchars(__('app.supervisor.actions.auto_refresh')) ?>
    </label>
  </div>
</div>

<?php if (!($state['enabled'] ?? true)): ?>
  <div class="sv-notice sv-notice--error"><?= htmlspecialchars(__('app.supervisor.notices.disabled')) ?></div>
<?php elseif (!$supervisorRunning): ?>
  <div class="sv-notice">
    <?= htmlspecialchars(__('app.supervisor.notices.not_running')) ?>
    <?php if (($paths['exe'] ?? '') !== ''): ?>
      <code><?= htmlspecialchars((string) $paths['exe']) ?></code>
    <?php endif; ?>
    <div class="sv-notice__hint"><?= htmlspecialchars(__('app.supervisor.notices.not_running_hint')) ?></div>
  </div>
<?php endif; ?>

<div class="sv-grid" id="sv-services">
  <?php if ($services === []): ?>
    <div class="sv-empty"><?= htmlspecialchars(__('app.supervisor.notices.no_services')) ?></div>
  <?php endif; ?>
  <?php foreach ($services as $service): ?>
    <?php
      $name = (string) ($service['name'] ?? '');
      $stateKey = (string) ($service['state'] ?? '');
      $healthKey = (string) ($service['health'] ?? '');
      $heartbeat = (int) ($service['heartbeat_age_seconds'] ?? -1);
      $probeOk = (bool) ($service['probe_ok'] ?? true);
      $hasProbe = (bool) ($service['probe_failed'] ?? false) || ($service['probe_detail'] ?? '') !== '';
    ?>
  <section class="sv-card sv-card--<?= htmlspecialchars((string) ($service['tone'] ?? 'muted')) ?>"
           data-sv-service="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
    <header class="sv-card__head">
      <h3><?= htmlspecialchars(__('app.supervisor.services.' . $name, [], $name)) ?></h3>
      <span class="<?= $toneClass((string) ($service['tone'] ?? 'muted')) ?>" data-sv-field="health_label">
        <?= htmlspecialchars((string) ($service['health_label'] ?? '')) ?>
      </span>
    </header>
    <dl class="sv-card__facts">
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.state')) ?></dt>
        <dd data-sv-field="state_label"><?= htmlspecialchars((string) ($service['state_label'] ?? $stateKey)) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.pid')) ?></dt>
        <dd data-sv-field="pid"><?= (int) ($service['pid'] ?? 0) > 0 ? (int) $service['pid'] : '--' ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.uptime')) ?></dt>
        <dd data-sv-field="uptime"><?= htmlspecialchars($formatDuration((int) ($service['uptime_seconds'] ?? 0))) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.heartbeat')) ?></dt>
        <dd data-sv-field="heartbeat">
          <?php if ($heartbeat >= 0): ?>
            <?= htmlspecialchars(__('app.supervisor.fields.heartbeat_ago', ['seconds' => $heartbeat])) ?>
            <span class="sv-muted">/ <?= (int) ($service['heartbeat_timeout_seconds'] ?? 0) ?>s</span>
          <?php else: ?>
            <span class="sv-muted"><?= htmlspecialchars(__('app.supervisor.fields.not_available')) ?></span>
          <?php endif; ?>
        </dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.probe')) ?></dt>
        <dd data-sv-field="probe">
          <span class="<?= $toneClass($probeOk ? 'ok' : 'error') ?>">
            <?= htmlspecialchars($probeOk ? __('app.supervisor.fields.probe_ok') : __('app.supervisor.fields.probe_failed')) ?>
          </span>
          <?php if (($service['probe_detail'] ?? '') !== ''): ?>
            <span class="sv-muted sv-small"><?= htmlspecialchars((string) $service['probe_detail']) ?></span>
          <?php endif; ?>
        </dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.restarts')) ?></dt>
        <dd data-sv-field="restarts"><?= (int) ($service['restarts'] ?? 0) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.memory')) ?></dt>
        <dd data-sv-field="memory"><?= htmlspecialchars(number_format((float) ($service['working_set_mb'] ?? 0), 0)) ?> MB</dd></div>
      <div class="sv-card__facts--wide"><dt><?= htmlspecialchars(__('app.supervisor.fields.last_event')) ?></dt>
        <dd data-sv-field="last_event"><?= htmlspecialchars((string) ($service['last_event'] ?? '')) ?></dd></div>
    </dl>
    <div class="sv-card__log">
      <span class="sv-muted sv-small"><?= htmlspecialchars((string) ($service['log_file'] ?? '')) ?></span>
    </div>
    <?php if ($capabilities['control'] ?? false): ?>
      <footer class="sv-card__actions">
        <button type="button" class="btn" data-sv-action="restart" data-sv-target="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars(__('app.supervisor.actions.restart')) ?>
        </button>
        <?php if ($stateKey === 'stopped'): ?>
          <button type="button" class="btn outline" data-sv-action="start" data-sv-target="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(__('app.supervisor.actions.start')) ?>
          </button>
        <?php else: ?>
          <button type="button" class="btn outline danger" data-sv-action="stop" data-sv-target="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                  data-sv-confirm="<?= htmlspecialchars(__('app.supervisor.confirm.stop_service', ['service' => $name]), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(__('app.supervisor.actions.stop')) ?>
          </button>
        <?php endif; ?>
      </footer>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>

<div class="sv-result" id="sv-result" hidden></div>

<section class="sv-panel">
  <header class="sv-panel__head">
    <h3><?= htmlspecialchars(__('app.supervisor.log.title')) ?></h3>
    <span class="sv-muted sv-small"><?= htmlspecialchars((string) ($paths['log_file'] ?? '')) ?></span>
  </header>
  <pre class="sv-log" id="sv-log"><?= htmlspecialchars($logLines === []
      ? __('app.supervisor.log.empty')
      : implode("\n", array_map('strval', $logLines))) ?></pre>
</section>

<section class="sv-panel sv-panel--meta">
  <header class="sv-panel__head">
    <h3><?= htmlspecialchars(__('app.supervisor.meta.title')) ?></h3>
  </header>
  <dl class="sv-meta">
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.instance')) ?></dt>
      <dd><?= htmlspecialchars((string) ($supervisor['instance'] ?? '--')) ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.version')) ?></dt>
      <dd><?= htmlspecialchars((string) ($supervisor['version'] ?? '--')) ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.pid')) ?></dt>
      <dd><?= (int) ($supervisor['pid'] ?? 0) > 0 ? (int) $supervisor['pid'] : '--' ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.uptime')) ?></dt>
      <dd><?= htmlspecialchars($formatDuration((int) ($supervisor['uptime_seconds'] ?? 0))) ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.control_file')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($paths['control_file'] ?? '--')) ?></code></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.status_file')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($paths['status_file'] ?? '--')) ?></code></dd></div>
    <div class="sv-meta__wide"><dt><?= htmlspecialchars(__('app.supervisor.meta.last_command')) ?></dt>
      <dd id="sv-last-command">
        <?php $last = is_array($supervisor['last_command'] ?? null) ? $supervisor['last_command'] : null; ?>
        <?php if ($last === null): ?>
          <span class="sv-muted"><?= htmlspecialchars(__('app.supervisor.meta.no_command')) ?></span>
        <?php else: ?>
          <span class="<?= $toneClass(($last['result'] ?? '') === 'ok' ? 'ok' : 'warn') ?>">
            <?= htmlspecialchars((string) ($last['action'] ?? '')) ?> / <?= htmlspecialchars((string) ($last['target'] ?? '')) ?>
          </span>
          <?= htmlspecialchars((string) ($last['message'] ?? '')) ?>
        <?php endif; ?>
      </dd></div>
  </dl>
</section>

<script type="application/json" data-panel-json data-global="SUPERVISOR_DATA"><?= json_encode([
  // root-relative on purpose: Panel.api prepends the panel base path itself
  'statusUrl' => '/supervisor/api/status',
  'logUrl' => '/supervisor/api/log',
  'commandUrl' => '/supervisor/api/command',
  'pollSeconds' => max(0, (int) ($state['poll_seconds'] ?? 5)),
  'canControl' => (bool) ($capabilities['control'] ?? false),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
