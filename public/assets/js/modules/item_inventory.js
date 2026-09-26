/**
 * File: public/assets/js/modules/item_inventory.js
 * Purpose: 统一物品/背包模块的前端控制器。
 *
 * 一套面板两个轴向：角色模式（搜角色 → 看背包 → 删堆叠）/ 物品模式（搜物品 → 定位持有者 → 批量删改）。
 * 角色详情页以嵌入模式启动同一脚本（先发 window.__ITEM_INVENTORY_CTX = { embed: {...} }，只渲染共用物品面板）。
 */

(function () {
  const qs = (sel, root = document) => root.querySelector(sel);
  /** Base path of the panel install: window.APP_BASE is never set by the server. */
  const resolveBasePath = () => ((window.Panel && window.Panel.basePath && window.Panel.basePath()) || (document.body && document.body.dataset && document.body.dataset.appBase) || '').replace(/\/$/, '');
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const apiBase = resolveBasePath();
  const hasPanelApi = !!(window.Panel && window.Panel.api);
  const csrf = window.__CSRF_TOKEN;
  const ctx = window.__ITEM_INVENTORY_CTX || {};
  const ctxPrefill = ctx.prefill || null;
  const ctxAutoSearch = !!ctx.autoSearch;
  const ctxCanManage = ctx.canManage !== false;
  const ctxLabels = ctx.labels || {};

  // 面板上下文由共用的物品面板自己输出，因此它是这个面板实例的权威描述
  const panelConfig = window.__ITEM_INVENTORY_PANEL || {};
  const panelShowDelete = panelConfig.showDelete !== false;
  const panelShowOwners = panelConfig.showOwners === true;
  // 物品编辑器的 base href（已含 base_path 与 ?edit_id=），无查看权限时为 null
  const panelEditUrl = typeof panelConfig.editUrl === 'string' && panelConfig.editUrl !== '' ? panelConfig.editUrl : null;

  // 嵌入模式（角色详情页背包 Tab）由角色页渲染的挂载点决定，而不是上下文负载：
  // character.js 动态注入本脚本，模块体可能早于 __ITEM_INVENTORY_CTX 赋值可见就执行。
  const embedMount = () => document.getElementById('char-bag-query');

  function resolveEmbed() {
    const mount = embedMount();
    if (!mount) return null;
    const guid = parseInt((ctx.embedGuid ?? (ctx.embed && ctx.embed.guid) ?? mount.dataset.guid ?? 0), 10) || 0;
    if (guid <= 0) return null;
    const name = ctx.embedName ?? (ctx.embed && ctx.embed.name) ?? mount.dataset.name ?? '';
    return { guid, name: String(name || '') };
  }

  const currentServer = new URLSearchParams(window.location.search).get('server') || '';

  const moduleTranslator = window.Panel && typeof window.Panel.createModuleTranslator === 'function'
    ? window.Panel.createModuleTranslator('item_inventory')
    : null;

  const CLASS_SLUGS = {
    1: 'warrior', 2: 'paladin', 3: 'hunter', 4: 'rogue', 5: 'priest', 6: 'death-knight',
    7: 'shaman', 8: 'mage', 9: 'warlock', 10: 'monk', 11: 'druid', 12: 'demon-hunter'
  };

  const Feedback = (window.Panel && window.Panel.feedback) ? window.Panel.feedback : {
    success(target, message) { flash(target, 'panel-flash--success', message); },
    error(target, message) { flash(target, 'panel-flash--error', message); },
    info(target, message) { flash(target, 'panel-flash--info', message); },
    clear(target) {
      const el = resolveFlash(target);
      if (!el) return;
      el.classList.remove('is-visible', 'panel-flash--success', 'panel-flash--error', 'panel-flash--info');
      el.textContent = '';
    }
  };

  /* ---- Small helpers ---- */

  function translate(path, fallback, replacements) {
    let text = moduleTranslator ? moduleTranslator(path, fallback ?? path) : (fallback ?? path);
    if (typeof text === 'string' && replacements && typeof replacements === 'object') {
      Object.entries(replacements).forEach(([key, value]) => {
        const pattern = new RegExp(':' + key + '(?![A-Za-z0-9_])', 'g');
        text = text.replace(pattern, String(value ?? ''));
      });
    }
    return text;
  }

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function resolveFlash(target) {
    if (!target) return null;
    if (typeof target === 'string') return qs(target);
    if (target instanceof HTMLElement) return target;
    return null;
  }

  function flash(target, variant, message) {
    const el = resolveFlash(target);
    if (!el) return;
    el.classList.add('panel-flash');
    ['panel-flash--success', 'panel-flash--error', 'panel-flash--info'].forEach((cls) => el.classList.remove(cls));
    if (variant) el.classList.add('panel-flash--' + variant);
    el.textContent = String(message ?? '');
    el.classList.add('is-visible');
    clearTimeout(el.__iiTimer);
    el.__iiTimer = setTimeout(() => {
      el.classList.remove('is-visible');
      el.textContent = '';
    }, 5200);
  }

  function withServer(path) {
    if (!currentServer) return path;
    if (String(path).includes('server=')) return path;
    return path + (String(path).includes('?') ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  function absolute(path) {
    const base = apiBase || '';
    return withServer(base + path);
  }

  function qualityIndex(value) {
    const num = typeof value === 'number' ? value : parseInt(value, 10);
    return Number.isNaN(num) || num < 0 || num > 7 ? -1 : num;
  }

  function qualityClass(index) {
    return index >= 0 ? 'item-quality-q' + index : 'item-quality-unknown';
  }

  function qualityLabel(index) {
    if (index < 0) return translate('quality.unknown', '?');
    return translate('quality.' + index, 'Q' + index);
  }

  function classSlug(classId) {
    const enums = window.APP_ENUMS && window.APP_ENUMS.classes;
    const meta = enums ? enums[classId] : null;
    if (meta && typeof meta === 'object' && meta.slug) return meta.slug;
    return CLASS_SLUGS[classId] || null;
  }

  /* ---- API access ---- */

  async function request(method, path, payload) {
    if (hasPanelApi) {
      try {
        return method === 'POST'
          ? await window.Panel.api.post(path, payload || {})
          : await window.Panel.api.get(path, payload || {});
      } catch (err) {
        return { success: false, message: err && err.message ? err.message : translate('errors.network', 'Network error') };
      }
    }

    try {
      const url = absolute(path + (method === 'GET' && payload ? '?' + new URLSearchParams(payload).toString() : ''));
      const init = { method, credentials: 'same-origin', headers: {} };
      if (method === 'POST') {
        const fd = new FormData();
        Object.entries(payload || {}).forEach(([key, value]) => {
          if (Array.isArray(value)) {
            value.forEach((entry) => fd.append(key + '[]', entry));
          } else {
            fd.append(key, value);
          }
        });
        if (csrf && !fd.has('_csrf')) fd.append('_csrf', csrf);
        init.body = fd;
      }
      const res = await fetch(url, init);
      return await res.json();
    } catch (err) {
      return { success: false, message: err && err.message ? err.message : translate('errors.parse_failed', 'Failed to parse response') };
    }
  }

  const api = {
    characters: (type, value) => request('GET', '/item-inventory/api/characters', { type, value }),
    characterItems: (guid) => request('GET', '/item-inventory/api/character-items', { guid }),
    searchItems: (keyword) => request('GET', '/item-inventory/api/search-items', { keyword }),
    ownership: (entry, page, perPage) => request('GET', '/item-inventory/api/ownership', {
      entry, page: page || 1, per_page: perPage || 100, paginate: 1
    }),
    reduce: (payload) => request('POST', '/item-inventory/api/reduce', payload),
    bulk: (payload) => request('POST', '/item-inventory/api/bulk', payload)
  };

  /* ---- State ---- */

  const state = {
    mode: 'character',
    characterGuid: 0,
    characterName: '',
    characterItems: [],
    ownershipItem: null,
    ownershipRows: [],
    ownershipSummary: null,
    ownershipPage: 1,
    ownershipPerPage: 100,
    selectedInstances: new Set(),
    deleteCtx: null,
    busy: false
  };

  const ownsCharacterPanel = () => !!qs('[data-mode-panel="character"]');

  /* ---- Mode switching ---- */

  function setMode(mode) {
    if (mode !== 'character' && mode !== 'item') return;
    if (mode === 'item' && !ownsCharacterPanel()) return;

    state.mode = mode;
    const layout = qs('#iiLayout');
    if (layout) layout.dataset.mode = mode;

    qsa('[data-mode-panel]').forEach((panel) => {
      panel.hidden = panel.dataset.modePanel !== mode;
    });
    qsa('.ii-tab').forEach((tab) => {
      const active = tab.dataset.mode === mode;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    // Keep the URL shareable without reloading the page.
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('mode', mode);
      window.history.replaceState({}, '', url.toString());
    } catch (err) { /* ignore */ }

    updateBulkButtons();
  }

  function bindTabs() {
    qsa('.ii-tab').forEach((tab) => {
      tab.addEventListener('click', () => setMode(tab.dataset.mode));
    });
  }

  /* ---- Character axis ---- */

  function updateItemsSubtitle(extra) {
    const el = qs('#iiItemsCurrent');
    if (!el) return;
    if (!state.characterGuid) {
      el.textContent = translate('items.subtitle.none', 'No character selected');
      return;
    }
    const base = state.characterName
      ? translate('items.subtitle.current_name', 'Current character: :name', { name: state.characterName })
      : translate('items.subtitle.current_guid', 'Current character GUID :guid', { guid: state.characterGuid });
    el.textContent = extra
      ? translate('items.subtitle.with_status', ':base (:status)', { base, status: extra })
      : base;
  }

  function resetItemsPlaceholder() {
    const tb = qs('#iiItemTable tbody');
    if (tb) {
      tb.innerHTML = `<tr><td colspan="${itemsColumnSpan()}" class="text-center muted">${esc(translate('items.placeholder.none', 'No character selected'))}</td></tr>`;
    }
    updateItemsSubtitle();
  }

  function itemsColumnSpan() {
    if (panelConfig.columns) return panelConfig.columns;
    const table = qs('#iiItemTable');
    if (!table) return 6;
    const headCount = table.querySelectorAll('thead th').length;
    return headCount || 6;
  }

  function activateCharacterRow() {
    qsa('#iiCharTable tbody tr').forEach((tr) => {
      const guid = parseInt(tr.dataset.guid || '0', 10);
      tr.classList.toggle('ii-row--active', guid === state.characterGuid && guid > 0);
    });
  }

  async function runCharacterSearch(type, value, options) {
    const opts = options || {};
    const trimmed = typeof value === 'string' ? value.trim() : '';
    if (!trimmed) {
      if (!opts.fromPrefill) {
        Feedback.error('#iiCharFlash', translate('character.validation.empty', 'Please enter a search value'));
        const input = qs('#iiCharValue');
        if (input) input.focus();
      }
      return false;
    }

    const safeType = ['character_name', 'username'].includes(type) ? type : 'character_name';
    state.characterGuid = 0;
    state.characterName = '';
    state.characterItems = [];
    renderCharacterLoading();
    resetItemsPlaceholder();
    Feedback.clear('#iiCharFlash');

    const res = await api.characters(safeType, trimmed);
    if (!res || res.success !== true) {
      renderCharacterError((res && res.message) || translate('character.error.failed', 'Query failed'));
      return false;
    }

    renderCharacters(res.data || []);
    const valueNode = qs('#iiCharValue');
    if (valueNode) valueNode.value = trimmed;
    const typeNode = qs('#iiCharType');
    if (typeNode) typeNode.value = safeType;
    return true;
  }

  function renderCharacterLoading() {
    const tb = qs('#iiCharTable tbody');
    if (tb) tb.innerHTML = `<tr><td colspan="6" class="text-center muted">${esc(translate('status.loading', 'Loading...'))}</td></tr>`;
  }

  function renderCharacterError(message) {
    const tb = qs('#iiCharTable tbody');
    if (tb) tb.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${esc(message)}</td></tr>`;
    Feedback.error('#iiCharFlash', message);
  }

  function renderCharacters(rows) {
    const tb = qs('#iiCharTable tbody');
    if (!tb) return;
    if (!rows.length) {
      tb.innerHTML = `<tr><td colspan="6" class="text-center muted">${esc(translate('character.empty', 'No results'))}</td></tr>`;
      return;
    }

    const viewLabel = translate('actions.view_items', ctxLabels.view || 'View items');

    tb.innerHTML = rows.map((row) => {
      const guid = parseInt(row.guid, 10);
      const classId = parseInt(row.class, 10);
      const slug = classSlug(classId);
      const classCls = slug ? ` ii-row--class ii-class--${slug}` : '';
      const active = guid === state.characterGuid && guid > 0 ? ' ii-row--active' : '';
      const accountId = parseInt(row.account_id, 10);
      const accountName = typeof row.account_username === 'string' && row.account_username.trim()
        ? row.account_username.trim()
        : '';
      let accountCell;
      if (accountName) {
        accountCell = `<a href="${esc(absolute('/account/view?id=' + accountId))}">${esc(accountName)}</a>`;
      } else if (Number.isFinite(accountId) && accountId > 0) {
        accountCell = `<a href="${esc(absolute('/account/view?id=' + accountId))}">#${accountId}</a>`;
      } else {
        accountCell = '&#8212;';
      }

      return `<tr class="ii-row${classCls}${active}" data-guid="${guid}" data-name="${esc(row.name)}" data-class="${Number.isFinite(classId) ? classId : ''}">`
        + `<td>${esc(row.guid)}</td>`
        + `<td class="ii-name"><a href="${esc(absolute('/character/view?guid=' + guid))}">${esc(row.name)}</a></td>`
        + `<td>${esc(row.level)}</td>`
        + `<td>${esc(row.race)}</td>`
        + `<td>${accountCell}</td>`
        + `<td><button type="button" class="btn-sm btn info" data-act="items">${esc(viewLabel)}</button></td>`
        + `</tr>`;
    }).join('');

    tb.querySelectorAll('button[data-act="items"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr');
        if (!tr) return;
        loadCharacterItems(tr.dataset.guid, tr.dataset.name);
      });
    });

    activateCharacterRow();
  }

  async function loadCharacterItems(guid, name) {
    state.characterGuid = parseInt(guid, 10) || 0;
    state.characterName = name || '';
    resetSelection();
    updateItemsSubtitle(translate('status.loading', 'Loading...'));
    activateCharacterRow();
    renderItemsLoading();
    Feedback.clear('#iiActionFlash');

    const res = await api.characterItems(guid);
    if (!res || res.success !== true) {
      renderItemsError((res && res.message) || translate('items.error.load_failed', 'Failed to load items'));
      return;
    }

    state.characterItems = res.data || [];
    renderItems(state.characterItems);
  }

  function renderItemsLoading() {
    const tb = qs('#iiItemTable tbody');
    if (!tb) return;
    const message = state.characterGuid
      ? translate('status.loading', 'Loading...')
      : translate('items.placeholder.none', 'No character selected');
    tb.innerHTML = `<tr><td colspan="${itemsColumnSpan()}" class="text-center muted">${esc(message)}</td></tr>`;
  }

  function renderItemsError(message) {
    const tb = qs('#iiItemTable tbody');
    if (tb) tb.innerHTML = `<tr><td colspan="${itemsColumnSpan()}" class="text-center text-danger">${esc(message)}</td></tr>`;
    updateItemsSubtitle(translate('items.error.load_failed', 'Failed to load items'));
    Feedback.error('#iiActionFlash', message);
  }

  function renderItems(rows) {
    const tb = qs('#iiItemTable tbody');
    if (!tb) return;
    updateItemsSubtitle();

    if (!rows || !rows.length) {
      const message = state.characterGuid
        ? translate('items.empty', 'No items found')
        : translate('items.placeholder.none', 'No character selected');
      tb.innerHTML = `<tr><td colspan="${itemsColumnSpan()}" class="text-center muted">${esc(message)}</td></tr>`;
      return;
    }

    const showOwners = panelShowOwners;
    const deleteLabel = translate('actions.delete', ctxLabels.delete || 'Delete');
    const ownersLabel = translate('actions.view_owners', 'View owners');

    tb.innerHTML = rows.map((row) => {
      const qIdx = qualityIndex(row.quality);
      const fallbackName = '#' + (row.entry || '');
      const rawName = (row.name || '').trim();
      const displayName = rawName || fallbackName;
      const nameHtml = `<span class="item-quality ${qualityClass(qIdx)}">${esc(displayName)}</span>`;
      // Deep link into the item editor; keeps the current realm via the panel href.
      const entryCell = panelEditUrl && row.entry > 0
        ? `<a class="ii-entry-link" href="${esc(panelEditUrl + row.entry)}" title="${esc(translate('items.open_in_editor', 'Open in items management'))}">${esc(row.entry)}</a>`
        : esc(row.entry);
      const filterName = displayName.toLowerCase();
      const location = row.location_label || '-';
      const suffix = row.is_container && row.contained_count > 0
        ? `<span class="ii-badge" title="${esc(translate('items.contains', 'Contains items'))}">${esc(translate('items.contains_count', '+:count in bag', { count: row.contained_count }))}</span>`
        : '';

      const actions = [];
      if (panelShowDelete) {
        actions.push(`<button type="button" class="btn-sm btn danger" data-act="del" data-inst="${row.instance_guid}">${esc(deleteLabel)}</button>`);
      }
      if (showOwners) {
        actions.push(`<button type="button" class="btn-sm btn neutral" data-act="owners" data-entry="${row.entry}">${esc(ownersLabel)}</button>`);
      }

      return `<tr data-name="${esc(filterName)}" data-inst="${row.instance_guid}">`
        + `<td>${esc(row.instance_guid)}</td>`
        + `<td>${entryCell}</td>`
        + `<td>${nameHtml}${suffix}</td>`
        + `<td>${esc(row.count)}</td>`
        + `<td>${esc(location)}</td>`
        + `<td>${actions.length ? actions.join(' ') : '&#8212;'}</td>`
        + `</tr>`;
    }).join('');

    tb.querySelectorAll('button[data-act="del"]').forEach((btn) => {
      btn.addEventListener('click', () => openDeleteModal(btn.dataset.inst));
    });
    tb.querySelectorAll('button[data-act="owners"]').forEach((btn) => {
      btn.addEventListener('click', () => locateOwners(parseInt(btn.dataset.entry, 10)));
    });
  }

  /** "查看持有者"：切到物品轴向并加载该条目；嵌入模式下没有角色面板，因此是无操作。 */
  async function locateOwners(entry) {
    if (!entry || Number.isNaN(entry)) return;
    setMode('item');
    const keyword = qs('#iiItemKeyword');
    if (keyword) keyword.value = String(entry);
    await runItemSearch(String(entry));
    await loadOwnership(entry, 1);
  }

  function bindItemFilter() {
    const input = qs('#iiItemFilter');
    if (!input) return;
    input.setAttribute('placeholder', translate('items.filter.placeholder', 'Filter items by name'));
    input.addEventListener('input', () => {
      const value = input.value.trim().toLowerCase();
      qsa('#iiItemTable tbody tr').forEach((tr) => {
        if (!value) {
          tr.hidden = false;
          return;
        }
        const name = tr.getAttribute('data-name') || '';
        tr.hidden = !name.includes(value);
      });
    });
  }

  /* ---- Item axis ---- */

  async function runItemSearch(keyword) {
    const trimmed = typeof keyword === 'string' ? keyword.trim() : '';
    const tb = qs('#iiSearchTable tbody');
    if (!trimmed) {
      Feedback.error('#iiItemSearchFlash', translate('item.validation.empty', 'Please input keyword'));
      const input = qs('#iiItemKeyword');
      if (input) input.focus();
      return false;
    }

    Feedback.clear('#iiItemSearchFlash');
    if (tb) tb.innerHTML = `<tr><td colspan="5" class="text-center muted">${esc(translate('status.loading', 'Loading...'))}</td></tr>`;

    const res = await api.searchItems(trimmed);
    if (!res || res.success !== true) {
      const message = (res && res.message) || translate('item.error.failed', 'Search failed');
      if (tb) tb.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${esc(message)}</td></tr>`;
      Feedback.error('#iiItemSearchFlash', message);
      return false;
    }

    renderSearchResults(res.data || []);
    return true;
  }

  function renderSearchResults(rows) {
    const tb = qs('#iiSearchTable tbody');
    if (!tb) return;
    if (!rows.length) {
      tb.innerHTML = `<tr><td colspan="5" class="text-center muted">${esc(translate('item.search.empty', 'No items found'))}</td></tr>`;
      return;
    }

    const viewLabel = translate('item.search.view', 'View owners');

    tb.innerHTML = rows.map((row) => {
      const qIdx = qualityIndex(row.quality);
      const stackable = typeof row.stackable === 'number' && row.stackable > 1 ? row.stackable : '-';
      const editorHref = panelEditUrl && row.entry > 0 ? panelEditUrl + row.entry : null;
      const nameCell = editorHref
        ? `<a class="ii-entry-link" href="${esc(editorHref)}" title="${esc(translate('item.search.open_in_editor', 'Open in items management'))}">${esc(row.name || row.name_en || ('#' + row.entry))}</a>`
        : esc(row.name || row.name_en || ('#' + row.entry));
      return `<tr data-entry="${row.entry}">`
        + `<td>${esc(row.entry)}</td>`
        + `<td>${nameCell}</td>`
        + `<td><span class="item-quality ${qualityClass(qIdx)}">${esc(qualityLabel(qIdx))}</span></td>`
        + `<td>${esc(stackable)}</td>`
        + `<td><button type="button" class="btn-sm btn info" data-act="owners" data-entry="${row.entry}">${esc(viewLabel)}</button></td>`
        + `</tr>`;
    }).join('');

    tb.querySelectorAll('button[data-act="owners"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const entry = parseInt(btn.dataset.entry, 10);
        if (Number.isNaN(entry)) return;
        qsa('#iiSearchTable tbody tr').forEach((tr) => tr.classList.remove('ii-row--active'));
        const tr = btn.closest('tr');
        if (tr) tr.classList.add('ii-row--active');
        loadOwnership(entry, 1);
      });
    });
  }

  async function loadOwnership(entry, page) {
    state.ownershipItem = null;
    state.ownershipRows = [];
    state.ownershipSummary = null;
    resetSelection();

    const titleEl = qs('#iiOwnerTitle');
    const summaryEl = qs('#iiOwnerSummary');
    const tb = qs('#iiOwnerTable tbody');
    if (titleEl) titleEl.textContent = translate('owners.title_loading', 'Loading ownership...');
    if (summaryEl) summaryEl.textContent = '';
    if (tb) tb.innerHTML = `<tr><td colspan="6" class="text-center muted">${esc(translate('status.loading', 'Loading...'))}</td></tr>`;

    const res = await api.ownership(entry, page || 1, state.ownershipPerPage);
    if (!res || res.success !== true) {
      const message = (res && res.message) || translate('owners.error.load_failed', 'Failed to load ownership');
      if (tb) tb.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${esc(message)}</td></tr>`;
      if (titleEl) titleEl.textContent = translate('owners.title_error', 'Failed to load');
      Feedback.error('#iiOwnerFlash', message);
      return false;
    }

    const data = res.data || {};
    state.ownershipItem = data.item || null;
    state.ownershipRows = data.rows || [];
    state.ownershipSummary = data.summary || null;
    state.ownershipPage = data.page || 1;

    updateOwnershipHeader();
    renderOwnerRows();
    updateBulkButtons();
    return true;
  }

  function updateOwnershipHeader() {
    const titleEl = qs('#iiOwnerTitle');
    const summaryEl = qs('#iiOwnerSummary');
    if (!titleEl || !summaryEl) return;

    if (!state.ownershipItem) {
      titleEl.textContent = translate('owners.title_empty', 'Select an item');
      summaryEl.textContent = translate('owners.subtitle_empty', 'Search an item to view ownership');
      return;
    }

    const name = state.ownershipItem.name || state.ownershipItem.name_en || ('#' + state.ownershipItem.entry);
    titleEl.textContent = `${name} (#${state.ownershipItem.entry})`;

    const summary = state.ownershipSummary || { characters: 0, instances: 0, count: 0, pages: 0, page: 1 };
    summaryEl.textContent = translate(
      'owners.subtitle_totals',
      ':characters characters · :instances stacks · total :count',
      { characters: summary.characters ?? 0, instances: summary.instances ?? 0, count: summary.count ?? 0 }
    );
  }

  function renderOwnerRows() {
    const tb = qs('#iiOwnerTable tbody');
    if (!tb) return;

    if (!state.ownershipRows.length) {
      tb.innerHTML = `<tr><td colspan="6" class="text-center muted">${esc(translate('owners.table.empty', 'No instances'))}</td></tr>`;
      updatePager(0);
      return;
    }

    tb.innerHTML = state.ownershipRows.map((row) => {
      const checked = state.selectedInstances.has(row.instance_guid) ? 'checked' : '';
      const containerParts = [];
      if (row.container) {
        if (row.container.location_label) containerParts.push(row.container.location_label);
        if (row.container.name) containerParts.push(row.container.name);
      }
      const containerText = containerParts.join(' · ') || '-';
      const charName = row.character_name || ('#' + row.character_guid);
      const slug = classSlug(row.character_class);
      const classCls = slug ? ` class="ii-class--${slug}"` : '';

      return `<tr data-instance="${row.instance_guid}">`
        + `<td><input type="checkbox" data-role="select-instance" value="${row.instance_guid}" ${checked}></td>`
        + `<td>${esc(row.instance_guid)}</td>`
        + `<td><a href="${esc(absolute('/character/view?guid=' + row.character_guid))}"${classCls}>${esc(charName)}</a></td>`
        + `<td>${esc(row.count)}</td>`
        + `<td>${esc(row.location_label || row.location_code || '-')}</td>`
        + `<td>${esc(containerText)}</td>`
        + `</tr>`;
    }).join('');

    tb.querySelectorAll('input[data-role="select-instance"]').forEach((input) => {
      input.addEventListener('change', () => {
        const value = parseInt(input.value, 10);
        if (Number.isNaN(value)) return;
        if (input.checked) state.selectedInstances.add(value);
        else state.selectedInstances.delete(value);
        updateBulkButtons();
      });
    });

    const selectAll = qs('#iiSelectAll');
    if (selectAll) {
      selectAll.checked = state.ownershipRows.length > 0
        && state.ownershipRows.every((row) => state.selectedInstances.has(row.instance_guid));
    }

    updatePager(state.ownershipSummary ? state.ownershipSummary.pages : 0);
  }

  function updatePager(pages) {
    const pager = qs('#iiOwnerPager');
    const info = qs('#iiOwnerPageInfo');
    const prev = qs('#iiOwnerPrev');
    const next = qs('#iiOwnerNext');
    if (!pager) return;

    const total = pages || 0;
    pager.hidden = total <= 1;
    if (info) {
      info.textContent = translate('owners.pager.info', 'Page :page / :pages', {
        page: state.ownershipPage,
        pages: total || 1
      });
    }
    if (prev) prev.disabled = state.ownershipPage <= 1;
    if (next) next.disabled = total === 0 || state.ownershipPage >= total;
  }

  function resetSelection() {
    state.selectedInstances = new Set();
    updateBulkButtons();
  }

  function updateBulkButtons() {
    const deleteBtn = qs('#iiBulkDeleteBtn');
    const replaceBtn = qs('#iiBulkReplaceBtn');
    const hasSelection = state.selectedInstances.size > 0 && state.mode === 'item';
    if (deleteBtn) deleteBtn.disabled = !hasSelection;
    if (replaceBtn) replaceBtn.disabled = !hasSelection;
  }

  /* ---- Modals ---- */

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('active');
    document.body.classList.add('modal-open');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('active');
    if (!qs('.modal-backdrop.active')) document.body.classList.remove('modal-open');
  }

  function bindModalDismiss(modal) {
    if (!modal || modal.__iiDismissBound) return;
    modal.addEventListener('click', (event) => {
      const target = event.target;
      if (target === modal || (target && target.dataset && Object.prototype.hasOwnProperty.call(target.dataset, 'close'))) {
        closeModal(modal);
      }
    });
    modal.__iiDismissBound = true;
  }

  /* ---- reduce one instance ---- */

  function openDeleteModal(instanceGuid) {
    const instance = parseInt(instanceGuid, 10) || 0;
    const row = state.characterItems.find((item) => item.instance_guid === instance);
    if (!row) return;
    state.deleteCtx = row;

    const modal = qs('#iiDeleteModal');
    if (!modal) return;

    const info = qs('#iiDelInfo', modal);
    if (info) {
      info.textContent = translate(
        'modal.delete.info',
        'Item #:entry :name — current stack :count — instance GUID :inst',
        { entry: row.entry, name: row.name || ('#' + row.entry), count: row.count, inst: row.instance_guid }
      );
    }

    const qty = qs('#iiDelQty', modal);
    const ok = qs('#iiDelOk', modal);
    if (!qty || !ok) return;

    const max = row.count > 0 ? row.count : 1;
    qty.value = 1;
    qty.setAttribute('max', String(max));
    qty.setAttribute('min', '1');

    const destroyRow = qs('#iiDelDestroyRow', modal);
    const destroyBox = qs('#iiDelDestroy', modal);
    const hasContents = !!(row.is_container && row.contained_count > 0);
    if (destroyRow) destroyRow.hidden = !hasContents;
    if (destroyBox) destroyBox.checked = false;

    if (qty.__iiHandler) qty.removeEventListener('input', qty.__iiHandler);
    qty.__iiHandler = () => validateQuantity(qty, ok);
    qty.addEventListener('input', qty.__iiHandler);

    if (ok.__iiHandler) ok.removeEventListener('click', ok.__iiHandler);
    ok.__iiHandler = (event) => {
      event.preventDefault();
      confirmReduce();
    };
    ok.addEventListener('click', ok.__iiHandler);

    Feedback.clear('#iiDelFeedback');
    bindModalDismiss(modal);
    validateQuantity(qty, ok);
    openModal(modal);
    qty.focus();
    qty.select();
  }

  function validateQuantity(qty, ok) {
    const value = parseInt(qty.value || '0', 10);
    const max = state.deleteCtx ? state.deleteCtx.count : 0;
    const valid = value > 0 && value <= max;
    ok.disabled = !valid;

    const destroys = !!(qs('#iiDelDestroy') && qs('#iiDelDestroy').checked);
    const needsDestroy = !!(state.deleteCtx && state.deleteCtx.is_container && state.deleteCtx.contained_count > 0);
    const wouldDeleteAll = value >= max;

    if (needsDestroy && wouldDeleteAll && !destroys) {
      Feedback.error('#iiDelFeedback', translate(
        'modal.delete.container_requires_confirm',
        'This bag still holds :count item(s). Tick the box to destroy them together.',
        { count: state.deleteCtx.contained_count }
      ));
      ok.disabled = true;
      return;
    }

    if (!valid) {
      Feedback.error('#iiDelFeedback', translate('modal.delete.validation.quantity', 'Quantity must be greater than 0 and no more than the current stack'));
      return;
    }

    Feedback.clear('#iiDelFeedback');
  }

  async function confirmReduce() {
    if (state.busy || !state.deleteCtx) return;

    const modal = qs('#iiDeleteModal');
    const qtyInput = qs('#iiDelQty', modal);
    const ok = qs('#iiDelOk', modal);
    if (!qtyInput || !ok) return;

    const qty = parseInt(qtyInput.value || '0', 10);
    const ctxRow = state.deleteCtx;
    const destroyBox = qs('#iiDelDestroy', modal);

    state.busy = true;
    const originalLabel = ok.textContent;
    ok.textContent = translate('actions.processing', ctxLabels.processing || 'Processing...');
    ok.disabled = true;

    const res = await api.reduce({
      character_guid: state.characterGuid,
      item_instance_guid: ctxRow.instance_guid,
      quantity: qty,
      item_entry: ctxRow.entry,
      destroy_contents: destroyBox && destroyBox.checked ? 1 : 0
    });

    state.busy = false;
    ok.textContent = originalLabel;
    ok.disabled = false;

    if (res && res.success) {
      closeModal(modal);
      Feedback.clear('#iiDelFeedback');
      Feedback.success('#iiActionFlash', res.message || translate('modal.delete.success', 'Item removed'));

      const newCount = typeof res.new_count === 'number' ? res.new_count : 0;
      state.characterItems = state.characterItems
        .map((item) => {
          if (item.instance_guid !== ctxRow.instance_guid) return item;
          return Object.assign({}, item, { count: newCount });
        })
        .filter((item) => item.count > 0);
      renderItems(state.characterItems);
      state.deleteCtx = null;
      return;
    }

    const message = (res && res.message) || translate('modal.delete.error', 'Operation failed');
    Feedback.error('#iiDelFeedback', message);

    // 容器拒绝时给出明确的确认控件，而不是让操作者无路可走
    const destroyRow = qs('#iiDelDestroyRow', modal);
    if (destroyRow && res && typeof res.contained === 'number' && res.contained > 0) {
      destroyRow.hidden = false;
      Feedback.error('#iiDelFeedback', message);
    }
  }

  /* ---- bulk delete ---- */

  function bindBulkDelete() {
    const btn = qs('#iiBulkDeleteBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
      if (!state.selectedInstances.size) return;
      openBulkDeleteModal();
    });

    const confirmBtn = qs('#iiBulkDeleteConfirm');
    if (confirmBtn) confirmBtn.addEventListener('click', runBulkDelete);
  }

  function openBulkDeleteModal() {
    const modal = qs('#iiBulkDeleteModal');
    if (!modal) return;

    const selected = Array.from(state.selectedInstances);
    const info = qs('#iiBulkDeleteInfo', modal);
    if (info) {
      info.textContent = translate(
        'modal.bulk_delete.info',
        'You are about to delete :count selected item instance(s). This cannot be undone.',
        { count: selected.length }
      );
    }

    const list = qs('#iiBulkDeleteList', modal);
    if (list) list.innerHTML = '';

    const feedback = qs('#iiBulkDeleteFeedback', modal);
    if (feedback) Feedback.clear(feedback);
    const destroyBox = qs('#iiBulkDestroy', modal);
    if (destroyBox) destroyBox.checked = false;

    const confirmBtn = qs('#iiBulkDeleteConfirm');
    if (confirmBtn) confirmBtn.disabled = false;

    bindModalDismiss(modal);
    openModal(modal);
  }

  async function runBulkDelete() {
    if (state.busy) return;
    const modal = qs('#iiBulkDeleteModal');
    const confirmBtn = qs('#iiBulkDeleteConfirm');
    const destroyBox = qs('#iiBulkDestroy', modal);
    const list = qs('#iiBulkDeleteList', modal);

    const selected = Array.from(state.selectedInstances);
    if (!selected.length) return;

    state.busy = true;
    if (confirmBtn) confirmBtn.disabled = true;

    const res = await api.bulk({
      action: 'delete',
      instances: selected,
      destroy_contents: destroyBox && destroyBox.checked ? 1 : 0
    });

    state.busy = false;
    if (confirmBtn) confirmBtn.disabled = false;

    if (res && res.success) {
      closeModal(modal);
      Feedback.success('#iiOwnerFlash', res.message || translate('modal.bulk_delete.success', 'Deleted'));
      if (state.ownershipItem) await loadOwnership(state.ownershipItem.entry, state.ownershipPage);
      return;
    }

    // Report per-instance failures instead of a single opaque summary.
    if (list && res && Array.isArray(res.failed) && res.failed.length) {
      const entryNames = new Map(state.ownershipRows.map((row) => [row.instance_guid, row]));
      list.innerHTML = `<div class="muted small">${esc(translate('modal.bulk_delete.failed_title', 'These instances could not be deleted:'))}</div>`
        + '<ul>' + res.failed.map((entry) => {
          const row = entryNames.get(entry.instance);
          const label = row ? `#${row.instance_guid} (${row.character_name || row.character_guid})` : `#${entry.instance}`;
          return `<li><strong>${esc(label)}</strong> — ${esc(entry.message)}</li>`;
        }).join('') + '</ul>';
    }

    const message = (res && res.message) || translate('modal.bulk_delete.error', 'Delete failed');
    Feedback.error('#iiBulkDeleteFeedback', message);
    if (state.ownershipItem) await loadOwnership(state.ownershipItem.entry, state.ownershipPage);
  }

  /* ---- bulk replace ---- */

  function bindBulkReplace() {
    const btn = qs('#iiBulkReplaceBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
      if (!state.selectedInstances.size) return;
      openReplaceModal();
    });

    const confirmBtn = qs('#iiReplaceConfirm');
    if (confirmBtn) confirmBtn.addEventListener('click', runBulkReplace);
  }

  function openReplaceModal() {
    const modal = qs('#iiReplaceModal');
    if (!modal) return;
    const input = qs('#iiReplaceEntry', modal);
    if (input) {
      input.value = '';
      input.focus();
    }
    const feedback = qs('#iiReplaceFeedback', modal);
    if (feedback) Feedback.clear(feedback);
    const confirmBtn = qs('#iiReplaceConfirm');
    if (confirmBtn) confirmBtn.disabled = false;
    bindModalDismiss(modal);
    openModal(modal);
  }

  async function runBulkReplace() {
    if (state.busy) return;

    const modal = qs('#iiReplaceModal');
    const input = qs('#iiReplaceEntry', modal);
    const confirmBtn = qs('#iiReplaceConfirm', modal);
    const value = input ? parseInt(input.value, 10) : NaN;

    if (!value || Number.isNaN(value) || value <= 0) {
      Feedback.error('#iiReplaceFeedback', translate('modal.replace.validation.entry', 'Enter a valid item entry'));
      if (input) input.focus();
      return;
    }

    state.busy = true;
    if (confirmBtn) confirmBtn.disabled = true;

    const res = await api.bulk({
      action: 'replace',
      new_entry: value,
      instances: Array.from(state.selectedInstances)
    });

    state.busy = false;
    if (confirmBtn) confirmBtn.disabled = false;

    if (res && res.success) {
      closeModal(modal);
      Feedback.success('#iiOwnerFlash', res.message || translate('modal.replace.success', 'Replaced'));
      if (state.ownershipItem) await loadOwnership(state.ownershipItem.entry, state.ownershipPage);
      return;
    }

    let message = (res && res.message) || translate('modal.replace.error', 'Replace failed');
    if (res && Array.isArray(res.failed) && res.failed.length) {
      const details = res.failed
        .map((entry) => `#${entry.instance}: ${entry.message}`)
        .join(' · ');
      message += ' — ' + details;
    }
    Feedback.error('#iiReplaceFeedback', message);
  }

  /* ---- Wiring ---- */

  function bindSelectAll() {
    const selectAll = qs('#iiSelectAll');
    if (!selectAll) return;
    selectAll.addEventListener('change', () => {
      qsa('#iiOwnerTable input[data-role="select-instance"]').forEach((input) => {
        input.checked = selectAll.checked;
        const value = parseInt(input.value, 10);
        if (Number.isNaN(value)) return;
        if (selectAll.checked) state.selectedInstances.add(value);
        else state.selectedInstances.delete(value);
      });
      updateBulkButtons();
    });
  }

  function bindPager() {
    const prev = qs('#iiOwnerPrev');
    const next = qs('#iiOwnerNext');
    if (prev) {
      prev.addEventListener('click', () => {
        if (!state.ownershipItem || state.ownershipPage <= 1) return;
        loadOwnership(state.ownershipItem.entry, state.ownershipPage - 1);
      });
    }
    if (next) {
      next.addEventListener('click', () => {
        const pages = state.ownershipSummary ? state.ownershipSummary.pages : 0;
        if (!state.ownershipItem || state.ownershipPage >= pages) return;
        loadOwnership(state.ownershipItem.entry, state.ownershipPage + 1);
      });
    }
  }

  function bindCharacterForm() {
    const form = qs('#iiCharSearchForm');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const typeNode = qs('#iiCharType');
      const valueNode = qs('#iiCharValue');
      await runCharacterSearch(
        typeNode ? typeNode.value : 'character_name',
        valueNode ? valueNode.value : ''
      );
    });
  }

  function bindItemForm() {
    const form = qs('#iiItemSearchForm');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const input = qs('#iiItemKeyword');
      await runItemSearch(input ? input.value : '');
    });
  }

  async function applyPrefill() {
    if (!ctxPrefill) return;
    const value = ctxPrefill.value != null ? String(ctxPrefill.value).trim() : '';
    const entry = parseInt(ctxPrefill.entry || 0, 10) || 0;

    if (entry > 0) {
      setMode('item');
      const input = qs('#iiItemKeyword');
      if (input) input.value = String(entry);
      if (ctxAutoSearch) {
        await runItemSearch(String(entry));
        await loadOwnership(entry, 1);
      }
      return;
    }

    if (value === '') return;

    if (ctxPrefill.mode === 'item') {
      setMode('item');
      const input = qs('#iiItemKeyword');
      if (input) input.value = value;
      if (ctxAutoSearch && await runItemSearch(value)) {
        const matches = qsa('#iiSearchTable tbody button[data-act="owners"]');
        if (matches.length) {
          const first = parseInt(matches[0].dataset.entry, 10);
          if (!Number.isNaN(first)) await loadOwnership(first, 1);
        }
      }
      return;
    }

    setMode('character');
    const typeNode = qs('#iiCharType');
    const valueNode = qs('#iiCharValue');
    const safeType = ['character_name', 'username'].includes(ctxPrefill.type) ? ctxPrefill.type : 'character_name';
    if (typeNode) typeNode.value = safeType;
    if (valueNode) valueNode.value = value;
    if (ctxAutoSearch) await runCharacterSearch(safeType, value, { fromPrefill: true });
  }

  function init() {
    bindItemFilter();

    // 嵌入模式（角色详情页背包 Tab）只有共用物品面板；guid 来自服务端渲染的挂载点，
    // 到这里必然已经存在。
    const embedInfo = resolveEmbed();
    if (embedInfo) {
      state.characterGuid = embedInfo.guid;
      state.characterName = embedInfo.name;
      updateItemsSubtitle();
      loadCharacterItems(embedInfo.guid, embedInfo.name).catch(() => {});
      return;
    }

    bindTabs();
    bindCharacterForm();
    bindItemForm();
    bindSelectAll();
    bindBulkDelete();
    bindBulkReplace();
    bindPager();

    if (!ownsCharacterPanel()) return;

    const initial = qs('#iiLayout') ? qs('#iiLayout').dataset.mode : 'character';
    setMode(initial === 'item' ? 'item' : 'character');
    resetItemsPlaceholder();
    applyPrefill().catch(() => {});
  }

  window.ItemInventory = { setMode, state, loadOwnership, runCharacterSearch, runItemSearch };

  function boot() {
    try {
      init();
    } finally {
      // 可观测标记：以后遇到"面板空白"的报告，服务端不靠浏览器也能定位
      window.__PANEL_BOOT = window.__PANEL_BOOT || {};
      window.__PANEL_BOOT.item_inventory = {
        mode: state.mode,
        embedGuid: state.characterGuid,
        at: document.readyState
      };
    }
  }

  // 必须容忍在文档仍解析时被注入（panel.js 用 body 内联脚本加载页面模块）
  if (window.Panel && typeof window.Panel.whenDomReady === 'function') {
    window.Panel.whenDomReady(boot);
  } else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
