<?php
/**
 * File: resources/views/item_inventory/_character_mode.php
 * Purpose: Character axis of the unified module — "what does this character
 *          carry?". Search by character name or account username, pick a
 *          character, then inspect or edit their inventory.
 */

?>
<div class="ii-mode-panel" data-mode-panel="character">
  <div class="ii-toolbar ii-card">
    <form id="iiCharSearchForm" class="ii-form">
      <div class="ii-field">
        <label for="iiCharType"><?= htmlspecialchars(__('app.item_inventory.character.form.type_label')) ?></label>
        <select name="type" id="iiCharType">
          <option value="character_name"><?= htmlspecialchars(__('app.item_inventory.character.form.type_character_name')) ?></option>
          <option value="username"><?= htmlspecialchars(__('app.item_inventory.character.form.type_username')) ?></option>
        </select>
      </div>
      <div class="ii-field ii-field--grow">
        <label for="iiCharValue"><?= htmlspecialchars(__('app.item_inventory.character.form.value_label')) ?></label>
        <input type="text" name="value" id="iiCharValue" placeholder="<?= htmlspecialchars(__('app.item_inventory.character.form.value_placeholder')) ?>">
      </div>
      <div class="ii-field ii-field--submit">
        <label>&nbsp;</label>
        <button type="submit" class="btn primary" id="iiCharSearchBtn"><?= htmlspecialchars(__('app.item_inventory.character.form.submit')) ?></button>
      </div>
    </form>
    <div class="panel-flash" id="iiCharFlash"></div>
  </div>

  <section class="ii-card ii-card--chars">
    <header class="ii-card__header">
      <div>
        <h3 class="ii-card__title"><?= htmlspecialchars(__('app.item_inventory.character.chars.title')) ?></h3>
        <div class="ii-card__subtitle muted"><?= htmlspecialchars(__('app.item_inventory.character.chars.subtitle')) ?></div>
      </div>
    </header>
    <div class="ii-table-wrap">
      <table class="table" id="iiCharTable">
        <thead><tr>
          <th><?= htmlspecialchars(__('app.item_inventory.character.chars.table.guid')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.character.chars.table.name')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.character.chars.table.level')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.character.chars.table.race')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.character.chars.table.account')) ?></th>
          <th><?= htmlspecialchars(__('app.item_inventory.character.chars.table.actions')) ?></th>
        </tr></thead>
        <tbody><tr><td colspan="6" class="text-center muted"><?= htmlspecialchars(__('app.item_inventory.character.chars.table.empty')) ?></td></tr></tbody>
      </table>
    </div>
  </section>

  <?php
  $iiItemsTitle = __('app.item_inventory.items.title');
  $iiShowSelect = true;
  // Mirror the capability check the mutation endpoints enforce, so an operator
  // without inventory.manage never sees a delete control that would be rejected.
  $iiShowDelete = (bool) ($__pageCapabilities['manage'] ?? false);
  include dirname(__DIR__) . '/components/inventory_items_panel.php';
  ?>
</div>
