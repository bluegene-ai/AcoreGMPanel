<?php
/**
 * File: resources/views/item/edit.php
 * Purpose: Provides functionality for the resources/views/item module.
 */

 $module='item'; include __DIR__.'/../layouts/base_top.php'; use Acme\Panel\Core\ItemMeta; use Acme\Panel\Support\ConfigLocalization; ?>
<div class="page-toolbar item-toolbar">
  <div class="toolbar-line top-line">
    <h1 class="page-title no-margin"><?= htmlspecialchars(__('app.item.edit.title', ['id' => (int)$item['entry']])) ?></h1>
    <div class="toolbar-spacer"></div>
    <div class="toolbar-actions primary-actions">
      <a class="btn outline" href="<?= url('/item?'.htmlspecialchars($cancel_query)) ?>"><?= htmlspecialchars(__('app.item.edit.back_to_list')) ?></a>
      <button class="btn outline" type="button" id="btn-compact-toggle" data-label-normal="<?= htmlspecialchars(__('app.item.edit.compact.normal')) ?>" data-label-compact="<?= htmlspecialchars(__('app.item.edit.compact.compact')) ?>"><?= htmlspecialchars(__('app.item.edit.compact.compact')) ?></button>
      <button class="btn danger" id="btn-delete-item" data-id="<?= (int)$item['entry'] ?>"><?= htmlspecialchars(__('app.item.edit.delete')) ?></button>
      <button class="btn success" type="button" id="btn-save-item-top"><?= htmlspecialchars(__('app.item.edit.save')) ?></button>
      <button class="btn info outline" type="button" id="btn-diff-sql"><?= htmlspecialchars(__('app.item.edit.diff_sql')) ?></button>
    </div>
  </div>
  <div class="toolbar-line nav-line">
    <div class="toolbar-nav" id="item-section-nav"></div>
  </div>
</div>
<div id="item-feedback" class="panel-flash panel-flash--inline"></div>
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
  <h2 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span><?= htmlspecialchars(__('app.item.edit.diff.title')) ?></span>
    <label style="font-size:12px;display:inline-flex;align-items:center;gap:4px;">
      <input type="checkbox" id="sqlFullMode" style="margin:0;"> <?= htmlspecialchars(__('app.item.edit.diff.full_mode')) ?>
    </label>
  <button type="button" class="btn info outline btn-sm" id="btn-copy-diff-inline" style="margin-left:auto;"><?= htmlspecialchars(__('app.item.edit.actions.copy')) ?></button>
  <button type="button" class="btn success btn-sm" id="btn-exec-diff-sql"><?= htmlspecialchars(__('app.item.edit.actions.execute')) ?></button>
  </h2>
  <div class="muted" style="font-size:12px;"><?= htmlspecialchars(__('app.item.edit.diff.hint')) ?></div>
  <pre id="itemDiffSqlLive" class="sql-result mono" style="min-height:90px;"><?= htmlspecialchars(__('app.item.edit.diff.placeholder')) ?></pre>
  <div id="itemDiffSqlExecResult" class="sql-exec-result" style="margin-top:10px;display:none;border:1px solid #2d3b45;background:#121a21;padding:10px 12px;border-radius:6px;font-size:12px;line-height:1.5">
    <div class="result-head" style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
      <strong style="color:#89c2ff"><?= htmlspecialchars(__('app.item.edit.diff.exec_title')) ?></strong>
      <span id="sqlExecStatus" class="badge" style="display:none;padding:2px 6px;border-radius:4px;font-size:11px"></span>
      <span id="sqlExecTiming" style="color:#6a7b86;font-size:11px"></span>
    </div>
    <div id="sqlExecSummary" style="margin-bottom:6px"></div>
    <pre id="sqlExecMessages" class="mono" style="max-height:180px;overflow:auto;background:#0c1318;padding:8px 10px;border-radius:4px;margin:0"></pre>
    <div id="sqlExecSampleWrapper" style="display:none;margin-top:8px">
      <div style="font-weight:bold;color:#8fa8b7;margin-bottom:4px"><?= htmlspecialchars(__('app.item.edit.diff.sample_title')) ?></div>
      <pre id="sqlExecSample" class="mono" style="max-height:160px;overflow:auto;background:#0c1318;padding:8px 10px;border-radius:4px;margin:0"></pre>
    </div>
    <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
  <button type="button" class="btn btn-sm outline" id="btn-clear-exec-result"><?= htmlspecialchars(__('app.item.edit.actions.clear')) ?></button>
  <button type="button" class="btn btn-sm neutral" id="btn-hide-exec-result"><?= htmlspecialchars(__('app.item.edit.actions.hide')) ?></button>
  <button type="button" class="btn btn-sm info outline" id="btn-copy-exec-json"><?= htmlspecialchars(__('app.item.edit.actions.copy_json')) ?></button>
    </div>
  </div>
