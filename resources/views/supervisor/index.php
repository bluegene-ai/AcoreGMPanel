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
$instances = is_array($instances ?? null) ? $instances : [];
$currentInstance = (string) ($current_instance ?? ($state['instance']['id'] ?? 'default'));
$currentInstanceLabel = (string) ($state['instance']['label'] ?? $currentInstance);

$capabilities = $__pageCapabilities ?? [
    'view' => $__can('supervisor.view'),
    'control' => $__can('supervisor.control'),
];
$__pageCapabilities = $capabilities;
$capabilityNotice = ($capabilities['control'] ?? false) ? null : __('app.common.capabilities.read_only');
$__pageHeader['intro_hint'] = __('app.supervisor.intro');

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
/**
 * 心跳超时为什么是这个值、这一行实际多久出现一次：supervisor 会把有效超时提高到
 * max(configured, RecordUpdateTimeDiffInterval + 120s)，避免世界循环较慢的服务器被判死；
 * 所以要连着显示配置值与实测节奏，否则自动抬高看起来就像面板显示了错的数字。
 */
$heartbeatNote = static function (array $service): string {
    $effective = (int) ($service['heartbeat_timeout_seconds'] ?? 0);
    $configured = (int) ($service['heartbeat_timeout_configured_seconds'] ?? $effective);
    $interval = (int) ($service['heartbeat_interval_seconds'] ?? 0);
    $cadence = (int) ($service['heartbeat_cadence_seconds'] ?? 0);

    $bits = [];
    if ($configured > 0 && $configured !== $effective) {
        $bits[] = __('app.supervisor.fields.heartbeat_raised', ['configured' => $configured, 'effective' => $effective]);
    } elseif ($interval > 0) {
        $bits[] = __('app.supervisor.fields.heartbeat_interval', ['seconds' => $interval]);
    }
    if ($cadence > 0) {
        $bits[] = __('app.supervisor.fields.heartbeat_cadence', ['seconds' => $cadence]);
    }

    return implode(' · ', $bits);
};
/**
 * 解析出的路径来自哪里：面板配置 / supervisor 自己的 ini / 目录里找到的文件 / 内置默认名。
 * 状态或控制文件被改过名时，看起来和"supervisor 没在运行"一模一样。
 */
$sourceBadge = static function (array $diagnostics, string $key): string {
    $source = (string) ($diagnostics['file_sources'][$key] ?? '');
    $labels = [
        'configured' => __('app.supervisor.diagnostics.source_configured'),
        'ini' => __('app.supervisor.diagnostics.source_ini'),
        'discovered' => __('app.supervisor.diagnostics.source_discovered'),
        'default' => __('app.supervisor.diagnostics.source_default'),
        'none' => __('app.supervisor.diagnostics.source_none'),
    ];

    return isset($labels[$source])
        ? '<span class="sv-muted sv-small">· ' . htmlspecialchars($labels[$source]) . '</span>'
        : '';
};
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<?php if (count($instances) > 1): ?>
<div class="sv-instances" id="sv-instances" role="tablist" aria-label="<?= htmlspecialchars(__('app.supervisor.instances.title')) ?>">
  <?php foreach ($instances as $instance): ?>
    <?php
      $instanceId = (string) ($instance['id'] ?? '');
      $instanceTone = (string) ($instance['tone'] ?? 'muted');
      $instanceRunning = (bool) ($instance['running'] ?? false);
    ?>
    <button type="button" role="tab"
            class="sv-instance<?= $instanceId === $currentInstance ? ' sv-instance--active' : '' ?>"
            data-sv-instance="<?= htmlspecialchars($instanceId, ENT_QUOTES, 'UTF-8') ?>"
            aria-selected="<?= $instanceId === $currentInstance ? 'true' : 'false' ?>">
      <span class="<?= $toneClass($instanceTone) ?>" data-sv-instance-field="dot">&nbsp;</span>
      <span class="sv-instance__label"><?= htmlspecialchars((string) ($instance['label'] ?? $instanceId)) ?></span>
      <span class="sv-muted sv-small" data-sv-instance-field="state">
        <?= htmlspecialchars($instanceRunning
            ? __('app.supervisor.supervisor.running')
            : __('app.supervisor.supervisor.not_running')) ?>
      </span>
    </button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

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

