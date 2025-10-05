/**
 * File: public/assets/js/modules/item_owner.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - resolveFlash()
 *   - flash()
 *   - init()
 *   - bindSearchForm()
 *   - runSearch()
 *   - renderItemsLoading()
 *   - renderItemError()
 *   - renderItemResults()
 *   - loadOwnership()
 *   - setSelectedItem()
 *   - renderOwnershipLoading()
 *   - renderOwnershipError()
 *   - updateSummary()
 *   - renderCharacters()
 *   - renderInstances()
 *   - updateActionButtons()
 *   - bindActionButtons()
 *   - runBulkDelete()
 *   - bindModal()
 *   - openReplaceModal()
 *   - closeReplaceModal()
 *   - runBulkReplace()
 *   - getClassMeta()
 *   - triggerColorize()
 *   - esc()
 *   - qs()
 *   - qsa()
 */

(function(){
  const qs = (sel, root=document) => root.querySelector(sel);
  const qsa = (sel, root=document) => Array.from(root.querySelectorAll(sel));
  const PanelApi = (window.Panel && Panel.api) ? window.Panel.api : null;
  const Feedback = (window.Panel && Panel.feedback) ? window.Panel.feedback : {
    success(target, msg){ flash(target, 'panel-flash--success', msg); },
    error(target, msg){ flash(target, 'panel-flash--error', msg); },
    info(target, msg){ flash(target, 'panel-flash--info', msg); },
    clear(target){ const el = resolveFlash(target); if(el){ el.classList.remove('is-visible','panel-flash--success','panel-flash--error','panel-flash--info'); el.textContent=''; } }
  };
  const moduleTranslator = window.Panel && typeof window.Panel.createModuleTranslator === 'function'
    ? window.Panel.createModuleTranslator('item_owner')
    : (path, fallback) => fallback;

  function translate(path, fallback, replacements){
    let text = moduleTranslator ? moduleTranslator(path, fallback ?? path) : (fallback ?? path);
    if(text && replacements){
      Object.entries(replacements).forEach(([key, value]) => {
        const pattern = new RegExp(':' + key + '(?![A-Za-z0-9_])', 'g');
        text = text.replace(pattern, String(value ?? ''));
      });
    }
    return text;
  }

  function resolveFlash(target){
    if(!target) return null;
    if(typeof target === 'string') return document.querySelector(target);
    if(target instanceof HTMLElement) return target;
    return null;
  }

  function flash(target, cls, message){
    const el = resolveFlash(target);
    if(!el) return;
    el.classList.add('panel-flash');
    ['panel-flash--success','panel-flash--error','panel-flash--info'].forEach(c=>el.classList.remove(c));
    if(cls) el.classList.add(cls);
    el.textContent = String(message ?? '');
    el.classList.add('is-visible');
    clearTimeout(el.__timer);
    el.__timer = setTimeout(()=>{
      el.classList.remove('is-visible');
      el.textContent='';
    }, 5000);
  }

  const api = {
    search(keyword){
      if(!PanelApi) return Promise.reject(new Error('API unavailable'));
      return PanelApi.get('/item-ownership/api/search-items', { keyword });
    },
    ownership(entry){
      if(!PanelApi) return Promise.reject(new Error('API unavailable'));
      return PanelApi.get('/item-ownership/api/ownership', { entry });
    },
    bulk(payload){
      if(!PanelApi) return Promise.reject(new Error('API unavailable'));
      return PanelApi.post('/item-ownership/api/bulk', payload);
    }
  };

  let currentItem = null;
  let currentOwnership = null;
  let selectedInstances = new Set();

  function init(){
    bindSearchForm();
    bindActionButtons();
    bindModal();
  }

  function bindSearchForm(){
    const form = qs('#itemOwnerSearch');
    if(!form) return;
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const input = qs('#itemOwnerKeyword');
      const keyword = input ? input.value.trim() : '';
      if(!keyword){
        Feedback.error('#itemOwnerSearchFlash', translate('search.validation.empty', 'Please input keyword'));
        input && input.focus();
        return;
      }
      await runSearch(keyword);
    });
  }

  async function runSearch(keyword){
    Feedback.clear('#itemOwnerSearchFlash');
    renderItemsLoading();
    try {
      const res = await api.search(keyword);
      if(!res || res.success !== true){
        throw new Error(res && res.message ? res.message : translate('search.error.failed', 'Search failed'));
      }
      renderItemResults(res.data || []);
    } catch (err){
      renderItemError(err && err.message ? err.message : translate('search.error.failed', 'Search failed'));
      Feedback.error('#itemOwnerSearchFlash', err && err.message ? err.message : translate('search.error.failed', 'Search failed'));
    }
  }

  function renderItemsLoading(){
    const tb = qs('#itemOwnerItemTable tbody');
    if(!tb) return;
    tb.innerHTML = `<tr><td colspan="5" class="text-center muted">${esc(translate('search.status.loading','Loading...'))}</td></tr>`;
  }

  function renderItemError(message){
    const tb = qs('#itemOwnerItemTable tbody');
    if(!tb) return;
    tb.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${esc(message || translate('search.error.failed','Search failed'))}</td></tr>`;
  }

  function renderItemResults(rows){
    const tb = qs('#itemOwnerItemTable tbody');
    if(!tb) return;
    if(!rows.length){
      tb.innerHTML = `<tr><td colspan="5" class="text-center muted">${esc(translate('search.results.empty','No items found'))}</td></tr>`;
      return;
    }
    tb.innerHTML = rows.map(row => {
      const quality = typeof row.quality === 'number' ? row.quality : null;
      const qualityClass = quality != null ? `item-quality item-quality-q${quality}` : 'item-quality';
      const qualityLabel = translate(`quality.${quality ?? 'unknown'}`, quality != null ? `Q${quality}` : '?');
      const btnLabel = translate('search.results.view', 'View owners');
      const stackable = typeof row.stackable === 'number' && row.stackable > 1 ? row.stackable : '-';
      return `<tr data-entry="${row.entry}">
        <td>${row.entry}</td>
        <td>${esc(row.name || row.name_en || ('#'+row.entry))}</td>
        <td><span class="${qualityClass}">${esc(qualityLabel)}</span></td>
        <td>${esc(stackable)}</td>
        <td><button type="button" class="btn-sm btn info" data-act="view" data-entry="${row.entry}">${esc(btnLabel)}</button></td>
      </tr>`;
    }).join('');
    tb.querySelectorAll('button[data-act="view"]').forEach(btn => {
      btn.addEventListener('click', ()=>{
        const entry = parseInt(btn.dataset.entry, 10);
        if(Number.isNaN(entry)) return;
        loadOwnership(entry, btn.closest('tr'));
      });
    });
  }

  async function loadOwnership(entry, row){
    if(row){
      qsa('#itemOwnerItemTable tbody tr').forEach(tr=> tr.classList.remove('item-owner-row--active'));
      row.classList.add('item-owner-row--active');
    }
    setSelectedItem(null);
    renderOwnershipLoading();
    try {
      const res = await api.ownership(entry);
      if(!res || res.success !== true){
        throw new Error(res && res.message ? res.message : translate('results.error.load_failed','Failed to load ownership'));
      }
      currentItem = res.data.item || null;
      currentOwnership = res.data;
      selectedInstances = new Set();
      updateSummary();
      renderCharacters(res.data.owners || []);
      renderInstances(res.data.owners || []);
      updateActionButtons();
      triggerColorize();
    } catch (err){
      renderOwnershipError(err && err.message ? err.message : translate('results.error.load_failed','Failed to load ownership'));
      Feedback.error('#itemOwnerActionFlash', err && err.message ? err.message : translate('results.error.load_failed','Failed to load ownership'));
    }
  }

  function setSelectedItem(item){
    currentItem = item;
    currentOwnership = null;
    selectedInstances = new Set();
    updateSummary();
    renderCharacters([]);
    renderInstances([]);
    updateActionButtons();
  }

  function renderOwnershipLoading(){
    const charTb = qs('#itemOwnerCharacterTable tbody');
    const instTb = qs('#itemOwnerInstanceTable tbody');
    if(charTb) charTb.innerHTML = `<tr><td colspan="3" class="text-center muted">${esc(translate('results.status.loading','Loading...'))}</td></tr>`;
    if(instTb) instTb.innerHTML = `<tr><td colspan="6" class="text-center muted">${esc(translate('results.status.loading','Loading...'))}</td></tr>`;
    qs('#itemOwnerSelectedTitle').textContent = translate('results.title_loading','Loading...');
    qs('#itemOwnerSummary').textContent = '';
  }

  function renderOwnershipError(message){
    const charTb = qs('#itemOwnerCharacterTable tbody');
    const instTb = qs('#itemOwnerInstanceTable tbody');
    if(charTb) charTb.innerHTML = `<tr><td colspan="3" class="text-center text-danger">${esc(message)}</td></tr>`;
    if(instTb) instTb.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${esc(message)}</td></tr>`;
    qs('#itemOwnerSelectedTitle').textContent = translate('results.title_error','Failed to load');
    qs('#itemOwnerSummary').textContent = '';
    selectedInstances = new Set();
    updateActionButtons();
  }

  function updateSummary(){
    const titleEl = qs('#itemOwnerSelectedTitle');
    const summaryEl = qs('#itemOwnerSummary');
    if(!titleEl || !summaryEl){
      return;
    }
    if(!currentItem || !currentOwnership){
      titleEl.textContent = translate('results.title_empty','Select an item');
      summaryEl.textContent = translate('results.subtitle_empty','Search an item to view ownership');
      return;
    }
    const title = `${currentItem.name || currentItem.name_en || ('#'+currentItem.entry)} (#${currentItem.entry})`;
    titleEl.textContent = title;
    const totals = currentOwnership.totals || { characters:0, instances:0, count:0 };
    summaryEl.textContent = translate('results.subtitle_totals', ':characters characters · :instances stacks · total :count', {
      characters: totals.characters ?? 0,
      instances: totals.instances ?? 0,
      count: totals.count ?? 0
    });
  }

  function renderCharacters(owners){
    const tb = qs('#itemOwnerCharacterTable tbody');
    if(!tb) return;
    if(!owners || !owners.length){
      tb.innerHTML = `<tr><td colspan="3" class="text-center muted">${esc(translate('results.characters.placeholder','No data'))}</td></tr>`;
      return;
    }
    tb.innerHTML = owners.map(owner => {
      const clsMeta = getClassMeta(owner.class);
      const name = owner.name || ('#'+owner.guid);
      const colorizedName = clsMeta
        ? `<span class="item-owner-character-name" data-class-id="${owner.class}" title="${esc(clsMeta.label ?? '')}">${esc(name)}</span>`
        : esc(name);
      return `<tr>
        <td>${colorizedName}</td>
        <td>${owner.level ?? '-'}</td>
        <td>${owner.total_count ?? 0}</td>
      </tr>`;
    }).join('');
  }

  function renderInstances(owners){
    const tb = qs('#itemOwnerInstanceTable tbody');
    if(!tb) return;
    const rows = [];
    owners.forEach(owner => {
      (owner.instances || []).forEach(inst => {
        rows.push({ owner, inst });
      });
    });
    if(!rows.length){
      tb.innerHTML = `<tr><td colspan="6" class="text-center muted">${esc(translate('results.instances.placeholder','No instances'))}</td></tr>`;
      return;
    }
    tb.innerHTML = rows.map(({owner, inst}) => {
      const checked = selectedInstances.has(inst.instance_guid) ? 'checked' : '';
      let containerText = '-';
      if(inst.container){
        const parts = [];
        if(inst.container.location_label){ parts.push(inst.container.location_label); }
        if(inst.container.name){ parts.push(inst.container.name); }
        containerText = parts.join(' · ') || '-';
      }
      return `<tr data-instance="${inst.instance_guid}">
        <td><input type="checkbox" data-role="select-instance" value="${inst.instance_guid}" ${checked}></td>
        <td>${inst.instance_guid}</td>
        <td>${esc(owner.name || ('#'+owner.guid))}</td>
        <td>${inst.count ?? 0}</td>
        <td>${esc(inst.location_label || inst.location_code || '-')}</td>
        <td>${esc(containerText)}</td>
      </tr>`;
    }).join('');
    tb.querySelectorAll('input[data-role="select-instance"]').forEach(input => {
      input.addEventListener('change', ()=>{
        const val = parseInt(input.value, 10);
        if(Number.isNaN(val)) return;
        if(input.checked) selectedInstances.add(val); else selectedInstances.delete(val);
        updateActionButtons();
      });
    });
    const selectAll = qs('#itemOwnerSelectAll');
    if(selectAll){
      selectAll.checked = rows.length > 0 && rows.every(({inst}) => selectedInstances.has(inst.instance_guid));
    }
  }

  function updateActionButtons(){
    const hasSelection = selectedInstances.size > 0;
    const deleteBtn = qs('#itemOwnerDeleteBtn');
    const replaceBtn = qs('#itemOwnerReplaceBtn');
    if(deleteBtn){ deleteBtn.disabled = !hasSelection; }
    if(replaceBtn){ replaceBtn.disabled = !hasSelection; }
  }

  function bindActionButtons(){
    const selectAll = qs('#itemOwnerSelectAll');
    if(selectAll){
      selectAll.addEventListener('change', ()=>{
        const inputs = qsa('input[data-role="select-instance"]');
        inputs.forEach(input => {
          input.checked = selectAll.checked;
          const val = parseInt(input.value, 10);
          if(Number.isNaN(val)) return;
          if(selectAll.checked) selectedInstances.add(val); else selectedInstances.delete(val);
        });
        updateActionButtons();
      });
    }
    const deleteBtn = qs('#itemOwnerDeleteBtn');
    if(deleteBtn){
      deleteBtn.addEventListener('click', ()=>{
        if(selectedInstances.size === 0) return;
        if(!confirm(translate('actions.confirm_delete','Delete selected items?'))){
          return;
        }
        runBulkDelete();
      });
    }
    const replaceBtn = qs('#itemOwnerReplaceBtn');
    if(replaceBtn){
      replaceBtn.addEventListener('click', ()=>{
        if(selectedInstances.size === 0) return;
        openReplaceModal();
      });
    }
  }

  async function runBulkDelete(){
    Feedback.clear('#itemOwnerActionFlash');
    try {
      const res = await api.bulk({
        action: 'delete',
        instances: Array.from(selectedInstances)
      });
      if(!res || res.success !== true){
        throw new Error(res && res.message ? res.message : translate('actions.delete_failed','Delete failed'));
      }
      Feedback.success('#itemOwnerActionFlash', res.message || translate('actions.delete_success','Delete succeeded'));
      if(currentItem){
        await loadOwnership(currentItem.entry);
      }
    } catch (err){
      Feedback.error('#itemOwnerActionFlash', err && err.message ? err.message : translate('actions.delete_failed','Delete failed'));
    }
  }

  function bindModal(){
    const modal = qs('#itemOwnerReplaceModal');
    if(!modal) return;
    modal.addEventListener('click', e => {
      if(e.target === modal || (e.target && e.target.dataset && Object.prototype.hasOwnProperty.call(e.target.dataset, 'close'))){
        closeReplaceModal();
      }
    });
    const confirmBtn = qs('#itemOwnerReplaceConfirm');
    if(confirmBtn){
      confirmBtn.addEventListener('click', runBulkReplace);
    }
  }

  function openReplaceModal(){
    const modal = qs('#itemOwnerReplaceModal');
    if(!modal) return;
    const input = qs('#itemOwnerReplaceEntry');
    if(input){ input.value = currentItem ? currentItem.entry : ''; input.focus(); input.select(); }
    Feedback.clear('#itemOwnerReplaceFeedback');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeReplaceModal(){
    const modal = qs('#itemOwnerReplaceModal');
    if(!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }

  async function runBulkReplace(){
    const input = qs('#itemOwnerReplaceEntry');
    const value = input ? parseInt(input.value, 10) : NaN;
    if(!value || value <= 0 || Number.isNaN(value)){
      Feedback.error('#itemOwnerReplaceFeedback', translate('modal.replace.validation.entry','Enter a valid item entry'));
      input && input.focus();
      return;
    }
    qs('#itemOwnerReplaceConfirm').disabled = true;
    try {
      const res = await api.bulk({
        action: 'replace',
        new_entry: value,
        instances: Array.from(selectedInstances)
      });
      if(!res || res.success !== true){
        throw new Error(res && res.message ? res.message : translate('actions.replace_failed','Replace failed'));
      }
      Feedback.success('#itemOwnerActionFlash', res.message || translate('actions.replace_success','Replace succeeded'));
      closeReplaceModal();
      if(currentItem){
        await loadOwnership(currentItem.entry);
      }
    } catch (err){
      Feedback.error('#itemOwnerReplaceFeedback', err && err.message ? err.message : translate('actions.replace_failed','Replace failed'));
    } finally {
      qs('#itemOwnerReplaceConfirm').disabled = false;
    }
  }

  function getClassMeta(classId){
    const enums = window.APP_ENUMS && window.APP_ENUMS.classes;
    if(!enums) return null;
    const meta = enums[classId];
    if(typeof meta === 'string'){
      return { label: meta, slug: '' };
    }
    return meta || null;
  }

  function triggerColorize(){
    if(window.GameMetaColorize) window.GameMetaColorize();
  }

  function esc(str){
    return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

