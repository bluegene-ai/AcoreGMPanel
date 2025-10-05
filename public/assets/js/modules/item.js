/**
 * File: public/assets/js/modules/item.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - qs()
 *   - qsa()
 *   - translate()
 *   - itemNotify()
 *   - escapeHtml()
 *   - load()
 *   - prime()
 *   - namesOf()
 *   - nameOf()
 *   - openModal()
 *   - closeModals()
 *   - initList()
 *   - ensureAll()
 *   - initNewItemModal()
 *   - fill()
 *   - collectChanges()
 *   - initEdit()
 *   - takeSnapshot()
 *   - currentValue()
 *   - markDirty()
 *   - gatherDiff()
 *   - buildUpdateSQL()
 *   - updateDiffPreview()
 *   - show()
 *   - setStatus()
 *   - ensureButtons()
 *   - boot()
 *   - refreshSqlLog()
 *   - attachSave()
 */

function qs(sel,ctx=document){ return ctx.querySelector(sel); }
function qsa(sel,ctx=document){ return Array.from(ctx.querySelectorAll(sel)); }

const ITEM_FEEDBACK_SELECTOR = '#item-feedback';
const itemFeedback = (window.Panel && window.Panel.feedback && typeof window.Panel.feedback.show==='function') ? window.Panel.feedback : null;

const panelLocale = window.Panel || {};
const moduleLocaleFn = typeof panelLocale.moduleLocale==='function'
  ? panelLocale.moduleLocale.bind(panelLocale)
  : null;
const moduleTranslator = typeof panelLocale.createModuleTranslator==='function'
  ? panelLocale.createModuleTranslator('item')
  : null;

function translate(path, fallback, replacements){
  const defaultValue = fallback ?? `modules.item.${path}`;
  let text;
  if(moduleLocaleFn){
    text = moduleLocaleFn('item', path, defaultValue);
  } else if(moduleTranslator){
    text = moduleTranslator(path, defaultValue);
  } else {
    text = defaultValue;
  }
  if(typeof text==='string' && text===`modules.item.${path}` && fallback){
    text = fallback;
  }
  if(typeof text==='string' && replacements && typeof replacements==='object'){
    Object.entries(replacements).forEach(([key,value])=>{
      const pattern = new RegExp(`:${key}(?![A-Za-z0-9_])`, 'g');
      text = text.replace(pattern, String(value ?? ''));
    });
  }
  return text;
}

function itemNotify(message,type='info',opts){
  const target = document.querySelector(ITEM_FEEDBACK_SELECTOR);
  if(itemFeedback && target){
    const severity = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
    const options = opts || {};
    const duration = typeof options.duration === 'number' ? options.duration : 3000;
    itemFeedback.show(target,severity,message,{duration});
    return;
  }
  let box=document.querySelector('.flash-zone');
  if(!box){
    box=document.createElement('div');
    box.className='flash-zone';
    document.body.appendChild(box);
  }
  const node=document.createElement('div');
  node.className='flash flash-'+type;
  node.textContent=message;
  box.appendChild(node);
  const duration=(opts && typeof opts.duration==='number') ? opts.duration : 3000;
  if(duration){ setTimeout(()=>{ if(node.parentNode===box) node.remove(); },duration); }
}

function escapeHtml(str){
  return String(str).replace(/[&<>"']/g,c=>({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;',
    '\'':'&#39;'
  })[c]);
}


const ItemSubclasses = (function(){
  const cache = {};
  const inflight = {};
  async function load(classId){
    if(classId==null || classId==='-1') return {};
    if(cache[classId]) return cache[classId];
    if(inflight[classId]) return inflight[classId];
    inflight[classId] = (async ()=>{
      try{
        const res = await Panel.api.get('/item/api/subclasses?class='+classId);
        const subs = (res && (res.subclasses||res.data) && typeof (res.subclasses||res.data)==='object') ? (res.subclasses||res.data) : {};
        cache[classId] = subs;
        return subs;
      }catch(e){ console.warn('[ItemSubclasses] load failed',e); cache[classId]={}; return {}; }
      finally{ delete inflight[classId]; }
    })();
    return inflight[classId];
  }
  function prime(classId, map){ if(classId!=null && map && typeof map==='object' && !cache[classId]) cache[classId]=map; }
  function namesOf(classId){ return cache[classId] || {}; }
  function nameOf(classId, subId){ return (cache[classId]||{})[subId]; }
  return { load, prime, namesOf, nameOf };
})();

