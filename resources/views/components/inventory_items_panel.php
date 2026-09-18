<?php
/**
 * File: resources/views/components/inventory_items_panel.php
 * Purpose: Shared item-instance panel for the unified item/inventory module.
 *          Included by the standalone page (character axis) and by the
 *          character detail "inventory" tab.
 *
 * Expects (all optional):
 *   $iiItemsTitle        string  panel heading
 *   $iiItemsSubtitle     string  initial subtitle when no character is selected
 *   $iiShowSelect        bool    render the per-row "locate owner" action
 *   $iiShowDelete        bool    render the per-row delete action
 *   $iiEmbedded          bool    hide the character subtitle (embedded mode)
 */

$iiItemsTitle = $iiItemsTitle ?? __('app.item_inventory.items.title');
$iiItemsSubtitle = $iiItemsSubtitle ?? __('app.item_inventory.items.subtitle_empty');
$iiShowSelect = $iiShowSelect ?? false;
$iiShowDelete = $iiShowDelete ?? true;
$iiEmbedded = $iiEmbedded ?? false;
$iiColumnCount = $iiShowSelect ? 7 : 6;
$iiShowOwners = $iiShowSelect;

// Item names deep-link into the item editor, exactly like quest ids do on this
// same page. Pure GET, no extra queries. Gated on content.view so we never offer
// a link the target screen would reject.
$iiCanEditContent = (bool) ($__can('content.view') ?? false);

// Tells the client module what this particular panel instance can do. This is
// required, not cosmetic: the panel is embedded in the character detail page,
// where the item-axis markup (owner table, search forms) does not exist, so DOM
// sniffing alone cannot decide which columns to render.
$iiPanelConfig = [
    'embedded' => (bool) $iiEmbedded,
    'showDelete' => (bool) $iiShowDelete,
    'showOwners' => (bool) $iiShowOwners,
    'columns' => $iiColumnCount,
    'editUrl' => $iiCanEditContent ? \Acme\Panel\Core\Url::to('/item') . '?edit_id=' : null,
];
?>

<script type="application/json" data-panel-json data-global="__ITEM_INVENTORY_PANEL"><?= json_encode(
    $iiPanelConfig,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<section class="ii-card ii-card--items">
  <header class="ii-card__header">
    <div>
      <h3 class="ii-card__title"><?= htmlspecialchars($iiItemsTitle) ?></h3>
      <?php if (!$iiEmbedded): ?>
        <div class="ii-card__subtitle muted" id="iiItemsCurrent"><?= htmlspecialchars($iiItemsSubtitle) ?></div>
      <?php endif; ?>
    </div>
    <div class="ii-card__actions">
      <input type="text" id="iiItemFilter" class="ii-filter" placeholder="<?= htmlspecialchars(__('app.item_inventory.items.filter_placeholder')) ?>">
    </div>
  </header>

  <div id="iiActionFlash" class="panel-flash"></div>

  <div class="ii-table-wrap">
    <table class="table" id="iiItemTable">
      <thead><tr>
        <th><?= htmlspecialchars(__('app.item_inventory.items.table.instance_guid')) ?></th>
        <th><?= htmlspecialchars(__('app.item_inventory.items.table.item_id')) ?></th>
        <th><?= htmlspecialchars(__('app.item_inventory.items.table.name')) ?></th>
        <th><?= htmlspecialchars(__('app.item_inventory.items.table.count')) ?></th>
        <th><?= htmlspecialchars(__('app.item_inventory.items.table.location')) ?></th>
        <th><?= htmlspecialchars(__('app.item_inventory.items.table.actions')) ?></th>
        <?php if ($iiShowSelect): ?>
          <th><?= htmlspecialchars(__('app.item_inventory.items.table.owners')) ?></th>
        <?php endif; ?>
      </tr></thead>
      <tbody><tr><td colspan="<?= $iiColumnCount ?>" class="text-center muted"><?= htmlspecialchars(__('app.item_inventory.items.table.empty')) ?></td></tr></tbody>
    </table>
  </div>
</section>

<!-- Reduce / delete one instance -->
<div id="iiDeleteModal" class="modal-backdrop">
  <div class="modal-panel modal-panel--narrow">
    <header>
      <h3 class="m-0"><?= htmlspecialchars(__('app.item_inventory.modal.delete.title')) ?></h3>
      <button type="button" class="modal-close" data-close>&times;</button>
    </header>
    <div class="modal-body">
      <div id="iiDelInfo" class="mb-2 small"></div>
      <div class="ii-del-row">
        <label for="iiDelQty"><?= htmlspecialchars(__('app.item_inventory.modal.delete.quantity_label')) ?></label>
        <input type="number" id="iiDelQty" min="1" value="1">
      </div>
      <div class="muted small"><?= htmlspecialchars(__('app.item_inventory.modal.delete.quantity_hint')) ?></div>
      <label class="ii-check-row" id="iiDelDestroyRow" hidden>
        <input type="checkbox" id="iiDelDestroy">
        <span><?= htmlspecialchars(__('app.item_inventory.modal.delete.destroy_contents')) ?></span>
      </label>
      <div id="iiDelFeedback" class="panel-flash panel-flash--inline small"></div>
    </div>
    <footer class="modal-footer">
      <button class="btn neutral" type="button" data-close><?= htmlspecialchars(__('app.item_inventory.modal.delete.cancel')) ?></button>
      <button class="btn danger" id="iiDelOk" disabled><?= htmlspecialchars(__('app.item_inventory.modal.delete.confirm')) ?></button>
    </footer>
  </div>
</div>
