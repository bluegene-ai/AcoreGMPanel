(function(){
  const feedback = document.getElementById('char-feedback');

  const searchParams = new URLSearchParams(location.search);
  const currentServer = searchParams.get('server') || '';

  function withServer(path){
    if(!currentServer) return path;
    if(String(path).includes('server=')) return path;
    return path + (String(path).includes('?') ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  // nfuwow name resolving (character/show)
  const nfCache = { spell: new Map(), skill: new Map(), achievement: new Map(), quest: new Map(), faction: new Map(), achievementcriteria: new Map() };
  const inflight = new Map();

  function buildUrl(path){
    const base = (window.APP_BASE || '').replace(/\/$/, '');
    return (base ? base : '') + withServer(path);
  }

  function chunk(arr, size){
    const out = [];
    for(let i=0;i<arr.length;i+=size){
      out.push(arr.slice(i, i+size));
    }
    return out;
  }

  async function fetchNames(type, ids){
    const key = type + ':' + ids.join(',');
    if(inflight.has(key)) return inflight.get(key);

    const url = buildUrl('/character/api/names?type=' + encodeURIComponent(type) + '&ids=' + encodeURIComponent(ids.join(',')));
    const p = fetch(url, { method: 'GET' })
      .then(r => r.json())
      .catch(() => ({ success: false, names: {} }))
      .finally(() => inflight.delete(key));
    inflight.set(key, p);
    return p;
  }

  async function resolveNamesIn(container){
    if(!container) return;
    const nodes = Array.from(container.querySelectorAll('.js-nfuwow'));
    if(!nodes.length) return;

    const byType = { spell: new Set(), skill: new Set(), achievement: new Set(), quest: new Set(), faction: new Set(), achievementcriteria: new Set() };
    nodes.forEach(el => {
      const type = (el.getAttribute('data-nfuwow-type') || '').toLowerCase();
      const id = parseInt(el.getAttribute('data-nfuwow-id') || '0', 10) || 0;
      if(!id) return;
      if(!(type in byType)) return;
      if(nfCache[type].has(id)) return;
      byType[type].add(id);
    });

    const tasks = [];
    Object.keys(byType).forEach(type => {
      const ids = Array.from(byType[type]);
      chunk(ids, 60).forEach(batch => {
        tasks.push((async () => {
          const json = await fetchNames(type, batch);
          if(!json || !json.success || !json.names) return;
          Object.keys(json.names).forEach(k => {
            const id = parseInt(k, 10) || 0;
            const name = json.names[k];
            nfCache[type].set(id, (typeof name === 'string' && name) ? name : null);
          });
        })());
      });
    });

    if(tasks.length){
      await Promise.all(tasks);
    }

    nodes.forEach(el => {
      const type = (el.getAttribute('data-nfuwow-type') || '').toLowerCase();
      const id = parseInt(el.getAttribute('data-nfuwow-id') || '0', 10) || 0;
      if(!id) return;
      if(!(type in nfCache)) return;
      const name = nfCache[type].get(id);
      if(!name) return;
      const nameEl = el.parentElement ? el.parentElement.querySelector('.js-nfuwow-name') : null;
      if(nameEl && !nameEl.textContent){
        nameEl.textContent = ' - ' + name;
      }
    });
  }

  // Embedded BagQuery items list (character/show inventory tab)
  let bagQueryBooted = false;
  function bootBagQueryItems(){
    if(bagQueryBooted) return;
    const mount = document.getElementById('char-bag-query');
    const table = document.getElementById('bqItemTable');
    if(!mount || !table) return;

    const guid = parseInt(mount.dataset.guid || '0', 10) || 0;
    if(!guid) return;
    const name = mount.dataset.name || '';

    window.__BAG_QUERY_CTX = Object.assign({}, window.__BAG_QUERY_CTX || {}, {
      embed: { guid, name }
    });

    const base = (window.APP_BASE || '').replace(/\/$/, '');
    const src = (base ? base : '') + '/assets/js/modules/bag_query.js';
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    document.head.appendChild(s);
    bagQueryBooted = true;
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

      // Resolve nfuwow names for visible tab content
      resolveNamesIn(target);

      if(targetId === 'inventory'){
        bootBagQueryItems();
      }
    };

    tabs.forEach(tab => {
      tab.addEventListener('click', () => activate(tab));
    });

    // First tab (summary) might not have nfuwow ids; resolve anyway for safety
    const active = document.querySelector('.char-tab-content.active');
    if(active){
      resolveNamesIn(active);
    }
  }

  function flash(msg, ok){
    if(!feedback) return;
    feedback.textContent = msg || (ok ? 'OK' : 'Error');
    feedback.classList.remove('panel-flash--success','panel-flash--danger');
    feedback.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    feedback.style.display = 'block';
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

        const data = new FormData(form);
        if(!data.get('_csrf') && window.__CSRF_TOKEN){
          data.set('_csrf', window.__CSRF_TOKEN);
        }

        try {
          const res = await fetch(endpoint, {
            method: 'POST',
            body: data,
            headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
          });
          const json = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
          flash(json.message || (json.success ? 'OK' : 'Failed'), !!json.success);
        } catch(err){
          flash((err && err.message) ? err.message : 'Network error', false);
        }
      });
    });
  }

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
      td.style.textAlign = 'center';
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
          row.style.display = q ? 'none' : '';
          return;
        }
        const text = (row.innerText || '').toLowerCase();
        const match = !q || text.includes(q);
        row.style.display = match ? '' : 'none';
        if(match) visible++;
      });

      if(q){
        noneRow.style.display = visible === 0 ? '' : 'none';
        if(emptyRow) emptyRow.style.display = 'none';
      } else {
        noneRow.style.display = 'none';
        if(emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
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
})();
