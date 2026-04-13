<?php
/**
 * File: resources/views/bag_query/index.php
 * Purpose: Provides functionality for the resources/views/bag_query module.
 */

include dirname(__DIR__) . '/components/page_header.php';
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
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.account')) ?></th>
          <th><?= htmlspecialchars(__('app.bag_query.chars.table.actions')) ?></th>
        </tr></thead>
        <tbody><tr><td colspan="6" class="text-center muted"><?= htmlspecialchars(__('app.bag_query.chars.table.empty')) ?></td></tr></tbody>
      </table>
    </div>
  </section>
</div>

<?php include __DIR__.'/_items_panel.php'; ?>

<?php
  $prefill = $prefill ?? ['type'=>null,'value'=>null,'auto'=>false];
  $prefillPayload = [
    'type' => $prefill['type'] ?? null,
    'value' => $prefill['value'] ?? null,
  ];
  $prefillJson = json_encode($prefillPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $autoSearch = !empty($prefill['auto']);
?>
<script type="application/json" data-panel-json data-global="__BAG_QUERY_CTX"><?= json_encode([
  'prefill' => $prefillPayload,
  'autoSearch' => $autoSearch,
  'labels' => [
    'view' => __('app.js.modules.bag_query.actions.view', [], 'View'),
    'delete' => __('app.js.modules.bag_query.actions.delete', [], 'Delete'),
    'processing' => __('app.js.modules.bag_query.actions.processing', [], 'Processing...'),
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

