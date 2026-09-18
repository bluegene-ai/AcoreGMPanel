<?php
/**
 * File: resources/views/item_inventory/_item_mode.php
 * Purpose: Item axis of the unified module — "who owns this item?". Search an
 *          item template, then list every stack across all characters, with
 *          pagination, bulk delete and bulk replace.
 */

$iiCanManage = (bool) (($__pageCapabilities['manage'] ?? false));
?>

<div class="ii-mode-panel" data-mode-panel="item">
  <div class="ii-toolbar ii-card">
    <form id="iiItemSearchForm" class="ii-form">
      <div class="ii-field ii-field--grow">
        <label for="iiItemKeyword"><?= htmlspecialchars(__('app.item_inventory.item.form.keyword_label')) ?></label>
        <input type="text" id="iiItemKeyword" name="keyword" placeholder="<?= htmlspecialchars(__('app.item_inventory.item.form.keyword_placeholder')) ?>">
      </div>
      <div class="ii-field ii-field--submit">
        <label>&nbsp;</label>
        <button type="submit" class="btn primary" id="iiItemSearchBtn"><?= htmlspecialchars(__('app.item_inventory.item.form.submit')) ?></button>
      </div>
    </form>
    <div class="panel-flash" id="iiItemSearchFlash"></div>
  </div>

  <section class="ii-card ii-card--search-results">
    <header class="ii-card__header">
      <div>
        <h3 class="ii-card__title"><?= htmlspecialchars(__('app.item_inventory.item.search.title')) ?></h3>
        <div class="ii-card__subtitle muted"><?= htmlspecialchars(__('app.item_inventory.item.search.subtitle')) ?></div>
      </div>
    </header>
    <div class="ii-table-wrap">
      <table class="table" id="iiSearchTable">
        <thead><tr>
          <th><?= htmlspecialchars(__('app.item_inventory.item.search.table.entry')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.item.search.table.name')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.item.search.table.quality')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.item.search.table.stackable')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.item.search.table.actions')) ?></th>
        </tr></thead>
        <tbody><tr><td colspan="5" class="text-center muted"><?= htmlspecialchars(__('app.item_inventory.item.search.table.placeholder')) ?></td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="ii-card ii-card--owners">
    <header class="ii-card__header ii-card__header--actions">
      <div>
        <h3 class="ii-card__title" id="iiOwnerTitle"><?= htmlspecialchars(__('app.item_inventory.owners.title_empty')) ?></h3>
        <div class="ii-card__subtitle muted" id="iiOwnerSummary"><?= htmlspecialchars(__('app.item_inventory.owners.subtitle_empty')) ?></div>
      </div>
      <?php if ($iiCanManage): ?>
        <div class="ii-card__actions">
          <button type="button" class="btn danger" id="iiBulkDeleteBtn" disabled><?= htmlspecialchars(__('app.item_inventory.owners.actions.delete_selected')) ?></button>
          <button type="button" class="btn info" id="iiBulkReplaceBtn" disabled><?= htmlspecialchars(__('app.item_inventory.owners.actions.replace_selected')) ?></button>
        </div>
      <?php endif; ?>
    </header>
    <div class="ii-card__body">
      <div class="panel-flash" id="iiOwnerFlash"></div>
      <div class="ii-table-wrap">
        <table class="table" id="iiOwnerTable">
          <thead><tr>
            <th><input type="checkbox" id="iiSelectAll"></th>
            <th><?= htmlspecialchars(__('app.item_inventory.owners.table.instance')) ?></th>
            <th><?= htmlspecialchars(__('app.item_inventory.owners.table.character')) ?></th>
            <th><?= htmlspecialchars(__('app.item_inventory.owners.table.count')) ?></th>
            <th><?= htmlspecialchars(__('app.item_inventory.owners.table.location')) ?></th>
            <th><?= htmlspecialchars(__('app.item_inventory.owners.table.container')) ?></th>
          </tr></thead>
          <tbody><tr><td colspan="6" class="text-center muted"><?= htmlspecialchars(__('app.item_inventory.owners.table.placeholder')) ?></td></tr></tbody>
        </table>
      </div>
      <div class="ii-pager" id="iiOwnerPager" hidden>
        <button type="button" class="btn-sm neutral" id="iiOwnerPrev"><?= htmlspecialchars(__('app.item_inventory.owners.pager.prev')) ?></button>
        <span id="iiOwnerPageInfo" class="muted small"></span>
        <button type="button" class="btn-sm neutral" id="iiOwnerNext"><?= htmlspecialchars(__('app.item_inventory.owners.pager.next')) ?></button>
      </div>
    </div>
  </section>
</div>

<?php if ($iiCanManage): ?>
<div id="iiReplaceModal" class="modal-backdrop">
  <div class="modal-panel modal-panel--narrow">
    <header>
      <h3 class="m-0"><?= htmlspecialchars(__('app.item_inventory.modal.replace.title')) ?></h3>
      <button type="button" class="modal-close" data-close>&times;</button>
    </header>
    <div class="modal-body">
      <div class="ii-del-row">
        <label for="iiReplaceEntry"><?= htmlspecialchars(__('app.item_inventory.modal.replace.entry_label')) ?></label>
        <input type="number" id="iiReplaceEntry" min="1" placeholder="<?= htmlspecialchars(__('app.item_inventory.modal.replace.entry_placeholder')) ?>">
      </div>
      <div class="muted small"><?= htmlspecialchars(__('app.item_inventory.modal.replace.entry_hint')) ?></div>
      <div id="iiReplaceFeedback" class="panel-flash panel-flash--inline"></div>
    </div>
    <footer class="modal-footer">
      <button type="button" class="btn neutral" data-close><?= htmlspecialchars(__('app.item_inventory.modal.replace.cancel')) ?></button>
      <button type="button" class="btn primary" id="iiReplaceConfirm"><?= htmlspecialchars(__('app.item_inventory.modal.replace.confirm')) ?></button>
    </footer>
  </div>
</div>

<div id="iiBulkDeleteModal" class="modal-backdrop">
  <div class="modal-panel modal-panel--narrow">
    <header>
      <h3 class="m-0"><?= htmlspecialchars(__('app.item_inventory.modal.bulk_delete.title')) ?></h3>
      <button type="button" class="modal-close" data-close>&times;</button>
    </header>
    <div class="modal-body">
      <div id="iiBulkDeleteInfo" class="mb-2 small"></div>
      <label class="ii-check-row">
        <input type="checkbox" id="iiBulkDestroy">
        <span><?= htmlspecialchars(__('app.item_inventory.modal.bulk_delete.destroy_contents')) ?></span>
      </label>
      <div id="iiBulkDeleteList" class="ii-failure-list"></div>
      <div id="iiBulkDeleteFeedback" class="panel-flash panel-flash--inline small"></div>
    </div>
    <footer class="modal-footer">
      <button type="button" class="btn neutral" data-close><?= htmlspecialchars(__('app.item_inventory.modal.bulk_delete.cancel')) ?></button>
      <button type="button" class="btn danger" id="iiBulkDeleteConfirm"><?= htmlspecialchars(__('app.item_inventory.modal.bulk_delete.confirm')) ?></button>
    </footer>
  </div>
</div>
<?php endif; ?>