<?php
// 状态文件必须属于 <dir>/supervisor.ini 描述的那个 supervisor：InstanceName 不同说明这个目录
// （或 status_file）指向另一个区的 supervisor —— 而页面会照样把那个区显示成"运行中"。
$instanceCheck = is_array($state['instance_check'] ?? null) ? $state['instance_check'] : null;
$iniInfo = is_array($state['ini'] ?? null) ? $state['ini'] : [];
?>
<?php if (($instanceCheck['mismatch'] ?? false)): ?>
  <div class="sv-notice sv-notice--error">
    <?= htmlspecialchars(__('app.supervisor.notices.instance_mismatch', [
        'status' => (string) ($instanceCheck['status'] ?? ''),
        'ini' => (string) ($instanceCheck['ini'] ?? ''),
        'file' => basename((string) ($iniInfo['file'] ?? 'supervisor.ini')),
    ])) ?>
  </div>
<?php endif; ?>

<?php
// 两个面板实例指向同一个 supervisor 目录会把同一个区显示（并控制）两次：一个 worldserver+authserver 只有一个 supervisor。
$dirGroups = [];
foreach ($instances as $instanceRow) {
    $instanceDir = trim((string) ($instanceRow['dir'] ?? ''));
    if ($instanceDir !== '') {
        $dirGroups[$instanceDir][] = (string) ($instanceRow['label'] ?? $instanceRow['id'] ?? '');
    }
}
$dirCollisions = array_filter($dirGroups, static fn (array $labels): bool => count($labels) > 1);
?>
<?php foreach ($dirCollisions as $collisionDir => $collisionLabels): ?>
  <div class="sv-notice sv-notice--error">
    <?= htmlspecialchars(__('app.supervisor.notices.dir_collision', [
        'instances' => implode(' / ', $collisionLabels),
        'dir' => $collisionDir,
    ])) ?>
  </div>
<?php endforeach; ?>

<?php if (!($state['enabled'] ?? true)): ?>
  <div class="sv-notice sv-notice--error"><?= htmlspecialchars(__('app.supervisor.notices.disabled')) ?></div>
<?php elseif (!$supervisorRunning): ?>
  <div class="sv-notice">
    <?php if (($state['reason'] ?? '') === 'dir_missing'): ?>
      <?= htmlspecialchars(__('app.supervisor.notices.dir_missing')) ?>
    <?php else: ?>
      <?= htmlspecialchars(__('app.supervisor.notices.not_running')) ?>
      <?php if (($paths['exe'] ?? '') !== ''): ?>
        <code><?= htmlspecialchars((string) $paths['exe']) ?></code>
      <?php endif; ?>
    <?php endif; ?>
    <div class="sv-notice__hint"><?= htmlspecialchars(__('app.supervisor.notices.not_running_hint')) ?></div>
  </div>
<?php endif; ?>

