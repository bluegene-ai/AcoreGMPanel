(function(){
  const feedback = document.getElementById('char-feedback');

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
      feedback.textContent = msg || (ok ? 'OK' : 'Error');
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
          if(form.dataset.confirm && !confirm(form.dataset.confirm)) return;

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
            const json = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
            flash(json.message || (json.success ? 'OK' : 'Failed'), !!json.success);
          } catch(err){
            flash((err && err.message) ? err.message : 'Network error', false);
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
      const emptyLabel = table.dataset.filterEmpty || 'No results';
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
        if(!confirm(`确认删除角色 ${name ? name : guid}？此操作不可恢复。`)) return;
        const res = await formPost('/character/api/delete', { guid });
        flash(res.message || (res.success ? 'OK' : 'Failed'), !!res.success);
        if(res.success){
          setTimeout(() => location.reload(), 600);
        }
        return;
      }

      const bulkBtn = event.target.closest('button.js-char-bulk');
      if(!bulkBtn) return;
      const action = bulkBtn.getAttribute('data-bulk') || '';
      if(!action) return;

      const guids = selectedGuids();
      if(!guids.length){
        flash('请先选择至少一项', false);
        return;
      }

      let hours = 0;
      let reason = '';
      if(action === 'delete'){
        if(!confirm('确认批量删除所选角色？此操作不可恢复。')) return;
      }
      if(action === 'ban'){
        hours = parseInt(prompt('封禁时长（小时，0 = 永久）：', '0') || '0', 10);
        if(!Number.isFinite(hours) || hours < 0){
          flash('封禁时长无效', false);
          return;
        }
        reason = prompt('封禁理由：', '后台封禁') || '';
      }
      if(action === 'unban'){
        if(!confirm('确认批量解封所选角色？')) return;
      }

      const res = await formPost('/character/api/bulk', { action, guids, hours, reason });
      if(res && res.success){
        flash(`OK: ${res.ok}/${res.requested}`, true);
        setTimeout(() => location.reload(), 600);
        return;
      }
      const failed = res && typeof res.failed === 'number' ? res.failed : null;
      flash(failed ? `Failed: ${failed}` : ((res && res.message) ? res.message : 'Failed'), false);
    });
  }

  if (window.Panel && typeof window.Panel.whenDomReady === 'function') {
    window.Panel.whenDomReady(setup);
  } else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setup, { once: true });
  } else {
    setup();
  }
})();