function openModal(id){ const el=qs(id); if(el){ el.classList.add('active'); document.body.style.overflow='hidden'; } }
function closeModals(){ qsa('.modal-backdrop.active').forEach(m=>m.classList.remove('active')); document.body.style.overflow=''; }
document.addEventListener('click',e=>{
  const closeBtn = e.target.closest('[data-close]');
  if(closeBtn){ closeModals(); return; }

  if(e.target.classList.contains('modal-backdrop')){
    closeModals();
  }
});
document.addEventListener('keydown',e=>{ if(e.key==='Escape'){ closeModals(); }});

function initList(){
  const newBtn=qs('#btn-new-item'); if(newBtn){ newBtn.addEventListener('click',()=>openModal('#modal-new-item')); }
  const createBtn=qs('#btn-create-item'); if(createBtn){ createBtn.addEventListener('click',async ()=>{
    const id=Number(qs('#newItemId').value||0); const copy=qs('#copyItemId').value.trim();
    if(!id){ itemNotify(translate('create.enter_new_id','Please enter a new ID'),'error'); qs('#newItemId')?.focus(); return; }
    try{
      const res=await Panel.api.post('/item/api/create',{new_item_id:id,copy_item_id:copy||null});
      if(res.success){ itemNotify(translate('create.success_redirect','Item created, redirecting...'),'success',{duration:1200}); setTimeout(()=>{ location.search='?edit_id='+id; },350); }
      else { itemNotify(res.message||translate('create.failure','Creation failed'),'error',{duration:5000}); }
    }catch(e){
      const reason = e?.message || e;
      itemNotify(translate('create.failure_with_reason','Creation failed: :reason',{reason}), 'error',{duration:5000});
    }
  }); }
  qsa('.action-delete').forEach(btn=> btn.addEventListener('click',async ()=>{
    const id=Number(btn.dataset.id); if(!confirm(translate('list.confirm_delete','Delete item #:id?',{id}))) return;
    try{
      const res=await Panel.api.post('/item/api/delete',{entry:id});
      if(res.success){ itemNotify(translate('list.delete_success','Item deleted'),'success',{duration:1000}); setTimeout(()=>location.reload(),400); }
      else { itemNotify(res.message||translate('list.delete_failed','Delete failed'),'error',{duration:5000}); }
    }catch(e){
      const reason = e?.message || e;
      itemNotify(translate('list.delete_failed_with_reason','Delete failed: :reason',{reason}),'error',{duration:5000});
    }
  }));
  const logBtn=qs('#btn-item-sql-log');
  const logTypeSel=qs('#itemLogType');
  if(logBtn){
    logBtn.addEventListener('click',async ()=>{
      openModal('#modal-item-sql-log'); await refreshSqlLog(logTypeSel?.value);
    });
  }
  if(logTypeSel){
    logTypeSel.addEventListener('change',()=>{ refreshSqlLog(logTypeSel.value); });
  }
  const refreshBtn=qs('#btn-refresh-item-sql-log'); if(refreshBtn){ refreshBtn.addEventListener('click',()=> refreshSqlLog(logTypeSel?.value)); }

  (function(){
    const clsSel=qs('#filter-class-select'); const subSel=qs('#filter-subclass-select'); if(!clsSel||!subSel) return;
    qsa('.list-class-tools,.list-sub-tools').forEach(el=>{ try{ el.remove(); }catch(_){} });
    function ensureAll(){ if(!subSel.querySelector('option[value="-1"]')){ const o=document.createElement('option'); o.value='-1'; o.textContent=translate('list.subclass.all_option','(All subclasses)'); subSel.insertBefore(o, subSel.firstChild); } }
    ensureAll();
    clsSel.addEventListener('change', async ()=>{
      const cid=clsSel.value; const keep='-1'; subSel.innerHTML=''; ensureAll();
      if(cid==='-1'){ subSel.value='-1'; return; }
      const loading=document.createElement('option'); loading.textContent=translate('list.subclass.loading_option','Loading...'); loading.disabled=true; subSel.appendChild(loading);
      const subs=await ItemSubclasses.load(cid); subSel.innerHTML=''; ensureAll(); Object.keys(subs).forEach(k=>{ const o=document.createElement('option'); o.value=k; o.textContent=subs[k]; subSel.appendChild(o); });
      if(subSel.options.length>1){ subSel.value=keep; }
    });
  })();
}

