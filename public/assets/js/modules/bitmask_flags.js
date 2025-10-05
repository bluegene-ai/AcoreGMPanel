/**
 * File: public/assets/js/modules/bitmask_flags.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - escapeHtml()
 *   - initBitmaskFlags()
 *   - groupFor()
 *   - ensureNodes()
 *   - namesFor()
 *   - updateLabel()
 *   - refreshCurrent()
 *   - buildOptions()
 *   - buildDynamic()
 *   - openMask()
 *   - closeMask()
 *   - wireDynamic()
 */

const panelLocale = typeof window !== 'undefined' ? (window.Panel || {}) : {};
const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function'
  ? panelLocale.moduleLocale.bind(panelLocale)
  : null;
const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
  ? panelLocale.createModuleTranslator('bitmask')
  : null;

function translate(path, fallback, replacements){
  const defaultValue = fallback ?? `modules.bitmask.${path}`;
  let text;
  if(moduleLocaleFn){
    text = moduleLocaleFn('bitmask', path, defaultValue);
  } else if(moduleTranslator){
    text = moduleTranslator(path, defaultValue);
  } else {
    text = defaultValue;
  }
  if(typeof text === 'string' && text === `modules.bitmask.${path}` && fallback){
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

function escapeHtml(value){
  return String(value ?? '').replace(/[&<>"']/g, char=>({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;',
    "'":'&#39;'
  })[char]);
}

export function initBitmaskFlags(opts={}){
  let modal = document.getElementById(opts.modalId||'bitmask-modal');
  const dynamic = !modal; 
  const FLAGS = (opts.enums && opts.enums.flags) ? opts.enums.flags : (window.APP_ENUMS?APP_ENUMS.flags:{regular:{},extra:{},custom:{}});

  function groupFor(name){
    const el=document.querySelector('input[name="'+name+'\"][data-bitmask]');
    if(el && el.dataset.bitmaskGroup) return el.dataset.bitmaskGroup;
    if(name==='flags') return 'regular';
    if(name==='flags_extra') return 'extra';
    if(name==='flagscustom') return 'custom';
    return 'regular';
  }

  let optBox=null, filterInput=null, curSpan=null;
  function ensureNodes(){
    if(dynamic){
      const host=document.getElementById('modal-bitmask');
      if(!host) return false;
      optBox=host.querySelector('#bitmask-options');
      filterInput=host.querySelector('#bitmask-filter');
      curSpan=host.querySelector('#bitmask-current');
      return true;
    } else {
      if(!optBox) optBox=modal.querySelector('#bitmask-options');
      if(!filterInput) filterInput=modal.querySelector('#bitmask-filter');
      if(!curSpan) curSpan=modal.querySelector('#bitmask-current');
      return true;
    }
  }
  let currentTarget=null, tempMask=0, dictRef={};

  function namesFor(mask, dict){ if(!mask) return []; const out=[]; for(const bit in dict){ const b=parseInt(bit); if((mask & b)===b) out.push(dict[bit]); } return out; }
  function updateLabel(id){ const inp=document.querySelector('input[name="'+id+'\"][data-bitmask]'); if(!inp) return; const val=parseInt(inp.value)||0; const dict=FLAGS[groupFor(id)]||{}; const names=namesFor(val,dict); const holder=document.getElementById(id+'-names'); if(holder){ const joiner=translate('labels.joiner',', '); const noneLabel=translate('labels.none','(none)'); holder.textContent=names.length?names.join(joiner):noneLabel; } }
  function refreshCurrent(){ if(curSpan) curSpan.textContent=translate('status.current_value','Current value: :value',{ value: tempMask }); }
  function buildOptions(){ ensureNodes(); if(!optBox) return; optBox.innerHTML=''; const kw=(filterInput?.value||'').trim().toLowerCase(); Object.keys(dictRef).forEach(bit=>{ const name=dictRef[bit]; if(kw && !name.toLowerCase().includes(kw)) return; const b=parseInt(bit); const checked=(tempMask & b)===b; const wrap=document.createElement('label'); wrap.className='flex gap-1 align-center';
    const cb=document.createElement('input'); cb.type='checkbox'; cb.value=bit; cb.checked=checked; cb.addEventListener('change',()=>{ if(cb.checked) tempMask|=b; else tempMask &= ~b; refreshCurrent(); });
    const span=document.createElement('span'); span.textContent=translate('option.label','(:bit) :name',{ bit, name }); wrap.appendChild(cb); wrap.appendChild(span); optBox.appendChild(wrap); }); }
  function buildDynamic(id){
    const filterPlaceholder=escapeHtml(translate('filter.placeholder','Filter keywords...'));
    const selectAllLabel=escapeHtml(translate('controls.select_all','Select all'));
    const selectNoneLabel=escapeHtml(translate('controls.select_none','Clear all'));
    const invertLabel=escapeHtml(translate('controls.select_invert','Invert'));
    const applyLabel=escapeHtml(translate('actions.apply','Apply'));
    const tipText=escapeHtml(translate('help.toggle_tip','Click to toggle bits. Hold Shift and drag to multi-select.'));
    const currentText=escapeHtml(translate('status.current_value','Current value: :value',{ value: tempMask }));
    const modalTitle=translate('modal.title','Edit :target',{ target: id });
    const content=[
      '<div class="bitmask-toolbar flex gap-2 align-center mb-2">',
      `  <input type="text" id="bitmask-filter" placeholder="${filterPlaceholder}" class="full-width flex-1">`,
      '  <div class="btn-group flex gap-1">',
      `    <button class="btn-sm btn outline" data-mask-op="all" type="button">${selectAllLabel}</button>`,
      `    <button class="btn-sm btn outline" data-mask-op="none" type="button">${selectNoneLabel}</button>`,
      `    <button class="btn-sm btn outline" data-mask-op="invert" type="button">${invertLabel}</button>`,
      '  </div>',
      '</div>',
      '<div id="bitmask-options" class="bitmask-options-grid"></div>',
      `<div class="muted small" data-bitmask-tip="1">${tipText}</div>`,
      `<div class="text-right mt-2"><span class="muted" id="bitmask-current">${currentText}</span> <button class="btn" id="bitmask-apply" type="button">${applyLabel}</button></div>`
    ].join('');
    if(window.Modal){ Modal.show({id:'bitmask', title: modalTitle, content, width:'820px'}); }
  }
  function openMask(id){ currentTarget=id; const inp=document.querySelector('input[name="'+id+'\"][data-bitmask]'); if(!inp) return; tempMask=parseInt(inp.value)||0; dictRef=FLAGS[groupFor(id)]||{};
    if(dynamic){ buildDynamic(id); setTimeout(()=>{ ensureNodes(); wireDynamic(); buildOptions(); refreshCurrent(); },0); }
    else {
      ensureNodes();
      buildOptions();
      modal.classList.add('active');
      document.body.style.overflow='hidden';
      const titleEl=document.getElementById('bitmask-modal-title');
      if(titleEl) titleEl.textContent=translate('modal.title','Edit :target',{ target: id });
      refreshCurrent();
      modal.querySelector('.modal-panel')?.focus();
    }
  }
  function closeMask(){ if(dynamic){ if(window.Modal) Modal.hide('bitmask'); } else { modal.classList.remove('active'); document.body.style.overflow=''; } currentTarget=null; }
  function wireDynamic(){ const host=document.getElementById('modal-bitmask'); if(!host || host.__wired) return; host.__wired=true; ensureNodes();
    filterInput?.addEventListener('input',buildOptions);
    host.querySelectorAll('[data-mask-op]').forEach(btn=> btn.addEventListener('click',()=>{ const op=btn.getAttribute('data-mask-op'); if(op==='all'){ tempMask=0; Object.keys(dictRef).forEach(bit=> tempMask|=parseInt(bit)); } else if(op==='none'){ tempMask=0; } else if(op==='invert'){ let full=0; Object.keys(dictRef).forEach(bit=> full|=parseInt(bit)); tempMask=(~tempMask)&full; } buildOptions(); refreshCurrent(); }));
    host.querySelector('#bitmask-apply')?.addEventListener('click',()=>{ if(!currentTarget) return; const inp=document.querySelector('input[name="'+currentTarget+'\"][data-bitmask]'); if(inp){ inp.value=tempMask; updateLabel(currentTarget); } closeMask(); });
  }
  document.querySelectorAll('[data-open-mask]').forEach(btn=> btn.addEventListener('click',()=> openMask(btn.getAttribute('data-open-mask'))));
  if(!dynamic){
    ensureNodes();
    const placeholderText=translate('filter.placeholder','Filter keywords...');
    if(filterInput) filterInput.placeholder=placeholderText;
    const applyLabel=translate('actions.apply','Apply');
    modal.querySelector('#bitmask-apply')?.textContent=applyLabel;
    const tipEl=modal.querySelector('[data-bitmask-tip]');
    if(tipEl) tipEl.textContent=translate('help.toggle_tip','Click to toggle bits. Hold Shift and drag to multi-select.');
    const opLabels={
      all: translate('controls.select_all','Select all'),
      none: translate('controls.select_none','Clear all'),
      invert: translate('controls.select_invert','Invert')
    };
    modal.querySelectorAll('[data-mask-op]').forEach(btn=>{
      const op=btn.getAttribute('data-mask-op');
      if(op && opLabels[op]) btn.textContent=opLabels[op];
      btn.addEventListener('click',()=>{ const action=btn.getAttribute('data-mask-op'); if(action==='all'){ tempMask=0; Object.keys(dictRef).forEach(bit=> tempMask|=parseInt(bit)); } else if(action==='none'){ tempMask=0; } else if(action==='invert'){ let full=0; Object.keys(dictRef).forEach(bit=> full|=parseInt(bit)); tempMask=(~tempMask)&full; } buildOptions(); refreshCurrent(); });
    });
    modal.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click',closeMask));
    filterInput?.addEventListener('input',buildOptions);
    modal.querySelector('#bitmask-apply')?.addEventListener('click',()=>{ if(!currentTarget) return; const inp=document.querySelector('input[name="'+currentTarget+'\"][data-bitmask]'); if(inp){ inp.value=tempMask; updateLabel(currentTarget); } closeMask(); });
    document.addEventListener('keydown',e=>{ if(e.key==='Escape'&& modal.classList.contains('active')) closeMask(); });
  }
  document.querySelectorAll('input[data-bitmask]').forEach(inp=> updateLabel(inp.name));
  return {open:openMask,close:closeMask,updateLabel};
}

