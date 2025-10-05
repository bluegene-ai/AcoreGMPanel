<?php
/**
 * File: resources/views/smartai/index.php
 * Purpose: Provides functionality for the resources/views/smartai module.
 */

 $module = 'smart_ai_wizard'; include __DIR__ . '/../layouts/base_top.php'; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.smartai.page_title')) ?></h1>
<p class="muted" style="margin-top:-8px;margin-bottom:24px"><?= htmlspecialchars(__('app.smartai.intro')) ?></p>
<div class="smartai-layout">
  <aside class="smartai-sidebar">
    <div class="smartai-card">
      <h2><?= htmlspecialchars(__('app.smartai.sidebar.nav_title')) ?></h2>
      <ol class="smartai-steps" id="smartAiSteps">
        <li class="active" data-step="1"><?= htmlspecialchars(__('app.smartai.sidebar.steps.base')) ?></li>
        <li data-step="2"><?= htmlspecialchars(__('app.smartai.sidebar.steps.event')) ?></li>
        <li data-step="3"><?= htmlspecialchars(__('app.smartai.sidebar.steps.action')) ?></li>
        <li data-step="4"><?= htmlspecialchars(__('app.smartai.sidebar.steps.target')) ?></li>
      </ol>
      <div class="smartai-meta">
        <h3><?= htmlspecialchars(__('app.smartai.sidebar.quick_view')) ?></h3>
        <ul>
          <?php foreach (($catalog['metadata']['notes'] ?? []) as $note): ?>
            <li><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (!empty($catalog['metadata']['source'])): ?>
          <a href="<?= htmlspecialchars($catalog['metadata']['source'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(__('app.smartai.sidebar.view_wiki')) ?></a>
        <?php endif; ?>
        <?php if (!empty($catalog['metadata']['updated_at'])): ?>
          <p class="small muted" style="margin-top:12px"><?= htmlspecialchars(__('app.smartai.sidebar.updated_at', ['date' => $catalog['metadata']['updated_at']])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </aside>
  <section class="smartai-content">
    <div id="smartAiFlash" class="panel-flash" style="display:none"></div>
    <form id="smartAiForm" autocomplete="off">
      <div class="smartai-step" data-step="1">
        <header>
          <h2><?= htmlspecialchars(__('app.smartai.base.title')) ?></h2>
          <p class="muted"><?= htmlspecialchars(__('app.smartai.base.description')) ?></p>
        </header>
        <div id="smartAiBaseFields" class="smartai-form-grid"></div>
      </div>
      <div id="smartAiSegmentSection" class="smartai-segment-section" hidden>
        <div class="smartai-segment-header">
          <div id="smartAiSegmentTabs" class="smartai-segment-tabs"></div>
          <button type="button" class="btn outline btn-sm" id="smartAiAddSegmentBtn">+ <?= htmlspecialchars(__('app.smartai.segment.add')) ?></button>
        </div>
        <p class="small muted smartai-segment-hint"><?= htmlspecialchars(__('app.smartai.segment.hint')) ?></p>
        <div id="smartAiSegmentBase" class="smartai-form-grid smartai-segment-base"></div>
      </div>
      <div class="smartai-step" data-step="2" hidden>
        <header>
          <h2><?= htmlspecialchars(__('app.smartai.event.title')) ?></h2>
          <p class="muted"><?= htmlspecialchars(__('app.smartai.event.description')) ?></p>
        </header>
        <div class="smartai-selector" id="smartAiEventSelect"></div>
        <div id="smartAiEventParams" class="smartai-form-grid"></div>
      </div>
      <div class="smartai-step" data-step="3" hidden>
        <header>
          <h2><?= htmlspecialchars(__('app.smartai.action.title')) ?></h2>
          <p class="muted"><?= htmlspecialchars(__('app.smartai.action.description')) ?></p>
        </header>
        <div class="smartai-selector" id="smartAiActionSelect"></div>
        <div id="smartAiActionParams" class="smartai-form-grid"></div>
      </div>
      <div class="smartai-step" data-step="4" hidden>
        <header>
          <h2><?= htmlspecialchars(__('app.smartai.target.title')) ?></h2>
          <p class="muted"><?= htmlspecialchars(__('app.smartai.target.description')) ?></p>
        </header>
        <div class="smartai-selector" id="smartAiTargetSelect"></div>
        <div id="smartAiTargetParams" class="smartai-form-grid"></div>
        <div class="smartai-preview-card">
          <div class="smartai-preview-header">
            <div>
              <h3><?= htmlspecialchars(__('app.smartai.preview.title')) ?></h3>
              <p class="small muted" id="smartAiSummary"></p>
            </div>
            <div class="smartai-preview-actions">
              <button type="button" class="btn primary" id="smartAiGenerateBtn"><?= htmlspecialchars(__('app.smartai.preview.generate')) ?></button>
              <button type="button" class="btn neutral" id="smartAiCopyBtn" disabled><?= htmlspecialchars(__('app.smartai.preview.copy')) ?></button>
            </div>
          </div>
          <pre id="smartAiPreview" class="smartai-preview-output"><?= htmlspecialchars(__('app.smartai.preview.placeholder')) ?></pre>
        </div>
      </div>
    </form>
    <footer class="smartai-footer">
      <div class="smartai-step-actions">
        <button type="button" class="btn neutral" id="smartAiPrevBtn" disabled><?= htmlspecialchars(__('app.smartai.footer.prev')) ?></button>
        <?php $stepIndicator = __('app.smartai.footer.step_indicator', ['current' => '<span id="smartAiStepLabel">1</span>', 'total' => '4']); ?>
        <div class="smartai-step-indicator"><?= $stepIndicator ?></div>
        <button type="button" class="btn primary" id="smartAiNextBtn"><?= htmlspecialchars(__('app.smartai.footer.next')) ?></button>
      </div>
    </footer>
  </section>
</div>
<script>
window.SMART_AI_WIZARD_DATA = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php include __DIR__ . '/../layouts/base_bottom.php'; ?>

