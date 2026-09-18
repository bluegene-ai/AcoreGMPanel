<?php
/**
 * File: resources/views/character_boost/index.php
 * Purpose: 直升管理统一入口：执行直升、模板概览、兑换码概览、直升历史。
 *
 * 原先"执行直升"散落在角色详情页与群发管理页，这里收敛为唯一入口；
 * 模板与兑换码的增删改仍由各自的子页面负责。
 */

use Acme\Panel\Support\Csrf;

$boostCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
        'apply' => $__can('boost.apply'),
        'templates' => $__can('boost.templates'),
        'codes' => $__can('boost.codes'),
    ];
$__pageCapabilities = $boostCapabilities;

$boostTemplates = is_array($templates ?? null) ? $templates : [];
$boostCodeStats = is_array($code_stats ?? null) ? $code_stats : ['total' => 0, 'unused' => 0, 'used' => 0];
$boostHistory = is_array($history ?? null) ? $history : [];
$boostRealmId = (int) ($realm_id ?? 1);

$boostApplyEndpoint = url('/character-boost/api/apply');
$boostHistoryEndpoint = url('/character-boost/api/history');
$boostTemplateListUrl = url('/character-boost/templates');
$boostCodeListUrl = url('/character-boost/redeem-codes');

include dirname(__DIR__) . '/components/page_header.php';
?>
<?php include dirname(__DIR__) . '/components/capability_notice.php'; ?>

<div id="boostFeedback" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

