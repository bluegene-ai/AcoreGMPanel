<?php
/**
 * File: resources/views/boss/_skill_prescreen.php
 * Purpose: 技能池预筛结果（只读）：预设 × 阶段的红/黄/绿表、每条证据展开、解析失败态、
 * 以及"这份结论对应哪份文件（SHA256）与哪个规则版本、什么时候算的"。
 *
 * 数据来自控制器传的 $boss_prescreen：result（缓存结果或 null）/ store_path /
 * boss_lua_path / rule_version。
 */

$prescreen = is_array($boss_prescreen ?? null) ? $boss_prescreen : [];
$prescreenResult = is_array($prescreen['result'] ?? null) ? $prescreen['result'] : null;
$prescreenPath = (string) ($prescreen['boss_lua_path'] ?? '');
$prescreenRuleVersion = (string) ($prescreen['rule_version'] ?? '');
$prescreenCanRun = $__can('boss.pools.write');

$prescreenEntries = is_array($prescreenResult['entries'] ?? null) ? $prescreenResult['entries'] : [];
$prescreenSummary = is_array($prescreenResult['summary'] ?? null) ? $prescreenResult['summary'] : [];
$prescreenStats = is_array($prescreenResult['stats'] ?? null) ? $prescreenResult['stats'] : [];
$prescreenWarnings = is_array($prescreenResult['warnings'] ?? null) ? $prescreenResult['warnings'] : [];
$prescreenDrift = is_array($prescreenResult['baseline_drift'] ?? null) ? $prescreenResult['baseline_drift'] : [];
$prescreenParseError = (string) ($prescreenResult['parse_error'] ?? '');

/** 结论级别 → 徽章文案 */
$prescreenLevelLabel = static function (string $level): string {
    return match ($level) {
        'red' => __('app.boss.prescreen.levels.red'),
        'yellow' => __('app.boss.prescreen.levels.yellow'),
        default => __('app.boss.prescreen.levels.green'),
    };
};