function initNewItemModal(){
  const classSel=qs('#newItemClass'); const subSel=qs('#newItemSubclass'); if(!classSel||!subSel) return;
  async function fill(){
    subSel.innerHTML=''; const cid=classSel.value; const loading=document.createElement('option'); loading.textContent=translate('create.subclass.loading_option','Loading...'); loading.disabled=true; subSel.appendChild(loading);
    if(cid==='-1'){ subSel.innerHTML=''; return; }
    const subs=await ItemSubclasses.load(cid); subSel.innerHTML=''; Object.keys(subs).forEach(k=>{ const o=document.createElement('option'); o.value=k; o.textContent=subs[k]; subSel.appendChild(o); });
  }
  classSel.addEventListener('change',fill);

  const openBtn=qs('#btn-new-item'); if(openBtn){ openBtn.addEventListener('click',()=>{ setTimeout(()=>{ if(!subSel.options.length) fill(); },30); }); }

  fill();
}

function collectChanges(form){
  const entry=Number(form.dataset.entry);
  const changes={entry};
  qsa('input[name],select[name],textarea[name]',form).forEach(inp=>{
    if(inp.disabled) return;
    const name=inp.name; if(!name) return;
    let v=inp.value;
    if(inp.type==='number') v=v!==''?Number(v):'';
    changes[name]=v;
  });
  return {entry,changes};
}

