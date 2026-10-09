(function(){
  const panel = window.Panel || {};
  const moduleTranslate = typeof panel.createModuleTranslator === 'function'
    ? panel.createModuleTranslator('character')
    : (path, fallback) => fallback;
  const t = (path, fallback, replacements)=>{
    let text = moduleTranslate(path, fallback ?? `modules.character.${path}`);
    if(typeof text === 'string' && text === `modules.character.${path}` && fallback) text = fallback;
    if(typeof text === 'string' && replacements && typeof replacements === 'object'){
      Object.entries(replacements).forEach(([key, value])=>{
        text = text.replace(new RegExp(`:${key}(?![A-Za-z0-9_])`, 'g'), String(value ?? ''));
      });
    }
    return text;
  };
  const feedback = document.getElementById('char-feedback');
  const confirmDialog = typeof panel.confirm === 'function'
    ? panel.confirm.bind(panel)
    : (options)=> Promise.resolve(window.confirm(String((options && options.message) || '')));

  /** 局部重绘角色列表：删除/批量操作只改表格，滚动位置与筛选条件都留着。 */
  function refreshCharacterList(){
    if(typeof panel.reloadRegion !== 'function'){ window.location.reload(); return; }
    panel.reloadRegion('table.character-table', window.location.href).then((ok)=>{
      if(!ok) panel.reload();
    });
  }

  const searchParams = new URLSearchParams(location.search);
  const currentServer = searchParams.get('server') || '';

  /** Base path of the panel install: window.APP_BASE is never set by the server. */
  const resolveBasePath = () => ((window.Panel && window.Panel.basePath && window.Panel.basePath()) || (document.body && document.body.dataset && document.body.dataset.appBase) || '').replace(/\/$/, '');

  function withServer(path){
    if(!currentServer) return path;
    if(String(path).includes('server=')) return path;
    return path + (String(path).includes('?') ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  function buildUrl(path){
    const base = resolveBasePath();
    return (base ? base : '') + withServer(path);
  }

  /**
   * DOM 绑定集中在这里，确保解析完文档后才执行：本模块由 body 内联脚本注入，
   * 顶层查询会在解析中途跑并静默绑不上任何东西（空 Tab 列表、失效的物品面板）。
   */
  function setup(){
    // 名称由服务端解析（CharacterController::resolveDetailNames）；这里只做旧页面残留节点的兜底
    const legacyNfuwowNodes = document.querySelectorAll('.js-nfuwow');
    if(legacyNfuwowNodes.length){
      legacyNfuwowNodes.forEach(el => {
        const nameEl = el.parentElement ? el.parentElement.querySelector('.js-nfuwow-name') : null;
        if(nameEl) nameEl.remove();
      });
    }

    // Embed the item/inventory panel into the inventory tab.
    let inventoryBooted = false;
    function bootInventoryItems(){
      if(inventoryBooted) return;
      const mount = document.getElementById('char-bag-query');
      const table = document.getElementById('iiItemTable');
      if(!mount || !table) return;

      const guid = parseInt(mount.dataset.guid || '0', 10) || 0;
      if(!guid) return;
      const name = mount.dataset.name || '';

      window.__ITEM_INVENTORY_CTX = Object.assign({}, window.__ITEM_INVENTORY_CTX || {}, {
        embed: { guid, name }
      });

      const base = resolveBasePath();
      const src = (base ? base : '') + '/assets/js/modules/item_inventory.js';
      const s = document.createElement('script');
      s.src = src;
      s.async = true;
      document.head.appendChild(s);
      inventoryBooted = true;
    }

    // Tabs (character/show)
    const tabs = Array.from(document.querySelectorAll('.char-tab-item'));
    const contents = Array.from(document.querySelectorAll('.char-tab-content'));
    if(tabs.length && contents.length){
      const activate = (tabEl) => {
        tabs.forEach(t => t.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        tabEl.classList.add('active');
        const targetId = tabEl.getAttribute('data-tab');
        if(!targetId) return;
        const target = document.getElementById(targetId);
        if(target) target.classList.add('active');

        if(targetId === 'inventory'){
          bootInventoryItems();
        }
      };

      tabs.forEach(tab => {
        tab.addEventListener('click', () => activate(tab));
      });
    }

    function flash(msg, ok){
      if(!feedback) return;
      if(typeof panel.feedback?.show === 'function'){
        panel.feedback.show(feedback, ok ? 'success' : 'error', msg || (ok ? 'OK' : t('errors.generic', 'Error')));
        return;
      }
      feedback.textContent = msg || (ok ? 'OK' : t('errors.generic', 'Error'));
      feedback.classList.remove('panel-flash--success','panel-flash--danger','char-feedback--hidden');
      feedback.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    }

    // Teleport presets (character/show)
    const teleportForm = document.getElementById('char-teleport-form');
    const teleportPreset = document.getElementById('char-teleport-preset');
    if(teleportForm && teleportPreset){
      const setField = (name, value) => {
        const el = teleportForm.querySelector('[name="' + name + '"]');
        if(!el) return;
        el.value = value;
      };

      teleportPreset.addEventListener('change', () => {
        const opt = teleportPreset.selectedOptions && teleportPreset.selectedOptions[0];
        if(!opt) return;
        const map = opt.getAttribute('data-map');
        const zone = opt.getAttribute('data-zone');
        const x = opt.getAttribute('data-x');
        const y = opt.getAttribute('data-y');
        const z = opt.getAttribute('data-z');
        if(map === null || zone === null || x === null || y === null || z === null) return;

        setField('map', String(parseInt(map, 10) || 0));
        setField('zone', String(parseInt(zone, 10) || 0));
        setField('x', String(x));
        setField('y', String(y));
        setField('z', String(z));
      });
    }

    const actionForms = document.querySelectorAll('.js-char-action');
    if(actionForms.length){
      actionForms.forEach(form => {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const endpoint = form.dataset.endpoint;
          if(!endpoint) return;
          if(form.dataset.confirm){
            const proceed = await confirmDialog({
              message: form.dataset.confirm,
              confirmLabel: form.dataset.confirmLabel || t('actions.confirm', 'Confirm'),
              danger: form.dataset.confirmDanger === '1'
            });
            if(!proceed) return;
          }

          const data = new FormData(form);
          if(!data.get('_csrf') && window.__CSRF_TOKEN){
            data.set('_csrf', window.__CSRF_TOKEN);
          }

          try {
            const res = await fetch(endpoint, {
              method: 'POST',
              body: data,
              headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '', 'Accept': 'application/json' }
            });
            const json = await res.json().catch(() => ({ success: false, message: t('errors.invalid_response', 'Invalid response') }));
            flash(json.message || (json.success ? 'OK' : t('errors.generic', 'Failed')), !!json.success);
          } catch(err){
            // 网络层异常只进控制台；界面上给一句可读的话
            console.error('[character] action request failed', err);
            flash(t('errors.network', 'Network error, please retry'), false);
          }
        });
      });
    }

    // Boost form: if template selected, target level is derived from template
    (function bindBoostTemplateToggle(){
      const form = document.getElementById('char-boost-form');
      if(!form) return;
      const tpl = document.getElementById('char-boost-template');
      const lvl = document.getElementById('char-boost-target-level');
      if(!tpl || !lvl) return;

      const apply = () => {
        const opt = tpl.selectedOptions && tpl.selectedOptions[0];
        const templateId = parseInt(tpl.value || '0', 10) || 0;
        if(templateId > 0){
          const t = opt ? (parseInt(opt.getAttribute('data-target-level') || '0', 10) || 0) : 0;
          lvl.value = t ? String(t) : '';
          lvl.setAttribute('disabled', 'disabled');
          lvl.hidden = true;
        } else {
          lvl.removeAttribute('disabled');
          lvl.hidden = false;
        }
      };

      tpl.addEventListener('change', apply);
      apply();
    })();

    document.querySelectorAll('.js-table-filter').forEach(input => {
      const targetSel = input.getAttribute('data-target');
      const table = targetSel ? document.querySelector(targetSel) : null;
      if(!table || !table.tBodies.length) return;

      const tbody = table.tBodies[0];
      const emptyLabel = table.dataset.filterEmpty || t('list.no_results', 'No results');
      let noneRow = tbody.querySelector('.js-filter-none');
      if(!noneRow){
        noneRow = document.createElement('tr');
        noneRow.className = 'js-filter-none';
        const cols = table.tHead ? table.tHead.rows[0].cells.length : 1;
        const td = document.createElement('td');
        td.colSpan = cols;
        td.className = 'char-empty-cell';
        td.textContent = emptyLabel;
        noneRow.appendChild(td);
        tbody.appendChild(noneRow);
      }

      const emptyRow = tbody.querySelector('.js-empty-row');

      const applyFilter = () => {
        const q = (input.value || '').trim().toLowerCase();
        let visible = 0;

        Array.from(tbody.rows).forEach(row => {
          if(row.classList.contains('js-filter-none')) return;
          if(row.classList.contains('js-empty-row')){
            row.hidden = !!q;
            return;
          }
          const text = (row.innerText || '').toLowerCase();
          const match = !q || text.includes(q);
          row.hidden = !match;
          if(match) visible++;
        });

        if(q){
          noneRow.hidden = visible !== 0;
          if(emptyRow) emptyRow.hidden = true;
        } else {
          noneRow.hidden = true;
          if(emptyRow) emptyRow.hidden = visible !== 0;
        }
      };

      input.addEventListener('input', applyFilter);
      applyFilter();
    });

    // Character list bulk actions (character/index)
    function formPost(endpoint, payload){
      const url = buildUrl(endpoint);
      const data = new FormData();
      Object.entries(payload || {}).forEach(([k,v]) => {
        if(Array.isArray(v)){
          v.forEach(item => data.append(k + '[]', String(item)));
        } else if(v !== undefined && v !== null){
          data.append(k, String(v));
        }
      });
      if(!data.get('_csrf') && window.__CSRF_TOKEN){
        data.set('_csrf', window.__CSRF_TOKEN);
      }
      return fetch(url, {
        method: 'POST',
        body: data,
        headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
      }).then(r => r.json().catch(() => ({ success:false, message:'Invalid response' })));
    }

    function selectedGuids(){
      return Array.from(document.querySelectorAll('input.js-char-select:checked'))
        .map(el => parseInt(el.value, 10))
        .filter(v => Number.isFinite(v) && v > 0);
    }

    document.addEventListener('change', (event) => {
      const target = event.target;
      if(!(target instanceof HTMLInputElement)) return;
      if(target.classList.contains('js-char-select-all')){
        const checked = target.checked;
        document.querySelectorAll('input.js-char-select-all').forEach(el => { el.checked = checked; });
        document.querySelectorAll('input.js-char-select').forEach(el => { el.checked = checked; });
        return;
      }
      if(target.classList.contains('js-char-select')){
        const all = Array.from(document.querySelectorAll('input.js-char-select'));
        const checked = all.filter(el => el.checked);
        const allChecked = all.length > 0 && checked.length === all.length;
        document.querySelectorAll('input.js-char-select-all').forEach(el => { el.checked = allChecked; });
      }
    });

    document.addEventListener('click', async (event) => {
      const delBtn = event.target.closest('button.js-char-delete');
      if(delBtn){
        const guid = parseInt(delBtn.getAttribute('data-guid') || '0', 10) || 0;
        const name = delBtn.getAttribute('data-name') || '';
        if(!guid) return;
        const proceed = await confirmDialog({
          title: t('delete.title', 'Delete character'),
          message: t('delete.confirm', 'Delete character :name? This cannot be undone.', { name: name || guid }),
          requireText: name || String(guid),
          requireLabel: t('delete.require_label', 'Type the character name to confirm'),
          confirmLabel: t('delete.submit', 'Delete'),
          danger: true
        });
        if(!proceed) return;
        const res = await formPost('/character/api/delete', { guid });
        flash(res.message || (res.success ? 'OK' : t('errors.generic', 'Failed')), !!res.success);
        if(res.success) refreshCharacterList();
        return;
      }

      const bulkBtn = event.target.closest('button.js-char-bulk');
      if(!bulkBtn) return;
      const action = bulkBtn.getAttribute('data-bulk') || '';
      if(!action) return;

      const guids = selectedGuids();
      if(!guids.length){
        flash(t('bulk.no_selection', 'Select at least one character first'), false);
        return;
      }

      if(action === 'delete'){
        const proceed = await confirmDialog({
          title: t('bulk.delete_title', 'Delete selected characters'),
          message: t('bulk.delete_confirm', 'Delete the selected characters? This cannot be undone.'),
          requireText: String(guids.length),
          requireLabel: t('bulk.delete_require_label', 'Type the number of selected characters (:count)', { count: guids.length }),
          confirmLabel: t('delete.submit', 'Delete'),
          danger: true
        });
        if(!proceed) return;
        const res = await formPost('/character/api/bulk', { action, guids, hours: 0, reason: '' });
        finishBulk(res);
        return;
      }

      if(action === 'ban'){
        openBanDialog(guids, bulkBtn);
        return;
      }

      if(action === 'unban'){
        const proceed = await confirmDialog({
          message: t('bulk.unban_confirm', 'Unban the selected characters?'),
          confirmLabel: t('actions.unban', 'Unban')
        });
        if(!proceed) return;
      }

      const res = await formPost('/character/api/bulk', { action, guids, hours: 0, reason: '' });
      finishBulk(res);
    });

    /** 批量封禁：时长 + 理由的弹窗表单，目标数量只读回显（不再用 prompt 收两个值）。 */
    function openBanDialog(guids, trigger){
      if(typeof panel.formModal !== 'function'){ window.alert(t('ban.unavailable', 'Ban form unavailable')); return; }
      const permanentLabel = t('ban.permanent', 'Permanent');
      const presets = [24, 72, 168, 720];
      const optionsHtml = [`<option value="0">${permanentLabel}</option>`]
        .concat(presets.map((hours, index)=> `<option value="${hours}"${index === 0 ? ' selected' : ''}>${t('ban.hours', ':count hours', { count: hours })}</option>`))
        .join('');
      const body = `<form class="form char-ban-form">`
        + `<div class="form-field"><label>${t('ban.target', 'Selected characters')}</label>`
        + `<div class="char-form-target" data-readonly="1"><strong>${guids.length}</strong> <span class="muted small">${t('bulk.ban_target_hint', 'The same duration and reason apply to every selected character')}</span></div></div>`
        + `<div class="form-field"><label for="char-ban-duration">${t('ban.duration_label', 'Duration')}</label>`
        + `<select name="hours" id="char-ban-duration">${optionsHtml}</select>`
        + `<input type="number" name="custom_hours" min="0" max="87600" step="1" placeholder="${t('ban.custom_hours', 'Custom hours (overrides the choice above)')}"></div>`
        + `<div class="form-field"><label for="char-ban-reason">${t('ban.reason_label', 'Reason')}</label>`
        + `<input type="text" name="reason" id="char-ban-reason" maxlength="255" value="${t('ban.default_reason', 'Panel ban')}" required></div>`
        + `<div class="form-error char-form-error" hidden></div></form>`;

      const ui = panel.formModal({
        id: 'character-ban-modal',
        title: t('bulk.ban_title', 'Ban selected characters'),
        body,
        submitLabel: t('ban.actions.submit', 'Ban'),
        danger: true,
        onSubmit: async (dialog)=>{
          const data = dialog.form ? new FormData(dialog.form) : new FormData();
          const custom = String(data.get('custom_hours') || '').trim();
          const hours = parseInt(custom !== '' ? custom : String(data.get('hours') || '0'), 10);
          const reason = String(data.get('reason') || '').trim() || t('ban.default_reason', 'Panel ban');
          const errors = [];
          if(!Number.isFinite(hours) || hours < 0 || hours > 87600){
            errors.push({ field: custom !== '' ? 'custom_hours' : 'hours', message: t('ban.error_hours', 'Invalid duration') });
          }
          if(reason === '') errors.push({ field: 'reason', message: t('ban.error_reason', 'Please enter a reason') });
          if(errors.length){ dialog.showFieldErrors(errors); return; }
          dialog.setBusy(true, t('ban.submitting', 'Banning…'));
          try{
            const res = await formPost('/character/api/bulk', { action: 'ban', guids, hours, reason });
            if(res && res.success){
              dialog.close();
              flash(t('bulk.ban_success', 'Selected characters banned'), true);
              refreshCharacterList();
              return;
            }
            dialog.showError((res && res.message) ? res.message : t('errors.generic', 'Failed'));
          } finally {
            dialog.setBusy(false);
          }
        }
      });
      if(ui && trigger) trigger.setAttribute('aria-haspopup', 'dialog');
    }

    /** 批量操作收尾：成功重绘列表，失败报出失败数量或服务端消息。 */
    function finishBulk(res){
      if(res && res.success){
        flash(`OK: ${res.ok}/${res.requested}`, true);
        refreshCharacterList();
        return;
      }
      const failed = res && typeof res.failed === 'number' ? res.failed : null;
      flash(failed ? `Failed: ${failed}` : ((res && res.message) ? res.message : t('errors.generic', 'Failed')), false);
    }

    /**
     * 列表页快捷键：`/` 聚焦角色名框、Esc 清空筛选、Ctrl+Enter 提交查询。
     * 弹窗（含封禁表单）打开时不抢键。
     */
    function initHotkeys(){
      if(typeof panel.hotkeys !== 'function') return;
      const filterForm = document.querySelector('form.list-filter');
      if(!filterForm) return;
      const hasModalOpen = ()=> !!document.querySelector('.modal-backdrop.active');
      panel.hotkeys({
        '/': ()=>{ if(hasModalOpen()) return; const field = filterForm.querySelector('input[name="name"]'); if(field){ field.focus(); field.select(); } },
        'escape': ()=>{
          if(hasModalOpen()) return;
          ['name', 'guid', 'account', 'level_min', 'level_max'].forEach((fieldName)=>{
            const field = filterForm.querySelector('[name="' + fieldName + '"]');
            if(field && field.value !== '') field.value = '';
          });
          // Esc 在输入框里也要有反应：清空之后再取消焦点
          if(document.activeElement && filterForm.contains(document.activeElement)) document.activeElement.blur();
        },
        'ctrl+enter': ()=>{ if(hasModalOpen()) return; filterForm.requestSubmit ? filterForm.requestSubmit() : filterForm.submit(); }
      });
    }

    initHotkeys();
  }

  if (window.Panel && typeof window.Panel.whenDomReady === 'function') {
    window.Panel.whenDomReady(setup);
  } else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup, { once: true });
  } else {
    setup();
  }
})();
