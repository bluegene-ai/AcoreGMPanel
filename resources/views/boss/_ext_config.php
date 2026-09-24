<?php
/**
 * File: resources/views/boss/_ext_config.php
 * Purpose: Boss 扩展配置表单（ac_eluna.boss_activity_config_ext）。
 *
 * 数据来自控制器传的 $boss_ext：config（当前值）/ tabs（二级 Tab → 字段组）/
 * fields（分组字段 schema）/ available（ext 表是否已由 boss.lua 建好）。
 * 字段按 schema 的 kind 渲染：text 单行 / lines 多行逐条 / keyedlines、keyedintlist
 * 多行"键=值" / intlist 逗号列表 / int 整数（min/max 与 Lua 边界一致）/ bool 开关。
 * 同一份 schema 也被 BossController::normalizeExtPayload 用来归一化提交值。
 */

$ext = is_array($boss_ext ?? null) ? $boss_ext : [];
$extConfig = is_array($ext['config'] ?? null) ? $ext['config'] : [];
$extTabs = is_array($ext['tabs'] ?? null) ? $ext['tabs'] : [];
$extFields = is_array($ext['fields'] ?? null) ? $ext['fields'] : [];
$extAvailable = ($ext['available'] ?? true) !== false;

// 定时启停（脚本侧 tick 执行）：面板只负责编辑 + 预览解析结果
$extSchedule = is_array($ext['schedule'] ?? null) ? $ext['schedule'] : [];
$extScheduleWindows = is_array($extSchedule['windows'] ?? null) ? $extSchedule['windows'] : [];
$extScheduleSamplesRendered = false;
?>
<section class="boss-panel boss-panel--config">
  <div class="boss-panel__head">
    <h2><?= htmlspecialchars(__('app.boss.ext.title')) ?></h2>
    <?php if (!$extAvailable): ?>
      <span class="boss-muted"><?= htmlspecialchars(__('app.boss.ext.unavailable')) ?></span>
    <?php endif; ?>
  </div>
  <p class="muted boss-config-note"><?= htmlspecialchars(__('app.boss.ext.note')) ?></p>

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
        <div class="boss-tabpanel<?= $extFirstPanel ? ' is-active' : '' ?>" role="tabpanel"
             data-boss-tabpanel="<?= htmlspecialchars((string) $extTabKey, ENT_QUOTES, 'UTF-8') ?>">
          <div class="boss-config-grid">
            <?php foreach ((array) $extTabGroups as $extGroupKey): ?>
              <?php
                $extGroupFields = is_array($extFields[$extGroupKey] ?? null) ? $extFields[$extGroupKey] : [];
                if ($extGroupFields === []) {
                    continue;
                }
              ?>
              <section class="boss-config-section">
                <div class="boss-config-section__head">
                  <h3><?= htmlspecialchars(__('app.boss.ext.groups.' . $extGroupKey, [], (string) $extGroupKey)) ?></h3>
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
                      $extLabel = __('app.boss.ext.fields.' . $extName, [], $extName);
                      $extHint = !empty($extField['hint']) ? __('app.boss.ext.hints.' . $extName, [], '') : '';
                      $extLongKinds = ['lines', 'keyedlines', 'keyedintlist'];
                      $extIsLong = in_array($extKind, $extLongKinds, true);
                      $extIsWide = $extIsLong || $extKind === 'intlist' || $extKind === 'schedule_windows';
                    ?>
                    <?php if ($extKind === 'bool'): ?>
                      <label class="boss-check">
                        <input type="hidden" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>" value="0">
                        <input type="checkbox" name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>" value="1"
                               <?= ((int) $extValue === 1) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars((string) $extLabel) ?></span>
                        <?php if ($extHint !== ''): ?>
                          <small class="muted boss-check__hint"><?= htmlspecialchars((string) $extHint) ?></small>
                        <?php endif; ?>
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
                                    placeholder="<?= htmlspecialchars((string) $extPlaceholder) ?>"><?= htmlspecialchars((string) $extValue) ?></textarea>
                        <?php elseif ($extKind === 'int'): ?>
                          <input type="number"
                                 name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                 min="<?= (int) ($extField['min'] ?? 0) ?>"
                                 max="<?= (int) ($extField['max'] ?? 2000000000) ?>"
                                 step="1"
                                 value="<?= htmlspecialchars((string) (int) $extValue) ?>">
                        <?php elseif ($extKind === 'schedule_windows'): ?>
                          <input type="text"
                                 name="<?= htmlspecialchars($extName, ENT_QUOTES, 'UTF-8') ?>"
                                 maxlength="<?= max(1, (int) ($extField['maxlength'] ?? 255)) ?>"
                                 list="bossScheduleSamples"
                                 autocomplete="off"
                                 placeholder="<?= htmlspecialchars(__('app.boss.ext.placeholders.schedule_windows')) ?>"
                                 value="<?= htmlspecialchars((string) $extValue) ?>">
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
                                 value="<?= htmlspecialchars((string) $extValue) ?>">
                        <?php endif; ?>
                        <?php if ($extHint !== ''): ?>
                          <small class="muted"><?= htmlspecialchars((string) $extHint) ?></small>
                        <?php endif; ?>
                      </label>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endforeach; ?>
          </div>
        </div>
      <?php $extFirstPanel = false; endforeach; ?>
    </div>

    <div class="boss-config-actions">
      <button type="submit" class="btn warn" id="bossExtConfigSaveBtn" <?= $extAvailable ? '' : 'disabled' ?>>
        <?= htmlspecialchars(__('app.boss.ext.save')) ?>
      </button>
    </div>
  </form>
</section>
