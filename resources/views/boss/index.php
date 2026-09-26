<?php

$bossDashboard = is_array($boss_dashboard ?? null) ? $boss_dashboard : [];
$bossRuntime = is_array($bossDashboard['runtime'] ?? null)
    ? $bossDashboard['runtime']
    : [];
$bossConfig = is_array($bossDashboard['config'] ?? null)
  ? $bossDashboard['config']
  : [];
$bossStats = is_array($bossDashboard['stats'] ?? null)
    ? $bossDashboard['stats']
    : [];
$bossEvents = is_array($bossDashboard['events'] ?? null)
    ? $bossDashboard['events']
    : [];
$bossContributors = is_array($bossDashboard['contributors'] ?? null)
    ? $bossDashboard['contributors']
    : [];
$bossCriticalWarnings = is_array($bossDashboard['critical_warnings'] ?? null)
  ? $bossDashboard['critical_warnings']
  : [];
$bossWarnings = is_array($bossDashboard['warnings'] ?? null)
    ? $bossDashboard['warnings']
    : [];
$bossOptions = is_array($boss_options ?? null) ? $boss_options : [];
$bossTiers = is_array($bossOptions['tiers'] ?? null) ? $bossOptions['tiers'] : [];
$bossTierItems = is_array($bossTiers['items'] ?? null) ? $bossTiers['items'] : [];
$bossTierBaseHp = (int) ($bossTiers['base_hp'] ?? 13945);
$bossTierDecimalScale = max(1, (int) ($bossTiers['decimal_scale'] ?? 100));
$bossCurrentTierEntry = (int) ($bossTiers['current_tier_entry'] ?? ($bossConfig['boss_entry'] ?? 0));
$bossCurrentEstimatedHp = (int) ($bossTiers['current_estimated_hp'] ?? 0);
$bossServerSupported = ($bossOptions['supported'] ?? true) !== false;
$bossCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
        'dashboard' => $__can('boss.dashboard'),
        'events' => $__can('boss.events'),
        'contributors' => $__can('boss.contributors'),
        'actions' => $__can('boss.actions'),
    ];
$__pageCapabilities = $bossCapabilities;
$bossCanAct = !empty($bossCapabilities['actions']) && $bossServerSupported;
$capabilityNotice = $__canAll(['boss.events', 'boss.contributors', 'boss.actions'])
    ? null
    : __('app.common.capabilities.page_limited');
$bossName = trim((string) ($bossRuntime['boss_name'] ?? ''));
$hasActiveBoss = (int) ($bossRuntime['boss_guid'] ?? 0) > 0;
$bossMapId = (int) ($bossRuntime['map_id'] ?? 0);
$bossMapLabel = \Acme\Panel\Support\GameMaps::mapLabel($bossMapId);
$bossInstanceId = (int) ($bossRuntime['instance_id'] ?? 0);
$bossHomeX = number_format((float) ($bossRuntime['home_x'] ?? 0), 3, '.', '');
$bossHomeY = number_format((float) ($bossRuntime['home_y'] ?? 0), 3, '.', '');
$bossHomeZ = number_format((float) ($bossRuntime['home_z'] ?? 0), 3, '.', '');
$bossExt = is_array($boss_ext ?? null) ? $boss_ext : [];

// 刷新点"解析预览"：面板只做展示（真正的坐标校验在脚本里），让 GM 一眼看出填了几条、都在哪张地图
$bossSpawnLineCount = 0;
$bossSpawnMapIds = [];
foreach (preg_split('/\r\n|\r|\n/', (string) ($bossConfig['spawn_points_text'] ?? '')) ?: [] as $bossSpawnLine) {
    $bossSpawnLine = trim((string) $bossSpawnLine);
    if ($bossSpawnLine === '') {
        continue;
    }
    $bossSpawnLineCount++;
    $bossSpawnMapIdValue = (int) trim((string) explode(',', $bossSpawnLine)[0]);
    if ($bossSpawnMapIdValue > 0) {
        $bossSpawnMapIds[$bossSpawnMapIdValue] = true;
    }
}
$bossSpawnMapLabels = [];
foreach (array_keys($bossSpawnMapIds) as $bossSpawnMapIdItem) {
    $bossSpawnMapLabels[] = \Acme\Panel\Support\GameMaps::mapLabel($bossSpawnMapIdItem);
}
// Aura 预览：逗号/空格/换行分隔的 ID 个数
$bossAuraCount = 0;
foreach (preg_split('/[\s,;]+/', (string) ($bossConfig['boss_auras_text'] ?? '')) ?: [] as $bossAuraToken) {
    if (trim((string) $bossAuraToken) !== '') {
        $bossAuraCount++;
    }
}

// 定时启停（脚本 tick 上报到 boss_activity_runtime）：
//   ''      = 本区脚本还没上报（旧版脚本 / 表里没有这三列）→ 显示"未上报"，不猜状态
//   off     = 计划未启用；empty = 已启用但没写有效时间段；open/closed = 时段内 / 时段外
$bossScheduleState = (string) ($bossRuntime['schedule_state'] ?? '');
$bossScheduleStateKey = in_array($bossScheduleState, ['off', 'open', 'closed', 'empty'], true)
    ? $bossScheduleState
    : 'unreported';
