<?php
/**
 * File: resources/views/item/edit.php
 * Purpose: Provides functionality for the resources/views/item module.
 */

use Acme\Panel\Core\ItemMeta; use Acme\Panel\Support\ConfigLocalization; ?>
<?php
  $itemEditCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
      'view' => $__can('content.view'),
      'update' => $__can('content.update'),
      'delete' => $__can('content.delete'),
      'sql' => $__can('content.sql'),
    ];
  $__pageCapabilities = $itemEditCapabilities;
  $capabilityNotice = $__canAll(['content.update', 'content.delete', 'content.sql'])
    ? null
    : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<div class="page-toolbar item-toolbar">
  <div class="toolbar-line top-line">
    <div class="toolbar-spacer"></div>
    <div class="toolbar-actions primary-actions">
      <button class="btn outline" type="button" id="btn-compact-toggle" data-label-normal="<?= htmlspecialchars(__('app.item.edit.compact.normal')) ?>" data-label-compact="<?= htmlspecialchars(__('app.item.edit.compact.compact')) ?>"><?= htmlspecialchars(__('app.item.edit.compact.compact')) ?></button>
      <?php if($itemEditCapabilities['delete']): ?>
      <button class="btn danger" id="btn-delete-item" data-id="<?= (int)$item['entry'] ?>"><?= htmlspecialchars(__('app.item.edit.delete')) ?></button>
      <?php endif; ?>
      <?php if($itemEditCapabilities['update']): ?>
      <button class="btn success" type="button" id="btn-save-item-top"><?= htmlspecialchars(__('app.item.edit.save')) ?></button>
      <?php endif; ?>
      <button class="btn info outline" type="button" id="btn-diff-sql"><?= htmlspecialchars(__('app.item.edit.diff_sql')) ?></button>
    </div>
  </div>
  <div class="toolbar-line nav-line">
    <div class="toolbar-nav" id="item-section-nav"></div>
  </div>
