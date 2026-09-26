<?php
/**
 * File: resources/views/components/inventory_items_panel.php
 * Purpose: Shared item-instance panel for the unified item/inventory module.
 *
 * 可选入参：$iiItemsTitle 面板标题 / $iiItemsSubtitle 未选角色时的副标题 /
 *           $iiShowSelect 行内"定位持有者" / $iiShowDelete 行内删除 / $iiEmbedded 嵌入模式（隐藏角色副标题）。
 */

$iiItemsTitle = $iiItemsTitle ?? __('app.item_inventory.items.title');
$iiItemsSubtitle = $iiItemsSubtitle ?? __('app.item_inventory.items.subtitle_empty');
$iiShowSelect = $iiShowSelect ?? false;
$iiShowDelete = $iiShowDelete ?? true;
$iiEmbedded = $iiEmbedded ?? false;
$iiColumnCount = $iiShowSelect ? 7 : 6;
$iiShowOwners = $iiShowSelect;

// 物品名深链到物品编辑器（与本页的任务 ID 一致）：纯 GET、无额外查询，
// 并以 content.view 为门槛，避免给出目标页会拒绝的链接。
$iiCanEditContent = (bool) ($__can('content.view') ?? false);

// 告诉前端模块这个面板实例能做什么：这是必需的，不是装饰 —— 面板嵌入角色详情页时
// 物品轴向的标记（持有者表、搜索表单）并不存在，只靠 DOM 探测无法决定渲染哪些列。
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
