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
 *   - applyLogFilter()
 *   - init()
 *   - qs()
 *   - qsa()
 *   - formatNumber()
 */

(function(){

  /** Base path of the panel install: window.APP_BASE is never set by the server. */
  const resolveBasePath = () => ((window.Panel && window.Panel.basePath && window.Panel.basePath()) || (document.body && document.body.dataset && document.body.dataset.appBase) || '').replace(/\/$/, '');
  const BASE=(resolveBasePath()||'').replace(/\/$/,'');
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

  /**
   * 统一的界面反馈：以前这里只是 console.log，用户看不到任何结果。
   * 优先用面板的 feedback 组件，缺失时退化为自绘的提示条。
   */
  function toast(msg, type){
    const text = String(msg == null ? '' : msg);
    if(!text) return;
    const kind = (type === 'error' || type === 'success' || type === 'info') ? type : 'info';
    const host = qs('#mmFeedback');

    if(host && window.Panel && Panel.feedback && typeof Panel.feedback.show === 'function'){
      Panel.feedback.show(host, kind, text, { duration: kind === 'error' ? 6000 : 4000 });
      return;
    }
    if(host){
      host.hidden = false;
      host.textContent = text;
      host.classList.remove('panel-flash--success','panel-flash--error','panel-flash--info','is-visible');
      host.classList.add('panel-flash--' + kind, 'is-visible');
      window.clearTimeout(host.__mmTimer);
      host.__mmTimer = window.setTimeout(() => { host.hidden = true; host.classList.remove('is-visible'); }, kind === 'error' ? 6000 : 4000);
    }
  }

  const formatNumber = n => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  async function post(path,data){
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
    const fd=new FormData(); if(data){ Object.entries(data).forEach(([k,v])=> fd.append(k,v)); }
    if(csrf) fd.append('_token',csrf);
    const url = apiBase + path;
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
      toast(res.message || translate('feedback.done','Done'), res && res.success ? 'success' : 'error');
      if(res.success) form.reset();
      refreshLogs();
    });
  }

  function bindMassSend(){ const f=qs('#massSendForm'); if(!f) return; const actionSel=qs('#mmAction',f); const targetSel=qs('#mmTargetType',f); const goldInput=qs('#goldAmount',f); const preview=qs('#goldPreview',f);
    actionSel.addEventListener('change',()=> updateCond()); targetSel.addEventListener('change',()=> updateCond());
  if(goldInput){ goldInput.addEventListener('input',()=>{ const v=parseInt(goldInput.value||'0',10); preview.textContent=v? formatGold(v):translate('send.gold_preview_placeholder','—'); }); }

    // Items editor: visual rows -> hidden items string ("id:qty id:qty")
    // 同时按 ID 解析物品名（world 库 / DBC），并在行内提示重复与缺失
    let syncItemsToHidden = null;
    let itemsEditorApi = null;

    /**
     * 确认弹窗里用"物品名 ×数量"来展示，比一行裸 ID 更容易核对。
     * 由物品编辑器的 updateSummary() 实时同步过来。
     */
    let confirmItemsMirror = '';

    (function initItemsEditor(){
      const editor = qs('#mmItemsEditor', f);
      if(!editor) return;
      const body = qs('#mmItemsBody', editor);
      const hidden = qs('#mmItems', editor);
      const addBtn = qs('#mmItemsAdd', editor);
      const clearBtn = qs('#mmItemsClear', editor);
      const summaryBox = qs('#mmItemsSummary');
      const summaryValue = qs('#mmItemsSummaryValue');
      if(!body || !hidden || !addBtn) return;

      const removeLabel = editor.dataset.removeLabel || 'Remove';
      const loadingLabel = editor.dataset.nameLoading || 'Loading…';
      const unknownLabel = editor.dataset.nameUnknown || 'Item not found';
      const emptyLabel = editor.dataset.nameEmpty || '';
      const duplicateLabel = editor.dataset.nameDuplicate || 'Duplicate item';

      /** 已解析的物品名缓存：同一 ID 不重复请求 */
      const nameCache = new Map();
      const namePending = new Set();
      let resolveTimer = null;

    const rowsOf = () => qsa('.massmail-items__row', editor);
    const rowId = (row) => parseInt(row.querySelector('[data-role="item-id"]')?.value || '0', 10) || 0;
    const rowQty = (row) => parseInt(row.querySelector('[data-role="item-qty"]')?.value || '0', 10) || 0;

    function updateConfirmSummaryMirror(info){
      confirmItemsMirror = info && info.text ? info.text : '';
    }

      function createRow(id, qty){
        const row = document.createElement('div');
        row.className = 'massmail-items__grid massmail-items__row';

        const idInput = document.createElement('input');
        idInput.type = 'number';
        idInput.min = '1';
        idInput.placeholder = 'ID';
        idInput.setAttribute('data-role', 'item-id');
        if(id) idInput.value = String(id);

        const nameEl = document.createElement('div');
        nameEl.className = 'mm-item-name';
        nameEl.setAttribute('data-role', 'item-name');
        nameEl.textContent = emptyLabel;

        const qtyWrap = document.createElement('div');
        qtyWrap.className = 'mm-item-qty-wrap';

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.min = '1';
        qtyInput.value = String(qty || 1);
        qtyInput.setAttribute('data-role', 'item-qty');
        qtyInput.setAttribute('aria-label', translate('send.quantity_label', 'Quantity'));

        const rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'btn btn-sm outline mm-item-remove';
        rm.textContent = removeLabel;
        rm.setAttribute('data-role', 'item-remove');

        qtyWrap.appendChild(qtyInput);
        qtyWrap.appendChild(rm);

        row.appendChild(idInput);
        row.appendChild(nameEl);
        row.appendChild(qtyWrap);
        body.appendChild(row);

        return row;
      }

      const buildItemsString = () => {
        const pairs = [];
        rowsOf().forEach(r => {
          const id = rowId(r);
          const qty = rowQty(r);
          if(id > 0 && qty > 0){
            pairs.push(id + ':' + qty);
          }
        });
        return pairs.join(' ');
      };

      function describe(){
        const items = parseItems(hidden.value);
        if(!items.length){ return { text: '', count: 0, total: 0 }; }
        const parts = items.map(it => {
          const name = nameCache.get(it.id);
          return (name ? name : ('#' + it.id)) + ' ×' + it.count;
        });
        const total = items.reduce((sum, it) => sum + it.count, 0);
        return { text: parts.join('、'), count: items.length, total: total };
      }

      function updateSummary(){
        const info = describe();
        if(summaryBox && summaryValue){
          if(info.text){
            summaryBox.hidden = false;
            summaryValue.textContent = info.text;
          } else {
            summaryBox.hidden = true;
            summaryValue.textContent = '';
          }
        }
        updateConfirmSummaryMirror(info);
      }

      function markDuplicates(){
        const seen = new Map();
        rowsOf().forEach(row => {
          const id = rowId(row);
          if(id > 0){ seen.set(id, (seen.get(id) || 0) + 1); }
        });
        rowsOf().forEach(row => {
          const id = rowId(row);
          const nameEl = row.querySelector('[data-role="item-name"]');
          const dup = id > 0 && (seen.get(id) || 0) > 1;
          row.classList.toggle('is-duplicate', dup);
          if(dup && nameEl){
            nameEl.classList.add('is-duplicate');
            nameEl.title = duplicateLabel;
          } else if(nameEl){
            nameEl.classList.remove('is-duplicate');
            nameEl.removeAttribute('title');
          }
        });
        return seen;
      }

      function paintName(id, row){
        const nameEl = row.querySelector('[data-role="item-name"]');
        if(!nameEl) return;
        if(id <= 0){
          nameEl.textContent = emptyLabel;
          nameEl.classList.remove('is-missing','is-pending');
          return;
        }
        const cached = nameCache.get(id);
        if(cached){
          nameEl.textContent = cached;
          nameEl.classList.remove('is-missing','is-pending');
          return;
        }
        if(nameCache.has(id) && !cached){
          nameEl.textContent = unknownLabel;
          nameEl.classList.add('is-missing');
          nameEl.classList.remove('is-pending');
          return;
        }
        if(namePending.has(id)){
          nameEl.textContent = loadingLabel;
          nameEl.classList.add('is-pending');
          nameEl.classList.remove('is-missing');
          return;
        }
        nameEl.textContent = emptyLabel;
        nameEl.classList.remove('is-missing','is-pending');
      }

      function repaintNames(){
        rowsOf().forEach(row => paintName(rowId(row), row));
      }

      async function resolveNames(){
        const ids = [];
        rowsOf().forEach(row => {
          const id = rowId(row);
          if(id > 0 && !nameCache.has(id) && !namePending.has(id)){
            ids.push(id);
          }
        });
        if(!ids.length) return;

        const unique = Array.from(new Set(ids)).slice(0, 200);
        unique.forEach(id => namePending.add(id));
        repaintNames();

        const res = await post('/api/items', { ids: unique.join(',') });
        unique.forEach(id => namePending.delete(id));

        if(res && res.success && res.names){
          Object.keys(res.names).forEach(key => {
            nameCache.set(parseInt(key, 10) || 0, String(res.names[key]));
          });
          // 请求成功但没有返回名字的 ID 记为"未找到"，避免无限重试
          unique.forEach(id => {
            if(!nameCache.has(id)){ nameCache.set(id, null); }
          });
        } else {
          unique.forEach(id => nameCache.delete(id));
          if(res && res.message){ toast(res.message, 'error'); }
        }

        repaintNames();
        updateSummary();
      }

      function scheduleResolve(){
        window.clearTimeout(resolveTimer);
        resolveTimer = window.setTimeout(resolveNames, 350);
      }

      function sync(){
        hidden.value = buildItemsString();
        markDuplicates();
        updateSummary();
      }
      syncItemsToHidden = sync;

      body.addEventListener('click', (e) => {
        const t = e.target;
        if(!(t instanceof HTMLElement)) return;
        if(t.getAttribute('data-role') !== 'item-remove') return;
        const row = t.closest('.massmail-items__row');
        if(row){
          row.remove();
          if(!rowsOf().length){
            createRow();
          }
          sync();
        }
      });

      body.addEventListener('input', (e) => {
        const t = e.target;
        if(!(t instanceof HTMLElement)) return;
        const role = t.getAttribute('data-role');
        if(role === 'item-id'){
          const row = t.closest('.massmail-items__row');
          if(row){
            const id = rowId(row);
            // ID 变了就重新解析：清掉该行的旧状态
            const nameEl = row.querySelector('[data-role="item-name"]');
            if(nameEl){ nameEl.textContent = emptyLabel; nameEl.classList.remove('is-missing','is-pending'); }
            if(id > 0) scheduleResolve();
          }
          sync();
        } else if(role === 'item-qty'){
          sync();
        }
      });

      addBtn.addEventListener('click', () => {
        createRow();
        sync();
      });

      if(clearBtn){
        clearBtn.addEventListener('click', () => {
          body.innerHTML = '';
          createRow();
          sync();
        });
      }

      if(!rowsOf().length){
        createRow();
      }
      sync();

      itemsEditorApi = { sync: sync, repaint: repaintNames, resolve: resolveNames };
    })();

    // 收件人预览：在线 = 实时查询人数；自定义 = 本地按行计数 + 服务端确认
    const recipientsCountEl = qs('#mmRecipientsCount');
    const recipientsDetailEl = qs('#mmRecipientsDetail');
    const recipientsRefreshBtn = qs('#mmRecipientsRefresh');
    const customListInput = qs('#mmCustomList', f);
    const customCountEl = qs('#mmCustomCount');

    function localCustomCount(){
      if(!customListInput) return 0;
      return (customListInput.value || '')
        .split(/\r?\n/)
        .map(s => s.trim())
        .filter(Boolean)
        .length;
    }

    function paintRecipients(count, detail, tone){
      if(recipientsCountEl){
        recipientsCountEl.textContent = count === null ? '—' : formatNumber(count);
        recipientsCountEl.classList.remove('is-ok','is-warn','is-over');
        if(tone){ recipientsCountEl.classList.add(tone); }
      }
      if(recipientsDetailEl && detail !== undefined){
        recipientsDetailEl.textContent = detail;
      }
    }

    async function refreshRecipients(options){
      const opts = options || {};
      const type = targetSel.value === 'custom' ? 'custom' : 'online';

      if(type === 'custom' && !opts.authoritative){
        const count = localCustomCount();
        paintRecipients(count, translate('send.recipients_custom_detail', 'Custom list (:count lines)', { count: count }));
        if(customCountEl){
          customCountEl.textContent = translate('send.custom_count', 'Parsed characters: :count', { count: count });
        }
        return count;
      }

      paintRecipients(null, translate('send.recipients_loading', 'Counting…'));
      const payload = { target_type: type };
      if(type === 'custom'){ payload.custom_char_list = customListInput ? customListInput.value : ''; }

      const res = await post('/api/targets', payload);
      if(!res || !res.success){
        paintRecipients(null, translate('send.recipients_failed', 'Count failed'));
        if(res && res.message){ toast(res.message, 'error'); }
        return null;
      }

      const count = parseInt(res.count, 10) || 0;
      const limit = parseInt(res.limit, 10) || 0;
      const sample = Array.isArray(res.sample) ? res.sample : [];
      const tone = (limit > 0 && count > limit) ? 'is-over' : (count === 0 ? 'is-warn' : 'is-ok');
      const detail = count === 0
        ? translate('send.recipients_empty', 'No recipients found')
        : (sample.length ? sample.slice(0, 3).join(', ') + (count > 3 ? ' …' : '') : '');

      paintRecipients(count, detail, tone);

      if(limit > 0 && count > limit){
        toast(translate('send.recipients_over_limit', 'Recipients (:count) exceed the batch limit (:limit)', { count: count, limit: limit }), 'error');
      }
      if(customCountEl && type === 'custom'){
        customCountEl.textContent = translate('send.custom_count', 'Parsed characters: :count', { count: count });
      }
      return count;
    }

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
      refreshRecipients();
    }
    updateCond();

    if(recipientsRefreshBtn){
      recipientsRefreshBtn.addEventListener('click', () => { refreshRecipients({ authoritative: true }); });
    }
    if(customListInput){
      let customTimer = null;
      customListInput.addEventListener('input', () => {
        window.clearTimeout(customTimer);
        customTimer = window.setTimeout(() => { refreshRecipients({ authoritative: true }); }, 500);
      });
    }

    f.addEventListener('submit', async e=>{ e.preventDefault(); const data={}; if(typeof syncItemsToHidden==='function') syncItemsToHidden(); new FormData(f).forEach((v,k)=> data[k]=v);

      const invalid = validateSendForm(data, f);
      if(invalid){ toast(invalid, 'error'); return; }

      if(await needConfirm(data,f)){
        pendingSendData=data; openConfirm(buildSummary(data,f)); return; }
      await actuallySend(data);
    });

    /** 提交前的本地校验：给出可操作的中文提示，而不是让服务端抛一句错误 */
    function validateSendForm(data, form){
      const action = data.action;
      if(!action){ return translate('send.validation.action', 'Please choose an action'); }

      const subject = String(data.subject || '').trim();
      if(!subject){ return translate('send.validation.subject', 'Please enter a subject'); }

      if(action === 'send_item' || action === 'send_item_gold'){
        const items = parseItems(data.items);
        if(!items.length){
          return translate('send.validation.items', 'Please add at least one item (ID:quantity)');
        }
        const dupes = items.map(it => it.id).filter((id, index, arr) => arr.indexOf(id) !== index);
        if(dupes.length){
          return translate('send.validation.duplicate_item', 'Item #:id is listed twice', { id: dupes[0] });
        }
      }

      if(action === 'send_gold' || action === 'send_item_gold'){
        const amount = parseInt(data.amount || '0', 10) || 0;
        if(amount <= 0){ return translate('send.validation.gold', 'Please enter a gold amount'); }
      }

      if(data.target_type === 'custom'){
        const lines = String(data.custom_char_list || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        if(!lines.length){ return translate('send.validation.custom_empty', 'Please enter the character list'); }
      }

      return null;
    }
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
    // 编辑器已经解析出名称时优先用它，未解析则退回 ID
    if(confirmItemsMirror){ return confirmItemsMirror; }
    return items.map(it=>`${it.id}×${it.count}`).join(', ');
  }

  function buildSummary(data,form){
    const tcount=countTargets(data,form);
    const actionLabel = (qs('#mmAction option:checked')?.textContent || data.action || '').trim();
    const targetLabel = (qs('#mmTargetType option:checked')?.textContent || data.target_type || '').trim();
    const heading=translate('confirm.heading','You are about to execute <strong>:action</strong>',{ action: esc(actionLabel) });
    const lines=[];
    lines.push(translate('confirm.subject','Subject: :value',{ value: esc(data.subject||'') }));
    if(data.action==='send_item' || data.action==='send_item_gold'){
      const sum = summarizeItems(data,form);
      lines.push(translate('confirm.items','Items: :items',{ items: esc(sum || '') }));
    }
    if(data.action==='send_gold' || data.action==='send_item_gold'){
      const g=parseInt(data.amount||'0',10);
      lines.push(translate('confirm.gold','Gold: :amount',{ amount: formatGold(Number.isNaN(g)?0:g) }));
    }
    lines.push(translate('confirm.target_type','Target type: :value',{ value: esc(targetLabel) }));
    if(tcount>0){
      lines.push(translate('confirm.custom_count','Custom characters: :count',{ count:formatNumber(tcount) }));
    }
    if(tcount===-1){
      const shown = (recipientsCountEl && recipientsCountEl.textContent) ? recipientsCountEl.textContent.trim() : '';
      lines.push(shown && shown !== '—'
        ? translate('confirm.online_count','Online characters: :count',{ count: shown })
        : translate('confirm.online','Online characters: real-time count (fetched on send)'));
    }
    const footer=translate('confirm.footer','Batch sending (size = 200) is enabled. Please double-check before continuing.');
    return `<p class="mb-2">${heading}</p>`+
      `<ul class="summary">${lines.map(line=>`<li>${line}</li>`).join('')}</ul>`+
      `<p class="muted small">${footer}</p>`;
  }
  function openConfirm(summaryHtml){ const modal=qs('#mmConfirmModal'); if(!modal) return; modal.classList.add('active'); document.body.classList.add('modal-open'); qs('#mmConfirmBody',modal).innerHTML=summaryHtml; const input=qs('#mmConfirmInput',modal); const ok=qs('#mmConfirmOk',modal); input.value=''; ok.disabled=true; const closeEls=qsa('[data-close]',modal); closeEls.forEach(el=> el.addEventListener('click',closeConfirm)); input.addEventListener('input',()=>{ ok.disabled = input.value.trim().toUpperCase()!=='CONFIRM'; }); ok.addEventListener('click',onConfirmOk,{once:true}); input.focus(); }
  function closeConfirm(){ const modal=qs('#mmConfirmModal'); if(!modal) return; modal.classList.remove('active'); if(!document.querySelector('.modal-backdrop.active')) document.body.classList.remove('modal-open'); const ok=qs('#mmConfirmOk',modal); ok.replaceWith(ok.cloneNode(true)); const input=qs('#mmConfirmInput',modal); if(input){ const newInput=input.cloneNode(true); input.replaceWith(newInput); }
    qsa('[data-close]',modal).forEach(btn=> btn.replaceWith(btn.cloneNode(true))); pendingSendData=null; }
  async function onConfirmOk(){ if(confirming) return; confirming=true; const data=pendingSendData; pendingSendData=null; closeConfirm(); if(data){ await actuallySend(data); } confirming=false; }
  async function actuallySend(data){
    disableBtn('#btnMassSend',true,translate('status.sending','Sending…'));
    const res=await post('/api/send',data);
    disableBtn('#btnMassSend',false);

    const ok = !!(res && res.success);
    const message = (res && res.message) || translate('feedback.done','Done');
    toast(message, ok ? 'success' : 'error');
    refreshLogs();
    refreshRecipients({ authoritative: true });
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

  async function refreshLogs(){
    const limit=qs('#logLimit')?.value||30;
    const res=await post('/api/logs',{limit});
    if(!res || !res.success){
      if(res && res.message){ toast(res.message, 'error'); }
      return;
    }
    renderLogs(res.logs||[]);
  }

  function applyLogFilter(){
    const input = qs('#logFilter');
    const tb = qs('#massMailLogTable tbody');
    if(!input || !tb) return;
    const query = (input.value || '').trim().toLowerCase();
    let visible = 0;
    Array.from(tb.rows).forEach(row => {
      if(row.classList.contains('js-log-empty')) return;
      const text = (row.innerText || '').toLowerCase();
      const match = !query || text.includes(query);
      row.hidden = !match;
      if(match) visible++;
    });
    let emptyRow = tb.querySelector('.js-log-filter-none');
    if(!emptyRow){
      emptyRow = document.createElement('tr');
      emptyRow.className = 'js-log-filter-none';
      const td = document.createElement('td');
      td.colSpan = 7;
      td.className = 'text-center muted';
      td.textContent = translate('logs.filter_no_results','No matching log entries');
      emptyRow.appendChild(td);
      tb.appendChild(emptyRow);
    }
    emptyRow.hidden = !query || visible !== 0;
  }

  function renderLogs(rows){
    const tb=qs('#massMailLogTable tbody');
    if(!tb) return;
    if(!rows.length){
      tb.innerHTML=`<tr class="js-log-empty"><td colspan="7" class="text-center muted">${esc(translate('logs.empty','No logs yet'))}</td></tr>`;
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
    applyLogFilter();
  }
  function esc(s){ return (s+'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
  function short(s,len){ if(s.length<=len) return s; return s.slice(0,len)+'…'; }

  const LOG_LIMIT_KEY = 'mm.logLimit';

  function bindLogs(){
    const limitSel = qs('#logLimit');
    if(limitSel){
      // 记住上次选择，避免每次进页面都要重新挑
      try{
        const saved = window.localStorage.getItem(LOG_LIMIT_KEY);
        if(saved && qsa('option', limitSel).some(o => o.value === saved)){ limitSel.value = saved; }
      }catch(e){ /* 隐私模式下 localStorage 可能不可用 */ }

      limitSel.addEventListener('change',()=>{
        try{ window.localStorage.setItem(LOG_LIMIT_KEY, limitSel.value); }catch(e){}
        refreshLogs();
      });
    }

    qs('#btnLogsRefresh')?.addEventListener('click',()=> refreshLogs());

    const filter = qs('#logFilter');
    if(filter){ filter.addEventListener('input', applyLogFilter); }
  }

  function init(){
    bindAnnounce();
    bindMassSend();
    bindLogs();
    const confirmModal=qs('#mmConfirmModal');
    if(confirmModal){ confirmModal.addEventListener('click',e=>{ if(e.target===confirmModal) closeConfirm(); }); }
    refreshLogs();
  }
  // panel.js injects page modules from an immediately-invoked body script, so
  // this module can execute while the document is still parsing. Defer via the
  // panel helper (it covers loading AND interactive) instead of the classic
  // readyState check, which silently skips init() in the interactive state.
  if (window.Panel && typeof window.Panel.whenDomReady === 'function') {
    window.Panel.whenDomReady(init);
  } else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();

