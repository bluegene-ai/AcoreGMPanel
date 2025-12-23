/**
 * File: public/assets/js/modules/mass_mail.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - toast()
 *   - post()
 *   - bindAnnounce()
 *   - bindMassSend()
 *   - updateCond()
 *   - countTargets()
 *   - needConfirm()
 *   - buildSummary()
 *   - openConfirm()
 *   - closeConfirm()
 *   - onConfirmOk()
 *   - actuallySend()
 *   - disableBtn()
 *   - formatGold()
 *   - refreshLogs()
 *   - renderLogs()
 *   - esc()
 *   - short()
 *   - bindLogs()
 *   - updateBoostSummary()
 *   - bindBoost()
 *   - init()
 *   - qs()
 *   - qsa()
 *   - formatNumber()
 */

(function(){
  const BASE=(window.APP_BASE||'').replace(/\/$/,'');
  const apiBase= BASE + '/mass-mail';
  const csrf = window.__CSRF_TOKEN;
  const qs=(s,r=document)=>r.querySelector(s); const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));

  const panelLocale = window.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function' ? panelLocale.moduleLocale.bind(panelLocale) : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('mass_mail')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.mass_mail.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('mass_mail', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    if(typeof text === 'string' && text === `modules.mass_mail.${path}` && fallback){
      text = fallback;
    }
    if(typeof text === 'string' && replacements && typeof replacements === 'object'){
      Object.entries(replacements).forEach(([key,value])=>{
        const pattern = new RegExp(`:${key}(?![A-Za-z0-9_])`,'g');
        text = text.replace(pattern, String(value ?? ''));
      });
    }
    return text;
  }

  function toast(msg){ console.log('[mass]',msg); }

  const BOOST_CONFIG = {
    goldCopper: 500 * 10000,
    extra: [{ id: 21841, count: 3 }, { id: 23720, count: 1 }]
  };

  const formatNumber = n => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  async function post(path,data){
    const fd=new FormData(); if(data){ Object.entries(data).forEach(([k,v])=> fd.append(k,v)); }
    if(csrf) fd.append('_token',csrf);
    const url = apiBase + path;
    if(window.Panel && Panel.api){
      try{
        return await Panel.api.post('/mass-mail'+path,data||{});
      }catch(e){
        return {
          success:false,
          message: (e && e.message) ? e.message : translate('errors.network','Network error')
        };
      }
    }
    const res=await fetch(url,{method:'POST',body:fd}); let json=null;
    try{
      json=await res.json();
    }catch(e){
      return {
        success:false,
        message: (e && e.message) ? e.message : translate('errors.parse_failed','Failed to parse response')
      };
    }
    return json;
  }

  function bindAnnounce(){
    const form=qs('#massAnnounceForm');
    if(!form) return;
    form.addEventListener('submit',async e=>{
      e.preventDefault();
      const msg=form.message.value.trim();
      if(!msg){
        toast(translate('announce.validation.empty','Please enter an announcement message'));
        return;
      }
      disableBtn('#btnAnnounce',true);
      const res=await post('/api/announce',{message:msg});
      disableBtn('#btnAnnounce',false);
      toast(res.message || translate('feedback.done','Done'));
      if(res.success) form.reset();
      refreshLogs();
    });
  }

  function bindMassSend(){ const f=qs('#massSendForm'); if(!f) return; const actionSel=qs('#mmAction',f); const targetSel=qs('#mmTargetType',f); const goldInput=qs('#goldAmount',f); const preview=qs('#goldPreview',f);
    actionSel.addEventListener('change',()=> updateCond()); targetSel.addEventListener('change',()=> updateCond());
  if(goldInput){ goldInput.addEventListener('input',()=>{ const v=parseInt(goldInput.value||'0',10); preview.textContent=v? formatGold(v):translate('send.gold_preview_placeholder','—'); }); }

    // Items editor: visual rows -> hidden items string ("id:qty id:qty")
    let syncItemsToHidden = null;
    (function initItemsEditor(){
      const editor = qs('#mmItemsEditor', f);
      if(!editor) return;
      const body = qs('#mmItemsBody', editor);
      const hidden = qs('#mmItems', editor);
      const addBtn = qs('#mmItemsAdd', editor);
      if(!body || !hidden || !addBtn) return;

      const removeLabel = editor.dataset.removeLabel || 'Remove';

      const createRow = () => {
        const row = document.createElement('div');
        row.className = 'massmail-items__grid massmail-items__row';

        const id = document.createElement('input');
        id.type = 'number';
        id.min = '1';
        id.placeholder = 'ID';
        id.setAttribute('data-role', 'item-id');

        const qty = document.createElement('input');
        qty.type = 'number';
        qty.min = '1';
        qty.value = '1';
        qty.setAttribute('data-role', 'item-qty');

        const rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'btn btn-sm outline';
        rm.textContent = removeLabel;
        rm.setAttribute('data-role', 'item-remove');

        row.appendChild(id);
        row.appendChild(qty);
        row.appendChild(rm);
        body.appendChild(row);
      };

      const buildItemsString = () => {
        const rows = qsa('.massmail-items__row', editor);
        const pairs = [];
        rows.forEach(r => {
          const idEl = r.querySelector('[data-role="item-id"]');
          const qtyEl = r.querySelector('[data-role="item-qty"]');
          const id = parseInt(idEl?.value || '0', 10) || 0;
          const qty = parseInt(qtyEl?.value || '0', 10) || 0;
          if(id > 0 && qty > 0){
            pairs.push(id + ':' + qty);
          }
        });
        return pairs.join(' ');
      };

      const sync = () => {
        hidden.value = buildItemsString();
      };
      syncItemsToHidden = sync;

      body.addEventListener('click', (e) => {
        const t = e.target;
        if(!(t instanceof HTMLElement)) return;
        if(t.getAttribute('data-role') !== 'item-remove') return;
        const row = t.closest('.massmail-items__row');
        if(row){
          row.remove();
          if(!qsa('.massmail-items__row', editor).length){
            createRow();
          }
          sync();
        }
      });

      body.addEventListener('input', (e) => {
        const t = e.target;
        if(!(t instanceof HTMLElement)) return;
        const role = t.getAttribute('data-role');
        if(role === 'item-id' || role === 'item-qty'){
          sync();
        }
      });

      addBtn.addEventListener('click', () => {
        createRow();
        sync();
      });

      if(!qsa('.massmail-items__row', editor).length){
        createRow();
      }
      sync();
    })();

    function updateCond(){
      const action=actionSel.value;
      qsa('.massmail-cond',f).forEach(box=>{
        const forAct=box.getAttribute('data-for');
        const allowed = (forAct||'').split('|').map(s=>s.trim()).filter(Boolean);
        const shouldShow = allowed.includes(action);
        box.classList.toggle('active',shouldShow);
      });
      const customBox=qs('.massmail-custom',f);
      if(customBox){ customBox.classList.toggle('active',targetSel.value==='custom'); }
    }
    updateCond();
    f.addEventListener('submit', async e=>{ e.preventDefault(); const data={}; if(typeof syncItemsToHidden==='function') syncItemsToHidden(); new FormData(f).forEach((v,k)=> data[k]=v);

      if(await needConfirm(data,f)){
        pendingSendData=data; openConfirm(buildSummary(data,f)); return; }
      await actuallySend(data);
    });
  }


  let pendingSendData=null; let confirming=false;
  function countTargets(data,form){
    if(data.target_type==='online') return -1;
    if(data.target_type==='custom'){
      const raw=form.querySelector('[name="custom_char_list"]').value||'';
      const lines=raw.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
      return lines.length;
    }
    return 0;
  }
  async function needConfirm(data,form){
    const action=data.action;
    const amt=parseInt(data.amount||'0',10);
    const tcount=countTargets(data,form);
    const riskyAction=(action==='send_item' || action==='send_item_gold');
    const highGold=(action==='send_gold' || action==='send_item_gold') && amt>=100000;
    const largeItem=maxItemCount(data,form)>50;
    const largeBatch=tcount>0 && tcount>=300;
    const onlineTargets=tcount===-1;
    return riskyAction || highGold || largeItem || largeBatch || onlineTargets;
  }

  function parseItems(raw){
    const text = String(raw || '').trim();
    if(!text) return [];
    // Supports:
    // - lines: 123:2
    // - space/comma separated: 123:2 456:1
    const tokens = text.split(/\s+|,|;|\r?\n/).map(s=>s.trim()).filter(Boolean);
    const out=[];
    tokens.forEach(tok=>{
      const m = tok.match(/^([0-9]+)\s*:\s*([0-9]+)$/);
      if(!m) return;
      const id = parseInt(m[1],10) || 0;
      const count = parseInt(m[2],10) || 0;
      if(id>0 && count>0) out.push({id,count});
    });
    return out;
  }

  function maxItemCount(data,form){
    const raw = (data.items !== undefined)
      ? data.items
      : (form.querySelector('[name="items"]')?.value || '');
    const items=parseItems(raw);
    let max=0;
    items.forEach(it=>{ if(it.count>max) max=it.count; });
    return max;
  }

  function summarizeItems(data,form){
    const raw = (data.items !== undefined)
      ? data.items
      : (form.querySelector('[name="items"]')?.value || '');
    const items=parseItems(raw);
    if(!items.length) return '';
    return items.map(it=>`${it.id}×${it.count}`).join(', ');
  }

  function buildSummary(data,form){
    const tcount=countTargets(data,form);
    const heading=translate('confirm.heading','You are about to execute <strong>:action</strong>',{ action: esc(data.action||'') });
    const lines=[];
    lines.push(translate('confirm.subject','Subject: :value',{ value: esc(data.subject||'') }));
    if(data.action==='send_item' || data.action==='send_item_gold'){
      const sum = summarizeItems(data,form);
      lines.push(translate('confirm.items','Items: :items',{ items: esc(sum || '') }));
    }
    if(data.action==='send_gold' || data.action==='send_item_gold'){
      const g=parseInt(data.amount||'0',10);
      lines.push(translate('confirm.gold','Gold (copper): :amount',{ amount:g }));
    }
    lines.push(translate('confirm.target_type','Target type: :value',{ value: esc(data.target_type||'') }));
    if(tcount>0){
      lines.push(translate('confirm.custom_count','Custom characters: :count',{ count:tcount }));
    }
    if(tcount===-1){
      lines.push(translate('confirm.online','Online characters: real-time count (fetched on send)'));
    }
    const footer=translate('confirm.footer','Batch sending (size = 200) is enabled. Please double-check before continuing.');
    return `<p class="mb-2">${heading}</p>`+
      `<ul class="summary">${lines.map(line=>`<li>${line}</li>`).join('')}</ul>`+
      `<p class="muted small">${footer}</p>`;
  }
  function openConfirm(summaryHtml){ const modal=qs('#mmConfirmModal'); if(!modal) return; modal.style.removeProperty('display'); modal.classList.add('active'); qs('#mmConfirmBody',modal).innerHTML=summaryHtml; const input=qs('#mmConfirmInput',modal); const ok=qs('#mmConfirmOk',modal); input.value=''; ok.disabled=true; const closeEls=qsa('[data-close]',modal); closeEls.forEach(el=> el.addEventListener('click',closeConfirm)); input.addEventListener('input',()=>{ ok.disabled = input.value.trim().toUpperCase()!=='CONFIRM'; }); ok.addEventListener('click',onConfirmOk,{once:true}); input.focus(); }
  function closeConfirm(){ const modal=qs('#mmConfirmModal'); if(!modal) return; modal.classList.remove('active'); modal.style.removeProperty('display'); const ok=qs('#mmConfirmOk',modal); ok.replaceWith(ok.cloneNode(true)); const input=qs('#mmConfirmInput',modal); if(input){ const newInput=input.cloneNode(true); input.replaceWith(newInput); }
    qsa('[data-close]',modal).forEach(btn=> btn.replaceWith(btn.cloneNode(true))); pendingSendData=null; }
  async function onConfirmOk(){ if(confirming) return; confirming=true; const data=pendingSendData; pendingSendData=null; closeConfirm(); if(data){ await actuallySend(data); } confirming=false; }
  async function actuallySend(data){
    disableBtn('#btnMassSend',true,translate('status.sending','Sending…'));
    const res=await post('/api/send',data);
    disableBtn('#btnMassSend',false);
    toast(res.message || translate('feedback.done','Done'));
    refreshLogs();
  }

  function disableBtn(sel,dis,text){ const b=qs(sel); if(!b) return; if(text){ if(!b.dataset.orig) b.dataset.orig=b.textContent; if(dis) b.textContent=text; }
    if(!dis && b.dataset.orig){ b.textContent=b.dataset.orig; }
    b.disabled=dis;
  }
  function formatGold(c){
    const g=Math.floor(c/10000);
    const rem=c%10000;
    const s=Math.floor(rem/100);
    const b=rem%100;
    const parts=[];
    if(g>0) parts.push(`${g} ${translate('gold.units.gold','Gold')}`);
    if(s>0) parts.push(`${s} ${translate('gold.units.silver','Silver')}`);
    if(b>0||parts.length===0) parts.push(`${b} ${translate('gold.units.copper','Copper')}`);
    return parts.join(' ');
  }

  async function refreshLogs(){ const limit=qs('#logLimit')?.value||30; const res=await post('/api/logs',{limit}); if(!res.success) return; renderLogs(res.logs||[]); }
  function renderLogs(rows){
    const tb=qs('#massMailLogTable tbody');
    if(!tb) return;
    if(!rows.length){
      tb.innerHTML=`<tr><td colspan="7" class="text-center muted">${esc(translate('logs.empty','No logs yet'))}</td></tr>`;
      return;
    }
    const nameSeparator=translate('logs.item_name_separator',' - ');
    const qtyPrefix=translate('logs.item_quantity_prefix',' ×');
    const errorPrefix=translate('logs.error_prefix','Error: ');
    const itemsLabel=translate('logs.items_label','Items: :value');
    tb.innerHTML=rows.map(r=>{
      const ok=parseInt(r.success,10)===1;
      let details=`<div class="strong">${esc(r.subject||'')}</div>`;
      if(r.items){
        details+=`<div class="small muted">${esc(itemsLabel.replace(':value', String(r.items)))}</div>`;
      } else if(r.item_id){
        const itemLabel=translate('logs.item_label','Item: #:id',{ id:r.item_id });
        const namePart=r.item_name ? `${nameSeparator}${esc(r.item_name)}` : '';
        const qtyPart=r.quantity ? `${qtyPrefix}${r.quantity}` : '';
        details+=`<div class="small muted">${esc(itemLabel)}${namePart}${qtyPart}</div>`;
      }
      if(r.amount){
        const amount=parseInt(r.amount,10);
        const goldLabel=translate('logs.gold_label','Gold: :value',{ value: formatGold(Number.isNaN(amount)?0:amount) });
        details+=`<div class="small muted">${esc(goldLabel)}</div>`;
      }
      if(!ok && r.sample_errors){
        details+=`<div class="small text-danger" title="${esc(r.sample_errors)}">${esc(errorPrefix + short(r.sample_errors,60))}</div>`;
      }
      let rec='-';
      if(r.recipients){
        const d=r.recipients;
        rec=esc(short(d,40));
        if(d.length>40) rec=`<span title="${esc(d)}">${rec}</span>`;
      }
      return `<tr class="${ok?'log-ok':'log-fail'}">`+
        `<td>${esc((r.created_at||'').slice(0,19))}</td>`+
        `<td>${esc(r.action||'')}</td>`+
        `<td>${details}</td>`+
        `<td>${r.targets||0}</td>`+
        `<td>${r.success_count||0}/${r.fail_count||0}</td>`+
        `<td>${ok?'✔':'✖'}</td>`+
        `<td>${rec}</td>`+
      `</tr>`;
    }).join('');
  }
  function esc(s){ return (s+'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function short(s,len){ if(s.length<=len) return s; return s.slice(0,len)+'…'; }

  function bindLogs(){ qs('#btnLogsRefresh')?.addEventListener('click',()=> refreshLogs()); qs('#logLimit')?.addEventListener('change',()=> refreshLogs()); }

  function updateBoostSummary(){
    const summary=qs('#boostSummary'); if(!summary) return;
    const bagCount=BOOST_CONFIG.extra.find(it=>it.id===21841)?.count||3;
    const mountCount=BOOST_CONFIG.extra.find(it=>it.id===23720)?.count||1;
    summary.value=[
      translate('boost.summary.gold','500 gold (:copper copper)',{ copper: formatNumber(BOOST_CONFIG.goldCopper) }),
      translate('boost.summary.bag','Netherweave Bag ×:count (#21841)',{ count: bagCount }),
      translate('boost.summary.mount','Sea Turtle ×:count (#23720)',{ count: mountCount }),
      translate('boost.summary.set','Class-specific Tier 2 set (auto-detected)')
    ].join('\n');
  }

  function bindBoost(){
    const form=qs('#massBoostForm'); if(!form) return;
    updateBoostSummary();
    form.addEventListener('submit', async e=>{
      e.preventDefault();
      const name=form.character?.value?.trim();
      const levelRaw=form.level?.value || '';
      if(!name){ alert(translate('boost.validation.name','Please enter a character name')); return; }
      const level=parseInt(levelRaw,10);
      if(!level || ![60,70,80].includes(level)){ alert(translate('boost.validation.level','Please choose a target level')); return; }
      disableBtn('#btnBoostExecute',true,translate('boost.status.executing','Executing…'));
      try{
        const payload={ character:name, level:String(level) };
        const res=await post('/api/boost',payload);
        alert(res.message || translate('feedback.done','Done'));
        if(res.success){ form.reset(); updateBoostSummary(); }
      }catch(err){ alert(translate('errors.request_failed_retry','Request failed, please try again later')); }
      disableBtn('#btnBoostExecute',false);
    });
  }

  function init(){
    bindAnnounce();
    bindMassSend();
    bindLogs();
    bindBoost();
    const confirmModal=qs('#mmConfirmModal');
    if(confirmModal){ confirmModal.addEventListener('click',e=>{ if(e.target===confirmModal) closeConfirm(); }); }
    refreshLogs();
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();