</div>
<div id="item-feedback" class="panel-flash panel-flash--inline"></div>
<?php include __DIR__.'/../components/capability_notice.php'; ?>
<form id="itemEditForm" data-entry="<?= (int)$item['entry'] ?>" class="item-edit-grid">
  <?php
    $schema = include __DIR__.'/../../../config/item_fields.php';
    $schema = is_array($schema) ? $schema : [];
    $schema = ConfigLocalization::localize($schema);
    $base = $schema['base'] ?? ['fields' => []];
    $qualityCn = \Acme\Panel\Core\ItemQuality::allLocalized();
    $curQ = (int)$item['quality'];
    $classNames = ItemMeta::classes();
    $curClass = (int)$item['class'];
    $curSub = (int)$item['subclass'];
    $curSubs = ItemMeta::subclassesOf($curClass);
  ?>
  <details open>
    <summary><?= htmlspecialchars($base['label']) ?></summary>
    <div class="field-grid">
      <label>ID <input type="number" value="<?= (int)$item['entry'] ?>" disabled></label>
      <?php foreach($base['fields'] as $f): $name=$f['name']; $val=$item[$name]??''; $type=$f['type']; ?>
        <?php if($name==='quality'): ?>
          <label><?= htmlspecialchars($f['label']) ?>
            <select name="quality" id="edit-quality-select">
              <?php foreach($qualityCn as $qv=>$qn): ?>
                <option value="<?= $qv ?>" <?= $qv===$curQ?'selected':''; ?>><?= htmlspecialchars($qn) ?></option>
              <?php endforeach; ?>
            </select>
            <span id="quality-preview" class="quality-badge quality-preview item-quality-<?= \Acme\Panel\Core\ItemQuality::code($curQ) ?>"><?= htmlspecialchars($qualityCn[$curQ]??'') ?></span>
          </label>
        <?php elseif($name==='class'): ?>
          <label><?= htmlspecialchars($f['label']) ?>
            <select name="class" id="edit-class-select">
              <?php foreach($classNames as $cid=>$cname): ?>
                <option value="<?= $cid ?>" <?= $cid===$curClass?'selected':''; ?>><?= htmlspecialchars($cname) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php elseif($name==='subclass'): ?>
          <label><?= htmlspecialchars($f['label']) ?>
            <select name="subclass" id="edit-subclass-select">
              <?php foreach($curSubs as $sid=>$sname): ?>
                <option value="<?= $sid ?>" <?= $sid===$curSub?'selected':''; ?>><?= htmlspecialchars($sname) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php else: ?>
          <label><?= htmlspecialchars($f['label']) ?> <input name="<?= $name ?>" type="<?= $type==='number'?'number':'text' ?>" value="<?= htmlspecialchars($val) ?>"></label>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </details>
  <details open class="flags-section">
    <summary><?= htmlspecialchars(__('app.item.edit.flags.title')) ?></summary>
    <div class="flags-grid">
      <div class="flag-item">
        <label class="flag-label">flags</label>
        <div class="flag-input-row">
          <input name="flags" data-bitmask value="<?= (int)($item['flags'] ?? 0) ?>" readonly>
          <button type="button" class="btn-xs btn outline info" data-open-mask="flags"><?= htmlspecialchars(__('app.item.edit.flags.choose')) ?></button>
        </div>
        <small class="muted" id="flags-names"><?= htmlspecialchars(__('app.item.edit.flags.loading')) ?></small>
      </div>
      <div class="flag-item">
        <label class="flag-label">flags_extra</label>
        <div class="flag-input-row">
          <input name="flags_extra" data-bitmask value="<?= (int)($item['flags_extra'] ?? 0) ?>" readonly>
          <button type="button" class="btn-xs btn outline info" data-open-mask="flags_extra"><?= htmlspecialchars(__('app.item.edit.flags.choose')) ?></button>
        </div>
        <small class="muted" id="flags_extra-names"><?= htmlspecialchars(__('app.item.edit.flags.loading')) ?></small>
      </div>
      <div class="flag-item">
        <label class="flag-label">flagscustom</label>
        <div class="flag-input-row">
          <input name="flagscustom" data-bitmask value="<?= (int)($item['flagscustom'] ?? 0) ?>" readonly>
          <button type="button" class="btn-xs btn outline info" data-open-mask="flagscustom"><?= htmlspecialchars(__('app.item.edit.flags.choose')) ?></button>
        </div>
        <small class="muted" id="flagscustom-names"><?= htmlspecialchars(__('app.item.edit.flags.loading')) ?></small>
      </div>
    </div>
  </details>
  <?php

    $skip=['base']; $order=['stats','combat','spells','resist','req','socket','economy'];
    foreach($order as $groupKey): if(!isset($schema[$groupKey])) continue; $grp=$schema[$groupKey]; ?>
    <details>
      <summary><?= htmlspecialchars($grp['label']) ?></summary>
      <div class="field-grid">
        <?php if(isset($grp['fields'])): foreach($grp['fields'] as $f): $name=$f['name']; $val=$item[$name]??''; ?>
          <label><?= htmlspecialchars($f['label']) ?> <input name="<?= $name ?>" type="<?= $f['type']==='number'?'number':'text' ?>" value="<?= htmlspecialchars($val) ?>"></label>
        <?php endforeach; endif; ?>
        <?php if(isset($grp['repeat'])): $rep=$grp['repeat']; $cnt=$rep['count']; $pattern=$rep['pattern']; for($i=1;$i<=$cnt;$i++): foreach($pattern as $pf): $fieldName=str_replace('{n}',$i,$pf['name']); $val=$item[$fieldName]??''; ?>
          <label><?= htmlspecialchars(str_replace('{n}',$i,$pf['label'])) ?> <input name="<?= $fieldName ?>" type="<?= $pf['type']==='number'?'number':'text' ?>" value="<?= htmlspecialchars($val) ?>"></label>
        <?php endforeach; endfor; if(!empty($rep['trailing'])): foreach($rep['trailing'] as $tf): $fieldName=$tf['name']; $val=$item[$fieldName]??''; ?>
          <label><?= htmlspecialchars($tf['label']) ?> <input name="<?= $fieldName ?>" type="<?= $tf['type']==='number'?'number':'text' ?>" value="<?= htmlspecialchars($val) ?>"></label>
        <?php endforeach; endif; endif; ?>
      </div>
    </details>
  <?php endforeach; ?>
  <details open class="item-edit-span-2">
    <summary><?= htmlspecialchars(__('app.item.edit.description')) ?></summary>
    <textarea name="description" rows="4" class="full-width"><?= htmlspecialchars($item['description']??'') ?></textarea>
  </details>
  <!-- 底部保存按钮行移除，统一使用 sticky 工具条保存 -->