/** kind → 文案 */
$prescreenKindLabel = static function (string $kind): string {
    return match ($kind) {
        'opening' => __('app.boss.prescreen.kinds.opening'),
        'combo' => __('app.boss.prescreen.kinds.combo'),
        'interrupt' => __('app.boss.prescreen.kinds.interrupt'),
        'phase' => __('app.boss.prescreen.kinds.phase'),
        'stage' => __('app.boss.prescreen.kinds.stage'),
        default => __('app.boss.prescreen.kinds.pool'),
    };
};
?>
<section class="boss-panel" data-boss-prescreen>
  <div class="boss-panel__head">
    <h2><?= htmlspecialchars(__('app.boss.prescreen.page_title')) ?></h2>
    <?php if ($prescreenCanRun): ?>
      <button type="button" class="btn warn" data-boss-prescreen-run>
        <?= htmlspecialchars(__('app.boss.prescreen.run')) ?>
      </button>
    <?php endif; ?>
  </div>
  <p class="muted boss-config-note">
    <?= htmlspecialchars(__('app.boss.prescreen.intro')) ?>
    <span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.prescreen.intro_hint')) ?>">i</span>
  </p>

  <div class="boss-runtime-meta">
    <span><?= htmlspecialchars(__('app.boss.prescreen.meta.file')) ?>: <?= htmlspecialchars((string) ($prescreenResult['file_path'] ?? ($prescreenPath !== '' ? $prescreenPath : __('app.boss.prescreen.meta.unset')))) ?></span>
    <span><?= htmlspecialchars(__('app.boss.prescreen.meta.sha')) ?>: <code><?= htmlspecialchars((string) ($prescreenResult['file_sha256'] ?? '—')) ?></code></span>
    <span><?= htmlspecialchars(__('app.boss.prescreen.meta.rule')) ?>: <?= htmlspecialchars((string) ($prescreenResult['rule_version'] ?? $prescreenRuleVersion)) ?></span>
    <span><?= htmlspecialchars(__('app.boss.prescreen.meta.checked_at')) ?>: <?= htmlspecialchars((string) ($prescreenResult['checked_at_text'] ?? '—')) ?></span>
  </div>

  <div class="boss-simulate" data-boss-prescreen-result></div>

  <?php if ($prescreenResult === null): ?>
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
      <?= htmlspecialchars(__('app.boss.prescreen.no_cache')) ?>
    </div>
  <?php elseif ($prescreenParseError !== ''): ?>
    <div class="panel-flash panel-flash--error panel-flash--inline is-visible">
      <strong><?= htmlspecialchars(__('app.boss.prescreen.failed_title')) ?></strong>
      <div><?= htmlspecialchars($prescreenParseError) ?></div>
      <div class="muted"><?= htmlspecialchars(__('app.boss.prescreen.failed_note')) ?></div>
    </div>
  <?php else: ?>
    <?php if ($prescreenWarnings !== []): ?>
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
        <?= htmlspecialchars(implode('；', array_map('strval', $prescreenWarnings))) ?>
      </div>
    <?php endif; ?>

    <div class="boss-kpi-row">
      <div class="boss-kpi">
        <span class="boss-kpi__label"><?= htmlspecialchars($prescreenLevelLabel('red')) ?></span>
        <span class="boss-kpi__value"><?= (int) ($prescreenSummary['red'] ?? 0) ?></span>
      </div>
      <div class="boss-kpi">
        <span class="boss-kpi__label"><?= htmlspecialchars($prescreenLevelLabel('yellow')) ?></span>
        <span class="boss-kpi__value"><?= (int) ($prescreenSummary['yellow'] ?? 0) ?></span>
      </div>
      <div class="boss-kpi">
        <span class="boss-kpi__label"><?= htmlspecialchars($prescreenLevelLabel('green')) ?></span>
        <span class="boss-kpi__value"><?= (int) ($prescreenSummary['green'] ?? 0) ?></span>
      </div>
      <div class="boss-kpi">
        <span class="boss-kpi__label"><?= htmlspecialchars(__('app.boss.prescreen.meta.unique_spells')) ?></span>
        <span class="boss-kpi__value"><?= (int) ($prescreenStats['unique_spell_ids'] ?? 0) ?></span>
      </div>
    </div>

    <div class="boss-runtime-meta">
      <span><?= htmlspecialchars(__('app.boss.prescreen.stats.presets')) ?> <?= (int) ($prescreenStats['preset_count'] ?? 0) ?></span>
      <span><?= htmlspecialchars(__('app.boss.prescreen.stats.pool_entries')) ?> <?= (int) ($prescreenStats['pool_entries'] ?? 0) ?></span>
      <span><?= htmlspecialchars(__('app.boss.prescreen.stats.openings')) ?> <?= (int) ($prescreenStats['opening_entries'] ?? 0) ?></span>
      <span><?= htmlspecialchars(__('app.boss.prescreen.stats.combos')) ?> <?= (int) ($prescreenStats['combo_chains'] ?? 0) ?> / <?= (int) ($prescreenStats['combo_refs'] ?? 0) ?></span>
      <span><?= htmlspecialchars(__('app.boss.prescreen.stats.interrupts')) ?> <?= (int) ($prescreenStats['interrupt_entries'] ?? 0) ?></span>
      <span><?= htmlspecialchars(__('app.boss.prescreen.stats.phases')) ?> <?= (int) ($prescreenStats['phase_spells'] ?? 0) ?></span>
    </div>

    <?php if ($prescreenDrift !== []): ?>
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
        <?= htmlspecialchars(__('app.boss.prescreen.drift', [
            'detail' => implode('，', array_map(
                static fn (string $key, array $item): string => $key . ' ' . (int) $item['expected'] . '→' . (int) $item['actual'],
                array_keys($prescreenDrift),
                $prescreenDrift
            )),
        ])) ?>
      </div>
    <?php endif; ?>

    <div class="boss-table-filters" data-boss-prescreen-filter role="group" aria-label="<?= htmlspecialchars(__('app.boss.prescreen.filters_label')) ?>">
      <button type="button" class="boss-chip is-active" data-boss-prescreen-level="all"><?= htmlspecialchars(__('app.boss.prescreen.filters.all')) ?></button>
      <button type="button" class="boss-chip" data-boss-prescreen-level="red"><?= htmlspecialchars(__('app.boss.prescreen.filters.red')) ?></button>
      <button type="button" class="boss-chip" data-boss-prescreen-level="yellow"><?= htmlspecialchars(__('app.boss.prescreen.filters.yellow')) ?></button>
    </div>

    <div class="boss-table-wrap">
      <table class="table boss-table boss-table--sticky" data-boss-prescreen-table>
        <thead>
          <tr>
            <th><?= htmlspecialchars(__('app.boss.prescreen.columns.level')) ?></th>
            <th><?= htmlspecialchars(__('app.boss.prescreen.columns.kind')) ?></th>
            <th><?= htmlspecialchars(__('app.boss.prescreen.columns.preset')) ?></th>
            <th><?= htmlspecialchars(__('app.boss.prescreen.columns.stage')) ?></th>
            <th class="boss-num"><?= htmlspecialchars(__('app.boss.prescreen.columns.line')) ?></th>
            <th class="boss-num"><?= htmlspecialchars(__('app.boss.prescreen.columns.spell')) ?></th>
            <th><?= htmlspecialchars(__('app.boss.prescreen.columns.name')) ?></th>
            <th><?= htmlspecialchars(__('app.boss.prescreen.columns.verdicts')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($prescreenEntries === []): ?>
            <tr>
              <td colspan="8" class="text-center muted"><?= htmlspecialchars(__('app.boss.prescreen.empty')) ?></td>
            </tr>
          <?php endif; ?>
          <?php foreach ($prescreenEntries as $prescreenEntry): ?>
            <?php
              $entryLevel = (string) ($prescreenEntry['level'] ?? 'green');
              $entryVerdicts = is_array($prescreenEntry['verdicts'] ?? null) ? $prescreenEntry['verdicts'] : [];
              $entryMessages = [];
              foreach ($entryVerdicts as $verdict) {
                  if (($verdict['level'] ?? '') !== $entryLevel || $entryLevel === 'green') {
                      continue;
                  }
                  $entryMessages[] = $verdict['rule'] . ' ' . $verdict['message'];
              }
            ?>
            <tr data-boss-prescreen-row="<?= htmlspecialchars($entryLevel, ENT_QUOTES, 'UTF-8') ?>">
              <td>
                <span class="boss-status <?= $entryLevel === 'red' ? 'boss-status--cooldown' : ($entryLevel === 'yellow' ? 'boss-status--engaged' : 'boss-status--spawned') ?>">
                  <?= htmlspecialchars($prescreenLevelLabel($entryLevel)) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($prescreenKindLabel((string) ($prescreenEntry['kind'] ?? 'pool'))) ?></td>
              <td><?= htmlspecialchars((string) ($prescreenEntry['preset'] ?? '') !== '' ? (string) $prescreenEntry['preset'] : '—') ?></td>
              <td><?= (int) ($prescreenEntry['stage'] ?? 0) > 0 ? (int) $prescreenEntry['stage'] : '—' ?></td>
              <td class="boss-num"><?= (int) ($prescreenEntry['line'] ?? 0) ?></td>
              <td class="boss-num"><?= (int) ($prescreenEntry['spell_id'] ?? 0) > 0 ? (int) $prescreenEntry['spell_id'] : '—' ?></td>
              <td><?= htmlspecialchars((string) ($prescreenEntry['name'] ?? '')) ?></td>
              <td>
                <?php if ($entryMessages === []): ?>
                  <span class="muted"><?= htmlspecialchars(__('app.boss.prescreen.verdict_ok')) ?></span>
                <?php else: ?>
                  <ul class="boss-prescreen__verdicts">
                    <?php foreach ($entryMessages as $entryMessage): ?>
                      <li><?= htmlspecialchars($entryMessage) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if ($entryVerdicts !== []): ?>
                  <details class="boss-prescreen__evidence">
                    <summary><?= htmlspecialchars(__('app.boss.prescreen.evidence')) ?></summary>
                    <pre><?= htmlspecialchars(json_encode($entryVerdicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '') ?></pre>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="muted boss-config-note">
      <?= htmlspecialchars(__('app.boss.prescreen.blind_spots')) ?>
    </p>
  <?php endif; ?>
</section>
