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
$bossMapId = (int) ($bossRuntime['map_id'] ?? 0);
$bossMapLabel = \Acme\Panel\Support\GameMaps::mapLabel($bossMapId);
$bossInstanceId = (int) ($bossRuntime['instance_id'] ?? 0);
$bossHomeX = number_format((float) ($bossRuntime['home_x'] ?? 0), 3, '.', '');
$bossHomeY = number_format((float) ($bossRuntime['home_y'] ?? 0), 3, '.', '');
$bossHomeZ = number_format((float) ($bossRuntime['home_z'] ?? 0), 3, '.', '');
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
        <div class="boss-runtime-card">
          <span class="boss-runtime-card__label"><?= htmlspecialchars(__('app.boss.runtime.current_position')) ?></span>
          <?php if ($hasActiveBoss): ?>
            <strong class="boss-runtime-card__value"><?= htmlspecialchars($bossMapLabel) ?></strong>
            <span class="small muted">
              <?= htmlspecialchars(__('app.boss.runtime.instance_id')) ?>: #<?= $bossInstanceId ?> ·
              <?= htmlspecialchars(__('app.boss.runtime.coordinates')) ?>:
              X <?= htmlspecialchars($bossHomeX) ?> ·
              Y <?= htmlspecialchars($bossHomeY) ?> ·
              Z <?= htmlspecialchars($bossHomeZ) ?>
            </span>
          <?php else: ?>
            <strong class="boss-runtime-card__value">-</strong>
          <?php endif; ?>
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

        <div class="boss-action-card">
          <div class="boss-action-card__body">
            <strong><?= htmlspecialchars(__('app.boss.actions.reload_config')) ?></strong>
            <p class="muted"><?= htmlspecialchars(__('app.boss.actions.reload_config_help')) ?></p>
          </div>
          <button type="button" class="btn outline" id="bossConfigReloadBtn" data-boss-action="config_reload">
            <?= htmlspecialchars(__('app.boss.actions.reload_config')) ?>
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

    <section class="boss-panel boss-panel--config">
      <div class="boss-panel__head">
        <h2><?= htmlspecialchars(__('app.boss.config.title')) ?></h2>
      </div>
      <p class="muted boss-config-note"><?= htmlspecialchars(__('app.boss.config.note')) ?></p>

      <form id="bossConfigForm" class="boss-config-form">
        <div class="boss-config-grid">
          <section class="boss-config-section">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.sections.identity')) ?></h3>
            </div>
            <div class="boss-config-columns">
              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_entry')) ?></span>
                <input type="number" name="boss_entry" min="1" step="1" value="<?= htmlspecialchars((string) ($bossConfig['boss_entry'] ?? 647)) ?>">
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
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_scale')) ?></span>
                <input type="number" name="boss_scale" min="0.10" max="50.00" step="0.01" value="<?= htmlspecialchars((string) ($bossConfig['boss_scale'] ?? '5.00')) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_health_multiplier')) ?></span>
                <input type="number" name="boss_health_multiplier" min="0.10" max="2000.00" step="0.01" value="<?= htmlspecialchars((string) ($bossConfig['boss_health_multiplier'] ?? '20.00')) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.respawn_time_minutes')) ?></span>
                <input type="number" name="respawn_time_minutes" min="1" max="1440" step="1" value="<?= htmlspecialchars((string) ($bossConfig['respawn_time_minutes'] ?? 10)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.ally_level')) ?></span>
                <input type="number" name="ally_level" min="1" max="255" step="1" value="<?= htmlspecialchars((string) ($bossConfig['ally_level'] ?? 20)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.ally_health_multiplier')) ?></span>
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

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.skill_preset')) ?></span>
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

              <label class="boss-field boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.fields.boss_auras_text')) ?></span>
                <textarea name="boss_auras_text" rows="3" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.id_list')) ?>"><?= htmlspecialchars((string) ($bossConfig['boss_auras_text'] ?? '')) ?></textarea>
                <small class="muted"><?= htmlspecialchars(__('app.boss.config.hints.boss_auras_text')) ?></small>
              </label>

              <label class="boss-field boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.fields.spawn_points_text')) ?></span>
                <textarea name="spawn_points_text" rows="6" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.spawn_point_line')) ?>"><?= htmlspecialchars((string) ($bossConfig['spawn_points_text'] ?? '')) ?></textarea>
                <small class="muted"><?= htmlspecialchars(__('app.boss.config.hints.spawn_points_text')) ?></small>
              </label>
            </div>
          </section>

          <section class="boss-config-section">
            <div class="boss-config-section__head">
              <h3><?= htmlspecialchars(__('app.boss.config.sections.rewards')) ?></h3>
            </div>
            <div class="boss-config-columns">
              <label class="boss-check">
                <input type="checkbox" name="guaranteed_reward_enabled" value="1" <?= !empty($bossConfig['guaranteed_reward_enabled']) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars(__('app.boss.config.fields.guaranteed_reward_enabled')) ?></span>
              </label>

              <label class="boss-check">
                <input type="checkbox" name="guaranteed_reward_notify" value="1" <?= !empty($bossConfig['guaranteed_reward_notify']) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars(__('app.boss.config.fields.guaranteed_reward_notify')) ?></span>
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.max_random_reward_players')) ?></span>
                <input type="number" name="max_random_reward_players" min="0" max="100" step="1" value="<?= htmlspecialchars((string) ($bossConfig['max_random_reward_players'] ?? 3)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.random_reward_mode')) ?></span>
                <select name="random_reward_mode">
                  <?php foreach (($bossOptions['random_modes'] ?? []) as $option): ?>
                    <option
                      value="<?= htmlspecialchars((string) ($option['value'] ?? '')) ?>"
                      <?= (string) ($bossConfig['random_reward_mode'] ?? '') === (string) ($option['value'] ?? '') ? 'selected' : '' ?>
                    ><?= htmlspecialchars((string) ($option['label'] ?? '')) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.class_reward_chance')) ?></span>
                <input type="number" name="class_reward_chance" min="0" max="100" step="1" value="<?= htmlspecialchars((string) ($bossConfig['class_reward_chance'] ?? 60)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.formula_reward_chance')) ?></span>
                <input type="number" name="formula_reward_chance" min="0" max="100" step="1" value="<?= htmlspecialchars((string) ($bossConfig['formula_reward_chance'] ?? 10)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.mount_reward_chance')) ?></span>
                <input type="number" name="mount_reward_chance" min="0" max="100" step="1" value="<?= htmlspecialchars((string) ($bossConfig['mount_reward_chance'] ?? 15)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.participation_range')) ?></span>
                <input type="number" name="participation_range" min="20" max="500" step="1" value="<?= htmlspecialchars((string) ($bossConfig['participation_range'] ?? 80)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.damage_weight')) ?></span>
                <input type="number" name="damage_weight" min="0" max="10000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['damage_weight'] ?? 100)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.healing_weight')) ?></span>
                <input type="number" name="healing_weight" min="0" max="10000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['healing_weight'] ?? 80)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.threat_weight')) ?></span>
                <input type="number" name="threat_weight" min="0" max="10000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['threat_weight'] ?? 35)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.presence_weight')) ?></span>
                <input type="number" name="presence_weight" min="0" max="10000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['presence_weight'] ?? 10)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.kill_weight')) ?></span>
                <input type="number" name="kill_weight" min="0" max="10000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['kill_weight'] ?? 3)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.guaranteed_item_id')) ?></span>
                <input type="number" name="guaranteed_item_id" min="0" max="2000000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['guaranteed_item_id'] ?? 40753)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.guaranteed_item_count')) ?></span>
                <input type="number" name="guaranteed_item_count" min="0" max="10000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['guaranteed_item_count'] ?? 2)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.gold_min_copper')) ?></span>
                <input type="number" name="gold_min_copper" min="0" max="2000000000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['gold_min_copper'] ?? 30000)) ?>">
              </label>

              <label class="boss-field">
                <span><?= htmlspecialchars(__('app.boss.config.fields.gold_max_copper')) ?></span>
                <input type="number" name="gold_max_copper" min="0" max="2000000000" step="1" value="<?= htmlspecialchars((string) ($bossConfig['gold_max_copper'] ?? 50000)) ?>">
              </label>

              <label class="boss-field boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.fields.reward_items_text')) ?></span>
                <textarea name="reward_items_text" rows="3" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.id_list')) ?>"><?= htmlspecialchars((string) ($bossConfig['reward_items_text'] ?? '')) ?></textarea>
                <small class="muted"><?= htmlspecialchars(__('app.boss.config.hints.reward_items_text')) ?></small>
              </label>

              <label class="boss-field boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.fields.reward_formulas_text')) ?></span>
                <textarea name="reward_formulas_text" rows="3" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.id_list')) ?>"><?= htmlspecialchars((string) ($bossConfig['reward_formulas_text'] ?? '')) ?></textarea>
                <small class="muted"><?= htmlspecialchars(__('app.boss.config.hints.reward_formulas_text')) ?></small>
              </label>

              <label class="boss-field boss-field--full">
                <span><?= htmlspecialchars(__('app.boss.config.fields.reward_mounts_text')) ?></span>
                <textarea name="reward_mounts_text" rows="4" placeholder="<?= htmlspecialchars(__('app.boss.config.placeholders.id_list')) ?>"><?= htmlspecialchars((string) ($bossConfig['reward_mounts_text'] ?? '')) ?></textarea>
                <small class="muted"><?= htmlspecialchars(__('app.boss.config.hints.reward_mounts_text')) ?></small>
              </label>
            </div>
          </section>
        </div>

        <div class="boss-config-actions">
          <button type="submit" class="btn warn" id="bossConfigSaveBtn">
            <?= htmlspecialchars(__('app.boss.config.save')) ?>
          </button>
        </div>
      </form>
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
                    <div class="boss-cell-title"><?= character_link((int) ($row['player_guid'] ?? 0), (string) ($row['player_name'] ?? '')) ?></div>
                    <div class="small muted">GUID #<?= (int) ($row['player_guid'] ?? 0) ?> · <?= account_link((int) ($row['account_id'] ?? 0), 'Account #' . (int) ($row['account_id'] ?? 0)) ?></div>
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