</section>

<!-- 受限 SQL 执行模块已移除，仅保留自动差异预览；如需恢复可从版本控制回滚 -->
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
<script>
// Bitmask 组件模块化加载
(function(){
  function ensure(){
    import((window.APP_BASE||'') + '/assets/js/modules/bitmask_flags.js')
      .then(mod=>{ mod.initBitmaskFlags(); })
      .catch(()=>{});
  }
  if('noModule' in document.createElement('script')){ ensure(); } else { // fallback 非 module 浏览器（基本不考虑）
    const s=document.createElement('script'); s.src=(window.APP_BASE||'') + '/assets/js/modules/bitmask_flags.js'; s.onload=ensure; document.head.appendChild(s);
  }
})();
// 品质预览
(function(){
  const sel=document.getElementById('edit-quality-select'); const preview=document.getElementById('quality-preview'); if(!sel||!preview||!window.APP_ENUMS) return;
  const codes=APP_ENUMS.qualityCodes; const names=APP_ENUMS.qualities; const fallback=<?= json_encode(__('app.item.quality.unknown'), JSON_UNESCAPED_UNICODE) ?>;
  sel.addEventListener('change',()=>{ const q=parseInt(sel.value)||0; const code=codes[q]||'unknown'; preview.className='quality-badge item-quality-'+code; preview.textContent=names[q]||fallback; });
})();
// 顶部保存按钮复用底部逻辑 (若已有全局监听可复用，这里简单触发原按钮 ID)
document.getElementById('btn-save-item-top')?.addEventListener('click',()=>{
  // 如果之前脚本绑定在 #btn-save-item，可以兼容：
  const legacy=document.getElementById('btn-save-item');
  if(legacy){ legacy.click(); return; }
  // 否则自行触发一个自定义事件供外部监听
  document.dispatchEvent(new CustomEvent('itemEditSaveRequested'));
});
// 折叠记忆 + 快速导航 + 脏离开 + 紧凑模式
(function(){
  const KEY='itemEdit:sections:v1';
  const details=[...document.querySelectorAll('#itemEditForm > details')];
  const fallbackTemplate=<?= json_encode(__('app.item.edit.group_fallback'), JSON_UNESCAPED_UNICODE) ?>;
  // 给每个 details 自动分配 id & data-title
  details.forEach((d,i)=>{
    if(!d.id) d.id='sec-'+i;
    if(!d.dataset.title){
      const sum=d.querySelector('summary');
      const fb=fallbackTemplate.replace(':index', (i+1).toString());
      d.dataset.title=sum?sum.textContent.trim():fb;
    }
  });
  // 恢复 open 状态
  try{ const saved=JSON.parse(localStorage.getItem(KEY)||'{}'); details.forEach(d=>{ if(saved[d.id]===false) d.open=false; }); }catch(_){ }
  // 监听状态变更
  details.forEach(d=> d.addEventListener('toggle',()=>{
    const cur={}; details.forEach(x=> cur[x.id]=x.open); localStorage.setItem(KEY,JSON.stringify(cur));
  }));
  // 快速导航
  const nav=document.getElementById('item-section-nav'); if(nav){ details.forEach(d=>{ const a=document.createElement('button'); a.type='button'; a.className='btn-sm btn outline'; a.textContent=d.dataset.title; a.addEventListener('click',()=>{ d.scrollIntoView({behavior:'smooth',block:'start'}); d.open=true; }); nav.appendChild(a); }); }
  // 脏离开提示
  let dirty=false; const form=document.getElementById('itemEditForm');
  form?.addEventListener('input',()=> dirty=true,{once:true});
  window.addEventListener('beforeunload',e=>{ if(dirty){ e.preventDefault(); e.returnValue=''; }});
  document.addEventListener('itemEditSaved',()=>{ dirty=false; });
  // 紧凑模式
  const compactBtn=document.getElementById('btn-compact-toggle');
  const COMPACT_KEY='itemEdit:compact';
  function applyCompact(flag){
    document.body.classList.toggle('compact',flag);
    localStorage.setItem(COMPACT_KEY,flag?'1':'0');
    if(compactBtn){
      const label = flag ? compactBtn.dataset.labelNormal : compactBtn.dataset.labelCompact;
      if(label) compactBtn.textContent = label;
    }
  }
  if(localStorage.getItem(COMPACT_KEY)==='1') applyCompact(true);
  compactBtn?.addEventListener('click',()=> applyCompact(!document.body.classList.contains('compact')));
})();
</script>

