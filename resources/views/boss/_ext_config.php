<?php
/**
 * File: resources/views/boss/_ext_config.php
 * Purpose: Boss 扩展配置表单（ac_eluna.boss_activity_config_ext）。
 *
 * 数据来自控制器传的 $boss_ext：config（当前值）/ tabs（二级 Tab → 字段组）/
 * fields（分组字段 schema）/ available（ext 表是否已由 boss.lua 建好）/
 * defaults（出厂默认值，用于"恢复默认"与"已改动"标记）/ changed（哪些字段与默认不同）/
 * reward_params（选人参数，属于主表，见下）。
 *
 * 字段按 schema 的 kind 渲染：text 单行 / lines 多行逐条 / keyedlines、keyedintlist
 * 多行"键=值" / intlist 逗号列表 / int 整数（min/max 与 Lua 边界一致）/ bool 开关 /
 * enum 枚举下拉 / preset_multi 预设多选。
 *
 * 版式约定：
 *   · 「奖励结算」二级 Tab 里选人参数（主表字段）排在普通分组前面 —— 它们一起决定怎么发奖；
 *   · 选人参数用 form="bossConfigForm" 挂到基础配置表单上，奖励参数集中在一处；
 *   · 表单底部是粘性保存栏，长表单在任何位置都能保存。
 *   · 奖池本体（boss_reward_pools）不在这里，见奖池页（/boss/pools）。
 */

$ext = is_array($boss_ext ?? null) ? $boss_ext : [];
$extConfig = is_array($ext['config'] ?? null) ? $ext['config'] : [];
$extTabs = is_array($ext['tabs'] ?? null) ? $ext['tabs'] : [];
$extFields = is_array($ext['fields'] ?? null) ? $ext['fields'] : [];
$extAvailable = ($ext['available'] ?? true) !== false;
$extDefaults = is_array($ext['defaults'] ?? null) ? $ext['defaults'] : [];
$extChanged = is_array($ext['changed'] ?? null) ? $ext['changed'] : [];
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
    <?= panel_hint(__('app.boss.ext.note_hint')) ?>
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
          $extGroupKeys = [];
          foreach ((array) $extTabGroups as $extGroupKey) {
              $extGroupKeys[] = (string) $extGroupKey;
          }
          $extTabPanelId = 'ext-panel-' . preg_replace('/[^a-z0-9_]/i', '', (string) $extTabKey);
        ?>
        <?php
          // 普通分组先渲染成一段 HTML：「奖励结算」Tab 要把它排在选人参数后面。
          ob_start();
        ?>
        <div class="boss-config-grid">
          <?php foreach ($extGroupKeys as $extGroupKey): ?>
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
                  <?= panel_hint(__('app.boss.ext.class_map.note')) ?>
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
                        ? ' ' . panel_hint($extHint)
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

          <?php if ($extTabKey === 'reward'): ?>
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
          <?php endif; ?>
          <?= $extPlainHtml ?>
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
