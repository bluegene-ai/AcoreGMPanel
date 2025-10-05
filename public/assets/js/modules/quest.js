/**
 * File: public/assets/js/modules/quest.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - questNotify()
 *   - resolveModal()
 *   - openModal()
 *   - hideModal()
 *   - hideAllModals()
 *   - refreshQuestLogs()
 *   - setupListPage()
 *   - setupEditPage()
 *   - countDirtyFields()
 *   - updateSqlAndCount()
 *   - highlightDirty()
 *   - updateTabDirtyIndicators()
 *   - bindValueChange()
 *   - applyRemoteRow()
 *   - showExecStatus()
 *   - updateMiniDiff()
 *   - escapeHtml()
 *   - buildGroupsUI()
 *   - initEnums()
 *   - initBitmasks()
 *   - qs()
 *   - qsa()
 *   - apiGet()
 *   - apiPost()
 */

(function(){
  if(!document.body || document.body.getAttribute('data-module')!=='quest') return;

  const qs = (sel, ctx=document) => ctx.querySelector(sel);
  const qsa = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
  const FEEDBACK_SELECTOR = '#quest-feedback';
  const panelApi = window.Panel?.api;
  const panelFeedback = window.Panel?.feedback;
  const panelUrl = typeof window.Panel?.url === 'function' ? window.Panel.url : (path => path);

  const panelLocale = window.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function'
    ? panelLocale.moduleLocale.bind(panelLocale)
    : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('quest')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.quest.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('quest', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    if(typeof text === 'string' && text === `modules.quest.${path}` && fallback){
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

  const STRINGS = {
    apiNotReady: translate('api.not_ready', 'Panel API is not ready'),
    logsLoading: translate('logs.loading_placeholder', '-- Loading... --'),
    logsEmpty: translate('logs.empty_placeholder', '-- No logs --'),
    logsErrorPlaceholder: translate('logs.error_placeholder', '-- Load failed --'),
    logsLoadFailed: translate('logs.load_failed', 'Failed to load logs'),
    logsLoadFailedWithReason: reason => translate('logs.load_failed_with_reason', 'Failed to load logs: :reason', { reason: String(reason ?? '') }),
    createEnterNewId: translate('create.enter_new_id', 'Please enter a new quest ID'),
    createSuccessRedirect: translate('create.success_redirect', 'Quest created, redirecting...'),
    createFailed: translate('create.failed', 'Failed to create quest'),
    createFailedWithReason: reason => translate('create.failed_with_reason', 'Failed to create quest: :reason', { reason: String(reason ?? '') }),
    listConfirmDelete: id => translate('list.confirm_delete', 'Delete quest :id?', { id }),
    listDeleteSuccess: translate('list.delete_success', 'Quest deleted'),
    listDeleteFailed: translate('list.delete_failed', 'Failed to delete quest'),
    listDeleteFailedWithReason: reason => translate('list.delete_failed_with_reason', 'Failed to delete quest: :reason', { reason: String(reason ?? '') }),
    editorNoChangesComment: translate('editor.no_changes_comment', '-- No changes --'),
    editorNoSqlAvailable: translate('editor.no_sql_available', 'No SQL to execute'),
    editorConfirmExecute: translate('editor.confirm_execute', 'Run current UPDATE?'),
    editorExecSuccess: translate('editor.exec_success', 'SQL executed'),
    editorExecFailed: translate('editor.exec_failed', 'Execution failed'),
    editorExecFailedWithReason: reason => translate('editor.exec_failed_with_reason', 'Execution failed: :reason', { reason: String(reason ?? '') }),
    editorCopySqlSuccess: translate('editor.copy_sql_success', 'SQL copied'),
    editorCopySqlFailedWithReason: reason => translate('editor.copy_sql_failed_with_reason', 'Copy failed: :reason', { reason: String(reason ?? '') }),
    editorDiffCount: count => translate('editor.diff_count', ':count changes', { count }),
    editorRowsLabel: translate('editor.rows_label', 'Rows:'),
    editorResetPrompt: translate('editor.reset_prompt', 'Reset all changes and reload current database row?'),
    editorResetSuccess: translate('editor.reset_success', 'Changes reset'),
    editorResetFailed: translate('editor.reset_failed', 'Reset failed'),
    editorResetFailedWithReason: reason => translate('editor.reset_failed_with_reason', 'Reset failed: :reason', { reason: String(reason ?? '') }),
    editorRestoreField: field => translate('editor.restore_field', 'Restored :field', { field: String(field ?? '') }),
    miniRevertTooltip: translate('mini.revert_tooltip', 'Restore this field'),
    miniCollapse: translate('mini.collapse', 'Collapse'),
    miniExpand: translate('mini.expand', 'Expand'),
    refreshFailedConsole: translate('editor.refresh_failed_console', 'Failed to refresh quest data'),
  };

  const apiGet = (path, params) => {
    if(panelApi?.get) return panelApi.get(path, params);
    if(typeof panelApi === 'function'){
      const query = params ? ('?' + new URLSearchParams(params).toString()) : '';
      return panelApi(path + query, { method: 'GET' });
    }
    return Promise.reject(new Error(STRINGS.apiNotReady));
  };

  const apiPost = (path, body) => {
    if(panelApi?.post) return panelApi.post(path, body);
    if(typeof panelApi === 'function') return panelApi(path, { method: 'POST', body });
    return Promise.reject(new Error(STRINGS.apiNotReady));
  };

  function questNotify(message, type='info', opts){
    const target = qs(FEEDBACK_SELECTOR);
    if(panelFeedback?.show && target){
      const severity = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
      const duration = typeof opts?.duration === 'number' ? opts.duration : (severity === 'error' ? 6000 : 3200);
      panelFeedback.show(target, severity, message, { duration });
      return;
    }
    if(type === 'error'){
      console.error(message);
      if(!opts || opts.fallback !== false) alert(message);
    } else {
      console.log(message);
    }
  }

  function resolveModal(id){
    if(!id) return null;
    if(typeof id === 'string'){
      if(id.startsWith('#')) return document.querySelector(id);
      return document.getElementById(id);
    }
    if(id && id.nodeType === 1) return id;
    return null;
  }

  function openModal(id){
    const el = resolveModal(id); if(!el) return;
    el.style.display = 'block';
    requestAnimationFrame(()=> el.classList.add('active'));
    document.body.style.overflow = 'hidden';
  }

  function hideModal(id){
    const el = resolveModal(id); if(!el) return;
    el.classList.remove('active');
    el.style.display = 'none';
    if(!document.querySelector('.modal-backdrop.active')) document.body.style.overflow = '';
  }

  function hideAllModals(){
    document.querySelectorAll('.modal-backdrop.active').forEach(el=>{
      el.classList.remove('active');
      el.style.display = 'none';
    });
    document.body.style.overflow = '';
  }

  if(!window.__questModalBound){
    window.__questModalBound = true;
    document.addEventListener('click', e=>{
      const closeBtn = e.target.closest('[data-close]');
      if(closeBtn){
        hideModal(closeBtn.closest('.modal-backdrop'));
        return;
      }
      if(e.target.classList.contains('modal-backdrop')){
        hideModal(e.target);
      }
    });
    document.addEventListener('keydown', e=>{
      if(e.key === 'Escape') hideAllModals();
    });
  }

  async function refreshQuestLogs(type){
    const box = qs('#questLogBox');
    const select = qs('#questLogType');
    const logType = type || (select ? select.value : 'sql');
    if(select) select.value = logType;
    if(box) box.textContent = STRINGS.logsLoading;
    try{
      const res = await apiPost('/quest/api/logs', { type: logType, limit: 200 });
      if(res && res.success){
        const lines = Array.isArray(res.logs) ? res.logs : [];
        if(box) box.textContent = lines.length ? lines.join('\n') : STRINGS.logsEmpty;
      } else {
        if(box) box.textContent = STRINGS.logsErrorPlaceholder;
        const message = res?.message;
        questNotify(message || STRINGS.logsLoadFailed, 'error', { duration: 6000 });
      }
    }catch(err){
      if(box) box.textContent = STRINGS.logsErrorPlaceholder;
      const reason = err?.message || err;
      questNotify(STRINGS.logsLoadFailedWithReason(reason), 'error', { duration: 6000 });
    }
  }

  function setupListPage(){
    const filterReset = qs('#btn-filter-reset');
    if(filterReset){
      filterReset.addEventListener('click', ()=>{
        const form = qs('#quest-filter-form');
        if(!form) return;
        form.reset();
        form.querySelector('input[name="sort_by"]')?.setAttribute('value','ID');
        form.querySelector('input[name="sort_dir"]')?.setAttribute('value','ASC');
      });
    }

    const newBtn = qs('#btn-new-quest');
    if(newBtn){
      newBtn.addEventListener('click', ()=> openModal('#modal-new-quest'));
    }

    const createBtn = qs('#btn-create-quest');
    if(createBtn){
      createBtn.addEventListener('click', async ()=>{
        const idInput = qs('#newQuestId');
        const copyInput = qs('#copyQuestId');
        const id = idInput ? parseInt(idInput.value, 10) : NaN;
        const copyRaw = copyInput ? copyInput.value.trim() : '';
        const copyId = copyRaw === '' ? null : parseInt(copyRaw, 10);
        if(!id || Number.isNaN(id)){
          questNotify(STRINGS.createEnterNewId, 'error');
          idInput?.focus();
          return;
        }
        try{
          const payload = { new_id: id };
          if(copyId) payload.copy_id = copyId;
          const res = await apiPost('/quest/api/create', payload);
          if(res && res.success && res.new_id){
            questNotify(STRINGS.createSuccessRedirect, 'success', { duration: 2000 });
            hideModal('#modal-new-quest');
            setTimeout(()=>{ location.href = panelUrl('/quest?edit_id=' + res.new_id); }, 400);
          } else {
            const message = res?.message;
            questNotify(message || STRINGS.createFailed, 'error', { duration: 6000 });
          }
        }catch(err){
          const reason = err?.message || err;
          questNotify(STRINGS.createFailedWithReason(reason), 'error', { duration: 6000 });
        }
      });
    }

    const logBtn = qs('#btn-quest-log');
    if(logBtn){
      logBtn.addEventListener('click', ()=>{
        openModal('#modal-quest-log');
        refreshQuestLogs();
      });
    }
    const logRefreshBtn = qs('#btn-refresh-quest-log');
    const logTypeSel = qs('#questLogType');
    if(logRefreshBtn){
      logRefreshBtn.addEventListener('click', ()=> refreshQuestLogs(logTypeSel ? logTypeSel.value : undefined));
    }
    if(logTypeSel){
      logTypeSel.addEventListener('change', ()=> refreshQuestLogs(logTypeSel.value));
    }

    qsa('.action-delete').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const id = btn.getAttribute('data-id');
        if(!id) return;
        if(!confirm(STRINGS.listConfirmDelete(id))) return;
        try{
          const res = await apiPost('/quest/api/delete', { id });
          if(res && res.success){
            questNotify(STRINGS.listDeleteSuccess, 'success');
            const row = btn.closest('tr');
            if(row) row.remove();
          } else {
            const message = res?.message;
            questNotify(message || STRINGS.listDeleteFailed, 'error', { duration: 6000 });
          }
        }catch(err){
          const reason = err?.message || err;
          questNotify(STRINGS.listDeleteFailedWithReason(reason), 'error', { duration: 6000 });
        }
      });
    });
  }

  function setupEditPage(form){
    const editorContainer = document.getElementById('qe-tabs') || form || document;
    const diffSqlEl = document.getElementById('diff-sql');
    const diffCountEl = document.getElementById('diff-count');
    const btnCopySql = document.getElementById('btn-copy-sql');
    const execStatusBox = document.getElementById('quest-exec-status');
    const fieldLabels = window.FIELD_LABELS || {};
    const meta = window.QUEST_META || { enums: {}, bitmasks: {} };

    if(window.QuestEditorCore && window.QUEST_DATA && !window.__qe_core_inited){
      window.__qe_core_inited = true;
      window.QuestEditorCore.init(window.QUEST_DATA);
    }
    const Core = window.QuestEditorCore;

    initEnums();
    initBitmasks();

    if(Core){
      Core.on('diff:update', ({dirty})=>{
        updateSqlAndCount(dirty);
        highlightDirty(dirty);
        updateTabDirtyIndicators(dirty);
        updateMiniDiff(dirty);
      });
    }

    if(Core){
      document.querySelectorAll('.qe-dirty-dot').forEach(dot=> dot.classList.add('d-none'));
      const initDirty = Core.getDirtyMap();
      updateSqlAndCount(initDirty);
      highlightDirty(initDirty);
      updateMiniDiff(initDirty);
    }

    bindValueChange(form);
    bindValueChange(editorContainer);

    document.getElementById('btn-exec-sql')?.addEventListener('click', async ()=>{
      if(!Core) return;
      const sqlText = Core.buildSqlUpdate();
      if(!sqlText || sqlText.startsWith(STRINGS.editorNoChangesComment)){
        questNotify(STRINGS.editorNoSqlAvailable, 'info');
        return;
      }
      if(!confirm(STRINGS.editorConfirmExecute)) return;
      const started = performance.now();
      try{
        const res = await apiPost('/quest/api/exec-sql', { sql: sqlText });
        const elapsed = (performance.now() - started).toFixed(1);
        if(res.success){
          questNotify(STRINGS.editorExecSuccess, 'success');
          try{
            const rf = await apiPost('/quest/api/fetch', { id: Core.get('ID') });
            if(rf.success && rf.quest){
              applyRemoteRow(rf.quest);
              Core.rebaseline(rf.quest);
            }
          }catch(err){
            console.warn(STRINGS.refreshFailedConsole, err);
          }
          refreshQuestLogs('sql');
        } else {
          questNotify(res.message || STRINGS.editorExecFailed, 'error', { duration: 6000 });
        }
        showExecStatus(!!res.success, 'EXEC', elapsed, res.affected);
      }catch(err){
        const reason = err?.message || err;
        questNotify(STRINGS.editorExecFailedWithReason(reason), 'error', { duration: 6000 });
      }
    });

    btnCopySql?.addEventListener('click', ()=>{
      if(btnCopySql.disabled) return;
      const txt = diffSqlEl?.textContent.trim();
      if(!txt || txt.startsWith('--')) return;
      navigator.clipboard.writeText(txt).then(()=>{
        questNotify(STRINGS.editorCopySqlSuccess, 'success');
      }).catch(err=>{
        const reason = err?.message || err;
        questNotify(STRINGS.editorCopySqlFailedWithReason(reason), 'error', { duration: 5000 });
      });
    });

    const logBtn = document.getElementById('btn-open-quest-log');
    if(logBtn){
      logBtn.addEventListener('click', ()=>{
        openModal('#modal-quest-log');
        refreshQuestLogs();
      });
    }
    const logTypeSel = document.getElementById('questLogType');
    const logRefreshBtn = document.getElementById('btn-refresh-quest-log');
    if(logRefreshBtn){
      logRefreshBtn.addEventListener('click', ()=> refreshQuestLogs(logTypeSel ? logTypeSel.value : undefined));
    }
    if(logTypeSel){
      logTypeSel.addEventListener('change', ()=> refreshQuestLogs(logTypeSel.value));
    }

    function countDirtyFields(dirty){
      if(!dirty) return 0;
      const keys = Object.keys(dirty);
      if(!keys.length) return 0;
      return keys.filter(k=>{
        if(!k || k === 'ID' || k === 'template.ID') return false;
        if(k === '$') return false;
        return true;
      }).length;
    }

    function updateSqlAndCount(dirty){
      if(!diffSqlEl || !Core) return;
      const sql = Core.buildSqlUpdate();
      diffSqlEl.textContent = sql;
      const count = countDirtyFields(dirty);
      if(diffCountEl) diffCountEl.textContent = STRINGS.editorDiffCount(count);
      if(btnCopySql) btnCopySql.disabled = (count === 0);
    }

    function highlightDirty(dirty){
      editorContainer.querySelectorAll('[name][data-orig]').forEach(el=>{
        if(el.name === 'ID') return;
  const wrapper = el.closest('.bitmask-wrapper') || el.parentElement;
        const changed = !!dirty[el.name];
        const fieldCol = el.closest('.col-md-6, .col-12');
        if(changed){
          wrapper && wrapper.classList.add('quest-changed');
          fieldCol && fieldCol.classList.add('quest-has-changed');
        } else {
          wrapper && wrapper.classList.remove('quest-changed');
          fieldCol && fieldCol.classList.remove('quest-has-changed');
        }
      });
    }

    function updateTabDirtyIndicators(dirty){
      const dots = document.querySelectorAll('.qe-dirty-dot');
      dots.forEach(d=>d.classList.add('d-none'));
      const changedFields = Object.keys(dirty || {}).filter(k=>k !== 'ID');
      if(!changedFields.length) return;
      const tabsAffected = {};
      changedFields.forEach(f=>{
        const el = editorContainer.querySelector(`[name="${f}"]`); if(!el) return;
        const group = el.closest('.quest-group'); if(!group) return;
        const tab = group.getAttribute('data-group-tab'); if(!tab) return;
        tabsAffected[tab] = true;
      });
      Object.keys(tabsAffected).forEach(tab=>{
        const dot = document.querySelector(`[data-tab-dirty="${tab}"]`);
        if(dot) dot.classList.remove('d-none');
      });
    }

    function bindValueChange(root){
      ['input','change'].forEach(evt=>{
        root.addEventListener(evt, e=>{
          const t = e.target;
          if(!t || !t.name) return;
          if(Core) Core.setField(t.name, t.value, { record: true });
        }, true);
      });
    }

    function applyRemoteRow(row){
      Object.entries(row).forEach(([k,v])=>{
        const el = editorContainer.querySelector(`[name="${k}"]`);
        if(!el) return;
        const val = v == null ? '' : String(v);
        el.value = val;
        el.setAttribute('data-orig', val);
      });
      editorContainer.querySelectorAll('.bitmask-wrapper').forEach(w=>{
        const input = w.querySelector('.bitmask-value');
        const boxes = w.querySelectorAll('.bitmask-bit');
        if(!input || !boxes.length) return;
        const val = parseInt(input.value || '0', 10) || 0;
        boxes.forEach(cb=>{
          const b = parseInt(cb.getAttribute('data-bit'), 10);
          cb.checked = !!(val & (1 << b));
        });
      });
    }

    function showExecStatus(ok, kind, elapsedMs, affected){
      if(!execStatusBox) return;
      execStatusBox.style.display = 'block';
      const cls = ok ? 'qe-status-ok' : 'qe-status-fail';
      let html = `<span class="qe-status-tag ${cls}">${kind} ${ok ? 'OK' : 'FAIL'}</span>`;
  if(typeof affected !== 'undefined') html += `<span class="qe-status-aff">${STRINGS.editorRowsLabel} ${affected}</span>`;
      html += `<span class="qe-status-time">${elapsedMs}ms</span>`;
      html += '<a href="javascript:void(0)" class="qe-status-hide" id="exec-status-hide">×</a>';
      execStatusBox.className = 'mb-3 small qe-exec-status-line';
      execStatusBox.innerHTML = html;
      document.getElementById('exec-status-hide')?.addEventListener('click', ()=>{ execStatusBox.style.display='none'; });
      execStatusBox.style.opacity = '1';
      setTimeout(()=>{ if(execStatusBox.style.display!=='none') execStatusBox.style.opacity = '0.75'; }, 3200);
    }

    const miniTable = document.getElementById('mini-diff-table');
    const miniTbody = miniTable ? miniTable.querySelector('tbody') : null;
    const miniEmpty = document.getElementById('mini-diff-empty');
    const miniCount = document.getElementById('mini-diff-count');
    const miniCollapseBtn = document.getElementById('mini-diff-collapse');
    const miniClearBtn = document.getElementById('mini-diff-clear');

    function updateMiniDiff(dirty){
      if(!miniTable || !miniTbody || !miniCount) return;
      const fields = Object.keys(dirty || {}).filter(f=>f !== 'ID');
      miniCount.textContent = fields.length;
      if(!fields.length){
        miniTable.style.display='none';
        miniEmpty.style.display='block';
        if(miniClearBtn) miniClearBtn.disabled = true;
        return;
      }
      miniEmpty.style.display='none';
      miniTable.style.display='table';
      if(miniClearBtn) miniClearBtn.disabled = false;
      miniTbody.innerHTML = fields.map(f=>{
        const rec = dirty[f] || {};
        const oldValRaw = rec.old === '' || rec.old === null || typeof rec.old === 'undefined' ? null : rec.old;
        const newValRaw = rec.new === '' || rec.new === null || typeof rec.new === 'undefined' ? null : rec.new;
        const oldVal = oldValRaw === null ? '<span class="text-muted mini-old">∅</span>' : `<span class="mini-old">${escapeHtml(oldValRaw)}</span>`;
        const newVal = newValRaw === null ? '<span class="text-muted mini-new">∅</span>' : `<span class="mini-new">${escapeHtml(newValRaw)}</span>`;
        const label = escapeHtml(fieldLabels[f] || f);
        const revertTitle = escapeHtml(STRINGS.miniRevertTooltip);
        return `<tr class="mini-row" data-field="${f}">` +
          `<td class="text-nowrap">${label}</td>` +
          `<td><code class="d-block">${oldVal} <span class="mini-arrow">→</span> ${newVal} <button type="button" class="btn btn-sm outline mini-revert-btn" data-mini-revert="${f}" title="${revertTitle}">↺</button></code></td>` +
        '</tr>';
      }).join('');
    }

    miniCollapseBtn?.addEventListener('click', ()=>{
      const collapsed = miniCollapseBtn.getAttribute('data-collapsed') === '1';
      miniCollapseBtn.setAttribute('data-collapsed', collapsed ? '0' : '1');
      const card = document.getElementById('quest-mini-diff-card'); if(!card) return;
      card.classList.toggle('collapsed', !collapsed);
      const body = card.querySelector('.flex-grow-1');
      if(body) body.style.display = collapsed ? 'block' : 'none';
      miniCollapseBtn.textContent = collapsed ? STRINGS.miniCollapse : STRINGS.miniExpand;
    });

    miniClearBtn?.addEventListener('click', ()=>{
      if(!Core) return;
      if(!confirm(STRINGS.editorResetPrompt)) return;
      apiPost('/quest/api/fetch', { id: Core.get('ID') }).then(res=>{
        if(res.success && res.quest){
          applyRemoteRow(res.quest);
          Core.rebaseline(res.quest);
          questNotify(STRINGS.editorResetSuccess, 'success');
        } else {
          questNotify(res?.message || STRINGS.editorResetFailed, 'error', { duration: 6000 });
        }
      }).catch(err=>{
        const reason = err?.message || err;
        questNotify(STRINGS.editorResetFailedWithReason(reason), 'error', { duration: 6000 });
      });
    });

    miniTbody?.addEventListener('click', e=>{
      const btn = e.target.closest('[data-mini-revert]'); if(!btn) return;
      const field = btn.getAttribute('data-mini-revert'); if(!field || !Core) return;
      const orig = Core.getOriginal(field);
      const input = editorContainer.querySelector(`[name="${field}"]`);
      const origVal = orig == null ? '' : String(orig);
      if(input){
        input.value = origVal;
        input.setAttribute('data-orig', origVal);
        Core.setField(field, input.value, { record: true });
        const fieldName = fieldLabels[field] || field;
        questNotify(STRINGS.editorRestoreField(fieldName), 'info');
      }
    });

    function escapeHtml(val){
      return String(val).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
    }

    buildGroupsUI();
    function buildGroupsUI(){
      const nav = document.getElementById('quest-nav'); if(!nav) return;
      nav.innerHTML = '';
      const allGroups = document.querySelectorAll('.qe-tab-panel .quest-group');
      allGroups.forEach(g=>{
        const title = g.querySelector('h5');
        if(title && !title.querySelector('.quest-group-toggle')){
          const btn = document.createElement('span');
          btn.className = 'quest-group-toggle';
          btn.textContent = '-';
          btn.addEventListener('click', ()=>{
            g.classList.toggle('collapsed');
            btn.textContent = g.classList.contains('collapsed') ? '+' : '-';
          });
          title.prepend(btn);
        }
        const id = g.id || ('group_' + Math.random().toString(36).slice(2));
        g.id = id;
        const tab = g.getAttribute('data-group-tab') || 'general';
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = '#' + id;
        a.textContent = title ? title.textContent.replace(/^[-+]/,'').trim() : id;
        a.dataset.tab = tab;
        a.addEventListener('click', e=>{
          e.preventDefault();
          const targetTabBtn = document.querySelector(`.qe-tab[data-tab="${tab}"]`);
          if(targetTabBtn && !targetTabBtn.classList.contains('active')) targetTabBtn.click();
          nav.querySelectorAll('a').forEach(x=>x.classList.remove('active'));
          a.classList.add('active');
          setTimeout(()=>{ document.getElementById(id)?.scrollIntoView({ behavior:'smooth', block:'start' }); }, 10);
        });
        li.appendChild(a);
        nav.appendChild(li);
      });
    }

    function initEnums(){
      if(!meta.enums) return;
      const questTypeSel = editorContainer.querySelector('select[name="QuestType"]');
      if(questTypeSel && meta.enums.quest_type){
        const current = questTypeSel.value || questTypeSel.getAttribute('data-orig');
        if(questTypeSel.options.length <= 1){
          questTypeSel.innerHTML = Object.entries(meta.enums.quest_type).map(([val,label])=>{
            const selected = String(val) === String(current) ? 'selected' : '';
            return `<option value="${val}" ${selected}>${label}</option>`;
          }).join('');
        }
      }
    }

    function initBitmasks(){
      editorContainer.querySelectorAll('.bitmask-wrapper').forEach(wrap=>{
        const maskKey = wrap.getAttribute('data-mask-key');
        const map = (meta.bitmasks || {})[maskKey]; if(!map) return;
        const boxes = wrap.querySelector('.bitmask-boxes');
        const input = wrap.querySelector('.bitmask-value');
        if(!boxes || !input) return;
        const origVal = parseInt(input.getAttribute('data-orig') || '0', 10) || 0;
        boxes.innerHTML = Object.entries(map).map(([idx,label])=>{
          const bit = parseInt(idx, 10);
          const checked = (origVal & (1 << bit)) ? 'checked' : '';
          const title = `${label} (bit ${bit}, ${1<<bit})`;
          return `<div class="col"><label class="form-check form-check-sm small" title="${title}">` +
                 `<input class="form-check-input bitmask-bit" data-bit="${bit}" type="checkbox" ${checked}> ` +
                 `<span class="form-check-label">${label}</span></label></div>`;
        }).join('');
        boxes.addEventListener('change', e=>{
          if(!e.target.classList.contains('bitmask-bit')) return;
          let val = 0;
          boxes.querySelectorAll('.bitmask-bit:checked').forEach(cb=>{
            const b = parseInt(cb.getAttribute('data-bit'), 10);
            val |= (1 << b);
          });
          input.value = String(val);
          if(Core) Core.setField(input.name, input.value, { record: true });
        });
        const undoBtn = wrap.querySelector('.bitmask-undo');
        if(undoBtn){
          undoBtn.addEventListener('click', ()=>{
            const orig = parseInt(input.getAttribute('data-orig') || '0', 10) || 0;
            boxes.querySelectorAll('.bitmask-bit').forEach(cb=>{
              const b = parseInt(cb.getAttribute('data-bit'), 10);
              cb.checked = !!(orig & (1 << b));
            });
            input.value = String(orig);
            if(Core) Core.setField(input.name, input.value, { record: true });
            const label = wrap.getAttribute('data-mask-label') || input.name;
            questNotify(STRINGS.editorRestoreField(label), 'info');
          });
        }
      });
    }
  }

  const form = document.getElementById('quest-edit-form');
  if(form){
    setupEditPage(form);
  } else {
    setupListPage();
  }
})();