<div class="cb-hub">
  <?php if ($boostCapabilities['apply']): ?>
  <section class="cb-hub__card cb-hub__card--apply">
    <h3 class="cb-section-title"><?= htmlspecialchars(__('app.character_boost.admin.apply.title')) ?></h3>
    <p class="cb-hub__note muted small"><?= htmlspecialchars(__('app.character_boost.admin.apply.note', ['realm' => (string) $boostRealmId])) ?></p>

    <form
      id="boostApplyForm"
      class="form"
      data-endpoint="<?= htmlspecialchars($boostApplyEndpoint) ?>"
      data-history-endpoint="<?= htmlspecialchars($boostHistoryEndpoint) ?>"
      data-label-preview="<?= htmlspecialchars(__('app.character_boost.admin.actions.preview'), ENT_QUOTES, 'UTF-8') ?>"
      data-label-apply="<?= htmlspecialchars(__('app.character_boost.admin.actions.apply'), ENT_QUOTES, 'UTF-8') ?>"
      data-label-working="<?= htmlspecialchars(__('app.character_boost.admin.actions.working'), ENT_QUOTES, 'UTF-8') ?>"
    >
      <?= Csrf::field() ?>

      <div class="cb-hub__grid">
        <label class="cb-field">
          <span><?= htmlspecialchars(__('app.character_boost.admin.fields.character_name')) ?></span>
          <input type="text" name="character_name" id="boostApplyName" autocomplete="off" placeholder="<?= htmlspecialchars(__('app.character_boost.admin.fields.character_name_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label class="cb-field">
          <span><?= htmlspecialchars(__('app.character_boost.admin.fields.guid')) ?></span>
          <input type="number" name="guid" id="boostApplyGuid" min="1" placeholder="<?= htmlspecialchars(__('app.character_boost.admin.fields.guid_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label class="cb-field cb-field--wide">
          <span><?= htmlspecialchars(__('app.character_boost.admin.fields.template')) ?></span>
          <select name="template_id" id="boostApplyTemplate">
            <option value=""><?= htmlspecialchars(__('app.character_boost.admin.fields.template_none')) ?></option>
            <?php foreach ($boostTemplates as $tpl): ?>
              <option value="<?= (int) ($tpl['id'] ?? 0) ?>" data-target-level="<?= (int) ($tpl['target_level'] ?? 0) ?>">
                <?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?> (Lv<?= (int) ($tpl['target_level'] ?? 0) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="cb-field">
          <span><?= htmlspecialchars(__('app.character_boost.admin.fields.target_level')) ?></span>
          <input type="number" name="target_level" id="boostApplyLevel" min="1" max="255" placeholder="<?= htmlspecialchars(__('app.character_boost.admin.fields.target_level_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
        </label>
      </div>

      <div class="cb-hub__actions">
        <button class="btn outline" type="button" id="boostApplyPreview"><?= htmlspecialchars(__('app.character_boost.admin.actions.preview')) ?></button>
        <button class="btn success" type="submit" id="boostApplySubmit"><?= htmlspecialchars(__('app.character_boost.admin.actions.apply')) ?></button>
      </div>

      <p class="cb-help"><?= htmlspecialchars(__('app.character_boost.admin.apply.hint')) ?></p>
    </form>

    <div id="boostApplyPreviewBox" class="cb-preview" hidden>
      <h4 class="cb-preview__title"><?= htmlspecialchars(__('app.character_boost.admin.preview.title')) ?></h4>
      <div id="boostApplyPreviewBody" class="cb-preview__body"></div>
    </div>
  </section>
  <?php endif; ?>

  <section class="cb-hub__card">
    <h3 class="cb-section-title"><?= htmlspecialchars(__('app.character_boost.admin.overview.title')) ?></h3>
    <div class="cb-hub__stats">
      <div class="cb-stat">
        <span class="cb-stat__label"><?= htmlspecialchars(__('app.character_boost.admin.overview.templates')) ?></span>
        <strong class="cb-stat__value"><?= count($boostTemplates) ?></strong>
      </div>
      <div class="cb-stat">
        <span class="cb-stat__label"><?= htmlspecialchars(__('app.character_boost.admin.overview.code_total')) ?></span>
        <strong class="cb-stat__value"><?= (int) $boostCodeStats['total'] ?></strong>
      </div>
      <div class="cb-stat">
        <span class="cb-stat__label"><?= htmlspecialchars(__('app.character_boost.admin.overview.code_unused')) ?></span>
        <strong class="cb-stat__value"><?= (int) $boostCodeStats['unused'] ?></strong>
      </div>
      <div class="cb-stat">
        <span class="cb-stat__label"><?= htmlspecialchars(__('app.character_boost.admin.overview.code_used')) ?></span>
        <strong class="cb-stat__value"><?= (int) $boostCodeStats['used'] ?></strong>
      </div>
    </div>

    <div class="cb-hub__links">
      <?php if ($boostCapabilities['templates']): ?>
        <a class="btn outline btn-sm" href="<?= htmlspecialchars($boostTemplateListUrl) ?>"><?= htmlspecialchars(__('app.character_boost.admin.links.templates')) ?></a>
      <?php endif; ?>
      <?php if ($boostCapabilities['codes']): ?>
        <a class="btn outline btn-sm" href="<?= htmlspecialchars($boostCodeListUrl) ?>"><?= htmlspecialchars(__('app.character_boost.admin.links.codes')) ?></a>
      <?php endif; ?>
    </div>

    <?php if ($boostTemplates !== []): ?>
      <div class="cb-table-wrap cb-hub__table">
        <table class="table table--compact">
          <thead>
            <tr>
              <th>ID</th>
              <th><?= htmlspecialchars(__('app.character_boost.templates.columns.name')) ?></th>
              <th><?= htmlspecialchars(__('app.character_boost.templates.columns.target_level')) ?></th>
              <th><?= htmlspecialchars(__('app.character_boost.templates.columns.money_gold')) ?></th>
              <th><?= htmlspecialchars(__('app.character_boost.templates.columns.items')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($boostTemplates as $tpl): ?>
              <?php $tplItems = is_array($tpl['items'] ?? null) ? $tpl['items'] : []; ?>
              <tr>
                <td><?= (int) ($tpl['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?></td>
                <td><?= (int) ($tpl['target_level'] ?? 0) ?></td>
                <td><?= (int) ($tpl['money_gold'] ?? 0) ?></td>
                <td class="cb-detail-cell">
                  <?php if ($tplItems === []): ?>
                    <span class="cb-muted">-</span>
                  <?php else: ?>
                    <?php foreach ($tplItems as $item): ?>
                      <?php
                        $entry = (int) ($item['item_entry'] ?? 0);
                        $qty = (int) ($item['quantity'] ?? 0);
                        $name = (string) ($item['item_name'] ?? '');
                        if ($entry <= 0 || $qty <= 0) {
                            continue;
                        }
                      ?>
                      <div><?= htmlspecialchars($name !== '' ? $name : ('#' . $entry)) ?> ×<?= $qty ?> <span class="cb-code-meta">(#<?= $entry ?>)</span></div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($boostCapabilities['apply']): ?>
  <section class="cb-hub__card cb-hub__card--wide">
    <div class="cb-hub__card-head">
      <h3 class="cb-section-title m-0"><?= htmlspecialchars(__('app.character_boost.admin.history.title')) ?></h3>
      <button class="btn btn-sm outline" type="button" id="boostHistoryRefresh"><?= htmlspecialchars(__('app.character_boost.admin.history.refresh')) ?></button>
    </div>
    <div class="cb-table-wrap">
      <table class="table table--compact" id="boostHistoryTable">
        <thead>
          <tr>
            <th><?= htmlspecialchars(__('app.character_boost.admin.history.columns.time')) ?></th>
            <th><?= htmlspecialchars(__('app.character_boost.admin.history.columns.character')) ?></th>
            <th><?= htmlspecialchars(__('app.character_boost.admin.history.columns.rewards')) ?></th>
            <th><?= htmlspecialchars(__('app.character_boost.admin.history.columns.status')) ?></th>
          </tr>
        </thead>
        <tbody id="boostHistoryBody">
          <?php if ($boostHistory === []): ?>
            <tr class="js-empty-row"><td colspan="4" class="cb-empty-cell"><?= htmlspecialchars(__('app.character_boost.admin.history.empty')) ?></td></tr>
          <?php else: ?>
            <?php foreach ($boostHistory as $log): ?>
              <?php $logOk = (int) ($log['success'] ?? 0) === 1; ?>
              <tr class="<?= $logOk ? 'log-ok' : 'log-fail' ?>">
                <td><?= htmlspecialchars(substr((string) ($log['created_at'] ?? ''), 0, 19)) ?></td>
                <td><?= htmlspecialchars((string) ($log['recipients'] ?? '')) ?></td>
                <td>
                  <div><?= htmlspecialchars((string) ($log['items'] ?? '')) ?></div>
                  <?php if ((int) ($log['amount'] ?? 0) > 0): ?>
                    <div class="small muted"><?= htmlspecialchars(format_money_gsc((int) $log['amount'])) ?></div>
                  <?php endif; ?>
                  <?php if (!$logOk && !empty($log['sample_errors'])): ?>
                    <div class="small text-danger" title="<?= htmlspecialchars((string) $log['sample_errors']) ?>"><?= htmlspecialchars(mb_strimwidth((string) $log['sample_errors'], 0, 80, '…', 'UTF-8')) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= $logOk ? '✔' : '✖' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>
</div>
