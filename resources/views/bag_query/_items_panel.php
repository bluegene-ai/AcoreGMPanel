<?php
/**
 * File: resources/views/bag_query/_items_panel.php
 * Purpose: Reusable BagQuery items panel (table + delete modal).
 */

$bagQueryItemsTitle = $bagQueryItemsTitle ?? __('app.bag_query.items.title');
?>

<section class="bag-query-card bag-query-card--items">
  <header class="bag-query-card__header">
    <div>
      <h3 class="bag-query-card__title"><?= htmlspecialchars($bagQueryItemsTitle) ?></h3>
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
        <th><?= htmlspecialchars(__('app.bag_query.items.table.count')) ?></th>
        <th><?= htmlspecialchars(__('app.bag_query.items.table.slot')) ?></th>
        <th><?= htmlspecialchars(__('app.bag_query.items.table.actions')) ?></th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="text-center muted"><?= htmlspecialchars(__('app.bag_query.items.table.empty')) ?></td></tr></tbody>
    </table>
  </div>
</section>

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
