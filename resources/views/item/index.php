<?php
/**
 * File: resources/views/item/index.php
 * Purpose: Provides functionality for the resources/views/item module.
 */


$module='item';
include __DIR__.'/../layouts/base_top.php';
use Acme\Panel\Core\ItemMeta;


$classNames = ItemMeta::classes();
?>
<h1 class="page-title"><?= htmlspecialchars(__('app.item.page_title')) ?></h1>
<div id="item-feedback" class="panel-flash panel-flash--inline"></div>
<?php
?>
<form method="get" action="" class="inline item-filter-form">
  <select name="search_type">
    <option value="name" <?= $search_type==='name'?'selected':'' ?>><?= htmlspecialchars(__('app.item.filter.type_name')) ?></option>
    <option value="id" <?= $search_type==='id'?'selected':'' ?>><?= htmlspecialchars(__('app.item.filter.type_id')) ?></option>
  </select>
  <input type="text" name="search_value" placeholder="<?= htmlspecialchars(__('app.item.filter.keyword_placeholder')) ?>" value="<?= htmlspecialchars($search_value) ?>">
  <?php
    $qualityCn = \Acme\Panel\Core\ItemQuality::allLocalized();
  ?>
  <select name="filter_quality">
    <option value="-1" <?= (int)$filter_quality===-1?'selected':'' ?>><?= htmlspecialchars(__('app.item.filter.quality_all')) ?></option>
    <?php foreach($qualityCn as $qVal=>$qName): ?>
      <option value="<?= $qVal ?>" <?= (int)$filter_quality===$qVal?'selected':'' ?>><?= htmlspecialchars($qName) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="number" name="limit" style="width:80px" value="<?= (int)$limit ?>">
  <?php ?>
  <select name="filter_class" id="filter-class-select" style="min-width:140px">
    <option value="-1" <?= (int)$filter_class===-1?'selected':'' ?>><?= htmlspecialchars(__('app.item.filter.class_all')) ?></option>
    <?php foreach($classNames as $cid=>$cname): ?>
      <option value="<?= $cid ?>" <?= (int)$filter_class===$cid?'selected':''; ?>><?= htmlspecialchars($cname) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="filter_subclass" id="filter-subclass-select" style="min-width:140px">
    <option value="-1" <?= (int)$filter_subclass===-1?'selected':'' ?>><?= htmlspecialchars(__('app.item.filter.subclass_all')) ?></option>
    <?php if((int)$filter_class>=0): $curSubs=ItemMeta::subclassesOf((int)$filter_class); foreach($curSubs as $sid=>$sname): ?>
      <option value="<?= $sid ?>" <?= (int)$filter_subclass===$sid?'selected':''; ?>><?= htmlspecialchars($sname) ?></option>
    <?php endforeach; endif; ?>
  </select>
  <div class="filter-actions">
    <button class="btn info" type="submit"><?= htmlspecialchars(__('app.item.filter.submit')) ?></button>
    <button class="btn outline" type="button" id="btn-filter-reset" title="<?= htmlspecialchars(__('app.item.filter.reset_title')) ?>"><?= htmlspecialchars(__('app.item.filter.reset')) ?></button>
    <button class="btn success" type="button" id="btn-new-item"><?= htmlspecialchars(__('app.item.filter.create')) ?></button>
    <button class="btn outline info" type="button" id="btn-item-sql-log"><?= htmlspecialchars(__('app.item.filter.sql_log')) ?></button>
  </div>
</form>
<table class="table item-table">
  <thead><tr><th><?= htmlspecialchars(__('app.item.table.id')) ?></th><th><?= htmlspecialchars(__('app.item.table.name')) ?></th><th><?= htmlspecialchars(__('app.item.table.quality')) ?></th><th><?= htmlspecialchars(__('app.item.table.class')) ?></th><th><?= htmlspecialchars(__('app.item.table.subclass')) ?></th><th><?= htmlspecialchars(__('app.item.table.level')) ?></th><th><?= htmlspecialchars(__('app.item.table.actions')) ?></th></tr></thead>
  <tbody>
  <?php

    foreach($pager->items as $row): ?>
    <tr data-entry="<?= (int)$row['entry'] ?>">
      <td><?= (int)$row['entry'] ?></td>
      <?php
        $q=(int)$row['quality'];
  $qName = $qualityCn[$q] ?? __('app.item.quality.unknown');
        $qClass = 'item-quality-'. \Acme\Panel\Core\ItemQuality::code($q);
  $classId=(int)$row['class']; $subId=(int)$row['subclass'];
  $classCn = ItemMeta::className($classId);
  $subCn = ItemMeta::subclassName($classId,$subId);
      ?>
  <td><a href="?<?= http_build_query(['edit_id'=>$row['entry']]+$_GET) ?>" class="item-name-link quality-badge <?= $qClass ?>" title="<?= htmlspecialchars(__('app.item.tooltip.quality', ['quality' => $qName, 'value' => $q])) ?>"><?= htmlspecialchars($row['name']??'') ?></a></td>
  <td><span class="quality-badge <?= $qClass ?>" title="<?= htmlspecialchars($qName) ?>"><?= htmlspecialchars($qName) ?></span></td>
  <td title="class=<?= $classId ?>"><?= htmlspecialchars($classCn) ?> <small class="muted">(<?= $classId ?>)</small></td>
  <td title="class=<?= $classId ?> subclass=<?= $subId ?>"><?= htmlspecialchars($subCn) ?> <small class="muted">(<?= $subId ?>)</small></td>
      <td><?= (int)$row['itemlevel'] ?></td>
      <td class="nowrap">
  <a class="btn-sm btn info outline" href="?<?= http_build_query(['edit_id'=>$row['entry']]+$_GET) ?>"><?= htmlspecialchars(__('app.item.actions.edit')) ?></a>
  <button class="btn-sm btn danger action-delete" data-id="<?= (int)$row['entry'] ?>"><?= htmlspecialchars(__('app.item.actions.delete')) ?></button>
      </td>
    </tr>
  <?php endforeach; if(!$pager->items): ?>
  <tr><td colspan="7" style="text-align:center" class="text-muted"><?= htmlspecialchars(__('app.item.table.empty')) ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
