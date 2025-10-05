<?php
/**
 * File: resources/views/bag_query/index.php
 * Purpose: Provides functionality for the resources/views/bag_query module.
 */

 $module='bag_query'; include __DIR__.'/../layouts/base_top.php'; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.bag_query.page_title')) ?></h1>
<?php

?>
<div class="bag-query-toolbar bag-query-card">
  <form id="bagSearchForm" class="bag-query-form">
    <div class="bag-query-field">
      <label for="bqType"><?= htmlspecialchars(__('app.bag_query.form.type_label')) ?></label>
      <select name="type" id="bqType">
        <option value="character_name"><?= htmlspecialchars(__('app.bag_query.form.type_character_name')) ?></option>
        <option value="username"><?= htmlspecialchars(__('app.bag_query.form.type_username')) ?></option>
      </select>
    </div>
    <div class="bag-query-field bag-query-field--grow">
      <label for="bqValue"><?= htmlspecialchars(__('app.bag_query.form.value_label')) ?></label>
      <input type="text" name="value" id="bqValue" required placeholder="<?= htmlspecialchars(__('app.bag_query.form.value_placeholder')) ?>">
    </div>
    <div class="bag-query-field bag-query-field--submit">
      <label>&nbsp;</label>
      <button type="submit" class="btn primary" id="bqSearchBtn"><?= htmlspecialchars(__('app.bag_query.form.submit')) ?></button>
    </div>
  </form>
</div>

<div class="bag-query-grid">
  <section class="bag-query-card bag-query-card--chars">
    <header class="bag-query-card__header">
      <div>
        <h3 class="bag-query-card__title"><?= htmlspecialchars(__('app.bag_query.chars.title')) ?></h3>
        <div class="bag-query-card__subtitle muted"><?= htmlspecialchars(__('app.bag_query.chars.subtitle')) ?></div>
      </div>
    </header>
    <div class="bag-query-table-wrap">
      <table class="table" id="bqCharTable">
        <thead><tr>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.guid')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.name')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.level')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.race')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.class')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.actions')) ?></th>
        </tr></thead>
        <tbody><tr><td colspan="6" class="text-center muted"><?= htmlspecialchars(__('app.bag_query.chars.table.empty')) ?></td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="bag-query-card bag-query-card--items">
    <header class="bag-query-card__header">
      <div>
        <h3 class="bag-query-card__title"><?= htmlspecialchars(__('app.bag_query.items.title')) ?></h3>
        <div class="bag-query-card__subtitle muted" id="bqItemsCurrent"><?= htmlspecialchars(__('app.bag_query.items.subtitle_empty')) ?></div>
      </div>
      <div class="bag-query-card__actions">
        <input type="text" id="bqItemFilter" class="bag-query-filter" placeholder="<?= htmlspecialchars(__('app.bag_query.items.filter_placeholder')) ?>">
      </div>
    </header>
  <div id="bqActionFlash" class="panel-flash"></div>
    <div class="bag-query-table-wrap">
      <table class="table" id="bqItemTable">
        <thead><tr>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.instance_guid')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.item_id')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.name')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.quality')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.count')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.slot')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.items.table.actions')) ?></th>
        </tr></thead>
        <tbody><tr><td colspan="7" class="text-center muted"><?= htmlspecialchars(__('app.bag_query.items.table.empty')) ?></td></tr></tbody>
      </table>
    </div>
  </section>
</div>

<!-- 删除确认 -->
<div id="bqDeleteModal" class="modal-backdrop">
  <div class="modal-panel modal-panel--narrow">
    <header>
      <h3 class="m-0"><?= htmlspecialchars(__('app.bag_query.modal.title')) ?></h3>
      <button type="button" class="modal-close" data-close>&times;</button>
    </header>
    <div class="modal-body">
      <div id="bqDelInfo" class="mb-2 small"></div>
      <div class="bag-query-del-row">
        <label for="bqDelQty"><?= htmlspecialchars(__('app.bag_query.modal.quantity_label')) ?></label>
        <input type="number" id="bqDelQty" min="1" value="1">
        <div class="bag-query-del-hint muted small"><?= htmlspecialchars(__('app.bag_query.modal.quantity_hint')) ?></div>
      </div>
      <div id="bqDelFeedback" class="panel-flash panel-flash--inline small"></div>
    </div>
    <footer class="modal-footer">
      <button class="btn neutral" type="button" data-close><?= htmlspecialchars(__('app.bag_query.modal.cancel')) ?></button>
      <button class="btn danger" id="bqDelOk" disabled><?= htmlspecialchars(__('app.bag_query.modal.confirm')) ?></button>
    </footer>
  </div>
</div>

<?php
  $prefill = $prefill ?? ['type'=>null,'value'=>null,'auto'=>false];
  $prefillPayload = [
    'type' => $prefill['type'] ?? null,
    'value' => $prefill['value'] ?? null,
  ];
  $prefillJson = json_encode($prefillPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $autoSearch = !empty($prefill['auto']);
?>
<script>
window.__BAG_QUERY_CTX = {
  csrf: window.__CSRF_TOKEN,
  prefill: <?= $prefillJson ?: 'null' ?>,
  autoSearch: <?= $autoSearch ? 'true':'false' ?>
};
</script>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>

