/**
 * File: public/assets/js/modules/bitmask.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - buildPanel()
 *   - positionPanel()
 *   - close()
 *   - toggleBit()
 *   - applyBits()
 */

(function(){
  if(!document.body) return;
  const panelLocale = window.Panel || {};
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

  function buildPanel(input){
    const name=input.getAttribute('data-bitmask');
    const wrap=document.createElement('div'); wrap.className='bitmask-popup';
    wrap.style.cssText='position:absolute;z-index:2200;background:#18242c;border:1px solid #2b3a44;padding:8px;box-shadow:0 4px 12px rgba(0,0,0,.4);border-radius:6px;min-width:320px;';
    const header=document.createElement('div'); header.style.cssText='display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:13px;color:#9fb2bf';
    const title=document.createElement('span');
    title.textContent=translate('popup.title','Bitmask: :name',{ name: name || '' });
    const closeBtn=document.createElement('button');
    closeBtn.type='button';
    closeBtn.className='btn-sm btn outline';
    closeBtn.setAttribute('data-close','');
    closeBtn.textContent=translate('actions.close','Close');
    header.appendChild(title);
    header.appendChild(closeBtn);
    const grid=document.createElement('div'); grid.style.cssText='display:grid;grid-template-columns:repeat(4,1fr);gap:4px;';
    const current=parseInt(input.value||'0',10)||0;
    for(let b=0;b<32;b++){
      const bitVal = 1<<b; const active=(current & bitVal)!==0;
      const cell=document.createElement('button'); cell.type='button'; cell.className='bit-cell';
      cell.textContent=b; cell.dataset.bit=b; cell.style.cssText='padding:4px 0;font-size:12px;border:1px solid #2e4450;background:'+(active?'#1b6b3d':'#23323c')+';color:'+(active?'#d6ffe8':'#9db1bf')+';border-radius:4px;cursor:pointer;';
      if(active) cell.setAttribute('data-active','1');
      grid.appendChild(cell);
    }
    const footer=document.createElement('div'); footer.style.cssText='margin-top:6px;display:flex;justify-content:space-between;align-items:center;font-size:12px';
    const tip=document.createElement('div'); tip.className='muted';
    tip.textContent=translate('help.toggle_tip','Click to toggle bits. Hold Shift and drag to multi-select.');
    const actions=document.createElement('div');
    const clearBtn=document.createElement('button');
    clearBtn.type='button';
    clearBtn.className='btn-sm btn';
    clearBtn.setAttribute('data-act','clear');
    clearBtn.textContent=translate('actions.clear','Clear');
    const applyBtn=document.createElement('button');
    applyBtn.type='button';
    applyBtn.className='btn-sm btn success';
    applyBtn.setAttribute('data-act','apply');
    applyBtn.textContent=translate('actions.apply','Apply');
    actions.appendChild(clearBtn);
    actions.appendChild(document.createTextNode(' '));
    actions.appendChild(applyBtn);
    footer.appendChild(tip);
    footer.appendChild(actions);
    wrap.appendChild(header); wrap.appendChild(grid); wrap.appendChild(footer);
    document.body.appendChild(wrap);
    positionPanel(input,wrap);
    return wrap;
  }
  function positionPanel(input,panel){
    const rect=input.getBoundingClientRect(); panel.style.top=(window.scrollY+rect.bottom+4)+'px'; panel.style.left=(window.scrollX+rect.left)+'px';
  }
  let activePanel=null; let activeInput=null; let drag=false; let dragState=null;
  document.addEventListener('click',e=>{
    if(e.target.matches('input[data-bitmask]')){
      const inp=e.target; if(activeInput===inp){ close(); return; }
      close(); activeInput=inp; activePanel=buildPanel(inp); return;
    }
    if(activePanel && !activePanel.contains(e.target)){ if(!e.target.matches('input[data-bitmask]')) close(); }
  });
  function close(){ if(activePanel){ activePanel.remove(); } activePanel=null; activeInput=null; }

  document.addEventListener('click',e=>{
    if(!activePanel) return;
    if(e.target.getAttribute('data-close')!==null){ close(); return; }
    const bitBtn=e.target.closest('.bit-cell'); if(bitBtn){ toggleBit(bitBtn,!bitBtn.hasAttribute('data-active')); }
    const act=e.target.getAttribute('data-act'); if(act==='clear'){ [...activePanel.querySelectorAll('.bit-cell[data-active]')].forEach(c=>toggleBit(c,false)); }
    else if(act==='apply'){ applyBits(); close(); }
  });

  document.addEventListener('mousedown',e=>{
    if(!activePanel) return; const bitBtn=e.target.closest('.bit-cell'); if(bitBtn){ drag=true; dragState=!bitBtn.hasAttribute('data-active'); toggleBit(bitBtn,dragState); e.preventDefault(); }});
  document.addEventListener('mouseover',e=>{ if(!drag||!activePanel) return; const bitBtn=e.target.closest('.bit-cell'); if(bitBtn) toggleBit(bitBtn,dragState); });
  document.addEventListener('mouseup',()=>{ drag=false; dragState=null; });
  window.addEventListener('scroll',()=>{ if(activePanel && activeInput) positionPanel(activeInput,activePanel); });

  function toggleBit(btn,on){
    if(on){ btn.setAttribute('data-active','1'); btn.style.background='#1b6b3d'; btn.style.color='#d6ffe8'; }
    else { btn.removeAttribute('data-active'); btn.style.background='#23323c'; btn.style.color='#9db1bf'; }
  }
  function applyBits(){ if(!activePanel||!activeInput) return; let val=0; activePanel.querySelectorAll('.bit-cell[data-active]').forEach(c=>{ const b=parseInt(c.dataset.bit,10); if(b>=0 && b<32) val|=(1<<b); }); activeInput.value=String(val); activeInput.dispatchEvent(new Event('input',{bubbles:true})); }

  console.log('[bitmask] module ready');
})();
