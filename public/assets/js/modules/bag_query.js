/**
 * File: public/assets/js/modules/bag_query.js
 * Purpose: Defines class bag for the public/assets/js/modules module.
 * Classes:
 *   - bag
 * Functions:
 *   - translate()
 *   - resolveFlashTarget()
 *   - applyFlashVariant()
 *   - setFlashMessage()
 *   - revealFlash()
 *   - fetchJson()
 *   - post()
 *   - showModal()
 *   - hideModal()
 *   - updateItemsSubtitle()
 *   - activateRow()
 *   - resetItemsPlaceholder()
 *   - bindSearch()
 *   - runSearch()
 *   - applyPrefill()
 *   - renderChars()
 *   - renderCharsLoading()
 *   - renderCharError()
 *   - loadItems()
 *   - renderItems()
 *   - renderItemsLoading()
 *   - renderItemsError()
 *   - bindFilter()
 *   - openDelete()
 *   - validateQty()
 *   - ensureModalBindings()
 *   - doDelete()
 *   - qualityIndex()
 *   - qualityClassName()
 *   - esc()
 *   - init()
 *   - qs()
 *   - qsa()
 */

(function(){
  const qs=(s,r=document)=>r.querySelector(s); const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));
  const apiBase = (window.APP_BASE||'').replace(/\/$/,'');
  const hasPanelApi = !!(window.Panel && Panel.api);
  const panelLocale = window.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function' ? panelLocale.moduleLocale.bind(panelLocale) : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('bag_query')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.bag_query.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('bag_query', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    if(typeof text === 'string' && text === `modules.bag_query.${path}` && fallback){
      text = fallback;
    }
    if(typeof text === 'string' && replacements && typeof replacements === 'object'){
      Object.entries(replacements).forEach(([key, value])=>{
        const pattern = new RegExp(`:${key}(?![A-Za-z0-9_])`, 'g');
        text = text.replace(pattern, String(value ?? ''));
      });
    }
    return text;
  }
  const api={
    chars:(t,v)=> `/bag/api/characters?type=${encodeURIComponent(t)}&value=${encodeURIComponent(v)}`,
    items:(g)=> `/bag/api/items?guid=${encodeURIComponent(g)}`,
    reduce: '/bag/api/reduce'
  };
  const csrf=window.__CSRF_TOKEN;
  const ctx=window.__BAG_QUERY_CTX||{};
  const ctxPrefill=ctx.prefill||null;
  const ctxAutoSearch=!!ctx.autoSearch;
  const QUALITY_FALLBACK = ['Poor','Common','Uncommon','Rare','Epic','Legendary','Artifact','Heirloom'];
  const QUALITY_TEXT = QUALITY_FALLBACK.map((label, idx)=> translate(`quality.${idx}`, label));
  const CLASS_META_FALLBACK = [
    [1,'warrior','Warrior'],
    [2,'paladin','Paladin'],
    [3,'hunter','Hunter'],
    [4,'rogue','Rogue'],
    [5,'priest','Priest'],
    [6,'death-knight','Death Knight'],
    [7,'shaman','Shaman'],
    [8,'mage','Mage'],
    [9,'warlock','Warlock'],
    [10,'monk','Monk'],
    [11,'druid','Druid'],
    [12,'demon-hunter','Demon Hunter']
  ];
  const CLASS_META = CLASS_META_FALLBACK.reduce((acc,[id,slug,label])=>{
    acc[id] = { slug, label: translate(`classes.${slug}`, label) };
    return acc;
  },{});
  const fallbackFlashVariants=['panel-flash--success','panel-flash--error','panel-flash--info'];
  const Feedback=(window.Panel && Panel.feedback)? Panel.feedback : {
    show(target,variant,message,opts){
      const el=resolveFlashTarget(target); if(!el) return;
      applyFlashVariant(el,variant); setFlashMessage(el,message);
      revealFlash(el,opts);
    },
    success(target,message,opts){ this.show(target,'success',message,opts); },
    error(target,message,opts){ this.show(target,'error',message,opts); },
    info(target,message,opts){ this.show(target,'info',message,opts); },
    clear(target){
      const el=resolveFlashTarget(target); if(!el) return;
      clearTimeout(el._flashTimer||0); el._flashTimer=null;
      fallbackFlashVariants.forEach(cls=> el.classList.remove(cls));
      el.classList.remove('is-visible');
      el.textContent='';
    }
  };
  function resolveFlashTarget(target){
    if(!target) return null;
    const el=typeof target==='string'? document.querySelector(target):target;
    if(!el) return null;
    if(!el.classList.contains('panel-flash')) el.classList.add('panel-flash');
    return el;
  }
  function applyFlashVariant(el,variant){
    fallbackFlashVariants.forEach(cls=> el.classList.remove(cls));
    if(!variant) return;
    const cls=`panel-flash--${variant}`;
    el.classList.add(cls);
  }
  function setFlashMessage(el,message){ if(typeof message==='string') el.textContent=message; }
  function revealFlash(el,opts){
    el.classList.add('is-visible');
    const duration=(opts&&typeof opts.duration==='number')? opts.duration:3200;
    clearTimeout(el._flashTimer||0);
    if(duration>0){ el._flashTimer=setTimeout(()=>Feedback.clear(el),duration); }
    else { el._flashTimer=null; }
  }
  async function fetchJson(path){
    try{
      if(hasPanelApi) return await Panel.api.get(path);
      const res=await fetch(apiBase+path,{credentials:'same-origin'});
      return await res.json();
    }catch(e){
      return {
        success:false,
        message: e && e.message ? e.message : translate('errors.parse_failed','Failed to parse response')
      };
    }
  }
  async function post(path,data){
    if(hasPanelApi){
      try{ return await Panel.api.post(path,data||{}); }
      catch(e){
        return {
          success:false,
          message: e && e.message ? e.message : translate('errors.network','Network error')
        };
      }
    }
    const fd=new FormData(); Object.entries(data||{}).forEach(([k,v])=> fd.append(k,v));
    if(csrf){ if(!fd.has('_token')) fd.append('_token',csrf); if(!fd.has('_csrf')) fd.append('_csrf',csrf); }
    try{
      const res=await fetch(apiBase+path,{method:'POST',body:fd,credentials:'same-origin'});
      return await res.json();
    }catch(e){
      return {
        success:false,
        message: e && e.message ? e.message : translate('errors.parse_failed','Failed to parse response')
      };
    }
  }
  function showModal(modal){ if(!modal) return; modal.classList.add('active'); document.body.style.overflow='hidden'; }
  function hideModal(modal){ if(!modal) return; modal.classList.remove('active'); if(!document.querySelector('.modal-backdrop.active')) document.body.style.overflow=''; }
  let selectedGuid=0; let selectedName=''; let currentItems=[];
  function updateItemsSubtitle(extra){
    const el=qs('#bqItemsCurrent');
    if(!el) return;
    if(!selectedGuid){
      el.textContent=translate('items.subtitle.none','No character selected');
      return;
    }
    const base = selectedName
      ? translate('items.subtitle.current_name','Current character: :name',{ name: selectedName })
      : translate('items.subtitle.current_guid','Current character GUID :guid',{ guid: selectedGuid });
    el.textContent = extra
      ? translate('items.subtitle.with_status', ':base (:status)', { base, status: extra })
      : base;
  }
  function activateRow(){ const rows=qsa('#bqCharTable tbody tr'); rows.forEach(tr=>{ const g=parseInt(tr.dataset.guid||'0',10); tr.classList.toggle('bag-query-row--active',g===selectedGuid); }); }
  function resetItemsPlaceholder(){
    const tb=qs('#bqItemTable tbody');
    if(tb) tb.innerHTML=`<tr><td colspan="7" class="text-center muted">${esc(translate('items.placeholder.none','No character selected'))}</td></tr>`;
    updateItemsSubtitle();
  }
  function bindSearch(){
    const f=qs('#bagSearchForm'); if(!f) return;
    f.addEventListener('submit',async e=>{
      e.preventDefault();
      const typeNode=qs('#bqType');
      const valueNode=qs('#bqValue');
      const type=typeNode? typeNode.value:'character_name';
      const value=valueNode? valueNode.value:'';
      await runSearch(type,value,{fromPrefill:false});
    });
  }

  async function runSearch(type,value,{fromPrefill=false}={}){
    const trimmed=typeof value==='string'? value.trim():'';
    if(!trimmed){
      if(!fromPrefill){
  Feedback.error('#bqActionFlash',translate('search.validation.empty','Please enter a search value'),{duration:4000});
        const input=qs('#bqValue'); if(input) input.focus();
      }
      return false;
    }
    const safeType=['character_name','username'].includes(type)? type:'character_name';
    selectedGuid=0; selectedName=''; currentItems=[];
    renderCharsLoading();
    resetItemsPlaceholder();
    Feedback.clear('#bqActionFlash');
    const json=await fetchJson(api.chars(safeType,trimmed));
    if(!json.success){
  renderCharError(json.message||translate('search.error.failed','Query failed'));
      return false;
    }
    renderChars(json.data||[]);
    const valueNode=qs('#bqValue'); if(valueNode) valueNode.value=trimmed;
    const typeNode=qs('#bqType'); if(typeNode) typeNode.value=safeType;
    return true;
  }

  async function applyPrefill(){
    if(!ctxPrefill) return;
    const rawValue=ctxPrefill.value!=null? String(ctxPrefill.value):'';
    const trimmedValue=rawValue.trim();
    if(trimmedValue==='') return;
    const safeType=['character_name','username'].includes(ctxPrefill.type)? ctxPrefill.type:'character_name';
    const typeNode=qs('#bqType'); if(typeNode) typeNode.value=safeType;
    const valueNode=qs('#bqValue'); if(valueNode) valueNode.value=trimmedValue;
    if(ctxAutoSearch){
      await runSearch(safeType, trimmedValue, {fromPrefill:true});
    }
  }

  function renderChars(rows){
    const tb=qs('#bqCharTable tbody'); if(!tb) return;
    if(!rows.length){
      tb.innerHTML=`<tr><td colspan="6" class="text-center muted">${esc(translate('search.empty','No results'))}</td></tr>`;
      return;
    }
    tb.innerHTML=rows.map(r=>{
      const guid=parseInt(r.guid,10);
      const classId=parseInt(r.class,10);
      const meta=Number.isFinite(classId)? CLASS_META[classId]||null:null;
      const active=guid===selectedGuid ? ' bag-query-row--active' : '';
      const classSlug=meta? ` bag-query-class bag-query-class--${meta.slug}`:'';
      const classLabel=meta? esc(meta.label):esc(r.class);
      const dataClass=Number.isFinite(classId)? ` data-class="${classId}"`:'';
      const viewLabel = translate('actions.view','View');
      return `<tr class="bag-query-row${classSlug}${active}" data-guid="${r.guid}"${dataClass}><td>${r.guid}</td><td class="bag-query-name">${esc(r.name)}</td><td>${r.level}</td><td>${r.race}</td><td>${classLabel}</td><td><button class="btn-sm btn info" data-act="view" data-guid="${r.guid}" data-name="${esc(r.name)}">${esc(viewLabel)}</button></td></tr>`;
    }).join('');
    tb.querySelectorAll('button[data-act=view]').forEach(b=> b.addEventListener('click',()=> loadItems(b.dataset.guid,b.dataset.name)));
    activateRow();
  }
  function renderCharsLoading(){
    const tb=qs('#bqCharTable tbody');
  if(tb) tb.innerHTML=`<tr><td colspan="6" class="text-center muted">${esc(translate('status.loading','Loading...'))}</td></tr>`;
  }
  function renderCharError(msg){
    const tb=qs('#bqCharTable tbody');
    if(tb) tb.innerHTML=`<tr><td colspan="6" class="text-center text-danger">${esc(msg)}</td></tr>`;
  }

  async function loadItems(guid,name){
    selectedGuid=parseInt(guid,10)||0;
    selectedName=name||'';
  updateItemsSubtitle(translate('status.loading','Loading...'));
    activateRow();
    renderItemsLoading();
    Feedback.clear('#bqActionFlash');
    const json=await fetchJson(api.items(guid));
    if(!json.success){
      renderItemsError(json.message||translate('items.error.load_failed','Failed to load items'));
      return;
    }
    currentItems=json.data||[];
    renderItems(currentItems);
  }
  function renderItems(rows){ const tb=qs('#bqItemTable tbody'); if(!tb) return; updateItemsSubtitle(); if(!rows||!rows.length){ const msg=selectedGuid? translate('items.empty','No items or bags empty'):translate('items.placeholder.none','No character selected'); tb.innerHTML='<tr><td colspan="7" class="text-center muted">'+esc(msg)+'</td></tr>'; return; }
    tb.innerHTML=rows.map(r=>{
      const qIdx=qualityIndex(r.quality);
      const qClass=qualityClassName(qIdx);
  const qualityText=qIdx>=0? `${QUALITY_TEXT[qIdx]} (${qIdx})`:translate('items.quality.unknown','Unknown');
      const rawName=(r.name||'').trim();
      const fallbackName='#'+(r.itemEntry||'');
      const safeName=rawName? esc(rawName):esc(fallbackName);
      const filterName=(rawName||fallbackName).toLowerCase();
      const nameHtml=`<span class="item-quality ${qClass}">${safeName}</span>`;
      const qualityHtml=`<span class="item-quality ${qClass}">${esc(qualityText)}</span>`;
      const buttonName=rawName? esc(rawName):esc(fallbackName);
      const deleteLabel = translate('actions.delete','Delete');
      return `<tr data-name="${esc(filterName)}" data-count="${r.count}" data-inst="${r.item_instance_guid}"><td>${r.item_instance_guid}</td><td>${r.itemEntry}</td><td>${nameHtml}</td><td>${qualityHtml}</td><td>${r.count}</td><td>${r.bag}/${r.slot}</td><td><button class="btn-sm btn danger" data-act="del" data-inst="${r.item_instance_guid}" data-count="${r.count}" data-entry="${r.itemEntry}" data-name="${buttonName}">${esc(deleteLabel)}</button></td></tr>`;
    }).join('');
    tb.querySelectorAll('button[data-act=del]').forEach(b=> b.addEventListener('click',()=> openDelete(b.dataset)));
  }
  function renderItemsLoading(){ const tb=qs('#bqItemTable tbody'); if(tb){ const msg=selectedGuid? translate('status.loading','Loading...'):translate('items.placeholder.none','No character selected'); tb.innerHTML='<tr><td colspan="7" class="text-center muted">'+esc(msg)+'</td></tr>'; } }
  function renderItemsError(msg){ const tb=qs('#bqItemTable tbody'); if(tb) tb.innerHTML='<tr><td colspan="7" class="text-center text-danger">'+esc(msg)+'</td></tr>'; updateItemsSubtitle(translate('items.error.load_failed','Failed to load items')); Feedback.error('#bqActionFlash',msg); }


  function bindFilter(){ const ip=qs('#bqItemFilter'); if(!ip) return; ip.setAttribute('placeholder', translate('items.filter.placeholder','Filter items by name')); ip.addEventListener('input',()=>{ const v=ip.value.trim().toLowerCase(); const rows=qsa('#bqItemTable tbody tr'); rows.forEach(tr=>{ if(!v){ tr.style.display=''; return; } const n=tr.getAttribute('data-name')||''; tr.style.display = n.includes(v)?'':'none'; }); }); }


  let delCtx=null;
  function openDelete(data){
    delCtx={inst:parseInt(data.inst,10),count:parseInt(data.count,10),entry:parseInt(data.entry,10),name:data.name};
    const modal=qs('#bqDeleteModal'); if(!modal) return;
    const info=qs('#bqDelInfo',modal);
    if(info){
      const tpl = translate('delete.info','Item <strong>#:entry :name</strong> current count <strong>:count</strong><br>Instance GUID: :inst',{
        entry: delCtx.entry,
        name: delCtx.name,
        count: delCtx.count,
        inst: delCtx.inst
      });
      info.innerHTML = tpl;
    }
    const qty=qs('#bqDelQty',modal);
    const ok=qs('#bqDelOk',modal);
    if(!qty||!ok) return;
  const max = delCtx.count>0? delCtx.count:1;
    qty.value = Math.min(Math.max(1,max>0?1:0),max);
    qty.setAttribute('max',String(max));
    if(qty._bagHandler){ qty.removeEventListener('input',qty._bagHandler); }
    qty._bagHandler=()=>{ validateQty(qty,ok); };
    qty.addEventListener('input',qty._bagHandler);
    validateQty(qty,ok);
    if(ok._bagHandler){ ok.removeEventListener('click',ok._bagHandler); }
  ok._bagHandler=(event)=>{ event.preventDefault(); doDelete(event); };
    ok.addEventListener('click',ok._bagHandler);
    Feedback.clear('#bqDelFeedback');
    ensureModalBindings(modal);
    showModal(modal);
    qty.focus(); qty.select();
  }
  function validateQty(qty,ok){ const q=parseInt(qty.value||'0',10); const valid=(q>0 && delCtx && q<=delCtx.count); ok.disabled=!valid; if(valid) Feedback.clear('#bqDelFeedback'); else Feedback.error('#bqDelFeedback', translate('delete.validation.quantity','Quantity must be greater than 0 and no more than stack count'), { duration: 0 }); }
  function ensureModalBindings(modal){
    if(modal._bagCloseBound) return;
    modal.addEventListener('click',e=>{
      if(e.target===modal || (e.target && e.target.dataset && Object.prototype.hasOwnProperty.call(e.target.dataset,'close'))){
        hideModal(modal);
        Feedback.clear('#bqDelFeedback');
        delCtx=null;
      }
    });
    modal._bagCloseBound=true;
  }
  async function doDelete(e){
    if(e) e.preventDefault();
    const modal=qs('#bqDeleteModal');
    if(!modal||!delCtx) return;
    const qtyInput=qs('#bqDelQty',modal);
    const ok=qs('#bqDelOk',modal);
    if(!qtyInput||!ok) return;
    const qty=parseInt(qtyInput.value||'0',10);
    if(!(qty>0 && qty<=delCtx.count)){
      Feedback.error('#bqDelFeedback',translate('delete.validation.quantity','Quantity must be greater than 0 and no more than stack count'),{duration:0});
      return;
    }
    ok.disabled=true;
    const originalLabel=ok.textContent;
  ok.textContent=translate('actions.processing','Processing...');
    const res=await post(api.reduce,{character_guid:selectedGuid,item_instance_guid:delCtx.inst,quantity:qty,item_entry:delCtx.entry});
    ok.textContent=originalLabel;
    if(res.success){
      hideModal(modal);
      Feedback.clear('#bqDelFeedback');
      Feedback.success('#bqActionFlash',res.message||translate('delete.success','Item deleted'));
      currentItems=currentItems.map(it=>{ if(it.item_instance_guid==delCtx.inst){ it.count=res.new_count; } return it; }).filter(it=> it.count>0);
      renderItems(currentItems);
      delCtx=null;
    } else {
      ok.disabled=false;
      const msg=res.message||translate('delete.error','Operation failed');
      Feedback.error('#bqDelFeedback',msg,{duration:0});
      Feedback.error('#bqActionFlash',msg);
    }
  }
  function qualityIndex(q){ const num=typeof q==='number'? q:parseInt(q,10); return Number.isNaN(num)||num<0||num>7?-1:num; }
  function qualityClassName(idx){ return idx>=0? `item-quality-q${idx}`:'item-quality-unknown'; }
  function esc(s){ return (s+'').replace(/[&<>"]|'/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c])); }

  function init(){
    bindSearch();
    bindFilter();
    updateItemsSubtitle();
    applyPrefill().catch(()=>{});
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();

