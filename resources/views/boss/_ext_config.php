<?php
/**
 * File: resources/views/boss/_ext_config.php
 * Purpose: Boss 扩展配置表单（ac_eluna.boss_activity_config_ext）。
 *
 * 数据来自控制器传的 $boss_ext：config（当前值）/ tabs（二级 Tab → 字段组）/
 * fields（分组字段 schema）/ available（ext 表是否已由 boss.lua 建好）/
 * defaults（出厂默认值，用于"恢复默认"与"已改动"标记）/ changed（哪些字段与默认不同）/
 * item_names（奖品 ID → 物品名）/ reward_params（选人参数，属于主表，见下）。
 *
 * 字段按 schema 的 kind 渲染：text 单行 / lines 多行逐条 / keyedlines、keyedintlist
 * 多行"键=值" / intlist 逗号列表 / int 整数（min/max 与 Lua 边界一致）/ bool 开关 /
 * enum 枚举下拉 / itemlist 物品ID列表（下面折叠显示物品名）。
 *
 * 版式约定（2026-09-26 改版）：
 *   · reward_pool_N 六组渲染成"奖池卡片"（右侧网格），左侧是吸顶的奖池目录；
 *   · 6 个奖池的重复说明收敛成一张"奖池说明"卡片（一份，不再每池重复）；
 *   · 顶部"奖池总览"把 开关/概率/人数/件数 一眼列全；
 *   · 普通分组（如「职业过滤映射」）在奖池 Tab 里排在奖池卡片**前面** ——
 *     它服务的正是下面的池子，放一起才看得出关系；
 *   · 选人参数（主表字段）用 form="bossConfigForm" 挂到基础配置表单上，奖励参数集中在一处；
 *   · 表单底部是粘性保存栏，长表单在任何位置都能保存。
 */

$ext = is_array($boss_ext ?? null) ? $boss_ext : [];
$extConfig = is_array($ext['config'] ?? null) ? $ext['config'] : [];
$extTabs = is_array($ext['tabs'] ?? null) ? $ext['tabs'] : [];
$extFields = is_array($ext['fields'] ?? null) ? $ext['fields'] : [];
$extAvailable = ($ext['available'] ?? true) !== false;
$extDefaults = is_array($ext['defaults'] ?? null) ? $ext['defaults'] : [];
$extChanged = is_array($ext['changed'] ?? null) ? $ext['changed'] : [];
$extItemNames = is_array($ext['item_names'] ?? null) ? $ext['item_names'] : [];
$extRewardParams = is_array($ext['reward_params'] ?? null) ? $ext['reward_params'] : [];
$extRewardValues = is_array($extRewardParams['values'] ?? null) ? $extRewardParams['values'] : [];
$extRewardModes = is_array($extRewardParams['modes'] ?? null) ? $extRewardParams['modes'] : [];

// 定时启停（脚本侧 tick 执行）：面板只负责编辑 + 预览解析结果
$extSchedule = is_array($ext['schedule'] ?? null) ? $ext['schedule'] : [];
$extScheduleWindows = is_array($extSchedule['windows'] ?? null) ? $extSchedule['windows'] : [];
$extScheduleSamplesRendered = false;

