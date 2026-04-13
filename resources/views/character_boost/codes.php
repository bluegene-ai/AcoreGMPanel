<?php
/**
 * File: resources/views/character_boost/codes.php
 * Purpose: Admin tool UI to generate redeem codes for boost templates.
 */

use Acme\Panel\Support\Csrf;

$endpoint = url('/character-boost/api/redeem-codes/generate');
$endpointStats = url('/character-boost/api/redeem-codes/stats');
$endpointList = url('/character-boost/api/redeem-codes/list');
$endpointDeleteUnused = url('/character-boost/api/redeem-codes/delete-unused');
$endpointPurgeUnused = url('/character-boost/api/redeem-codes/purge-unused');
$realmId = (int) ($realm_id ?? 1);
$templates = is_array($templates ?? null) ? $templates : [];

include dirname(__DIR__) . '/components/page_header.php';
?>

<div class="panel cb-panel-section">
  <div id="boostCodesFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

  <form id="boostCodesForm" class="form" data-endpoint="<?= htmlspecialchars($endpoint) ?>">
    <?= Csrf::field() ?>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.fields.realm')) ?></label>
      <div class="input cb-inline-input">
        <strong><?= htmlspecialchars((string)$realmId) ?></strong>
        <span class="cb-muted"><?= htmlspecialchars(__('app.character_boost.codes.hint.realm_from_server')) ?></span>
      </div>
    </div>

    <div class="form-row">
      <label class="label" for="boostCodesTemplate"><?= htmlspecialchars(__('app.character_boost.codes.fields.template')) ?></label>
      <select id="boostCodesTemplate" name="template_id" class="input" required>
        <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.fields.template_all')) ?></option>
        <?php foreach ($templates as $tpl): ?>
          <option value="<?= (int)($tpl['id'] ?? 0) ?>">
            <?= htmlspecialchars((string)($tpl['name'] ?? '')) ?> (Lv.<?= (int)($tpl['target_level'] ?? 0) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label class="label" for="boostCodesCount"><?= htmlspecialchars(__('app.character_boost.codes.fields.count')) ?></label>
      <input id="boostCodesCount" name="count" type="number" class="input" min="1" max="10000" value="10" required />
      <div class="help cb-help">
        <?= htmlspecialchars(__('app.character_boost.codes.hint.count_limit')) ?>
      </div>
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.fields.output')) ?></label>
      <label class="cb-inline-check">
        <input type="checkbox" name="download" value="1" />
        <span><?= htmlspecialchars(__('app.character_boost.codes.fields.download')) ?></span>
      </label>
      <div class="help cb-help">
        <?= htmlspecialchars(__('app.character_boost.codes.hint.download')) ?>
      </div>
    </div>

    <div class="form-row">
      <button class="btn btn-primary" type="submit"><?= htmlspecialchars(__('app.character_boost.codes.actions.generate')) ?></button>
    </div>
  </form>
</div>

<div class="panel">
  <h3 class="cb-section-title"><?= htmlspecialchars(__('app.character_boost.codes.generated.title')) ?></h3>
  <textarea id="boostCodesOutput" class="input cb-output" readonly></textarea>
  <div class="help cb-help">
    <?= htmlspecialchars(__('app.character_boost.codes.generated.hint')) ?>
  </div>
</div>

<div class="panel cb-manage-section">
  <h3 class="cb-section-title"><?= htmlspecialchars(__('app.character_boost.codes.manage.title')) ?></h3>

  <div id="boostCodesManageFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

  <form id="boostCodesManageForm" class="form"
        data-endpoint-stats="<?= htmlspecialchars($endpointStats) ?>"
        data-endpoint-list="<?= htmlspecialchars($endpointList) ?>"
        data-endpoint-delete-unused="<?= htmlspecialchars($endpointDeleteUnused) ?>"
        data-endpoint-purge-unused="<?= htmlspecialchars($endpointPurgeUnused) ?>">
    <?= Csrf::field() ?>

    <div class="form-row">
      <label class="label" for="boostCodesManageTemplate"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.template')) ?></label>
      <select id="boostCodesManageTemplate" name="template_id" class="input">
        <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.fields.template_all')) ?></option>
        <?php foreach ($templates as $tpl): ?>
          <option value="<?= (int)($tpl['id'] ?? 0) ?>">
            <?= htmlspecialchars((string)($tpl['name'] ?? '')) ?> (Lv.<?= (int)($tpl['target_level'] ?? 0) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.unused_only')) ?></label>
      <label class="cb-inline-check">
        <input type="checkbox" id="boostCodesManageUnusedOnly" name="unused_only" value="1" checked />
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.unused_only_label')) ?></span>
      </label>
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.title')) ?></label>
      <div class="input cb-inline-stats">
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.total')) ?>: <strong id="boostCodesStatTotal">-</strong></span>
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.unused')) ?>: <strong id="boostCodesStatUnused">-</strong></span>
        <span><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.used')) ?>: <strong id="boostCodesStatUsed">-</strong></span>
      </div>
    </div>

    <div class="form-row cb-action-row">
      <button class="btn" id="boostCodesManageRefresh" type="button"><?= htmlspecialchars(__('app.character_boost.codes.manage.actions.refresh')) ?></button>
      <button class="btn btn-danger" id="boostCodesManagePurgeUnused" type="button"><?= htmlspecialchars(__('app.character_boost.codes.manage.actions.purge_unused')) ?></button>
    </div>
  </form>

  <div class="cb-table-wrap">
    <table class="table table--compact cb-table-min">
      <thead>
        <tr>
          <th>
            <a href="#" id="boostCodesSortId" class="cb-sort-link">
              <span><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.id')) ?></span>
              <span id="boostCodesSortIdIcon" class="cb-sort-icon"></span>
            </a>
          </th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.template')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.code')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.status')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.used_by')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.created_at')) ?></th>
          <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.actions')) ?></th>
        </tr>
      </thead>
      <tbody id="boostCodesManageTbody">
        <tr><td colspan="7" class="cb-empty-cell"><?= htmlspecialchars(__('app.common.loading')) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="help cb-help cb-help--spaced">
    <?= htmlspecialchars(__('app.character_boost.codes.manage.hint')) ?>
  </div>

  <div class="cb-pager">
    <button class="btn" id="boostCodesPagePrev" type="button">&larr;</button>
    <span id="boostCodesPageInfo" class="cb-toolbar-note"></span>
    <button class="btn" id="boostCodesPageNext" type="button">&rarr;</button>
  </div>
</div>