function initEdit(){
  const form=qs('#itemEditForm'); if(!form) return;

  const initialClassSel = qs('#edit-class-select');
  const initialSubSel = qs('#edit-subclass-select');
  if(initialClassSel && initialSubSel){ const cur=initialClassSel.value; const tmp={}; qsa('#edit-subclass-select option').forEach(o=> tmp[o.value]=o.textContent.trim()); ItemSubclasses.prime(cur,tmp); }

  if(initialClassSel && initialSubSel){
    initialClassSel.addEventListener('change', async ()=>{
      const cid=initialClassSel.value; initialSubSel.innerHTML='';
      if(cid==='-1'){ return; }
  const loading=document.createElement('option'); loading.textContent=translate('list.subclass.loading_option','Loading...'); loading.disabled=true; initialSubSel.appendChild(loading);
      const subs=await ItemSubclasses.load(cid); initialSubSel.innerHTML='';
      Object.keys(subs).forEach(k=>{ const o=document.createElement('option'); o.value=k; o.textContent=subs[k]; initialSubSel.appendChild(o); });

      if(initialSubSel.options.length){ initialSubSel.selectedIndex=0; }

      initialSubSel.dispatchEvent(new Event('change',{bubbles:true}));
    });
  }

  const SNAP_ATTR='data-dirty';
  const initial={};
  function takeSnapshot(){
    qsa('input[name],select[name],textarea[name]',form).forEach(inp=>{ if(inp.disabled) return; const name=inp.name; if(!name) return; let v=inp.value; if(inp.type==='number') v=v!==''?Number(v):''; initial[name]=v; inp.removeAttribute(SNAP_ATTR); });
    updateDiffPreview();
  }
  function currentValue(inp){ let v=inp.value; if(inp.type==='number') v=v!==''?Number(v):''; return v; }
  function markDirty(){
    qsa('input[name],select[name],textarea[name]',form).forEach(inp=>{
      if(inp.disabled) return; const name=inp.name; if(!name) return; const initVal=initial[name]; const now=currentValue(inp);
      if(now!==initVal){ inp.setAttribute(SNAP_ATTR,'1'); }
      else { inp.removeAttribute(SNAP_ATTR); }
    });
    updateDiffPreview();
  }
  function gatherDiff(){
    const diff={}; let count=0;
  qsa('input[name],select[name],textarea[name]',form).forEach(inp=>{ if(inp.disabled) return; const name=inp.name; if(!name) return; const initVal=initial[name]; const now=currentValue(inp); if(now!==initVal){ diff[name]=now; count++; }});
    return {diff,count};
  }
  function buildUpdateSQL(entry,diff, opts={}){
    const {all=false} = opts;

    let source = diff;
    if(all){
      source={};
      qsa('input[name],select[name],textarea[name]',form).forEach(inp=>{ if(inp.disabled) return; const n=inp.name; if(!n||n==='entry') return; let v=inp.value; if(inp.type==='number') v=v!==''?Number(v):''; source[n]=v; });
    }
    const cols=[]; Object.keys(source).forEach(k=>{ if(k==='entry') return; const v=source[k]; if(v===''||v===null||v===undefined){ cols.push('`'+k+'`=NULL'); }
      else if(typeof v==='number' && !isNaN(v)){ cols.push('`'+k+'`='+v); }
      else {
        let str=String(v);
        if(str.length>200){ const origLen=str.length; str=str.slice(0,200); str += '/* truncated len='+origLen+' */'; }
        const escaped=str.replace(/'/g,"''");
        cols.push('`'+k+'`='+'\''+escaped+'\'');
      }
    });
    if(!cols.length) return translate('diff.no_changes_comment','-- No changes (modify the form and retry)');
    let comment='';
    const hasClass=('class' in source);
    const hasSubclass=('subclass' in source);
    if(hasClass){
      const clsId=source['class'];
      const fallbackName=translate('diff.comment.class_fallback_name','Class :id',{id:clsId});
      const name=(window.APP_ENUMS?.classes?.[clsId]) || fallbackName;
      const classLabel=translate('diff.comment.class_label','class');
      comment+=` -- ${classLabel}=${clsId} ${name}`;
    }
    if(hasSubclass){
      const scId=source['subclass'];
      const clsId=source['class'] || initial['class'];
      const fallbackSub=translate('diff.comment.subclass_fallback_name','Subclass :id',{id:scId});
      const scName=ItemSubclasses.nameOf(clsId,scId) || fallbackSub;
      const subclassLabel=translate('diff.comment.subclass_label','subclass');
      comment+=(comment?' | ':' -- ')+`${subclassLabel}=${scId} ${scName}`;
    }
    return 'UPDATE `item_template`\n  SET '+cols.join(',\n      ')+'\nWHERE `entry`='+entry+';'+(comment?'\n'+comment:'');
  }
  takeSnapshot();
  form.addEventListener('input', markDirty);
  form.addEventListener('change', markDirty);
  function updateDiffPreview(){
    const pre=document.getElementById('itemDiffSqlLive'); if(!pre) return;
    const entry=Number(form.dataset.entry)||0; const {diff}=gatherDiff();
    const fullMode = !!qs('#sqlFullMode')?.checked;
    if(!Object.keys(diff).length && !fullMode){ pre.textContent=translate('diff.no_changes_placeholder','-- No changes --'); return; }
    const sql=buildUpdateSQL(entry,diff,{all:fullMode}); pre.textContent=sql;
  }

  const fullModeBox=qs('#sqlFullMode'); if(fullModeBox){ fullModeBox.addEventListener('change',updateDiffPreview); }
  const copyBtn=qs('#btn-copy-diff-inline'); if(copyBtn){ copyBtn.addEventListener('click',()=>{ const pre=qs('#itemDiffSqlLive'); if(!pre) return; navigator.clipboard.writeText(pre.textContent||''); itemNotify(translate('common.copy_success','Copied'),'success'); }); }
  const execBtn=qs('#btn-exec-diff-sql'); if(execBtn){ execBtn.addEventListener('click', async ()=>{
    const pre=qs('#itemDiffSqlLive'); if(!pre) return; const sql=pre.textContent.trim();
    const {diff}=gatherDiff();
    const fullMode=!!qs('#sqlFullMode')?.checked;
    const noChangesComment=translate('diff.no_changes_comment','-- No changes (modify the form and retry)');
    if((!Object.keys(diff).length && !fullMode) || !sql || sql===noChangesComment){
      itemNotify(translate('diff.no_changes_to_execute','No changes to execute'),'info');
      return;
    }
    if(!/^UPDATE\s+`?item_template`?/i.test(sql.split('\n')[0])){ itemNotify(translate('exec.only_item_template_update','Only UPDATE on item_template is allowed'),'error'); return; }
    if(!confirm(translate('exec.confirm_run_diff','Run the current SQL?'))) return;
    const box=qs('#itemDiffSqlExecResult'); const status=qs('#sqlExecStatus'); const summary=qs('#sqlExecSummary'); const msgs=qs('#sqlExecMessages'); const timing=qs('#sqlExecTiming'); const sampleWrap=qs('#sqlExecSampleWrapper'); const sampleBox=qs('#sqlExecSample');
    function show(){ if(box) box.style.display='block'; }
    function setStatus(ok){ if(!status) return; status.style.display='inline-block'; status.textContent= ok? translate('exec.status.success','Success'):translate('exec.status.failed','Failed'); status.style.background= ok? '#1f5f2e':'#5f1f28'; status.style.color= ok? '#7dffb0':'#ff9ca9'; }
    try{
      const start=performance.now();
      const res=await Panel.api.post('/item/api/exec-sql',{sql});
      const elapsed=(performance.now()-start).toFixed(1)+'ms';
      show(); setStatus(!!res.success); timing && (timing.textContent=translate('exec.timing','Duration :duration',{duration:elapsed}));
      const rowsCount=(res.affected??res.rows??0);
      const rowsLabel=translate('exec.summary.rows_label','Rows affected:');
      const failureFallback=translate('exec.default_error','Execution failed');
      summary && (summary.innerHTML = res.success ?
        `<span style="color:#7dffb0">${escapeHtml(rowsLabel)} <strong>${rowsCount}</strong></span>` :
        `<span style="color:#ff9ca9">${escapeHtml(res.message||failureFallback)}</span>`);

      const messages=[]; const warningPrefix=translate('exec.warning_prefix','WARNING:'); const errorPrefix=translate('exec.error_prefix','ERROR:');
      if(res.warning){ messages.push(`${warningPrefix} ${res.warning}`); }
      if(res.warnings && Array.isArray(res.warnings)){ res.warnings.forEach(w=> messages.push(`${warningPrefix} ${w}`)); }
      if(res.error){ messages.push(`${errorPrefix} ${res.error}`); }
      const noWarnings=translate('exec.messages.none','-- No warnings');
      const checkAbove=translate('exec.messages.check_above','-- See error above');
      msgs && (msgs.textContent = messages.length? messages.join('\n') : (res.success? noWarnings : checkAbove));

      if(sampleWrap && sampleBox){
        if(res.sample || res.row || res.snapshot){
          const sampleObj = res.sample || res.row || res.snapshot;
          sampleWrap.style.display='block';
          sampleBox.textContent = JSON.stringify(sampleObj,null,2);
        } else { sampleWrap.style.display='none'; }
      }
      if(res.success){ itemNotify(translate('exec.run_success','Execution succeeded'),'success'); takeSnapshot(); updateDiffPreview(); }
      else {
        const reason = res.message || translate('exec.default_error','Execution failed');
        itemNotify(translate('exec.run_failed_with_reason','Execution failed: :reason',{reason}),'error',{duration:5000});
      }

      (function ensureButtons(){
        const clearBtn=qs('#btn-clear-exec-result'); const hideBtn=qs('#btn-hide-exec-result'); const copyBtn=qs('#btn-copy-exec-json');
        if(clearBtn && !clearBtn.__bound){ clearBtn.addEventListener('click',()=>{ summary.innerHTML=''; msgs.textContent=''; sampleWrap && (sampleWrap.style.display='none'); status.style.display='none'; timing.textContent=''; }); clearBtn.__bound=true; }
        if(hideBtn && !hideBtn.__bound){ hideBtn.addEventListener('click',()=>{ box.style.display='none'; }); hideBtn.__bound=true; }
        if(copyBtn && !copyBtn.__bound){ copyBtn.addEventListener('click',()=>{ navigator.clipboard.writeText(JSON.stringify(res,null,2)); itemNotify(translate('exec.copy_json_success','Copied JSON'),'success'); }); copyBtn.__bound=true; }
      })();
    }catch(e){
      const reason = e?.message || e;
      itemNotify(translate('errors.request_failed_reason','Request failed: :reason',{reason}),'error',{duration:5000});
      if(summary){ summary.innerHTML=`<span style="color:#ff9ca9">${escapeHtml(translate('exec.request_exception','Request exception: :reason',{reason}))}</span>`; }
      show(); setStatus(false);
    }
  }); }


  const attachSave=(btn)=>{
    if(!btn) return;
    btn.addEventListener('click',async ()=>{
    const entry=Number(form.dataset.entry)||0; const {diff,count}=gatherDiff(); if(!count){ itemNotify(translate('save.no_changes','No changes to save'),'info'); return; }
    const res=await Panel.api.post('/item/api/save',{entry,changes:diff});
    if(res.success){ itemNotify(translate('save.success','Saved successfully'),'success'); takeSnapshot(); }
    else {
      const reason = res.message;
      itemNotify(reason ? translate('save.failed_with_reason','Save failed: :reason',{reason}) : translate('save.failed','Save failed'),'error');
    }
    });
  };
  attachSave(qs('#btn-save-item'));
  attachSave(qs('#btn-save-item-top'));

  const diffBtn=qs('#btn-diff-sql');
  if(diffBtn){ diffBtn.addEventListener('click',()=>{
    const entry=Number(form.dataset.entry)||0; const {diff}=gatherDiff();
    const sql=buildUpdateSQL(entry,diff);
    const content='<pre class="sql-result mono" id="diff-sql-preview">'+sql.replace(/</g,'&lt;')+'</pre>';
    const footer=`<button class="btn outline" type="button" data-action="copy-diff-sql">${translate('diff.modal.copy_button','Copy')}</button> <button class="btn" data-close>${translate('diff.modal.close_button','Close')}</button>`;
    if(window.Modal){
      Modal.show({id:'diff-sql', title:translate('diff.modal.title','UPDATE Preview'), content, footer, width:'760px'});

      const wrap=document.getElementById('modal-diff-sql');
      if(wrap && !wrap.__diffBound){
        wrap.addEventListener('click',e=>{
          if(e.target && e.target.getAttribute('data-action')==='copy-diff-sql'){
            const pre=wrap.querySelector('#diff-sql-preview'); if(pre){ navigator.clipboard.writeText(pre.textContent||''); itemNotify(translate('common.copy_success','Copied'),'success'); }
          }
        });
        wrap.__diffBound=true;
      }
    } else {
      alert(sql);
    }
  }); }
  const delBtn=qs('#btn-delete-item'); if(delBtn){ delBtn.addEventListener('click',async ()=>{
    const id=Number(delBtn.dataset.id); if(!confirm(translate('save.confirm_delete_item','Delete item #:id?',{id}))) return; const res=await Panel.api.post('/item/api/delete',{entry:id}); if(res.success){ itemNotify(translate('save.delete_success','Item deleted'),'success',{duration:2000}); window.location=Panel.url('/item'); } else { itemNotify(res.message||translate('save.delete_failed','Delete failed'),'error'); }
  }); }



}

function boot(){
  if(window.__ITEM_BOOTED){ return; }
  if(!document.body || document.body.dataset.module!=='item'){ return; }
  window.__ITEM_BOOTED = true;
  if(qs('.item-filter-form')) initList();
  if(qs('#itemEditForm')) initEdit();
  if(qs('#newItemClass')) initNewItemModal();
  setTimeout(()=>{ if(typeof window.__forceDiff==='function'){ window.__forceDiff(); } },50);
}

document.addEventListener('DOMContentLoaded',boot);

if(document.readyState==='interactive' || document.readyState==='complete'){
  setTimeout(boot,0);
}

async function refreshSqlLog(type){
  const box=qs('#itemSqlLogBox');
  const select=qs('#itemLogType');
  const logType=type || (select?select.value:'sql');
  if(box){ box.textContent=translate('logs.loading_placeholder','-- Loading... --'); }
  try{
    const res=await Panel.api.post('/item/api/logs',{type:logType,limit:200});
    if(res.success){
      const lines=Array.isArray(res.logs)?res.logs:[];
      if(box){ box.textContent=lines.length? lines.join('\n') : translate('logs.empty_placeholder','-- No logs --'); }
    } else {
      if(box){ box.textContent=translate('logs.load_failed_placeholder','-- Load failed --'); }
      itemNotify(res.message||translate('logs.load_failed','Failed to load logs'),'error',{duration:5000});
    }
  }catch(e){
    if(box){ box.textContent=translate('logs.load_failed_placeholder','-- Load failed --'); }
    const reason = e?.message || e;
    itemNotify(translate('logs.load_failed_with_reason','Failed to load logs: :reason',{reason}),'error',{duration:5000});
  }
}


window.__forceDiff = function(){
  try{
    const form=qs('#itemEditForm'); if(!form) return;

    const ev=new Event('change',{bubbles:true});
    form.dispatchEvent(ev);
  }catch(e){ console.warn('forceDiff error',e); }
};

