<?php

$bossDashboard = is_array($boss_dashboard ?? null) ? $boss_dashboard : [];
$bossRuntime = is_array($bossDashboard['runtime'] ?? null)
    ? $bossDashboard['runtime']
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
$bossWarnings = is_array($bossDashboard['warnings'] ?? null)
    ? $bossDashboard['warnings']
    : [];
$bossOptions = is_array($boss_options ?? null) ? $boss_options : [];
$bossCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
        'dashboard' => $__can('boss.dashboard'),
        'events' => $__can('boss.events'),
        'contributors' => $__can('boss.contributors'),
        'actions' => $__can('boss.actions'),
    ];
$__pageCapabilities = $bossCapabilities;
$capabilityNotice = $__canAll(['boss.events', 'boss.contributors', 'boss.actions'])
    ? null
    : __('app.common.capabilities.page_limited');
$bossName = trim((string) ($bossRuntime['boss_name'] ?? ''));
$hasActiveBoss = (int) ($bossRuntime['boss_guid'] ?? 0) > 0;
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="boss-page">
  <div id="bossFeedback" class="panel-flash panel-flash--inline" hidden></div>

  <?php foreach ($bossWarnings as $warning): ?>
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
      <?= htmlspecialchars((string) $warning) ?>
    </div>
  <?php endforeach; ?>

  <section class="boss-top-grid">
    <article class="boss-panel boss-panel--runtime">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.runtime.title')) ?></h2>
      </div>
      <div class="boss-runtime-grid">
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.status')) ?></span>
          <strong class="boss-runtime-card__value boss-status boss-status--<?= htmlspecialchars((string) ($bossRuntime['status'] ?? 'idle')) ?>">
            <?= htmlspecialchars(__('app.boss.status.' . ($bossRuntime['status'] ?? 'idle'))) ?>
          </strong>
        </div>
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.current_boss')) ?></span>
          <strong class="boss-runtime-card__value">
            <?= htmlspecialchars($hasActiveBoss ? $bossName : __('app.boss.runtime.no_active_boss')) ?>
          </strong>
          <?php if ($hasActiveBoss): ?>
            <span class="small muted">GUID #<?= (int) ($bossRuntime['boss_guid'] ?? 0) ?> · Entry #<?= (int) ($bossRuntime['boss_entry'] ?? 0) ?></span>
          <?php endif; ?>
        </div>
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.phase')) ?></span>
          <strong class="boss-runtime-card__value"><?= (int) ($bossRuntime['phase'] ?? 0) > 0 ? (int) $bossRuntime['phase'] : '-' ?></strong>
        </div>
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.skill_preset')) ?></span>
          <strong class="boss-runtime-card__value"><?= htmlspecialchars(__('app.boss.presets.labels.' . ($bossRuntime['skill_preset'] ?? ''), [], (string) ($bossRuntime['skill_preset'] ?? '-'))) ?></strong>
        </div>
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.skill_difficulty')) ?></span>
          <strong class="boss-runtime-card__value"><?= htmlspecialchars(__('app.boss.difficulties.labels.' . ($bossRuntime['skill_difficulty'] ?? ''), [], (string) ($bossRuntime['skill_difficulty'] ?? '-'))) ?></strong>
        </div>
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.respawn_at')) ?></span>
          <strong class="boss-runtime-card__value"><?= htmlspecialchars(format_datetime((int) ($bossRuntime['respawn_at'] ?? 0))) ?></strong>
        </div>
      </div>

      <div class="boss-runtime-meta">
        <span><?= htmlspecialchars(__('app.boss.runtime.last_spawn_at')) ?>: <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_spawn_at'] ?? 0))) ?></span>
        <span><?= htmlspecialchars(__('app.boss.runtime.last_engage_at')) ?>: <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_engage_at'] ?? 0))) ?></span>
        <span><?= htmlspecialchars(__('app.boss.runtime.last_death_at')) ?>: <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_death_at'] ?? 0))) ?></span>
        <span><?= htmlspecialchars(__('app.boss.runtime.last_reset_at')) ?>: <?= htmlspecialchars(format_datetime((int) ($bossRuntime['last_reset_at'] ?? 0))) ?></span>
      </div>
    </article>

    <article class="boss-panel boss-panel--stats">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.stats.title')) ?></h2>
      </div>
      <div class="boss-stats-grid">
        <article class="boss-stat-card">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.events_24h')) ?></span>
          <strong class="boss-stat-card__value"><?= (int) ($bossStats['events_24h'] ?? 0) ?></strong>
        </article>
        <article class="boss-stat-card">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.kills_7d')) ?></span>
          <strong class="boss-stat-card__value"><?= (int) ($bossStats['kills_7d'] ?? 0) ?></strong>
        </article>
        <article class="boss-stat-card">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.contributors_7d')) ?></span>
          <strong class="boss-stat-card__value"><?= (int) ($bossStats['contributors_7d'] ?? 0) ?></strong>
        </article>
        <article class="boss-stat-card">
          <span class="boss-stat-card__label"><?= htmlspecialchars(__('app.boss.stats.random_rewarded_7d')) ?></span>
          <strong class="boss-stat-card__value"><?= (int) ($bossStats['random_rewarded_7d'] ?? 0) ?></strong>
        </article>
      </div>
    </article>
  </section>

  <?php if ($bossCapabilities['actions']): ?>
    <section class="boss-panel">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.actions.title')) ?></h2>
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

        <div class="boss-action-card">
          <div class="boss-action-card__body">
            <strong><?= htmlspecialchars(__('app.boss.actions.rebase')) ?></strong>
            <p class="muted"><?= htmlspecialchars(__('app.boss.actions.rebase_help')) ?></p>
          </div>
          <button type="button" class="btn outline" id="bossRebaseBtn" data-boss-action="rebase">
            <?= htmlspecialchars(__('app.boss.actions.rebase')) ?>
          </button>
        </div>

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
    </section>
  <?php else: ?>
    <section class="boss-panel">
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
        <?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.boss.actions.title')])) ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="boss-bottom-grid">
    <?php if ($bossCapabilities['events']): ?>
      <article class="boss-panel boss-panel--table">
        <div class="boss-panel__head">
          <h2><?= htmlspecialchars(__('app.boss.events.title')) ?></h2>
        </div>
        <div class="boss-table-wrap">
          <table class="table boss-table">
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
                <tr>
                  <td><?= htmlspecialchars(format_datetime((int) ($event['created_at'] ?? 0))) ?></td>
                  <td>
                    <span class="boss-event-type">
                      <?= htmlspecialchars(__('app.boss.events.types.' . ($event['event_type'] ?? ''), [], (string) ($event['event_type'] ?? ''))) ?>
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
        </div>
        <div class="boss-table-wrap">
          <table class="table boss-table">
            <thead>
              <tr>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.time')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.player')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.boss')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.score')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.damage')) ?></th>
                <th><?= htmlspecialchars(__('app.boss.contributors.columns.healing')) ?></th>
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
                <tr>
                  <td><?= htmlspecialchars(format_datetime((int) ($row['created_at'] ?? 0))) ?></td>
                  <td>
                    <div class="boss-cell-title"><?= htmlspecialchars((string) ($row['player_name'] ?? '-')) ?></div>
                    <div class="small muted">GUID #<?= (int) ($row['player_guid'] ?? 0) ?> · Account #<?= (int) ($row['account_id'] ?? 0) ?></div>
                  </td>
                  <td>
                    <div class="boss-cell-title"><?= htmlspecialchars((string) ($row['boss_name'] ?? '-')) ?></div>
                    <div class="small muted">GUID #<?= (int) ($row['boss_guid'] ?? 0) ?></div>
                  </td>
                  <td><?= htmlspecialchars(number_format((float) ($row['contribution_score'] ?? 0), 2)) ?></td>
                  <td><?= (int) ($row['damage_done'] ?? 0) ?></td>
                  <td><?= (int) ($row['healing_done'] ?? 0) ?></td>
                  <td>
                    <div class="boss-badge-list">
                      <?php if (!empty($row['guaranteed_reward'])): ?>
                        <span class="badge"><?= htmlspecialchars(__('app.boss.contributors.badges.guaranteed_reward')) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($row['rewarded_random'])): ?>
                        <span class="badge badge--warn"><?= htmlspecialchars(__('app.boss.contributors.badges.random_reward')) ?></span>
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