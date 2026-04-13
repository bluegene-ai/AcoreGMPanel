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
    const header=document.createElement('div'); header.className='bitmask-popup__header';
    const title=document.createElement('span');
    title.textContent=translate('popup.title','Bitmask: :name',{ name: name || '' });
    const closeBtn=document.createElement('button');
    closeBtn.type='button';
    closeBtn.className='btn-sm btn outline';
    closeBtn.setAttribute('data-close','');
    closeBtn.textContent=translate('actions.close','Close');
    header.appendChild(title);
    header.appendChild(closeBtn);
    const grid=document.createElement('div'); grid.className='bitmask-popup__grid';
    const current=parseInt(input.value||'0',10)||0;
    for(let b=0;b<32;b++){
      const bitVal = 1<<b; const active=(current & bitVal)!==0;
      const cell=document.createElement('button'); cell.type='button'; cell.className='bit-cell';
      cell.textContent=b; cell.dataset.bit=b;
      if(active) cell.setAttribute('data-active','1');
      grid.appendChild(cell);
    }
    const footer=document.createElement('div'); footer.className='bitmask-popup__footer';
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
    const anchor = input.closest('.bitmask-anchor') || input.parentElement || input;
    anchor.classList.add('bitmask-anchor');
    anchor.appendChild(wrap);
    return wrap;
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

  function toggleBit(btn,on){
    if(on) btn.setAttribute('data-active','1');
    else btn.removeAttribute('data-active');
  }
  function applyBits(){ if(!activePanel||!activeInput) return; let val=0; activePanel.querySelectorAll('.bit-cell[data-active]').forEach(c=>{ const b=parseInt(c.dataset.bit,10); if(b>=0 && b<32) val|=(1<<b); }); activeInput.value=String(val); activeInput.dispatchEvent(new Event('input',{bubbles:true})); }

  console.log('[bitmask] module ready');
})();
