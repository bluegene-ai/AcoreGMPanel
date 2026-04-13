<?php
/**
 * File: resources/views/quest/edit.php
 * Purpose: Provides functionality for the resources/views/quest module.
 */


  use Acme\Panel\Support\ConfigLocalization;
  use Acme\Panel\Views\Helpers\QuestFieldRenderer;
  $questCfg = include dirname(__DIR__,3).'/config/quest.php';
  $questCfg = is_array($questCfg) ? $questCfg : [];
  $questCfg = ConfigLocalization::localize($questCfg);
  $cfg = $questCfg['fields'] ?? [];
  $questId = (int)($quest['ID'] ?? 0);
  $questTitle = trim((string)($quest['LogTitle'] ?? ''));
  $questEditCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
      'view' => $__can('content.view'),
      'update' => $__can('content.update'),
      'sql' => $__can('content.sql'),
      'logs' => $__can('content.logs'),
    ];
  $__pageCapabilities = $questEditCapabilities;
  $capabilityNotice = $__canAll(['content.update', 'content.sql', 'content.logs'])
    ? null
    : __('app.common.capabilities.page_limited');
?>

<?php include __DIR__.'/../components/page_header.php'; ?>
<div class="page-toolbar quest-toolbar">
  <div class="toolbar-line top-line">
    <div class="toolbar-spacer"></div>
    <div class="toolbar-actions primary-actions">
      <?php if($questEditCapabilities['logs']): ?>
      <button class="btn outline info" type="button" id="btn-open-quest-log"><?= htmlspecialchars(__('app.quest.edit.toolbar.log')) ?></button>
      <?php endif; ?>
      <?php if($questEditCapabilities['sql']): ?>
      <button class="btn outline" type="button" id="btn-exec-sql"><?= htmlspecialchars(__('app.quest.edit.toolbar.execute_sql')) ?></button>
      <?php endif; ?>
      <button class="btn info outline" type="button" id="btn-copy-sql" disabled><?= htmlspecialchars(__('app.quest.edit.toolbar.copy_sql')) ?></button>
    </div>
  </div>
</div>

<div id="quest-feedback" class="panel-flash panel-flash--inline"></div>
<?php include __DIR__.'/../components/capability_notice.php'; ?>

