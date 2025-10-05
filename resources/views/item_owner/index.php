<?php
/**
 * File: resources/views/item_owner/index.php
 * Purpose: Provides functionality for the resources/views/item_owner module.
 */

 $module = 'item_owner'; include __DIR__ . '/../layouts/base_top.php'; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.item_owner.page_title')) ?></h1>
<div class="item-owner-layout">
  <section class="item-owner-card item-owner-card--search">
    <header class="item-owner-card__header">
      <div>
        <h3 class="item-owner-card__title"><?= htmlspecialchars(__('app.item_owner.search.title')) ?></h3>
        <div class="item-owner-card__subtitle muted"><?= htmlspecialchars(__('app.item_owner.search.subtitle')) ?></div>
      </div>
    </header>
    <div class="item-owner-card__body">
      <form id="itemOwnerSearch" class="item-owner-form">
        <div class="item-owner-form__field item-owner-form__field--grow">
          <label for="itemOwnerKeyword"><?= htmlspecialchars(__('app.item_owner.search.keyword_label')) ?></label>
          <input type="text" id="itemOwnerKeyword" name="keyword" placeholder="<?= htmlspecialchars(__('app.item_owner.search.keyword_placeholder')) ?>" required>
        </div>
        <div class="item-owner-form__field">
          <label>&nbsp;</label>
          <button type="submit" class="btn primary" id="itemOwnerSearchBtn"><?= htmlspecialchars(__('app.item_owner.search.submit')) ?></button>
        </div>
      </form>
      <div class="panel-flash" id="itemOwnerSearchFlash"></div>
      <div class="item-owner-table-wrap">
        <table class="table" id="itemOwnerItemTable">
          <thead>
            <tr>
              <th><?= htmlspecialchars(__('app.item_owner.search.results.entry')) ?></th>
              <th><?= htmlspecialchars(__('app.item_owner.search.results.name')) ?></th>
              <th><?= htmlspecialchars(__('app.item_owner.search.results.quality')) ?></th>
              <th><?= htmlspecialchars(__('app.item_owner.search.results.stackable')) ?></th>
              <th><?= htmlspecialchars(__('app.item_owner.search.results.actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="5" class="text-center muted"><?= htmlspecialchars(__('app.item_owner.search.results.placeholder')) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
  <div class="item-owner-layout__row">
    <section class="item-owner-card item-owner-card--characters">
      <header class="item-owner-card__header item-owner-card__header--summary">
        <div>
          <h3 class="item-owner-card__title" id="itemOwnerSelectedTitle"><?= htmlspecialchars(__('app.item_owner.results.title_empty')) ?></h3>
          <div class="item-owner-card__subtitle muted" id="itemOwnerSummary"><?= htmlspecialchars(__('app.item_owner.results.subtitle_empty')) ?></div>
        </div>
      </header>
      <div class="item-owner-card__body">
        <h4 class="item-owner-card__section-title"><?= htmlspecialchars(__('app.item_owner.results.characters.title')) ?></h4>
        <div class="item-owner-table-wrap">
          <table class="table" id="itemOwnerCharacterTable">
            <thead>
              <tr>
                <th><?= htmlspecialchars(__('app.item_owner.results.characters.name')) ?></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.characters.level')) ?></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.characters.total')) ?></th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="3" class="text-center muted"><?= htmlspecialchars(__('app.item_owner.results.characters.placeholder')) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    <section class="item-owner-card item-owner-card--instances">
      <header class="item-owner-card__header item-owner-card__header--actions">
        <div>
          <h3 class="item-owner-card__title"><?= htmlspecialchars(__('app.item_owner.results.instances.title')) ?></h3>
        </div>
        <div class="item-owner-card__actions">
          <button type="button" class="btn danger" id="itemOwnerDeleteBtn" disabled><?= htmlspecialchars(__('app.item_owner.actions.delete_selected')) ?></button>
          <button type="button" class="btn info" id="itemOwnerReplaceBtn" disabled><?= htmlspecialchars(__('app.item_owner.actions.replace_selected')) ?></button>
        </div>
      </header>
      <div class="item-owner-card__body">
        <div class="panel-flash" id="itemOwnerActionFlash"></div>
        <div class="item-owner-table-wrap">
          <table class="table" id="itemOwnerInstanceTable">
            <thead>
              <tr>
                <th><input type="checkbox" id="itemOwnerSelectAll"></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.instances.instance')) ?></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.instances.character')) ?></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.instances.count')) ?></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.instances.location')) ?></th>
                <th><?= htmlspecialchars(__('app.item_owner.results.instances.container')) ?></th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="6" class="text-center muted"><?= htmlspecialchars(__('app.item_owner.results.instances.placeholder')) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>

<div id="itemOwnerReplaceModal" class="modal-backdrop">
  <div class="modal-panel modal-panel--narrow">
    <header>
      <h3 class="m-0"><?= htmlspecialchars(__('app.item_owner.modal.replace.title')) ?></h3>
      <button type="button" class="modal-close" data-close>&times;</button>
    </header>
    <div class="modal-body">
      <div class="form-row">
        <label for="itemOwnerReplaceEntry"><?= htmlspecialchars(__('app.item_owner.modal.replace.entry_label')) ?></label>
        <input type="number" id="itemOwnerReplaceEntry" min="1" placeholder="<?= htmlspecialchars(__('app.item_owner.modal.replace.entry_placeholder')) ?>">
        <div class="muted small"><?= htmlspecialchars(__('app.item_owner.modal.replace.entry_hint')) ?></div>
      </div>
      <div class="panel-flash panel-flash--inline" id="itemOwnerReplaceFeedback"></div>
    </div>
    <footer class="modal-footer">
      <button type="button" class="btn neutral" data-close><?= htmlspecialchars(__('app.item_owner.modal.replace.cancel')) ?></button>
      <button type="button" class="btn primary" id="itemOwnerReplaceConfirm"><?= htmlspecialchars(__('app.item_owner.modal.replace.confirm')) ?></button>
    </footer>
  </div>
</div>

<script>
window.__ITEM_OWNER_CTX = {
  csrf: window.__CSRF_TOKEN,
  locale: <?= json_encode(\Acme\Panel\Core\Lang::locale(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<?php include __DIR__ . '/../layouts/base_bottom.php'; ?>