<?php
// 面板为什么看不到 supervisor：它试过哪些目录，以及指向真实位置的两种办法。生产部署常把
// supervisor 放在 web 根之外，没有这段时页面只会说"supervisor directory not found"。
$diagnostics = is_array($state['diagnostics'] ?? null) ? $state['diagnostics'] : null;
$pathExample = __('app.supervisor.diagnostics.placeholder_dir');
if ($diagnostics !== null && is_array($diagnostics['candidates'] ?? null)) {
    // 优先选真正放着 supervisor / ini 的目录；第二个循环故意不回退到"任何已存在的目录" ——
    // 那个示例会把人送到一个不可能工作的路径，占位符会提示填真实路径。
    foreach ($diagnostics['candidates'] as $candidate) {
        if (($candidate['has_exe'] ?? false) || ($candidate['has_ini'] ?? false)) {
            $pathExample = (string) ($candidate['path'] ?? $pathExample);
            break;
        }
    }
}
?>
<?php if ($diagnostics !== null && !$supervisorRunning): ?>
<section class="sv-panel sv-panel--diagnostics">
  <header class="sv-panel__head">
    <h3><?= htmlspecialchars(__('app.supervisor.diagnostics.title')) ?></h3>
  </header>
  <p class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.intro_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.supervisor.diagnostics.intro')) ?>">i</span></p>
  <?php if (($diagnostics['ini_conflicts'] ?? []) !== []): ?>
    <div class="sv-notice sv-notice--error">
      <?= htmlspecialchars(__('app.supervisor.diagnostics.conflict_title_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.supervisor.diagnostics.conflict_title')) ?>">i</span>
      <ul class="sv-muted sv-small">
        <?php foreach ((array) $diagnostics['ini_conflicts'] as $conflictKey => $conflictPair): ?>
          <?php
            $iniKeyName = match ($conflictKey) {
                'control_file' => 'control',
                'log_file' => 'log',
                default => 'status',
            };
          ?>
          <li><code><?= htmlspecialchars(__('app.supervisor.diagnostics.ini_key_' . $iniKeyName)) ?></code> —
            <?= htmlspecialchars(__('app.supervisor.diagnostics.conflict_line', [
                'configured' => (string) ($conflictPair['configured'] ?? ''),
                'ini' => (string) ($conflictPair['ini'] ?? ''),
            ])) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <dl class="sv-meta">
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.configured')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['configured_dir'] ?? '') !== ''
          ? (string) $diagnostics['configured_dir']
          : __('app.supervisor.diagnostics.configured_empty')) ?></code></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.env', ['var' => (string) ($diagnostics['env_var'] ?? '')])) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['env_dir'] ?? '') !== ''
          ? (string) $diagnostics['env_dir']
          : __('app.supervisor.diagnostics.env_empty')) ?></code></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.resolved')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['resolved_dir'] ?? '') !== ''
          ? (string) $diagnostics['resolved_dir']
          : __('app.supervisor.diagnostics.resolved_empty')) ?></code></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.status_file')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['status_file'] ?? '') ?: '--') ?></code>
        <?php if (!($diagnostics['status_file_exists'] ?? false)): ?>
          <span class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.status_file_missing')) ?></span>
        <?php elseif (($diagnostics['status_file_age_seconds'] ?? null) !== null): ?>
          <span class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.status_file_age', ['seconds' => (int) $diagnostics['status_file_age_seconds']])) ?></span>
        <?php endif; ?>
        <?= $sourceBadge($diagnostics, 'status_file') ?>
      </dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.control_file')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['control_file'] ?? '') ?: '--') ?></code>
        <?php if (($diagnostics['control_file'] ?? '') !== '' && !is_file((string) $diagnostics['control_file'])): ?>
          <span class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.control_file_absent')) ?></span>
        <?php endif; ?>
        <?= $sourceBadge($diagnostics, 'control_file') ?>
      </dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.log_file')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['log_file'] ?? '') ?: '--') ?></code>
        <?= $sourceBadge($diagnostics, 'log_file') ?>
      </dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.ini_file')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($diagnostics['ini_file'] ?? '') ?: '--') ?></code>
        <span class="sv-muted sv-small">
          <?= htmlspecialchars(($diagnostics['ini_found'] ?? false)
              ? __('app.supervisor.diagnostics.ini_read')
              : __('app.supervisor.diagnostics.ini_missing')) ?>
        </span>
      </dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.process_user')) ?></dt>
      <dd><?= htmlspecialchars((string) ($diagnostics['process_user'] ?? '') ?: '--') ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.diagnostics.open_basedir')) ?></dt>
      <dd><?= htmlspecialchars((string) ($diagnostics['open_basedir'] ?? '') !== ''
          ? (string) $diagnostics['open_basedir']
          : __('app.supervisor.diagnostics.open_basedir_empty')) ?></dd></div>
  </dl>
  <?php if ((string) ($diagnostics['open_basedir'] ?? '') !== ''): ?>
    <div class="sv-notice sv-notice--error"><?= htmlspecialchars(__('app.supervisor.diagnostics.open_basedir_warning_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.supervisor.diagnostics.open_basedir_warning')) ?>">i</span></div>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th><?= htmlspecialchars(__('app.supervisor.diagnostics.candidate_path')) ?></th>
        <th><?= htmlspecialchars(__('app.supervisor.diagnostics.candidate_state')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ((array) ($diagnostics['candidates'] ?? []) as $candidate): ?>
        <tr>
          <td><code><?= htmlspecialchars((string) ($candidate['path'] ?? '')) ?></code></td>
          <td>
            <span class="<?= $toneClass(($candidate['exists'] ?? false) ? 'ok' : 'muted') ?>">
              <?= htmlspecialchars(($candidate['exists'] ?? false) ? __('app.supervisor.diagnostics.exists_yes') : __('app.supervisor.diagnostics.exists_no')) ?>
            </span>
            <?php if (($candidate['has_exe'] ?? false) || ($candidate['has_ini'] ?? false) || ($candidate['has_status'] ?? false)): ?>
              <span class="sv-muted sv-small">
                <?= ($candidate['has_exe'] ?? false) ? 'exe ' : '' ?>
                <?= ($candidate['has_ini'] ?? false) ? 'ini ' : '' ?>
                <?= ($candidate['has_status'] ?? false) ? 'status' : '' ?>
              </span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="sv-notice">
    <strong><?= htmlspecialchars(__('app.supervisor.diagnostics.fix_title')) ?></strong>
    <?php if (($state['reason'] ?? '') === 'dir_missing'): ?>
      <div class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.fix_configured_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.supervisor.diagnostics.fix_configured')) ?>">i</span></div>
    <?php endif; ?>
    <div class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.fix_hint_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.supervisor.diagnostics.fix_hint')) ?>">i</span></div>
    <ol class="sv-muted sv-small">
      <li><?= htmlspecialchars(__('app.supervisor.diagnostics.fix_option_config', ['file' => (string) ($diagnostics['override_file'] ?? 'config/generated/supervisor.php')])) ?>
        <pre>return [
    'dir' =&gt; '<?= htmlspecialchars($pathExample) ?>',
];</pre>
      </li>
      <li><?= htmlspecialchars(__('app.supervisor.diagnostics.fix_option_env', ['var' => (string) ($diagnostics['env_var'] ?? '')])) ?></li>
    </ol>
    <div class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.diagnostics.fix_option_note_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.supervisor.diagnostics.fix_option_note')) ?>">i</span></div>
  </div>
</section>
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
      $heartbeatNoteText = $heartbeatNote($service);
      $probeOk = (bool) ($service['probe_ok'] ?? true);
      $probeDetail = (string) ($service['probe_detail'] ?? '');
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
          <?php // 实时更新器只写这些 span；整格覆写会在第一次轮询时丢掉超时与说明（见 supervisor.js） ?>
          <span data-sv-field="heartbeat_age"><?= htmlspecialchars($heartbeat >= 0
              ? __('app.supervisor.fields.heartbeat_ago', ['seconds' => $heartbeat])
              : __('app.supervisor.fields.not_available')) ?></span>
          <?php if ($heartbeat >= 0): ?>
            <span class="sv-muted">/ <span data-sv-field="heartbeat_timeout"><?= (int) ($service['heartbeat_timeout_seconds'] ?? 0) ?></span>s</span>
          <?php endif; ?>
          <?php if ($heartbeatNoteText !== ''): ?>
            <span class="sv-muted sv-small" data-sv-field="heartbeat_note"><?= htmlspecialchars($heartbeatNoteText) ?></span>
          <?php else: ?>
            <span class="sv-muted sv-small" data-sv-field="heartbeat_note" hidden></span>
          <?php endif; ?>
        </dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.fields.probe')) ?></dt>
        <dd data-sv-field="probe">
          <span class="<?= $toneClass($probeOk ? 'ok' : 'error') ?>" data-sv-field="probe_state">
            <?= htmlspecialchars($probeOk ? __('app.supervisor.fields.probe_ok') : __('app.supervisor.fields.probe_failed')) ?>
          </span>
          <span class="sv-muted sv-small" data-sv-field="probe_detail"<?= $probeDetail === '' ? ' hidden' : '' ?>><?= htmlspecialchars($probeDetail) ?></span>
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
    <?php
      // 本 supervisor 不跑的服务（ini 里 Enabled=false，例如由别的区托管的共享 authserver）不给任何控制按钮：
      // 点名会启动一份属于另一个 supervisor 的服务副本。
      $serviceEnabled = (bool) ($service['enabled'] ?? true);
    ?>
    <?php if (($capabilities['control'] ?? false) && $serviceEnabled): ?>
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
    <?php elseif (!$serviceEnabled): ?>
      <footer class="sv-card__note">
        <span class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.notices.service_disabled')) ?></span>
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
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.panel_instance')) ?></dt>
      <dd data-sv-field="panel_instance"><?= htmlspecialchars($currentInstanceLabel) ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.instance')) ?></dt>
      <dd><?= htmlspecialchars((string) ($supervisor['instance'] ?? '--')) ?></dd></div>
    <div><dt><?= htmlspecialchars(__('app.supervisor.meta.ini')) ?></dt>
      <dd><code><?= htmlspecialchars((string) ($iniInfo['file'] ?? '') ?: '--') ?></code>
        <span class="sv-muted sv-small">
          <?= htmlspecialchars(($iniInfo['found'] ?? false)
              ? __('app.supervisor.meta.ini_read')
              : __('app.supervisor.meta.ini_missing')) ?>
        </span>
      </dd></div>
    <?php if (($iniInfo['found'] ?? false)): ?>
      <div><dt><?= htmlspecialchars(__('app.supervisor.meta.ini_instance')) ?></dt>
        <dd><?= htmlspecialchars((string) ($iniInfo['instance_name'] ?? '') ?: '--') ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.meta.ini_status_enabled')) ?></dt>
        <dd><?= htmlspecialchars(($iniInfo['status_enabled'] ?? true)
            ? __('app.supervisor.meta.yes')
            : __('app.supervisor.meta.no_status')) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.supervisor.meta.ini_tick')) ?></dt>
        <dd><?= (int) ($iniInfo['tick_ms'] ?? 0) ?> ms</dd></div>
      <div class="sv-meta__wide"><dt><?= htmlspecialchars(__('app.supervisor.meta.ini_services')) ?></dt>
        <dd>
          <?php foreach ((array) ($iniInfo['services'] ?? []) as $iniService): ?>
            <div>
              <span class="<?= $toneClass(($iniService['enabled'] ?? false) ? 'ok' : 'muted') ?>">
                <?= htmlspecialchars((string) ($iniService['name'] ?? '')) ?>
              </span>
              <?= htmlspecialchars(($iniService['enabled'] ?? false)
                  ? __('app.supervisor.meta.ini_service_on')
                  : __('app.supervisor.meta.ini_service_off')) ?>
              <code><?= htmlspecialchars((string) ($iniService['exe'] ?? '')) ?></code>
              <?php if ((int) ($iniService['probe_port'] ?? 0) > 0): ?>
                <span class="sv-muted sv-small"><?= htmlspecialchars(__('app.supervisor.meta.ini_probe', ['port' => (int) $iniService['probe_port']])) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </dd></div>
    <?php endif; ?>
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
          <?php if (($last['age_seconds'] ?? null) !== null): ?>
            <span class="sv-muted sv-small" data-sv-field="last_command_age">·
              <?= htmlspecialchars(__('app.supervisor.meta.command_age', ['seconds' => (int) $last['age_seconds']])) ?></span>
          <?php endif; ?>
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
  'instance' => $currentInstance,
  'instanceLabel' => $currentInstanceLabel,
  'hasInstances' => count($instances) > 1,
  'instances' => array_map(static fn (array $instance): array => [
      'id' => (string) ($instance['id'] ?? ''),
      'label' => (string) ($instance['label'] ?? ''),
      'running' => (bool) ($instance['running'] ?? false),
      'tone' => (string) ($instance['tone'] ?? 'muted'),
  ], $instances),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