<div class="quest-layout-wide" id="quest-layout" data-qe-layout="tabs">
  <div id="quest-editor-main">
    <!-- 顶部动作条已移除：必要操作集成到 SQL diff 卡片 -->

    <!-- SQL Diff / Exec 面板（已从原 Tab 中独立出来，随时可见） -->
    <div class="card mb-3" id="sql-inline-panel">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <h6 class="mb-0"><?= htmlspecialchars(__('app.quest.edit.diff.title')) ?></h6>
          <div class="small text-muted"><?= htmlspecialchars(__('app.quest.edit.diff.hint')) ?></div>
        </div>
        <pre class="mb-0 small mono quest-diff-preview" id="diff-sql"><?= htmlspecialchars(__('app.quest.edit.diff.empty')) ?></pre>
      </div>
    </div>
    <!-- 精简执行状态 -->
    <div id="quest-exec-status" class="mb-3 small quest-exec-status"></div>

    <div id="qe-tabs" class="qe-tabs">
    <div class="qe-tab-bar" role="tablist">
  <button class="qe-tab active" data-tab="general" type="button"><?= htmlspecialchars(__('app.quest.edit.tabs.general')) ?><span class="qe-dirty-dot d-none" data-tab-dirty="general"></span></button>
  <button class="qe-tab" data-tab="objectives" type="button"><?= htmlspecialchars(__('app.quest.edit.tabs.objectives')) ?><span class="qe-dirty-dot d-none" data-tab-dirty="objectives"></span></button>
  <button class="qe-tab" data-tab="requirements" type="button"><?= htmlspecialchars(__('app.quest.edit.tabs.requirements')) ?><span class="qe-dirty-dot d-none" data-tab-dirty="requirements"></span></button>
  <button class="qe-tab" data-tab="rewards" type="button"><?= htmlspecialchars(__('app.quest.edit.tabs.rewards')) ?><span class="qe-dirty-dot d-none" data-tab-dirty="rewards"></span></button>
  <button class="qe-tab" data-tab="internal" type="button"><?= htmlspecialchars(__('app.quest.edit.tabs.internal')) ?><span class="qe-dirty-dot d-none" data-tab-dirty="internal"></span></button>
      </div>
      <div class="qe-tab-panels">
        <div class="qe-tab-panel active" data-tab-panel="general">
          <form id="quest-edit-form" class="mb-4">
            <input type="hidden" name="ID" value="<?= (int)$quest['ID'] ?>" data-orig="<?= (int)$quest['ID'] ?>" />
            <?php

              foreach($cfg['groups'] as $gk=>$gcfg){
                if(in_array($gk,['basic','flags'])) {
                  echo QuestFieldRenderer::renderGroup($gk,$gcfg,$cfg['fields'],$quest,'general');
                }
              }
            ?>
          </form>
        </div>
        <div class="qe-tab-panel" data-tab-panel="objectives">
          <?php if(isset($cfg['groups']['objectives'])) echo QuestFieldRenderer::renderGroup('objectives',$cfg['groups']['objectives'],$cfg['fields'],$quest,'objectives'); ?>
        </div>
        <div class="qe-tab-panel" data-tab-panel="requirements">
          <?php if(isset($cfg['groups']['requirements'])) echo QuestFieldRenderer::renderGroup('requirements',$cfg['groups']['requirements'],$cfg['fields'],$quest,'requirements'); ?>
        </div>
        <div class="qe-tab-panel" data-tab-panel="rewards">
          <?php if(isset($cfg['groups']['rewards'])) echo QuestFieldRenderer::renderGroup('rewards',$cfg['groups']['rewards'],$cfg['fields'],$quest,'rewards'); ?>
        </div>
        <div class="qe-tab-panel" data-tab-panel="internal">
          <?php if(isset($cfg['groups']['internal'])) echo QuestFieldRenderer::renderGroup('internal',$cfg['groups']['internal'],$cfg['fields'],$quest,'internal'); ?>
        </div>
      </div><!-- /qe-tab-panels -->
    </div><!-- /qe-tabs -->
  </div><!-- /main -->
  <aside id="quest-side-panel">
    <div class="panel-card d-none d-xl-block" id="quest-nav-card">
      <h6 class="mb-2"><?= htmlspecialchars(__('app.quest.edit.nav.title')) ?></h6>
      <ul id="quest-nav" class="mb-0" data-nav-list></ul>
    </div>
    <div class="panel-card quest-mini-diff-card" id="quest-mini-diff-card">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <h6 class="mb-0"><?= htmlspecialchars(__('app.quest.edit.mini_diff.title')) ?></h6>
        <small class="text-muted" id="mini-diff-count">0</small>
      </div>
      <div class="small text-muted" id="mini-diff-empty"><?= htmlspecialchars(__('app.quest.edit.mini_diff.empty')) ?></div>
      <div class="flex-grow-1 quest-mini-diff-scroll">
        <table class="table table-sm table-hover align-middle mb-0 small quest-mini-diff-table" id="mini-diff-table">
          <thead><tr><th class="quest-mini-diff-col-field"><?= htmlspecialchars(__('app.quest.edit.mini_diff.table.field')) ?></th><th><?= htmlspecialchars(__('app.quest.edit.mini_diff.table.value')) ?></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="mt-1 d-flex gap-2">
        <button type="button" class="btn btn-sm outline" id="mini-diff-collapse" data-collapsed="0"><?= htmlspecialchars(__('app.quest.edit.mini_diff.collapse')) ?></button>
        <button type="button" class="btn btn-sm outline" id="mini-diff-clear" title="<?= htmlspecialchars(__('app.quest.edit.mini_diff.reset_title')) ?>" disabled><?= htmlspecialchars(__('app.quest.edit.mini_diff.reset')) ?></button>
      </div>
    </div>
  </aside>
</div><!-- /layout -->


<!-- Quest Log Modal -->
<?php if($questEditCapabilities['logs']): ?>
<div class="modal-backdrop" id="modal-quest-log">
  <div class="modal-panel large">
    <header><h3><?= htmlspecialchars(__('app.quest.log_modal.title')) ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <div class="quest-log-toolbar">
        <label class="quest-log-type-label">
          <span class="quest-log-type-caption"><?= htmlspecialchars(__('app.quest.log_modal.type_label')) ?></span>
          <select id="questLogType" class="quest-log-type-select">
            <option value="sql"><?= htmlspecialchars(__('app.quest.log_modal.types.sql')) ?></option>
            <option value="deleted"><?= htmlspecialchars(__('app.quest.log_modal.types.deleted')) ?></option>
            <option value="actions"><?= htmlspecialchars(__('app.quest.log_modal.types.actions')) ?></option>
          </select>
        </label>
        <button class="btn info outline" type="button" id="btn-refresh-quest-log"><?= htmlspecialchars(__('app.quest.log_modal.refresh')) ?></button>
      </div>
      <pre id="questLogBox" class="quest-log-box"><?= htmlspecialchars(__('app.quest.log_modal.empty')) ?></pre>
    </div>
    <footer class="quest-modal-footer quest-modal-footer--compact">
      <button class="btn outline" data-close><?= htmlspecialchars(__('app.quest.log_modal.close')) ?></button>
    </footer>
  </div>
</div>
<?php endif; ?>
<?php $repoHashFn = new \Acme\Panel\Domain\Quest\QuestRepository(); $hash = $repoHashFn->rowHash($quest); ?>
<script type="application/json" data-panel-json data-global="QUEST_DATA"><?= json_encode($quest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" data-panel-json data-global="QUEST_META"><?= json_encode(['enums'=>$cfg['enums'],'bitmasks'=>$cfg['bitmasks']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" data-panel-json data-global="FIELD_LABELS"><?= json_encode(array_map(fn($f)=>$f['label']??'', $cfg['fields']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" data-panel-json data-global="QUEST_HASH"><?= json_encode($hash, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/modules/quest_editor_core.js') ?>"></script>