<?php
  $pages=$pager->pages; $page=$pager->page;


  $base=url('/item');


  $qs = $_GET; unset($qs['page']); if(!empty($qs)){ $base .= '?'.http_build_query($qs); }
  include __DIR__.'/../components/pagination.php';
?>

<!-- 新增物品 Modal -->
<div class="modal-backdrop" id="modal-new-item" style="display:none">
  <div class="modal-panel small">
    <header><h3><?= htmlspecialchars(__('app.item.modal.new.title')) ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <label><?= htmlspecialchars(__('app.item.modal.new.id_label')) ?> <input type="number" id="newItemId"></label>
      <label style="margin-top:8px"><?= htmlspecialchars(__('app.item.modal.new.copy_label')) ?> <input type="number" id="copyItemId"></label>
      <div class="muted" style="margin-top:4px"><?= htmlspecialchars(__('app.item.modal.new.copy_hint')) ?></div>
          <?php $classNames = ItemMeta::classes(); ?>
          <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <label><?= htmlspecialchars(__('app.item.modal.new.class')) ?>
              <select id="newItemClass">
                <?php foreach($classNames as $cid=>$cname): ?><option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option><?php endforeach; ?>
              </select>
            </label>
            <label><?= htmlspecialchars(__('app.item.modal.new.subclass')) ?>
              <select id="newItemSubclass"></select>
            </label>
          </div>
    </div>
    <footer style="text-align:right;margin-top:12px">
  <button class="btn outline" data-close><?= htmlspecialchars(__('app.item.modal.common.cancel')) ?></button>
  <button class="btn success" id="btn-create-item"><?= htmlspecialchars(__('app.item.modal.new.submit')) ?></button>
    </footer>
  </div>
</div>
<!-- SQL 日志 Modal -->
<div class="modal-backdrop" id="modal-item-sql-log" style="display:none">
  <div class="modal-panel large">
    <header><h3><?= htmlspecialchars(__('app.item.modal.log.title')) ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:10px;flex-wrap:wrap">
        <label style="display:flex;flex-direction:column;font-size:12px;color:#9bb0c0">
          <span style="margin-bottom:4px;color:#c8d6e5;font-size:13px"><?= htmlspecialchars(__('app.item.modal.log.type_label')) ?></span>
          <select id="itemLogType" style="min-width:140px">
            <option value="sql"><?= htmlspecialchars(__('app.item.modal.log.type_sql')) ?></option>
            <option value="deleted"><?= htmlspecialchars(__('app.item.modal.log.type_deleted')) ?></option>
            <option value="actions"><?= htmlspecialchars(__('app.item.modal.log.type_actions')) ?></option>
          </select>
        </label>
        <button class="btn info outline" type="button" id="btn-refresh-item-sql-log"><?= htmlspecialchars(__('app.item.modal.log.refresh')) ?></button>
      </div>
      <pre id="itemSqlLogBox" style="max-height:400px;overflow:auto;background:#111;color:#9f9;padding:8px"><?= htmlspecialchars(__('app.item.modal.log.placeholder')) ?></pre>
    </div>
    <footer style="text-align:right;margin-top:8px">
      <button class="btn outline" data-close><?= htmlspecialchars(__('app.item.modal.common.close')) ?></button>
    </footer>
  </div>
</div>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
<script>
// 新建物品 modal 子类联动已统一到 item.js (ItemSubclasses 服务)
// 兜底：若 item.js 未加载，重置按钮仍可工作
(function(){
  const reset=document.getElementById('btn-filter-reset'); if(!reset) return;
  if(reset.__bound) return; reset.__bound=true;
  reset.addEventListener('click',()=>{
    const form=reset.closest('form'); if(!form) return;
    const defaults={ search_type:'name', search_value:'', filter_quality:'-1', filter_class:'-1', filter_subclass:'-1' };
    Object.keys(defaults).forEach(n=>{ const el=form.querySelector('[name="'+n+'"]'); if(el) el.value=defaults[n]; });
    // limit 保持用户当前值
    form.submit();
  });
})();
</script>

