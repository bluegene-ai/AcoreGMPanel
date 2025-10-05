<?php
/**
 * File: resources/views/soap/index.php
 * Purpose: Provides functionality for the resources/views/soap module.
 */

 $module='soap_wizard'; include __DIR__.'/../layouts/base_top.php'; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.soap.page_title')) ?></h1>
<p class="muted" style="margin-top:-6px;margin-bottom:18px;font-size:13px;">
  <?= htmlspecialchars(__('app.soap.intro')) ?>
</p>

<div class="soap-wizard">
  <aside class="soap-wizard__sidebar">
    <div class="soap-wizard__search">
  <label for="soapSearchBox" class="small muted"><?= htmlspecialchars(__('app.soap.search_label')) ?></label>
  <input type="text" id="soapSearchBox" placeholder="<?= htmlspecialchars(__('app.soap.search_placeholder')) ?>" autofocus>
    </div>
    <div class="soap-wizard__categories" id="soapCategoryList"></div>
    <div class="soap-wizard__commands" id="soapCommandList"></div>
  </aside>
  <section class="soap-wizard__content">
    <div id="soapActionFlash" class="panel-flash" style="display:none"></div>
    <div id="soapCommandSummary" class="soap-wizard__summary">
  <h2><?= htmlspecialchars(__('app.soap.summary.title')) ?></h2>
  <p class="muted"><?= htmlspecialchars(__('app.soap.summary.hint')) ?></p>
      <div class="soap-wizard__meta small muted" id="soapWizardMeta"></div>
    </div>
    <div id="soapCommandDetail" class="soap-wizard__detail" hidden>
      <header class="soap-wizard__detail-header">
        <div>
          <h2 id="soapDetailName"></h2>
          <div class="soap-wizard__syntax" id="soapDetailSyntax"></div>
        </div>
        <span class="soap-risk" id="soapDetailRisk"></span>
      </header>
      <p class="muted" id="soapDetailDesc"></p>
      <ul class="soap-notes" id="soapDetailNotes"></ul>
      <div class="soap-target-hint" id="soapTargetHint" hidden>⚠️ <?= htmlspecialchars(__('app.soap.target_hint')) ?></div>

      <form id="soapCommandForm" class="soap-form" autocomplete="off">
        <h3 class="soap-form__title"><?= htmlspecialchars(__('app.soap.steps.fill')) ?></h3>
        <div id="soapFormFields" class="soap-form__fields"></div>
        <h3 class="soap-form__title"><?= htmlspecialchars(__('app.soap.steps.confirm')) ?></h3>
        <div class="soap-preview">
          <label class="small muted"><?= htmlspecialchars(__('app.soap.preview_label')) ?></label>
          <div class="soap-preview__command" id="soapCommandPreview"></div>
        </div>
        <div class="soap-form__actions">
          <button type="button" class="btn neutral" id="soapCopyBtn"><?= htmlspecialchars(__('app.soap.actions.copy')) ?></button>
          <button type="submit" class="btn primary" id="soapExecuteBtn" disabled><?= htmlspecialchars(__('app.soap.actions.execute')) ?></button>
        </div>
      </form>

      <section class="soap-output" id="soapOutputSection" hidden>
        <header class="soap-output__header">
          <h3><?= htmlspecialchars(__('app.soap.output_title')) ?></h3>
          <span class="small muted" id="soapOutputMeta"></span>
        </header>
        <pre id="soapOutputText" class="soap-output__log"></pre>
      </section>
    </div>
  </section>
</div>

<script>
window.SOAP_WIZARD_DATA = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
window.SOAP_WIZARD_DEFAULT_SERVER = <?= (int)($current_server ?? 0) ?>;
</script>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>

