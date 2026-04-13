<?php

$aegis_data = $aegis_data ?? [];
$options = $aegis_data['options'] ?? [];
$defaults = $aegis_data['defaults'] ?? [];
$aegisCapabilities = is_array($__pageCapabilities ?? null)
  ? $__pageCapabilities
  : [
    'dashboard' => $__can('aegis.dashboard'),
    'overview' => $__can('aegis.overview'),
    'offenses' => $__can('aegis.offenses'),
    'events' => $__can('aegis.events'),
    'player' => $__can('aegis.player'),
    'actions' => $__can('aegis.actions'),
    'logs' => $__can('aegis.logs'),
  ];
$__pageCapabilities = $aegisCapabilities;
$capabilityNotice = $__canAll(['aegis.overview', 'aegis.offenses', 'aegis.events', 'aegis.player', 'aegis.actions', 'aegis.logs'])
    ? null
    : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="aegis-layout">
  <?php if($aegisCapabilities['overview']): ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="aegis-toolbar">
      <div class="aegis-toolbar__group">
        <label class="aegis-inline-field">
          <span><?= htmlspecialchars(__('app.aegis.overview.days')) ?></span>
          <select id="aegisOverviewDays">
            <?php foreach ([1, 3, 7, 14, 30] as $days): ?>
              <option value="<?= $days ?>" <?= $days === (int) ($defaults['overview_days'] ?? 7) ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.aegis.overview.days_value', ['days' => $days])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="aegis-toolbar__group">
        <button type="button" class="btn" id="aegisRefreshAllBtn"><?= htmlspecialchars(__('app.aegis.actions.refresh_all')) ?></button>
      </div>
    </div>

    <div id="aegisFeedback" class="panel-flash panel-flash--inline aegis-feedback-hidden"></div>

    <div class="aegis-stats" id="aegisStatsGrid">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <article class="aegis-stat-card aegis-stat-card--loading">
          <div class="aegis-stat-card__label"><?= htmlspecialchars(__('app.common.loading')) ?></div>
          <div class="aegis-stat-card__value">--</div>
        </article>
      <?php endfor; ?>
    </div>

    <div class="aegis-summary-grid">
      <article class="aegis-summary-card">
        <div class="aegis-summary-card__head">
          <h2><?= htmlspecialchars(__('app.aegis.overview.stage_distribution')) ?></h2>
        </div>
        <div id="aegisStageSummary" class="aegis-badge-list"></div>
      </article>
      <article class="aegis-summary-card">
        <div class="aegis-summary-card__head">
          <h2><?= htmlspecialchars(__('app.aegis.overview.cheat_distribution')) ?></h2>
        </div>
        <div id="aegisCheatSummary" class="aegis-badge-list"></div>
      </article>
      <article class="aegis-summary-card">
        <div class="aegis-summary-card__head">
          <h2><?= htmlspecialchars(__('app.aegis.overview.top_offenders')) ?></h2>
        </div>
        <div id="aegisTopOffenders" class="aegis-list-stack"></div>
      </article>
    </div>
  </section>
  <?php else: ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.aegis.overview.stage_distribution')])) ?></div>
  </section>
  <?php endif; ?>

  <?php if($aegisCapabilities['player']): ?>
  <section class="aegis-panel">
    <div class="aegis-panel__head">
      <h2><?= htmlspecialchars(__('app.aegis.player.title')) ?></h2>
    </div>
    <form id="aegisPlayerForm" class="aegis-form-grid">
      <label class="aegis-field aegis-field--span-2">
        <span><?= htmlspecialchars(__('app.aegis.player.lookup_label')) ?></span>
        <input type="text" id="aegisPlayerLookup" placeholder="<?= htmlspecialchars(__('app.aegis.player.lookup_placeholder')) ?>">
      </label>
      <div class="aegis-form-actions aegis-field--span-2">
        <button type="submit" class="btn"><?= htmlspecialchars(__('app.aegis.player.lookup_submit')) ?></button>
      </div>
    </form>
    <div id="aegisPlayerCard" class="aegis-player-card aegis-player-card--empty">
      <?= htmlspecialchars(__('app.aegis.player.empty')) ?>
    </div>
  </section>
  <?php else: ?>
  <section class="aegis-panel">
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.aegis.player.title')])) ?></div>
  </section>
  <?php endif; ?>

  <?php if($aegisCapabilities['actions']): ?>
  <section class="aegis-panel">
    <div class="aegis-panel__head">
      <h2><?= htmlspecialchars(__('app.aegis.manual.title')) ?></h2>
    </div>
    <form id="aegisManualForm" class="aegis-form-grid">
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.manual.action_label')) ?></span>
        <select id="aegisManualAction" name="action">
          <?php foreach (($options['manual_actions'] ?? []) as $action): ?>
            <option value="<?= htmlspecialchars((string) ($action['value'] ?? '')) ?>" data-needs-target="<?= !empty($action['needs_target']) ? '1' : '0' ?>">
              <?= htmlspecialchars((string) ($action['label'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.manual.target_label')) ?></span>
        <input type="text" id="aegisManualTarget" name="target" placeholder="<?= htmlspecialchars(__('app.aegis.manual.target_placeholder')) ?>">
      </label>
      <div class="aegis-form-actions aegis-field--span-2">
        <button type="submit" class="btn warn" id="aegisManualSubmit"><?= htmlspecialchars(__('app.aegis.manual.submit')) ?></button>
      </div>
    </form>
    <ul class="aegis-help-list">
      <li><?= htmlspecialchars(__('app.aegis.manual.help.clear')) ?></li>
      <li><?= htmlspecialchars(__('app.aegis.manual.help.delete')) ?></li>
      <li><?= htmlspecialchars(__('app.aegis.manual.help.reload')) ?></li>
      <li><?= htmlspecialchars(__('app.aegis.manual.help.purge')) ?></li>
    </ul>
  </section>
  <?php else: ?>
  <section class="aegis-panel">
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.aegis.manual.title')])) ?></div>
  </section>
  <?php endif; ?>

  <?php if($aegisCapabilities['offenses']): ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="aegis-panel__head">
      <h2><?= htmlspecialchars(__('app.aegis.offense.title')) ?></h2>
    </div>
    <form id="aegisOffenseForm" class="aegis-form-grid aegis-form-grid--dense">
      <label class="aegis-field aegis-field--span-2">
        <span><?= htmlspecialchars(__('app.aegis.filters.query')) ?></span>
        <input type="text" name="query" id="aegisOffenseQuery" placeholder="<?= htmlspecialchars(__('app.aegis.filters.query_placeholder')) ?>">
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.filters.stage')) ?></span>
        <select name="stage" id="aegisOffenseStage">
          <option value=""><?= htmlspecialchars(__('app.aegis.filters.all')) ?></option>
          <?php foreach (($options['punish_stages'] ?? []) as $stage): ?>
            <option value="<?= (int) ($stage['value'] ?? 0) ?>"><?= htmlspecialchars((string) ($stage['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.filters.cheat_type')) ?></span>
        <select name="cheat_type" id="aegisOffenseCheatType">
          <option value="0"><?= htmlspecialchars(__('app.aegis.filters.all')) ?></option>
          <?php foreach (($options['cheat_types'] ?? []) as $type): ?>
            <?php if ((int) ($type['value'] ?? 0) === 0) { continue; } ?>
            <option value="<?= (int) ($type['value'] ?? 0) ?>"><?= htmlspecialchars((string) ($type['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.filters.status_label')) ?></span>
        <select name="status" id="aegisOffenseStatus">
          <?php foreach (($options['status_filters'] ?? []) as $status): ?>
            <option value="<?= htmlspecialchars((string) ($status['value'] ?? '')) ?>"><?= htmlspecialchars((string) ($status['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="aegis-form-actions">
        <button type="submit" class="btn"><?= htmlspecialchars(__('app.aegis.actions.search')) ?></button>
      </div>
    </form>
    <div class="aegis-table-wrap">
      <table class="table aegis-table">
        <thead>
          <tr>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.player')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.account')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.cheat')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.stage')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.offense_count')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.tier')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.last_reason')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.last_offense_at')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.offense.columns.actions')) ?></th>
          </tr>
        </thead>
        <tbody id="aegisOffenseTableBody">
          <tr><td colspan="9" class="text-center muted"><?= htmlspecialchars(__('app.common.loading')) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="aegis-pagination" id="aegisOffensePagination"></div>
  </section>
  <?php else: ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.aegis.offense.title')])) ?></div>
  </section>
  <?php endif; ?>

  <?php if($aegisCapabilities['events']): ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="aegis-panel__head">
      <h2><?= htmlspecialchars(__('app.aegis.event.title')) ?></h2>
    </div>
    <form id="aegisEventForm" class="aegis-form-grid aegis-form-grid--dense">
      <label class="aegis-field aegis-field--span-2">
        <span><?= htmlspecialchars(__('app.aegis.filters.query')) ?></span>
        <input type="text" name="query" id="aegisEventQuery" placeholder="<?= htmlspecialchars(__('app.aegis.filters.query_placeholder')) ?>">
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.filters.cheat_type')) ?></span>
        <select name="cheat_type" id="aegisEventCheatType">
          <option value="0"><?= htmlspecialchars(__('app.aegis.filters.all')) ?></option>
          <?php foreach (($options['cheat_types'] ?? []) as $type): ?>
            <?php if ((int) ($type['value'] ?? 0) === 0) { continue; } ?>
            <option value="<?= (int) ($type['value'] ?? 0) ?>"><?= htmlspecialchars((string) ($type['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.filters.evidence_level')) ?></span>
        <select name="evidence_level" id="aegisEventEvidenceLevel">
          <option value=""><?= htmlspecialchars(__('app.aegis.filters.all')) ?></option>
          <?php foreach (($options['evidence_levels'] ?? []) as $level): ?>
            <option value="<?= (int) ($level['value'] ?? 0) ?>"><?= htmlspecialchars((string) ($level['label'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="aegis-field">
        <span><?= htmlspecialchars(__('app.aegis.filters.days')) ?></span>
        <select name="days" id="aegisEventDays">
          <?php foreach ([1, 3, 7, 14, 30] as $days): ?>
            <option value="<?= $days ?>" <?= $days === (int) ($defaults['events_days'] ?? 7) ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.aegis.overview.days_value', ['days' => $days])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="aegis-form-actions">
        <button type="submit" class="btn"><?= htmlspecialchars(__('app.aegis.actions.search')) ?></button>
      </div>
    </form>
    <div class="aegis-table-wrap">
      <table class="table aegis-table">
        <thead>
          <tr>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.time')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.player')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.account')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.cheat')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.level')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.tag')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.risk')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.position')) ?></th>
            <th><?= htmlspecialchars(__('app.aegis.event.columns.detail')) ?></th>
          </tr>
        </thead>
        <tbody id="aegisEventTableBody">
          <tr><td colspan="9" class="text-center muted"><?= htmlspecialchars(__('app.common.loading')) ?></td></tr>
        </tbody>
      </table>
    </div>
    <div class="aegis-pagination" id="aegisEventPagination"></div>
  </section>
  <?php else: ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.aegis.event.title')])) ?></div>
  </section>
  <?php endif; ?>

  <?php if($aegisCapabilities['logs']): ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="aegis-panel__head">
      <h2><?= htmlspecialchars(__('app.aegis.log.title')) ?></h2>
      <button type="button" class="btn outline" id="aegisLogRefreshBtn"><?= htmlspecialchars(__('app.aegis.log.refresh')) ?></button>
    </div>
    <div class="aegis-log-meta" id="aegisLogMeta"><?= htmlspecialchars(__('app.aegis.log.meta_empty')) ?></div>
    <pre class="aegis-log-box" id="aegisLogBox"><?= htmlspecialchars(__('app.aegis.log.empty')) ?></pre>
  </section>
  <?php else: ?>
  <section class="aegis-panel aegis-panel--full">
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.aegis.log.title')])) ?></div>
  </section>
  <?php endif; ?>
</div>

<script type="application/json" data-panel-json data-global="AEGIS_DATA"><?= json_encode($aegis_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>