</form>

<section class="item-edit-span-2 sql-section" id="itemDiffSqlSection">
  <h2 class="item-sql-section__heading">
    <span><?= htmlspecialchars(__('app.item.edit.diff.title')) ?></span>
    <label class="item-sql-section__toggle-label">
      <input type="checkbox" id="sqlFullMode" class="item-sql-section__toggle-input"> <?= htmlspecialchars(__('app.item.edit.diff.full_mode')) ?>
    </label>
  <button type="button" class="btn info outline btn-sm item-sql-section__copy-button" id="btn-copy-diff-inline"><?= htmlspecialchars(__('app.item.edit.actions.copy')) ?></button>
  <?php if($itemEditCapabilities['sql']): ?>
  <button type="button" class="btn success btn-sm" id="btn-exec-diff-sql"><?= htmlspecialchars(__('app.item.edit.actions.execute')) ?></button>
  <?php endif; ?>
  </h2>
  <div class="muted item-sql-section__hint"><?= htmlspecialchars(__('app.item.edit.diff.hint')) ?></div>
  <pre id="itemDiffSqlLive" class="sql-result mono item-sql-section__live-box"><?= htmlspecialchars(__('app.item.edit.diff.placeholder')) ?></pre>
  <div id="itemDiffSqlExecResult" class="sql-exec-result item-sql-section__exec-result">
    <div class="result-head item-sql-section__result-head">
      <strong class="item-sql-section__result-title"><?= htmlspecialchars(__('app.item.edit.diff.exec_title')) ?></strong>
      <span id="sqlExecStatus" class="badge item-sql-section__status"></span>
      <span id="sqlExecTiming" class="item-sql-section__timing"></span>
    </div>
    <div id="sqlExecSummary" class="item-sql-section__summary"></div>
    <pre id="sqlExecMessages" class="mono item-sql-section__messages"></pre>
    <div id="sqlExecSampleWrapper" class="item-sql-section__sample-wrapper">
      <div class="item-sql-section__sample-title"><?= htmlspecialchars(__('app.item.edit.diff.sample_title')) ?></div>
      <pre id="sqlExecSample" class="mono item-sql-section__sample"></pre>
    </div>
    <div class="item-sql-section__actions">
  <button type="button" class="btn btn-sm outline" id="btn-clear-exec-result"><?= htmlspecialchars(__('app.item.edit.actions.clear')) ?></button>
  <button type="button" class="btn btn-sm neutral" id="btn-hide-exec-result"><?= htmlspecialchars(__('app.item.edit.actions.hide')) ?></button>
  <button type="button" class="btn btn-sm info outline" id="btn-copy-exec-json"><?= htmlspecialchars(__('app.item.edit.actions.copy_json')) ?></button>
    </div>
  </div>
</section>

<!-- 受限 SQL 执行模块已移除，仅保留自动差异预览；如需恢复可从版本控制回滚 -->
<script type="application/json" data-panel-json data-global="ITEM_EDIT_CONFIG"><?= json_encode([
  'quality_unknown' => __('app.item.quality.unknown'),
  'group_fallback' => __('app.item.edit.group_fallback'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

