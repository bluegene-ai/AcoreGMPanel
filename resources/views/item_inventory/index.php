<?php
/**
 * File: resources/views/item_inventory/index.php
 * Purpose: Unified item / inventory page shell. Hosts both search axes and
 *          hands the client a single context payload.
 *
 * 共用的物品实例面板放在 components/inventory_items_panel.php：角色详情页的背包 Tab 用同一份标记。
 */

include dirname(__DIR__) . '/components/page_header.php';

$iiPrefill = $prefill ?? ['mode' => 'character', 'type' => 'character_name', 'value' => '', 'entry' => 0, 'auto' => false];
$iiCanManage = (bool) (($__pageCapabilities['manage'] ?? false));
$iiCanView = (bool) (($__pageCapabilities['view'] ?? true));

$iiMode = in_array($iiPrefill['mode'] ?? '', ['character', 'item'], true) ? $iiPrefill['mode'] : 'character';
$iiPrefillPayload = [
    'mode' => $iiMode,
    'type' => $iiPrefill['type'] ?? 'character_name',
    'value' => $iiPrefill['value'] ?? '',
    'entry' => (int) ($iiPrefill['entry'] ?? 0),
];
$iiAutoSearch = !empty($iiPrefill['auto']);
?>
<?php if (!$iiCanView): ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
    <?= htmlspecialchars(__('app.common.capabilities.page_limited')) ?>
  </div>
<?php else: ?>

<div class="ii-tabs" role="tablist">
  <button type="button" class="ii-tab" data-mode="character" role="tab"><?= htmlspecialchars(__('app.item_inventory.tabs.character')) ?></button>
  <button type="button" class="ii-tab" data-mode="item" role="tab"><?= htmlspecialchars(__('app.item_inventory.tabs.item')) ?></button>
</div>

<div class="ii-layout" id="iiLayout" data-mode="<?= htmlspecialchars($iiMode, ENT_QUOTES, 'UTF-8') ?>">
  <?php include __DIR__ . '/_character_mode.php'; ?>
  <?php include __DIR__ . '/_item_mode.php'; ?>
</div>

<script type="application/json" data-panel-json data-global="__ITEM_INVENTORY_CTX"><?= json_encode([
    'prefill' => $iiPrefillPayload,
    'autoSearch' => $iiAutoSearch,
    'canManage' => $iiCanManage,
    'maxPageSize' => 200,
    'labels' => [
        'view' => __('app.item_inventory.actions.view'),
        'delete' => __('app.item_inventory.actions.delete'),
        'processing' => __('app.item_inventory.actions.processing'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php endif; ?>