/** 把 schema 里的默认值渲染成 data-boss-default（恢复默认按钮用） */
$extDefaultFor = static function (string $extName, array $extDefaults): ?string {
    if (!array_key_exists($extName, $extDefaults)) {
        return null;
    }
    $value = $extDefaults[$extName];
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_array($value)) {
        return implode(',', array_map('strval', $value));
    }

    return (string) $value;
};
?>
<section class="boss-panel boss-panel--config">
  <div class="boss-panel__head">
    <h2><?= htmlspecialchars(__('app.boss.ext.title')) ?></h2>
    <?php if (!$extAvailable): ?>
      <span class="boss-muted"><?= htmlspecialchars(__('app.boss.ext.unavailable')) ?></span>
    <?php endif; ?>
  </div>
  <p class="muted boss-config-note">
    <?= htmlspecialchars(__('app.boss.ext.note')) ?>
    <span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.ext.note_hint')) ?>">i</span>
  </p>

  <form id="bossExtConfigForm" class="boss-config-form" autocomplete="off"
        data-boss-ext-available="<?= $extAvailable ? '1' : '0' ?>">
    <div class="boss-tabs boss-tabs--sub" data-boss-tabs="ext">
      <nav class="boss-tabs__nav" role="tablist" aria-label="<?= htmlspecialchars(__('app.boss.ext.tabs_label')) ?>">
        <?php $extFirstTab = true; foreach ($extTabs as $extTabKey => $extTabGroups): ?>
          <button type="button" class="boss-tab<?= $extFirstTab ? ' is-active' : '' ?>" role="tab"
                  aria-selected="<?= $extFirstTab ? 'true' : 'false' ?>"
                  data-boss-tab="<?= htmlspecialchars((string) $extTabKey, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(__('app.boss.ext.tabs.' . $extTabKey, [], (string) $extTabKey)) ?>
          </button>
        <?php $extFirstTab = false; endforeach; ?>
      </nav>

      <?php $extFirstPanel = true; foreach ($extTabs as $extTabKey => $extTabGroups): ?>
        <?php
          // 奖池 Tab 里"奖池卡片"走单独网格，其余组是普通分组；两个集合分开渲染
          $extPoolGroups = [];
          $extPlainGroups = [];
          foreach ((array) $extTabGroups as $extGroupKey) {
              if (preg_match('/^reward_pool_(\d+)$/', (string) $extGroupKey, $extPoolMatch) === 1) {
                  $extPoolGroups[(int) $extPoolMatch[1]] = (string) $extGroupKey;
                  continue;
              }
              $extPlainGroups[] = (string) $extGroupKey;
          }
          ksort($extPoolGroups);
          $extTabPanelId = 'ext-panel-' . preg_replace('/[^a-z0-9_]/i', '', (string) $extTabKey);
        ?>
        <?php
          // 普通分组先渲染成一段 HTML：奖金池 Tab 要把它放在奖池卡片前面（职业过滤映射紧挨奖池），
          // 其它 Tab 就照常放在面板里。
          ob_start();
        ?>
        <div class="boss-config-grid">
          <?php foreach ($extPlainGroups as $extGroupKey): ?>
            <?php
              $extGroupFields = is_array($extFields[$extGroupKey] ?? null) ? $extFields[$extGroupKey] : [];
              if ($extGroupFields === []) {
                  continue;
              }
              $extIsClassReward = $extGroupKey === 'class_reward';
            ?>
            <section class="boss-config-section<?= $extIsClassReward ? ' boss-config-section--full' : '' ?>"
                     data-boss-ext-group="<?= htmlspecialchars((string) $extGroupKey, ENT_QUOTES, 'UTF-8') ?>">
              <div class="boss-config-section__head">
                <h3><?= htmlspecialchars(__('app.boss.ext.groups.' . $extGroupKey, [], (string) $extGroupKey)) ?></h3>
                <div class="boss-config-section__tools">
                  <span class="boss-config-section__count"><?= htmlspecialchars(__('app.boss.config.item_count', ['count' => (string) count($extGroupFields)])) ?></span>
                  <?php if ($extIsClassReward): ?>
                    <button type="button" class="btn outline" data-boss-class-autofill>
                      <?= htmlspecialchars(__('app.boss.ext.class_map.autofill')) ?>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($extIsClassReward): ?>
                <p class="muted boss-config-note">
                  <?= htmlspecialchars(__('app.boss.ext.class_map.note_short')) ?>
                  <span class="panel-hint" title="<?= htmlspecialchars(__('app.boss.ext.class_map.note')) ?>">i</span>
                </p>
              <?php endif; ?>
              <div class="boss-config-columns">
                <?php foreach ($extGroupFields as $extField): ?>
                  <?php
                    if (!is_array($extField)) {
                        continue;
                    }

                    $extName = trim((string) ($extField['name'] ?? ''));
                    if ($extName === '') {
                        continue;
                    }

                    $extKind = (string) ($extField['kind'] ?? 'text');
                    $extValue = $extConfig[$extName] ?? (($extKind === 'int' || $extKind === 'bool') ? 0 : '');
                    $extLabel = __('app.boss.ext.fields.' . $extName, [], $extName);
                    $extHint = !empty($extField['hint']) ? __('app.boss.ext.hints.' . $extName, [], '') : '';
                    // 提示默认收进 ⓘ 悬浮提示；要当场看到的（单位/留空语义）在 config/boss.php 标 hint_visible
                    $extHintVisible = !empty($extField['hint_visible']);
                    $extHintBadge = ($extHint !== '' && !$extHintVisible)
                        ? ' <span class="panel-hint" title="' . htmlspecialchars($extHint, ENT_QUOTES, 'UTF-8') . '">i</span>'
                        : '';
                    $extLongKinds = ['lines', 'keyedlines', 'keyedintlist'];
                    $extIsLong = in_array($extKind, $extLongKinds, true);
                    $extIsWide = $extIsLong || $extKind === 'intlist' || $extKind === 'schedule_windows';
                    $extDefault = $extDefaultFor($extName, $extDefaults);
                    $extIsChanged = !empty($extChanged[$extName]);
                    $extDefaultAttr = $extDefault !== null
                        ? ' data-boss-default="' . htmlspecialchars($extDefault, ENT_QUOTES, 'UTF-8') . '"'
                        : '';
                  ?>
                  <?php if ($extKind === 'bool'): ?>
                    <label class="boss-check">
                      <input type="hidden" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>" value="0"<?= $extDefaultAttr ?>>
                      <input type="checkbox" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>" value="1"<?= $extDefaultAttr ?>
                             <?= ((int) $extValue === 1) ? 'checked' : '' ?>>
                      <span><?= htmlspecialchars((string) $extLabel) ?><?= $extHintBadge ?></span>
                      <?php if ($extIsChanged): ?>
                        <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                      <?php endif; ?>
                      <?php if ($extHintVisible): ?>
                        <small class="muted boss-check__hint"><?= htmlspecialchars((string) $extHint) ?></small>
                      <?php endif; ?>
                    </label>
                  <?php elseif ($extKind === 'preset_multi'): ?>
                    <?php
                      // 多选预设（技能池随机的随机池）：值 = 逗号分隔的预设 key，空 = 全部预设。
                      // 复选框用 name[] 提交，由 boss.js 合并成一个逗号串（面板的其它字段仍是单值）。
                      $extPresetOptions = is_array($ext['presets'] ?? null) ? $ext['presets'] : [];
                      $extPresetSelected = [];
                      foreach (preg_split('/[\s,;]+/', (string) $extValue) ?: [] as $extPresetToken) {
                          $extPresetToken = strtolower(trim((string) $extPresetToken));
                          if ($extPresetToken !== '') {
                              $extPresetSelected[$extPresetToken] = true;
                          }
                      }
                    ?>
                    <div class="boss-field boss-field--full">
                      <span><?= htmlspecialchars((string) $extLabel) ?></span>
                      <div class="boss-check-list">
                        <?php // 空值占位：全部不勾选时也要把"空池"提交上去（空 = 全部预设，不能被当成"未提交"） ?>
                        <input type="hidden" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>[]" value="">
                        <?php foreach ($extPresetOptions as $extPresetOption): ?>
                          <?php
                            $extPresetValue = trim((string) ($extPresetOption['value'] ?? ''));
                            if ($extPresetValue === '') {
                                continue;
                            }
                            $extPresetSummary = trim((string) ($extPresetOption['summary'] ?? ''));
                          ?>
                          <label class="boss-check"
                                 <?= $extPresetSummary !== '' ? 'title="' . htmlspecialchars($extPresetSummary, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <input type="checkbox"
                                   name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>[]"
                                   value="<?= htmlspecialchars($extPresetValue, ENT_QUOTES, 'UTF-8') ?>"
                                   <?= isset($extPresetSelected[strtolower($extPresetValue)]) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars((string) ($extPresetOption['label'] ?? $extPresetValue)) ?></span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                      <?= $extHintBadge ?><?php if ($extHintVisible): ?><small class="muted"><?= htmlspecialchars((string) $extHint) ?></small><?php endif; ?>
                    </div>
                  <?php elseif ($extKind === 'enum'): ?>
                    <label class="boss-field">
                      <span><?= htmlspecialchars((string) $extLabel) ?></span>
                      <select name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"<?= $extDefaultAttr ?>>
                        <?php foreach ((array) ($extField['options'] ?? []) as $extEnumOption): ?>
                          <?php $extEnumOption = (string) $extEnumOption; ?>
                          <option
                            value="<?= htmlspecialchars($extEnumOption, ENT_QUOTES, 'UTF-8') ?>"
                            <?= (string) $extValue === $extEnumOption ? 'selected' : '' ?>
                          ><?= htmlspecialchars(__('app.boss.ext.enum_options.' . $extEnumOption, [], $extEnumOption)) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php if ($extIsChanged): ?>
                        <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                      <?php endif; ?>
                      <?= $extHintBadge ?><?php if ($extHintVisible): ?><small class="muted"><?= htmlspecialchars((string) $extHint) ?></small><?php endif; ?>
                    </label>
                  <?php elseif ($extKind === 'itemlist'): ?>
                    <?php
                      // 奖品列表：输入物品ID，下面折叠显示物品名（查不到的显示 #ID）
                      $extItemIds = [];
                      foreach (preg_split('/[\s,;]+/', (string) $extValue) ?: [] as $extItemToken) {
                          $extItemToken = (int) trim((string) $extItemToken);
                          if ($extItemToken > 0) {
                              $extItemIds[$extItemToken] = true;
                          }
                      }
                      $extItemIds = array_keys($extItemIds);
                    ?>
                    <div class="boss-field boss-field--full boss-pool-items<?= count($extItemIds) > 12 ? '' : ' is-open' ?>"
                         data-boss-pool-items>
                      <span>
                        <?= htmlspecialchars((string) $extLabel) ?>
                        <span class="boss-field__unit" data-boss-pool-items-count><?= htmlspecialchars(__('app.boss.ext.pools.items_count', ['count' => (string) count($extItemIds)])) ?></span>
                        <?php if ($extIsChanged): ?>
                          <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                        <?php endif; ?>
                      </span>
                      <textarea name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                rows="<?= max(2, (int) ($extField['rows'] ?? 3)) ?>"
                                data-boss-pool-items-input<?= $extDefaultAttr ?>
                                placeholder="<?= htmlspecialchars(__('app.boss.ext.placeholders.intlist')) ?>"><?= htmlspecialchars((string) $extValue) ?></textarea>
                      <?= $extHintBadge ?><?php if ($extHintVisible): ?><small class="muted"><?= htmlspecialchars((string) $extHint) ?></small><?php endif; ?>
                      <?php if (count($extItemIds) > 0): ?>
                        <button type="button" class="boss-chip" data-boss-pool-items-toggle>
                          <?= htmlspecialchars(__('app.boss.ext.pools.items_toggle')) ?>
                        </button>
                      <?php endif; ?>
                      <div class="boss-pool-items__list">
                        <?php if ($extItemIds === []): ?>
                          <span class="muted"><?= htmlspecialchars(__('app.boss.ext.pools.no_items')) ?></span>
                        <?php else: ?>
                          <?php foreach ($extItemIds as $extItemId): ?>
                            <span class="badge"><?= htmlspecialchars($extItemId . ' · ' . (string) ($extItemNames[$extItemId] ?? ('#' . $extItemId))) ?></span>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php else: ?>
                    <label class="boss-field<?= $extIsWide ? ' boss-field--full' : '' ?>">
                      <span><?= htmlspecialchars((string) $extLabel) ?></span>
                      <?php if ($extIsLong): ?>
                        <?php
                          $extPlaceholder = $extKind === 'lines'
                              ? __('app.boss.ext.placeholders.lines')
                              : ($extKind === 'keyedlines'
                                  ? __('app.boss.ext.placeholders.keyedlines')
                                  : __('app.boss.ext.placeholders.keyedintlist'));
                        ?>
                        <textarea name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                  rows="<?= max(2, (int) ($extField['rows'] ?? 4)) ?>"
                                  <?= $extIsClassReward ? 'data-boss-class-map-input' : '' ?>
                                  placeholder="<?= htmlspecialchars((string) $extPlaceholder) ?>"<?= $extDefaultAttr ?>><?= htmlspecialchars((string) $extValue) ?></textarea>
                      <?php elseif ($extKind === 'int'): ?>
                        <input type="number"
                               name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                               min="<?= (int) ($extField['min'] ?? 0) ?>"
                               max="<?= (int) ($extField['max'] ?? 2000000000) ?>"
                               step="1"
                               value="<?= htmlspecialchars((string) (int) $extValue) ?>"<?= $extDefaultAttr ?>>
                      <?php elseif ($extKind === 'schedule_windows'): ?>
                        <input type="text"
                               name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                               maxlength="<?= max(1, (int) ($extField['maxlength'] ?? 255)) ?>"
                               list="bossScheduleSamples"
                               autocomplete="off"
                               placeholder="<?= htmlspecialchars(__('app.boss.ext.placeholders.schedule_windows')) ?>"
                               value="<?= htmlspecialchars((string) $extValue) ?>"<?= $extDefaultAttr ?>>
                        <?php if (!$extScheduleSamplesRendered): $extScheduleSamplesRendered = true; ?>
                          <datalist id="bossScheduleSamples">
                            <option value="08:00-09:00"><?= htmlspecialchars(__('app.boss.ext.schedule.sample_daily')) ?></option>
                            <option value="08:00-09:00; 20:00-22:00"><?= htmlspecialchars(__('app.boss.ext.schedule.sample_twice')) ?></option>
                            <option value="1-5@20:00-23:00"><?= htmlspecialchars(__('app.boss.ext.schedule.sample_weekday')) ?></option>
                            <option value="6,7@10:00-12:00"><?= htmlspecialchars(__('app.boss.ext.schedule.sample_weekend')) ?></option>
                            <option value="20:00-24:00; 00:00-02:00"><?= htmlspecialchars(__('app.boss.ext.schedule.sample_overnight')) ?></option>
                          </datalist>
                        <?php endif; ?>
                        <small class="muted">
                          <?= htmlspecialchars(__('app.boss.ext.schedule.preview', [
                              'windows' => $extScheduleWindows !== []
                                  ? implode('，', $extScheduleWindows)
                                  : __('app.boss.ext.schedule.none'),
                          ])) ?>
                        </small>
                      <?php else: ?>
                        <input type="text"
                               name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                               maxlength="<?= max(1, (int) ($extField['maxlength'] ?? 255)) ?>"
                               <?= $extKind === 'intlist' ? 'placeholder="' . htmlspecialchars(__('app.boss.ext.placeholders.intlist')) . '"' : '' ?>
                               value="<?= htmlspecialchars((string) $extValue) ?>"<?= $extDefaultAttr ?>>
                      <?php endif; ?>
                      <?php if ($extIsChanged): ?>
                        <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                      <?php endif; ?>
                      <?= $extHintBadge ?><?php if ($extHintVisible): ?><small class="muted"><?= htmlspecialchars((string) $extHint) ?></small><?php endif; ?>
                    </label>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php if ($extIsClassReward): ?>
                <div class="boss-simulate" data-boss-class-autofill-result hidden></div>
              <?php endif; ?>
            </section>
          <?php endforeach; ?>
        </div>
        <?php $extPlainHtml = (string) ob_get_clean(); ?>

        <div class="boss-tabpanel<?= $extFirstPanel ? ' is-active' : '' ?>" role="tabpanel"
             id="<?= htmlspecialchars($extTabPanelId, ENT_QUOTES, 'UTF-8') ?>"
             data-boss-tabpanel="<?= htmlspecialchars((string) $extTabKey, ENT_QUOTES, 'UTF-8') ?>">

          <?php if ($extPoolGroups !== []): ?>
            <?php // ---------- 选人参数（主表字段，与基础配置一起保存） ---------- ?>
            <section class="boss-config-section boss-config-section--full" data-boss-reward-params>
              <div class="boss-config-section__head">
                <h3><?= htmlspecialchars(__('app.boss.ext.reward_params.title')) ?></h3>
                <span class="boss-config-section__count"><?= htmlspecialchars(__('app.boss.ext.reward_params.note')) ?></span>
              </div>
              <div class="boss-config-columns">
                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.random_reward_mode')) ?></span>
                  <select name="random_reward_mode" form="bossConfigForm">
                    <?php foreach ($extRewardModes as $extRewardModeOption): ?>
                      <option
                        value="<?= htmlspecialchars((string) ($extRewardModeOption['value'] ?? '')) ?>"
                        <?= (string) ($extRewardValues['random_reward_mode'] ?? '') === (string) ($extRewardModeOption['value'] ?? '') ? 'selected' : '' ?>
                      ><?= htmlspecialchars((string) ($extRewardModeOption['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <small class="muted"><?= htmlspecialchars(__('app.boss.ext.reward_params.mode_hint')) ?></small>
                </label>

                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.participation_range')) ?></span>
                  <input type="number" name="participation_range" form="bossConfigForm" min="20" max="500" step="1"
                         value="<?= htmlspecialchars((string) ($extRewardValues['participation_range'] ?? 80)) ?>">
                  <small class="muted"><?= htmlspecialchars(__('app.boss.ext.reward_params.range_hint')) ?></small>
                </label>

                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.damage_weight')) ?></span>
                  <input type="number" name="damage_weight" form="bossConfigForm" min="0" max="10000" step="1"
                         value="<?= htmlspecialchars((string) ($extRewardValues['damage_weight'] ?? 100)) ?>">
                </label>

                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.healing_weight')) ?></span>
                  <input type="number" name="healing_weight" form="bossConfigForm" min="0" max="10000" step="1"
                         value="<?= htmlspecialchars((string) ($extRewardValues['healing_weight'] ?? 80)) ?>">
                </label>

                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.threat_weight')) ?></span>
                  <input type="number" name="threat_weight" form="bossConfigForm" min="0" max="10000" step="1"
                         value="<?= htmlspecialchars((string) ($extRewardValues['threat_weight'] ?? 35)) ?>">
                </label>

                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.presence_weight')) ?></span>
                  <input type="number" name="presence_weight" form="bossConfigForm" min="0" max="10000" step="1"
                         value="<?= htmlspecialchars((string) ($extRewardValues['presence_weight'] ?? 10)) ?>">
                </label>

                <label class="boss-field">
                  <span><?= htmlspecialchars(__('app.boss.config.fields.kill_weight')) ?></span>
                  <input type="number" name="kill_weight" form="bossConfigForm" min="0" max="10000" step="1"
                         value="<?= htmlspecialchars((string) ($extRewardValues['kill_weight'] ?? 3)) ?>">
                </label>

                <div class="boss-field boss-field--action">
                  <button type="submit" class="btn warn" form="bossConfigForm" id="bossRewardParamsSaveBtn"
                          <?= ($extRewardValues !== [] ? '' : 'disabled') ?>>
                    <?= htmlspecialchars(__('app.boss.ext.reward_params.save')) ?>
                  </button>
                  <small class="muted"><?= htmlspecialchars(__('app.boss.ext.reward_params.save_hint')) ?></small>
                </div>
              </div>
            </section>

            <?php // ---------- 奖池说明（一份，替代每池重复的长提示） + 两个工具 ---------- ?>
            <section class="boss-legend">
              <div class="boss-config-section__head">
                <strong><?= htmlspecialchars(__('app.boss.ext.pools.legend.title')) ?></strong>
                <div class="boss-config-section__tools">
                  <button type="button" class="btn outline" data-boss-simulate>
                    <?= htmlspecialchars(__('app.boss.ext.pools.tools.simulate')) ?>
                  </button>
                  <button type="button" class="btn outline" data-boss-copy-open>
                    <?= htmlspecialchars(__('app.boss.ext.pools.tools.copy')) ?>
                  </button>
                </div>
              </div>
              <ul>
                <li><?= htmlspecialchars(__('app.boss.ext.pools.legend.settlement')) ?></li>
                <li><?= htmlspecialchars(__('app.boss.ext.pools.legend.winner_mode')) ?></li>
                <li><?= htmlspecialchars(__('app.boss.ext.pools.legend.class_filter')) ?></li>
                <li><?= htmlspecialchars(__('app.boss.ext.pools.legend.items')) ?></li>
              </ul>
              <div class="boss-pool-summary" data-boss-pool-summary>
                <?php foreach ($extPoolGroups as $extPoolIndex => $extPoolGroupKey): ?>
                  <?php
                    $extPoolEnabled = (int) ($extConfig[$extPoolGroupKey . '_enabled'] ?? 0) === 1;
                    $extPoolChance = (int) ($extConfig[$extPoolGroupKey . '_chance'] ?? 0);
                    $extPoolMode = (string) ($extConfig[$extPoolGroupKey . '_winner_mode'] ?? 'count') === 'all' ? 'all' : 'count';
                    $extPoolCount = (int) ($extConfig[$extPoolGroupKey . '_winner_count'] ?? 1);
                    $extPoolItemCount = 0;
                    foreach (preg_split('/[\s,;]+/', (string) ($extConfig[$extPoolGroupKey . '_items_text'] ?? '')) ?: [] as $extPoolItemToken) {
                        if ((int) trim((string) $extPoolItemToken) > 0) {
                            $extPoolItemCount++;
                        }
                    }
                  ?>
                  <button type="button"
                          class="boss-pool-summary__item<?= $extPoolEnabled ? '' : ' is-off' ?>"
                          data-boss-pool-summary-item="<?= (int) $extPoolIndex ?>"
                          data-boss-pool-jump="<?= (int) $extPoolIndex ?>"
                          title="<?= htmlspecialchars(__('app.boss.ext.pools.summary_jump')) ?>">
                    <span><?= htmlspecialchars(__('app.boss.ext.pools.summary_pool', ['pool' => (string) $extPoolIndex])) ?></span>
                    <span data-boss-pool-summary-state><?= htmlspecialchars($extPoolEnabled ? __('app.boss.ext.pools.state_on') : __('app.boss.ext.pools.state_off')) ?></span>
                    <span data-boss-pool-summary-chance><?= (int) $extPoolChance ?>%</span>
                    <span data-boss-pool-summary-count><?= htmlspecialchars($extPoolMode === 'all' ? __('app.boss.ext.enum_options.all') : __('app.boss.ext.pools.winners_count', ['count' => (string) $extPoolCount])) ?></span>
                    <span data-boss-pool-summary-items><?= htmlspecialchars(__('app.boss.ext.pools.items_count', ['count' => (string) $extPoolItemCount])) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>

              <?php // 模拟结果（只读，不碰游戏服） ?>
              <div class="boss-simulate" data-boss-simulate-result hidden></div>

              <?php // 跨区复制 ?>
              <div class="boss-simulate" data-boss-copy-panel hidden>
                <div class="boss-config-columns">
                  <label class="boss-field">
                    <span><?= htmlspecialchars(__('app.boss.ext.pools.tools.copy_target')) ?></span>
                    <select data-boss-copy-target></select>
                  </label>
                  <label class="boss-check">
                    <input type="checkbox" data-boss-copy-main value="1">
                    <span><?= htmlspecialchars(__('app.boss.ext.pools.tools.copy_main')) ?></span>
                    <small class="muted boss-check__hint"><?= htmlspecialchars(__('app.boss.ext.pools.tools.copy_main_hint')) ?></small>
                  </label>
                </div>
                <div class="boss-check-list" data-boss-copy-groups>
                  <label class="boss-check">
                    <input type="checkbox" data-boss-copy-group="all" value="all" checked>
                    <span><?= htmlspecialchars(__('app.boss.ext.pools.tools.copy_all')) ?></span>
                  </label>
                  <?php foreach ($extTabs as $extTabKey => $extTabGroupsIgnored): ?>
                    <label class="boss-check">
                      <input type="checkbox" data-boss-copy-group="<?= htmlspecialchars((string) $extTabKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $extTabKey, ENT_QUOTES, 'UTF-8') ?>">
                      <span><?= htmlspecialchars(__('app.boss.ext.tabs.' . $extTabKey, [], (string) $extTabKey)) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div class="boss-field--action">
                  <button type="button" class="btn warn" data-boss-copy-run>
                    <?= htmlspecialchars(__('app.boss.ext.pools.tools.copy_run')) ?>
                  </button>
                  <small class="muted"><?= htmlspecialchars(__('app.boss.ext.pools.tools.copy_hint')) ?></small>
                </div>
                <div class="boss-simulate" data-boss-copy-result hidden></div>
              </div>
            </section>

            <?php // 职业过滤映射（普通分组）排在奖池卡片前面：它服务的正是下面这些池子 ?>
            <?= $extPlainHtml ?>

            <div class="boss-pool-layout">
              <nav class="boss-toc" aria-label="<?= htmlspecialchars(__('app.boss.ext.pools.toc_label')) ?>">
                <?php foreach ($extPoolGroups as $extPoolIndex => $extPoolGroupKey): ?>
                  <?php $extPoolEnabled = (int) ($extConfig[$extPoolGroupKey . '_enabled'] ?? 0) === 1; ?>
                  <button type="button" class="boss-toc__item<?= $extPoolEnabled ? '' : ' is-off' ?>" data-boss-pool-jump="<?= (int) $extPoolIndex ?>">
                    <span class="boss-toc__dot"></span>
                    <span><?= htmlspecialchars(__('app.boss.ext.pools.title', ['pool' => (string) $extPoolIndex])) ?></span>
                  </button>
                <?php endforeach; ?>
              </nav>

              <div class="boss-pool-grid">
                <?php foreach ($extPoolGroups as $extPoolIndex => $extPoolGroupKey): ?>
                  <?php
                    $extGroupFields = is_array($extFields[$extPoolGroupKey] ?? null) ? $extFields[$extPoolGroupKey] : [];
                    if ($extGroupFields === []) {
                        continue;
                    }
                    $extPoolEnabled = (int) ($extConfig[$extPoolGroupKey . '_enabled'] ?? 0) === 1;
                  ?>
                  <section class="boss-pool-card<?= $extPoolEnabled ? '' : ' is-off' ?>"
                           id="boss-pool-card-<?= (int) $extPoolIndex ?>"
                           data-boss-pool-card="<?= (int) $extPoolIndex ?>">
                    <div class="boss-pool-card__head">
                      <span class="boss-pool-card__title">
                        <?= htmlspecialchars(__('app.boss.ext.pools.title', ['pool' => (string) $extPoolIndex])) ?>
                        <span class="boss-field__flag"><?= htmlspecialchars(__('app.boss.ext.groups.' . $extPoolGroupKey, [], $extPoolGroupKey)) ?></span>
                      </span>
                      <span class="boss-pool-card__state" data-boss-pool-state>
                        <?= htmlspecialchars($extPoolEnabled ? __('app.boss.ext.pools.state_on') : __('app.boss.ext.pools.state_off')) ?>
                      </span>
                    </div>
                    <div class="boss-config-columns">
                      <?php foreach ($extGroupFields as $extField): ?>
                        <?php
                          if (!is_array($extField)) {
                              continue;
                          }
                          $extName = trim((string) ($extField['name'] ?? ''));
                          if ($extName === '') {
                              continue;
                          }
                          $extKind = (string) ($extField['kind'] ?? 'text');
                          $extValue = $extConfig[$extName] ?? (($extKind === 'int' || $extKind === 'bool') ? 0 : '');
                          $extPoolSuffix = (string) preg_replace('/^reward_pool_\d+_/', '', $extName);
                          $extLabel = __('app.boss.ext.pools.fields.' . $extPoolSuffix, [], $extName);
                          $extDefault = $extDefaultFor($extName, $extDefaults);
                          $extIsChanged = !empty($extChanged[$extName]);
                          // 说明只在"奖池说明"里讲一遍：这里只给字段级短提示
                          $extHint = '';
                        ?>
                        <?php if ($extKind === 'bool'): ?>
                          <label class="boss-check<?= $extPoolSuffix === 'enabled' ? ' boss-check--primary' : '' ?>"
                                 data-boss-pool-enabled-wrap>
                            <input type="hidden" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>" value="0" data-boss-default="<?= htmlspecialchars((string) ($extDefault ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="checkbox" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>" value="1"
                                   data-boss-pool-enabled
                                   data-boss-default="<?= htmlspecialchars((string) ($extDefault ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                   <?= ((int) $extValue === 1) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars((string) $extLabel) ?></span>
                            <?php if ($extIsChanged): ?>
                              <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                            <?php endif; ?>
                          </label>
                        <?php elseif ($extKind === 'enum'): ?>
                          <label class="boss-field" data-boss-pool-mode-wrap>
                            <span><?= htmlspecialchars((string) $extLabel) ?></span>
                            <select name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                    data-boss-pool-mode
                                    data-boss-default="<?= htmlspecialchars((string) ($extDefault ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                              <?php foreach ((array) ($extField['options'] ?? []) as $extEnumOption): ?>
                                <?php $extEnumOption = (string) $extEnumOption; ?>
                                <option value="<?= htmlspecialchars($extEnumOption, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (string) $extValue === $extEnumOption ? 'selected' : '' ?>
                                ><?= htmlspecialchars(__('app.boss.ext.enum_options.' . $extEnumOption, [], $extEnumOption)) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <?php if ($extIsChanged): ?>
                              <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                            <?php endif; ?>
                          </label>
                        <?php elseif ($extKind === 'int'): ?>
                          <?php
                            $extPoolModeIsAll = ((string) ($extConfig[$extPoolGroupKey . '_winner_mode'] ?? 'count') === 'all');
                            // data 钩子要区分开：概率 / 人数 是两个不同的联动目标
                            $extPoolCountAttr = $extPoolSuffix === 'winner_count' ? ' data-boss-pool-count' : '';
                            $extPoolChanceAttr = $extPoolSuffix === 'chance' ? ' data-boss-pool-chance' : '';
                          ?>
                          <label class="boss-field<?= ($extPoolSuffix === 'winner_count' && $extPoolModeIsAll) ? ' is-disabled' : '' ?>"
                                 data-boss-pool-count-wrap>
                            <span><?= htmlspecialchars((string) $extLabel) ?></span>
                            <input type="number" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                   min="<?= (int) ($extField['min'] ?? 0) ?>"
                                   max="<?= (int) ($extField['max'] ?? 2000000000) ?>"
                                   step="1"<?= $extPoolCountAttr ?><?= $extPoolChanceAttr ?>
                                   data-boss-default="<?= htmlspecialchars((string) ($extDefault ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                   <?= ($extPoolSuffix === 'winner_count' && $extPoolModeIsAll) ? 'disabled' : '' ?>
                                   value="<?= htmlspecialchars((string) (int) $extValue) ?>">
                            <?php if ($extIsChanged): ?>
                              <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                            <?php endif; ?>
                          </label>
                        <?php elseif ($extKind === 'itemlist'): ?>
                          <?php
                            $extItemIds = [];
                            foreach (preg_split('/[\s,;]+/', (string) $extValue) ?: [] as $extItemToken) {
                                $extItemToken = (int) trim((string) $extItemToken);
                                if ($extItemToken > 0) {
                                    $extItemIds[$extItemToken] = true;
                                }
                            }
                            $extItemIds = array_keys($extItemIds);
                          ?>
                          <div class="boss-field boss-field--full boss-pool-items<?= count($extItemIds) > 12 ? '' : ' is-open' ?>"
                               data-boss-pool-items>
                            <span>
                              <?= htmlspecialchars((string) $extLabel) ?>
                              <span class="boss-field__unit" data-boss-pool-items-count><?= htmlspecialchars(__('app.boss.ext.pools.items_count', ['count' => (string) count($extItemIds)])) ?></span>
                              <?php if ($extIsChanged): ?>
                                <span class="boss-field__flag boss-field__flag--changed"><?= htmlspecialchars(__('app.boss.ext.changed')) ?></span>
                              <?php endif; ?>
                            </span>
                            <textarea name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                      rows="<?= max(2, (int) ($extField['rows'] ?? 3)) ?>"
                                      data-boss-pool-items-input
                                      data-boss-default="<?= htmlspecialchars((string) ($extDefault ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                      placeholder="<?= htmlspecialchars(__('app.boss.ext.placeholders.intlist')) ?>"><?= htmlspecialchars((string) $extValue) ?></textarea>
                            <?php if (count($extItemIds) > 0): ?>
                              <button type="button" class="boss-chip" data-boss-pool-items-toggle>
                                <?= htmlspecialchars(__('app.boss.ext.pools.items_toggle')) ?>
                              </button>
                            <?php endif; ?>
                            <div class="boss-pool-items__list">
                              <?php if ($extItemIds === []): ?>
                                <span class="muted"><?= htmlspecialchars(__('app.boss.ext.pools.no_items')) ?></span>
                              <?php else: ?>
                                <?php foreach ($extItemIds as $extItemId): ?>
                                  <span class="badge"><?= htmlspecialchars($extItemId . ' · ' . (string) ($extItemNames[$extItemId] ?? ('#' . $extItemId))) ?></span>
                                <?php endforeach; ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </section>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <?= $extPlainHtml ?>
          <?php endif; ?>
        </div>
      <?php $extFirstPanel = false; endforeach; ?>
    </div>

    <div class="boss-save-bar" data-boss-save-bar="ext">
      <span class="boss-save-bar__meta" data-boss-dirty-label><?= htmlspecialchars(__('app.boss.config.no_changes')) ?></span>
      <div class="boss-save-bar__actions">
        <button type="button" class="btn outline" data-boss-discard disabled>
          <?= htmlspecialchars(__('app.boss.config.discard')) ?>
        </button>
        <button type="submit" class="btn warn" id="bossExtConfigSaveBtn" <?= $extAvailable ? '' : 'disabled' ?>>
          <?= htmlspecialchars(__('app.boss.ext.save')) ?>
        </button>
      </div>
    </div>
  </form>
</section>