$bossScheduleWindow = trim((string) ($bossRuntime['schedule_window'] ?? ''));
$bossScheduleNextChangeAt = (int) ($bossRuntime['schedule_next_change_at'] ?? 0);
$bossScheduleNextInText = '';
$bossScheduleRemaining = $bossScheduleNextChangeAt - time();
if ($bossScheduleNextChangeAt > 0 && $bossScheduleRemaining > 0) {
    $bossScheduleNextInText = $bossScheduleRemaining >= 3600
        ? __('app.boss.runtime.schedule_hours_minutes', [
            'hours' => (string) intdiv($bossScheduleRemaining, 3600),
            'minutes' => (string) intdiv($bossScheduleRemaining % 3600, 60),
        ])
        : __('app.boss.runtime.schedule_minutes', ['minutes' => (string) max(1, intdiv($bossScheduleRemaining, 60))]);
}

// 顶层 Tab：运行控制/配置相关 Tab 需要 boss.actions 权限；事件与贡献 Tab 始终可见
// （其内部仍按 boss.events / boss.contributors 权限位决定是否渲染表格）。
$bossTabs = [
    'status' => __('app.boss.tabs.status'),
];
if ($bossCanAct) {
    $bossTabs['actions'] = __('app.boss.tabs.actions');
    $bossTabs['config'] = __('app.boss.tabs.config');
    $bossTabs['ext'] = __('app.boss.tabs.ext');
}
$bossTabs['log'] = __('app.boss.tabs.log');
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="boss-page">
  <div id="bossFeedback" class="panel-flash panel-flash--inline" hidden></div>

  <?php foreach ($bossCriticalWarnings as $warning): ?>
    <div class="panel-flash panel-flash--error panel-flash--inline is-visible">
      <?= htmlspecialchars((string) $warning) ?>
    </div>
  <?php endforeach; ?>

  <?php foreach ($bossWarnings as $warning): ?>
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
      <?= htmlspecialchars((string) $warning) ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$bossCanAct): ?>
    <section class="boss-panel">
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
        <?php if ($bossServerSupported): ?>
          <?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.boss.actions.title')])) ?>
        <?php else: ?>
          <?= htmlspecialchars(__('app.boss.warnings.server_not_supported')) ?>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="boss-tabs" data-boss-tabs="main">
    <nav class="boss-tabs__nav" role="tablist" aria-label="<?= htmlspecialchars(__('app.boss.tabs.label')) ?>">
      <?php $bossFirstTab = true; foreach ($bossTabs as $bossTabKey => $bossTabLabel): ?>
        <button type="button" class="boss-tab<?= $bossFirstTab ? ' is-active' : '' ?>" role="tab"
                aria-selected="<?= $bossFirstTab ? 'true' : 'false' ?>"
                data-boss-tab="<?= htmlspecialchars((string) $bossTabKey, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars((string) $bossTabLabel) ?>
        </button>
      <?php $bossFirstTab = false; endforeach; ?>
    </nav>

    <!-- Tab 面板用 div 而不是 section：面板里可能再嵌一层 Tab（扩展配置），
         section 嵌套在非 HTML5 解析器里会被隐式闭合，div 更稳妥 -->
    <div class="boss-tabpanel is-active" role="tabpanel" data-boss-tabpanel="status">
  <section class="boss-status-stack">
    <article class="boss-panel boss-panel--runtime">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.runtime.title')) ?></h2>
        <div class="boss-badge-list">
          <span class="boss-status boss-status--<?= htmlspecialchars((string) ($bossRuntime['status'] ?? 'idle')) ?>">
            <?= htmlspecialchars(__('app.boss.status.' . ($bossRuntime['status'] ?? 'idle'))) ?>
          </span>
        </div>
      </div>

      <div class="boss-statusbar">
        <div class="boss-statusbar__boss">
          <span class="boss-statusbar__name"><?= htmlspecialchars($hasActiveBoss ? $bossName : __('app.boss.runtime.no_active_boss')) ?></span>
          <?php if ($hasActiveBoss): ?>
            <span class="small muted">GUID #<?= (int) ($bossRuntime['boss_guid'] ?? 0) ?> · Entry #<?= (int) ($bossRuntime['boss_entry'] ?? 0) ?></span>
          <?php else: ?>
            <span class="small muted"><?= htmlspecialchars(__('app.boss.runtime.no_active_boss_hint')) ?></span>
          <?php endif; ?>
        </div>

        <div class="boss-statusbar__facts">
          <div class="boss-fact">
            <span class="boss-fact__label"><?= htmlspecialchars(__('app.boss.runtime.phase')) ?></span>
            <strong><?= (int) ($bossRuntime['phase'] ?? 0) > 0 ? (int) $bossRuntime['phase'] : '-' ?></strong>
          </div>
          <div class="boss-fact">
            <span class="boss-fact__label"><?= htmlspecialchars(__('app.boss.runtime.skill_preset')) ?></span>
            <strong><?= htmlspecialchars(__('app.boss.presets.labels.' . ($bossRuntime['skill_preset'] ?? ''), [], (string) ($bossRuntime['skill_preset'] ?? '-'))) ?></strong>
          </div>
          <div class="boss-fact">
            <span class="boss-fact__label"><?= htmlspecialchars(__('app.boss.runtime.skill_difficulty')) ?></span>
            <strong><?= htmlspecialchars(__('app.boss.difficulties.labels.' . ($bossRuntime['skill_difficulty'] ?? ''), [], (string) ($bossRuntime['skill_difficulty'] ?? '-'))) ?></strong>
          </div>
          <div class="boss-fact boss-fact--countdown"
               data-boss-countdown
               data-boss-countdown-at="<?= (int) ($bossRuntime['respawn_at'] ?? 0) ?>">
            <span class="boss-fact__label"><?= htmlspecialchars(__('app.boss.runtime.respawn_at')) ?></span>
            <strong data-boss-countdown-value><?= htmlspecialchars(format_datetime((int) ($bossRuntime['respawn_at'] ?? 0))) ?></strong>
            <span class="small muted" data-boss-countdown-hint></span>
          </div>
        </div>
      </div>

      <div class="boss-runtime-grid boss-runtime-grid--4">
        <div class="boss-runtime-card boss-runtime-card--wide">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.schedule')) ?></span>
          <strong class="boss-runtime-card__value boss-schedule-state boss-schedule-state--<?= htmlspecialchars($bossScheduleStateKey) ?>">
            <?= htmlspecialchars(__('app.boss.runtime.schedule_states.' . $bossScheduleStateKey)) ?>
          </strong>
          <span class="small muted">
            <?php if ($bossScheduleWindow !== ''): ?>
              <?= htmlspecialchars(__('app.boss.runtime.schedule_window')) ?>:
              <?= htmlspecialchars($bossScheduleWindow) ?>
            <?php endif; ?>
            <?php if ($bossScheduleNextChangeAt > 0): ?>
              <?= $bossScheduleWindow !== '' ? '·' : '' ?>
              <?= htmlspecialchars(__('app.boss.runtime.schedule_next_change')) ?>:
              <?= htmlspecialchars(format_datetime($bossScheduleNextChangeAt)) ?>
              <?php if ($bossScheduleNextInText !== ''): ?>(<?= htmlspecialchars($bossScheduleNextInText) ?>)<?php endif; ?>
            <?php endif; ?>
            <?php if ($bossScheduleWindow === '' && $bossScheduleNextChangeAt === 0): ?>
              <?= htmlspecialchars(__('app.boss.runtime.schedule_hint')) ?>
            <?php endif; ?>
          </span>
        </div>

        <div class="boss-runtime-card boss-runtime-card--wide">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.current_position')) ?></span>
          <?php if ($hasActiveBoss): ?>
            <strong class="boss-runtime-card__value"><?= htmlspecialchars($bossMapLabel) ?></strong>
            <span class="small muted">
              <?= htmlspecialchars(__('app.boss.runtime.instance_id')) ?>: #<?= $bossInstanceId ?> ·
              <?= htmlspecialchars(__('app.boss.runtime.coordinates')) ?>:
              <?= htmlspecialchars($bossHomeX) ?>, <?= htmlspecialchars($bossHomeY) ?>, <?= htmlspecialchars($bossHomeZ) ?>
            </span>
          <?php else: ?>
            <strong class="boss-runtime-card__value">-</strong>
          <?php endif; ?>
        </div>

        <div class="boss-runtime-card boss-runtime-card--wide">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.last_spawn_at')) ?> / <?= htmlspecialchars(__('app.boss.runtime.last_engage_at')) ?></span>
          <strong class="boss-runtime-card__value" data-boss-ago="<?= (int) ($bossRuntime['last_spawn_at'] ?? 0) ?>">
            <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_spawn_at'] ?? 0))) ?>
          </strong>
          <span class="small muted" data-boss-ago="<?= (int) ($bossRuntime['last_engage_at'] ?? 0) ?>">
            <?= htmlspecialchars(__('app.boss.runtime.last_engage_at')) ?>: <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_engage_at'] ?? 0))) ?>
          </span>
        </div>

        <div class="boss-runtime-card boss-runtime-card--wide">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.last_death_at')) ?> / <?= htmlspecialchars(__('app.boss.runtime.last_reset_at')) ?></span>
          <strong class="boss-runtime-card__value" data-boss-ago="<?= (int) ($bossRuntime['last_death_at'] ?? 0) ?>">
            <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_death_at'] ?? 0))) ?>
          </strong>
          <span class="small muted" data-boss-ago="<?= (int) ($bossRuntime['last_reset_at'] ?? 0) ?>">
            <?= htmlspecialchars(__('app.boss.runtime.last_reset_at')) ?>: <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_reset_at'] ?? 0))) ?>
          </span>
        </div>
      </div>
    </article>

    <article class="boss-panel boss-panel--stats">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.stats.title')) ?></h2>
        <span class="boss-muted"><?= htmlspecialchars(__('app.boss.stats.window')) ?></span>
      </div>
      <div class="boss-kpi-row">
        <article class="boss-kpi">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.events_24h')) ?></span>
          <strong class="boss-kpi__value"><?= (int) ($bossStats['events_24h'] ?? 0) ?></strong>
        </article>
        <article class="boss-kpi">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.kills_7d')) ?></span>
          <strong class="boss-kpi__value"><?= (int) ($bossStats['kills_7d'] ?? 0) ?></strong>
        </article>
        <article class="boss-kpi">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.contributors_7d')) ?></span>
          <strong class="boss-kpi__value"><?= (int) ($bossStats['contributors_7d'] ?? 0) ?></strong>
        </article>
        <article class="boss-kpi">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.random_rewarded_7d')) ?></span>
          <strong class="boss-kpi__value"><?= (int) ($bossStats['random_rewarded_7d'] ?? 0) ?></strong>
        </article>
      </div>
    </article>
  </section>
    </div>

  <?php if ($bossCanAct): ?>
    <div class="boss-tabpanel" role="tabpanel" data-boss-tabpanel="actions">
    <section class="boss-panel">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.actions.title')) ?></h2>
        <span class="boss-muted"><?= htmlspecialchars(__('app.boss.actions.note')) ?></span>
      </div>

      <div class="boss-action-group boss-action-group--danger">
        <div class="boss-action-group__head">
          <h3><?= htmlspecialchars(__('app.boss.actions.groups.combat')) ?></h3>
          <p class="muted"><?= htmlspecialchars(__('app.boss.actions.groups.combat_help')) ?></p>
        </div>
        <div class="boss-action-grid">
          <div class="boss-action-card">
            <div class="boss-action-card__body">
              <strong><?= htmlspecialchars(__('app.boss.actions.spawn')) ?></strong>
              <p class="muted"><?= htmlspecialchars(__('app.boss.actions.spawn_help')) ?></p>
            </div>
            <button type="button" class="btn warn" id="bossSpawnBtn" data-boss-action="spawn">
              <?= htmlspecialchars(__('app.boss.actions.spawn')) ?>
            </button>
          </div>

          <div class="boss-action-card boss-action-card--danger">
            <div class="boss-action-card__body">
              <strong><?= htmlspecialchars(__('app.boss.actions.kill')) ?></strong>
              <p class="muted"><?= htmlspecialchars(__('app.boss.actions.kill_help')) ?></p>
            </div>
            <button type="button" class="btn danger" id="bossKillBtn" data-boss-action="kill">
              <?= htmlspecialchars(__('app.boss.actions.kill')) ?>
            </button>
          </div>

          <div class="boss-action-card boss-action-card--danger">
            <div class="boss-action-card__body">
              <strong><?= htmlspecialchars(__('app.boss.actions.clear')) ?></strong>
              <p class="muted"><?= htmlspecialchars(__('app.boss.actions.clear_help')) ?></p>
            </div>
            <button type="button" class="btn outline danger" id="bossClearBtn" data-boss-action="clear">
              <?= htmlspecialchars(__('app.boss.actions.clear')) ?>
            </button>
          </div>
        </div>
      </div>

      <div class="boss-action-group">
        <div class="boss-action-group__head">
          <h3><?= htmlspecialchars(__('app.boss.actions.groups.runtime')) ?></h3>
          <p class="muted"><?= htmlspecialchars(__('app.boss.actions.groups.runtime_help')) ?></p>
        </div>
        <div class="boss-action-grid">
          <div class="boss-action-card boss-action-card--form">
            <label class="boss-field">
              <span><?= htmlspecialchars(__('app.boss.actions.preset_label')) ?></span>
              <select id="bossPresetSelect">
                <?php foreach (($bossOptions['presets'] ?? []) as $option): ?>
                  <option
                    value="<?= htmlspecialchars((string) ($option['value'] ?? '')) ?>"
                    title="<?= htmlspecialchars((string) ($option['summary'] ?? '')) ?>"
                    <?= (string) ($bossRuntime['skill_preset'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                  ><?= htmlspecialchars((string) ($option['label'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button type="button" class="btn" id="bossPresetBtn" data-boss-action="preset">
              <?= htmlspecialchars(__('app.boss.actions.apply_preset')) ?>
            </button>
          </div>

          <div class="boss-action-card boss-action-card--form">
            <label class="boss-field">
              <span><?= htmlspecialchars(__('app.boss.actions.difficulty_label')) ?></span>
              <select id="bossDifficultySelect">
                <?php foreach (($bossOptions['difficulties'] ?? []) as $option): ?>
                  <option
                    value="<?= htmlspecialchars((string) ($option['value'] ?? '')) ?>"
                    title="<?= htmlspecialchars((string) ($option['summary'] ?? '')) ?>"
                    <?= (string) ($bossRuntime['skill_difficulty'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                  ><?= htmlspecialchars((string) ($option['label'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button type="button" class="btn" id="bossDifficultyBtn" data-boss-action="difficulty">
              <?= htmlspecialchars(__('app.boss.actions.apply_difficulty')) ?>
            </button>
          </div>
        </div>
      </div>

      <div class="boss-action-group">
        <div class="boss-action-group__head">
          <h3><?= htmlspecialchars(__('app.boss.actions.groups.maintenance')) ?></h3>
          <p class="muted"><?= htmlspecialchars(__('app.boss.actions.groups.maintenance_help')) ?></p>
        </div>
        <div class="boss-action-grid">
          <div class="boss-action-card">
            <div class="boss-action-card__body">
              <strong><?= htmlspecialchars(__('app.boss.actions.rebase')) ?></strong>
              <p class="muted"><?= htmlspecialchars(__('app.boss.actions.rebase_help')) ?></p>
            </div>
            <button type="button" class="btn outline" id="bossRebaseBtn" data-boss-action="rebase">
              <?= htmlspecialchars(__('app.boss.actions.rebase')) ?>
            </button>
          </div>

          <div class="boss-action-card">
            <div class="boss-action-card__body">
              <strong><?= htmlspecialchars(__('app.boss.actions.reload_config')) ?></strong>
              <p class="muted"><?= htmlspecialchars(__('app.boss.actions.reload_config_help')) ?></p>
            </div>
            <button type="button" class="btn outline" id="bossConfigReloadBtn" data-boss-action="config_reload">
              <?= htmlspecialchars(__('app.boss.actions.reload_config')) ?>
            </button>
          </div>
        </div>
      </div>
    </section>
    </div>

    <div class="boss-tabpanel" role="tabpanel" data-boss-tabpanel="config">
    <section class="boss-panel boss-panel--config">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.config.title')) ?></h2>
      </div>
      <p class="muted boss-config-note">
        <?= htmlspecialchars(__('app.boss.config.note_short')) ?>
        <span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.config.note')) ?>">i</span>
      </p>

      <form id="bossConfigForm" class="boss-config-form">
        <div class="boss-config-grid">
          <section class="boss-config-section">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.sections.identity')) ?></h3>
              <span class="boss-config-section__count"><?= htmlspecialchars(__('app.boss.config.item_count', ['count' => '5'])) ?></span>
            </div>
            <div class="boss-config-columns">
              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_entry')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.config.hints.boss_entry')) ?>">i</span></span>
                <select
                  name="boss_entry"
                  id="bossTierSelect"
                  data-boss-tier-select
                  data-boss-base-hp="<?= $bossTierBaseHp ?>"
                  data-boss-hp-scale="<?= $bossTierDecimalScale ?>"
                >
                  <?php foreach ($bossTierItems as $bossTierItem): ?>
                    <?php if (!is_array($bossTierItem)) continue; ?>
                    <?php $bossTierEntry = (int) ($bossTierItem['entry'] ?? 0); ?>
                    <option
                      value="<?= $bossTierEntry ?>"
                      data-boss-health-modifier="<?= htmlspecialchars((string) ($bossTierItem['health_modifier'] ?? 0)) ?>"
                      data-boss-estimated-hp="<?= (int) ($bossTierItem['estimated_hp'] ?? 0) ?>"
                      <?= $bossTierEntry === $bossCurrentTierEntry ? 'selected' : '' ?>
                    ><?= htmlspecialchars((string) ($bossTierItem['label'] ?? $bossTierEntry)) ?> · <?= htmlspecialchars(__('app.boss.fields.estimated_hp')) ?> <?= htmlspecialchars(number_format((int) ($bossTierItem['estimated_hp'] ?? 0), 0, '.', ',')) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_name')) ?></span>
                <input type="text" name="boss_name" maxlength="120" value="<?= htmlspecialchars((string) ($bossConfig['boss_name'] ?? '')) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_level')) ?></span>
                <input type="number" name="boss_level" min="1" max="255" step="1" value="<?= htmlspecialchars((string) ($bossConfig['boss_level'] ?? 83)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_scale')) ?><span class="boss-field__unit">×</span></span>
                <input type="number" name="boss_scale" min="0.10" max="50.00" step="0.01" value="<?= htmlspecialchars((string) ($bossConfig['boss_scale'] ?? '5.00')) ?>">
              </label>

              <label class="boss-field boss-field--full">
                <span>
                  <?= htmlspecialchars(__('app.boss.config.fields.boss_auras_text')) ?>
                  <span class="boss-field__unit"><?= htmlspecialchars(__('app.boss.config.aura_count', ['count' => (string) $bossAuraCount])) ?></span>
                </span>
                <textarea name="boss_auras_text" rows="3" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.id_list')) ?>"><?= htmlspecialchars((string) ($bossConfig['boss_auras_text'] ?? '')) ?></textarea>
                <small class="muted"><?= htmlspecialchars(__('app.boss.config.hints.boss_auras_text')) ?></small>
              </label>
            </div>
          </section>

          <section class="boss-config-section">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.sections.combat')) ?></h3>
              <span class="boss-config-section__count"><?= htmlspecialchars(__('app.boss.config.item_count', ['count' => '4'])) ?></span>
            </div>
            <div class="boss-config-columns">
              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_health_multiplier')) ?><span class="boss-field__unit">×</span></span>
                <input type="number" name="boss_health_multiplier" id="bossHealthMultiplierInput" min="0.10" max="2000.00" step="0.01" value="<?= htmlspecialchars((string) ($bossConfig['boss_health_multiplier'] ?? '20.00')) ?>">
              </label>

              <div class="boss-field boss-field--readonly">
                <span><?= htmlspecialchars(__('app.boss.fields.estimated_hp')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.config.hints.estimated_hp')) ?>">i</span></span>
                <strong
                  class="boss-estimated-hp"
                  id="bossEstimatedHp"
                  data-boss-hp-preview
                  data-boss-base-hp="<?= $bossTierBaseHp ?>"
                  data-boss-hp-scale="<?= $bossTierDecimalScale ?>"
                ><?= htmlspecialchars(number_format($bossCurrentEstimatedHp, 0, '.', ',')) ?></strong>
              </div>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.skill_preset')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.config.hints.skill_preset')) ?>">i</span></span>
                <select name="skill_preset">
                  <?php foreach (($bossOptions['presets'] ?? []) as $option): ?>
                    <option
                      value="<?= htmlspecialchars((string) ($option['value'] ?? '')) ?>"
                      <?= (string) ($bossConfig['skill_preset'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                    ><?= htmlspecialchars((string) ($option['label'] ?? '')) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.skill_difficulty')) ?></span>
                <select name="skill_difficulty">
                  <?php foreach (($bossOptions['difficulties'] ?? []) as $option): ?>
                    <option
                      value="<?= htmlspecialchars((string) ($option['value'] ?? '')) ?>"
                      <?= (string) ($bossConfig['skill_difficulty'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                    ><?= htmlspecialchars((string) ($option['label'] ?? '')) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </section>

          <section class="boss-config-section">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.sections.ally')) ?></h3>
              <span class="boss-config-section__count"><?= htmlspecialchars(__('app.boss.config.item_count', ['count' => '5'])) ?></span>
            </div>
            <div class="boss-config-columns">
              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.respawn_time_minutes')) ?></span>
                <input type="number" name="respawn_time_minutes" min="1" max="1440" step="1" value="<?= htmlspecialchars((string) ($bossConfig['respawn_time_minutes'] ?? 10)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.ally_level')) ?></span>
                <input type="number" name="ally_level" min="1" max="255" step="1" value="<?= htmlspecialchars((string) ($bossConfig['ally_level'] ?? 20)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.ally_health_multiplier')) ?><span class="boss-field__unit">×</span></span>
                <input type="number" name="ally_health_multiplier" min="0.10" max="2000.00" step="0.01" value="<?= htmlspecialchars((string) ($bossConfig['ally_health_multiplier'] ?? '1.50')) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.minion_count_min')) ?></span>
                <input type="number" name="minion_count_min" min="0" max="20" step="1" value="<?= htmlspecialchars((string) ($bossConfig['minion_count_min'] ?? 1)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.minion_count_max')) ?></span>
                <input type="number" name="minion_count_max" min="0" max="20" step="1" value="<?= htmlspecialchars((string) ($bossConfig['minion_count_max'] ?? 2)) ?>">
              </label>
            </div>
          </section>

          <section class="boss-config-section">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.sections.rewards')) ?></h3>
              <button type="button" class="btn outline" data-boss-goto="ext:reward_pools">
                <?= htmlspecialchars(__('app.boss.config.goto_rewards')) ?>
              </button>
            </div>
            <p class="muted boss-config-note">
              <?= htmlspecialchars(__('app.boss.config.hints.reward_pools_moved_short')) ?>
              <span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.config.hints.reward_pools_moved')) ?>">i</span>
            </p>
            <div class="boss-config-columns">
              <div class="boss-field boss-field--readonly">
                <span><?= htmlspecialchars(__('app.boss.config.fields.random_reward_mode')) ?></span>
                <strong class="boss-estimated-hp">
                  <?php
                    $bossCurrentRewardMode = (string) ($bossConfig['random_reward_mode'] ?? 'weighted');
                    $bossCurrentRewardModeLabel = $bossCurrentRewardMode;
                    foreach (($bossOptions['random_modes'] ?? []) as $bossRandomModeOption) {
                        if ((string) ($bossRandomModeOption['value'] ?? '') === $bossCurrentRewardMode) {
                            $bossCurrentRewardModeLabel = (string) ($bossRandomModeOption['label'] ?? $bossCurrentRewardMode);
                        }
                    }
                  ?>
                  <?= htmlspecialchars($bossCurrentRewardModeLabel) ?>
                </strong>
              </div>
              <div class="boss-field boss-field--readonly">
                <span><?= htmlspecialchars(__('app.boss.config.fields.participation_range')) ?></span>
                <strong class="boss-estimated-hp"><?= (int) ($bossConfig['participation_range'] ?? 80) ?></strong>
              </div>
              <div class="boss-field boss-field--readonly boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.weights_summary')) ?></span>
                <strong class="boss-estimated-hp">
                  <?= htmlspecialchars(__('app.boss.config.fields.damage_weight')) ?> <?= (int) ($bossConfig['damage_weight'] ?? 100) ?> ·
                  <?= htmlspecialchars(__('app.boss.config.fields.healing_weight')) ?> <?= (int) ($bossConfig['healing_weight'] ?? 80) ?> ·
                  <?= htmlspecialchars(__('app.boss.config.fields.threat_weight')) ?> <?= (int) ($bossConfig['threat_weight'] ?? 35) ?> ·
                  <?= htmlspecialchars(__('app.boss.config.fields.presence_weight')) ?> <?= (int) ($bossConfig['presence_weight'] ?? 10) ?> ·
                  <?= htmlspecialchars(__('app.boss.config.fields.kill_weight')) ?> <?= (int) ($bossConfig['kill_weight'] ?? 3) ?>
                </strong>
              </div>
            </div>
          </section>

          <section class="boss-config-section boss-config-section--full">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.fields.spawn_points_text')) ?></h3>
              <span class="boss-config-section__count">
                <?= htmlspecialchars(__('app.boss.config.spawn_preview', [
                    'count' => (string) $bossSpawnLineCount,
                    'maps' => $bossSpawnMapLabels !== [] ? implode('、', $bossSpawnMapLabels) : __('app.boss.config.spawn_preview_none'),
                ])) ?>
              </span>
            </div>
            <div class="boss-config-columns">
              <label class="boss-field boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.fields.spawn_points_text')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.config.hints.spawn_points_text')) ?>">i</span></span>
                <textarea name="spawn_points_text" rows="6" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.spawn_point_line')) ?>"><?= htmlspecialchars((string) ($bossConfig['spawn_points_text'] ?? '')) ?></textarea>
              </label>
            </div>
          </section>
        </div>

        <div class="boss-save-bar" data-boss-save-bar="config">
          <span class="boss-save-bar__meta" data-boss-dirty-label><?= htmlspecialchars(__('app.boss.config.no_changes')) ?></span>
          <div class="boss-save-bar__actions">
            <button type="button" class="btn outline" data-boss-discard disabled>
              <?= htmlspecialchars(__('app.boss.config.discard')) ?>
            </button>
            <button type="submit" class="btn warn" id="bossConfigSaveBtn">
              <?= htmlspecialchars(__('app.boss.config.save')) ?>
            </button>
          </div>
        </div>
      </form>
    </section>
    </div>

    <div class="boss-tabpanel" role="tabpanel" data-boss-tabpanel="ext">
      <?php include __DIR__ . '/_ext_config.php'; ?>
    </div>
  <?php endif; ?>

  <div class="boss-tabpanel" role="tabpanel" data-boss-tabpanel="log">
  <section class="boss-bottom-grid">
    <?php if ($bossCapabilities['events']): ?>
      <article class="boss-panel boss-panel--table">
        <div class="boss-panel__head">
          <h2><?= htmlspecialchars(__('app.boss.events.title')) ?></h2>
          <span class="boss-muted"><?= htmlspecialchars(__('app.boss.events.limit_note', ['count' => (string) $event_limit])) ?></span>
        </div>
        <div class="boss-table-filters" data-boss-table-filter="events" role="group" aria-label="<?= htmlspecialchars(__('app.boss.log.filters_label')) ?>">
          <button type="button" class="boss-chip is-active" data-boss-event-filter="all"><?= htmlspecialchars(__('app.boss.log.filters.all')) ?></button>
          <button type="button" class="boss-chip" data-boss-event-filter="death"><?= htmlspecialchars(__('app.boss.log.filters.death')) ?></button>
          <button type="button" class="boss-chip" data-boss-event-filter="spawn"><?= htmlspecialchars(__('app.boss.log.filters.spawn')) ?></button>
          <button type="button" class="boss-chip" data-boss-event-filter="reward"><?= htmlspecialchars(__('app.boss.log.filters.reward')) ?></button>
          <button type="button" class="boss-chip" data-boss-event-filter="schedule"><?= htmlspecialchars(__('app.boss.log.filters.schedule')) ?></button>
          <button type="button" class="boss-chip" data-boss-event-filter="command"><?= htmlspecialchars(__('app.boss.log.filters.command')) ?></button>
        </div>
        <div class="boss-table-wrap">
          <table class="table boss-table boss-table--sticky" data-boss-events-table>
            <thead>
              <tr>
                <th><?= htmlspecialchars(__('app.boss.events.columns.time')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.events.columns.type')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.events.columns.boss')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.events.columns.actor')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.events.columns.note')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($bossEvents === []): ?>
                <tr>
                  <td colspan="5" class="text-center muted"><?= htmlspecialchars(__('app.boss.events.empty')) ?></td>
                </tr>
              <?php endif; ?>
              <?php foreach ($bossEvents as $event): ?>
                <?php $bossEventType = (string) ($event['event_type'] ?? ''); ?>
                <tr data-boss-event-type="<?= htmlspecialchars($bossEventType, ENT_QUOTES, 'UTF-8') ?>">
                  <td><span data-boss-ago="<?= (int) ($event['created_at'] ?? 0) ?>"><?= htmlspecialchars(format_datetime((int) ($event['created_at'] ?? 0))) ?></span></td>
                  <td>
                    <span class="boss-event-type">
                      <?= htmlspecialchars(__('app.boss.events.types.' . $bossEventType, [], $bossEventType)) ?>
                    </span>
                  </td>
                  <td>
                    <div class="boss-cell-title"><?= htmlspecialchars((string) ($event['boss_name'] ?? '-')) ?></div>
                    <div class="small muted">Entry #<?= (int) ($event['boss_entry'] ?? 0) ?></div>
                  </td>
                  <td>
                    <?php $actorName = trim((string) ($event['actor_name'] ?? '')); ?>
                    <?= htmlspecialchars($actorName !== '' ? $actorName : '-') ?>
                  </td>
                  <td><?= htmlspecialchars((string) ($event['event_note'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
    <?php else: ?>
      <article class="boss-panel">
        <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
          <?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.boss.events.title')])) ?>
        </div>
      </article>
    <?php endif; ?>

    <?php if ($bossCapabilities['contributors']): ?>
      <article class="boss-panel boss-panel--table">
        <div class="boss-panel__head">
          <h2><?= htmlspecialchars(__('app.boss.contributors.title')) ?></h2>
          <span class="boss-muted"><?= htmlspecialchars(__('app.boss.contributors.limit_note', ['count' => (string) $contributor_limit])) ?></span>
        </div>
        <div class="boss-table-filters" data-boss-table-filter="contributors" role="group" aria-label="<?= htmlspecialchars(__('app.boss.log.filters_label')) ?>">
          <button type="button" class="boss-chip is-active" data-boss-contributor-filter="all"><?= htmlspecialchars(__('app.boss.log.filters.all')) ?></button>
          <button type="button" class="boss-chip" data-boss-contributor-filter="rewarded"><?= htmlspecialchars(__('app.boss.log.filters.rewarded_only')) ?></button>
          <button type="button" class="boss-chip" data-boss-contributor-filter="killer"><?= htmlspecialchars(__('app.boss.log.filters.killer_only')) ?></button>
        </div>
        <div class="boss-table-wrap">
          <table class="table boss-table boss-table--sticky" data-boss-contributors-table>
            <thead>
              <tr>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.time')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.player')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.boss')) ?></th>
                <th class="boss-num"><?= htmlspecialchars(__('app.boss.contributors.columns.score')) ?></th>
                <th class="boss-num"><?= htmlspecialchars(__('app.boss.contributors.columns.damage')) ?></th>
                <th class="boss-num"><?= htmlspecialchars(__('app.boss.contributors.columns.healing')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.rewards')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($bossContributors === []): ?>
                <tr>
                  <td colspan="7" class="text-center muted"><?= htmlspecialchars(__('app.boss.contributors.empty')) ?></td>
                </tr>
              <?php endif; ?>
              <?php foreach ($bossContributors as $row): ?>
                <?php
                  $bossRowRewardPools = is_array($row['reward_pools'] ?? null) ? $row['reward_pools'] : [];
                  $bossRowRewarded = !empty($row['rewarded_random']) || $bossRowRewardPools !== [];
                ?>
                <tr class="<?= $bossRowRewarded ? 'boss-row--rewarded' : '' ?>"
                    data-boss-rewarded="<?= $bossRowRewarded ? '1' : '0' ?>"
                    data-boss-killer="<?= !empty($row['was_killer']) ? '1' : '0' ?>">
                  <td><span data-boss-ago="<?= (int) ($row['created_at'] ?? 0) ?>"><?= htmlspecialchars(format_datetime((int) ($row['created_at'] ?? 0))) ?></span></td>
                  <td>
                    <div class="boss-cell-title"><?= character_link((int) ($row['player_guid'] ?? 0), (string) ($row['player_name'] ?? '')) ?></div>
                    <div class="small muted">GUID #<?= (int) ($row['player_guid'] ?? 0) ?> · <?= account_link((int) ($row['account_id'] ?? 0), 'Account #' . (int) ($row['account_id'] ?? 0)) ?></div>
                  </td>
                  <td>
                    <div class="boss-cell-title"><?= htmlspecialchars((string) ($row['boss_name'] ?? '-')) ?></div>
                    <div class="small muted">GUID #<?= (int) ($row['boss_guid'] ?? 0) ?></div>
                  </td>
                  <td class="boss-num"><?= htmlspecialchars(number_format((float) ($row['contribution_score'] ?? 0), 2)) ?></td>
                  <td class="boss-num"><?= htmlspecialchars(number_format((int) ($row['damage_done'] ?? 0), 0, '.', ',')) ?></td>
                  <td class="boss-num"><?= htmlspecialchars(number_format((int) ($row['healing_done'] ?? 0), 0, '.', ',')) ?></td>
                  <td>
                    <div class="boss-badge-list">
                      <?php if ($bossRowRewardPools !== []): ?>
                        <span class="badge badge--warn" title="<?= htmlspecialchars(__('app.boss.contributors.badges.reward_pool_hint')) ?>">
                          <?= htmlspecialchars(__('app.boss.contributors.badges.reward_pool_list', ['pools' => implode('·', array_map('strval', $bossRowRewardPools))])) ?>
                        </span>
                      <?php elseif (!empty($row['rewarded_random'])): ?>
                        <span class="badge badge--warn"><?= htmlspecialchars(__('app.boss.contributors.badges.random_reward')) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($row['guaranteed_reward'])): ?>
                        <span class="badge"><?= htmlspecialchars(__('app.boss.contributors.badges.guaranteed')) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($row['was_killer'])): ?>
                        <span class="badge badge--danger"><?= htmlspecialchars(__('app.boss.contributors.badges.last_hit')) ?></span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
    <?php else: ?>
      <article class="boss-panel">
        <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
          <?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.boss.contributors.title')])) ?>
        </div>
      </article>
    <?php endif; ?>
  </section>
  </div>
  </div>
</div>