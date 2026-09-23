/**
 * File: public/assets/js/modules/character_boost_codes.js
 * Purpose: 直升兑换码生成与管理的交互。
 * Functions:
 *   - translate()
 *   - showFlash()
 *   - postJson()
 *   - renderRows()
 *   - applyRowFilter()
 *   - updatePager()
 *   - updateStats()
 *   - refreshManage()
 *   - bindGenerate()
 *   - bindManage()
 */

(function(){
  const panel = window.Panel || {};
  const moduleLocaleFn = typeof panel.moduleLocale === 'function'
    ? panel.moduleLocale.bind(panel)
    : null;
  const moduleTranslator = typeof panel.createModuleTranslator === 'function'
    ? panel.createModuleTranslator('character_boost')
    : null;

  const form = document.getElementById('boostCodesForm');
  const flashBox = document.getElementById('boostCodesFlash');
  const output = document.getElementById('boostCodesOutput');
  const resultPanel = document.getElementById('boostCodesResultPanel');
  const resultCount = document.getElementById('boostCodesResultCount');
  const copyBtn = document.getElementById('boostCodesCopy');
  const collapseBtn = document.getElementById('boostCodesResultCollapse');
  const quickSet = document.getElementById('boostCodesQuickSet');
  const countInput = document.getElementById('boostCodesCount');
  const submitBtn = document.getElementById('boostCodesSubmit');

  const manageForm = document.getElementById('boostCodesManageForm');
  const manageFlash = document.getElementById('boostCodesManageFlash');
  const manageTbody = document.getElementById('boostCodesManageTbody');
  const statTotal = document.getElementById('boostCodesStatTotal');
  const statUnused = document.getElementById('boostCodesStatUnused');
  const statUsed = document.getElementById('boostCodesStatUsed');
  const statUnusedPct = document.getElementById('boostCodesStatUnusedPct');
  const statUsedPct = document.getElementById('boostCodesStatUsedPct');
  const btnRefresh = document.getElementById('boostCodesManageRefresh');
  const btnPurgeUnused = document.getElementById('boostCodesManagePurgeUnused');
  const selectTemplate = document.getElementById('boostCodesManageTemplate');
  const selectStatus = document.getElementById('boostCodesManageStatus');
  const chkUnusedOnly = document.getElementById('boostCodesManageUnusedOnly');
  const searchInput = document.getElementById('boostCodesManageSearch');
  const perPageSelect = document.getElementById('boostCodesManagePerPage');
  const btnPrev = document.getElementById('boostCodesPagePrev');
  const btnNext = document.getElementById('boostCodesPageNext');
  const pageInfo = document.getElementById('boostCodesPageInfo');

  const sortIdLink = document.getElementById('boostCodesSortId');
  const sortIdIcon = document.getElementById('boostCodesSortIdIcon');

  let managePage = 1;
  let managePerPage = perPageSelect ? (parseInt(perPageSelect.value, 10) || 50) : 50;
  const manageSort = 'id';
  let manageDir = 'desc';

  /** 当前页拉回来的行，搜索过滤在客户端做 */
  let loadedRows = [];

  const translate = (path, fallback, replacements) => {
    const sentinel = 'modules.character_boost.' + path;
    let text = sentinel;
    if(moduleLocaleFn){
      text = moduleLocaleFn('character_boost', path, sentinel);
    } else if(moduleTranslator){
      text = moduleTranslator(path, sentinel);
    }
    if(text === sentinel){
      text = fallback ?? sentinel;
    }
    if(typeof text === 'string' && replacements){
      Object.entries(replacements).forEach(([key, value]) => {
        text = text.replace(new RegExp(':' + key + '(?![A-Za-z0-9_])', 'g'), String(value ?? ''));
      });
    }
    return text;
  };

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]
  ));

  const updateSortUi = () => {
    if(!sortIdIcon) return;
    sortIdIcon.textContent = (manageDir === 'asc') ? '▲' : '▼';
  };

  const setFlash = (box, msg, ok, fallbackPath) => {
    if(!box) return;
    box.textContent = msg || translate(
      fallbackPath || (ok ? 'common.ok' : 'common.failed'),
      ok ? 'OK' : 'Failed'
    );
    box.classList.remove('panel-flash--success','panel-flash--danger');
    box.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
    box.hidden = false;
  };

  const showFlash = (msg, ok) => setFlash(flashBox, msg, ok);
  const showManageFlash = (msg, ok) => setFlash(manageFlash, msg, ok);

  const csrfFrom = (fallbackForm) => {
    try {
      const el = (fallbackForm || document).querySelector('input[name="_csrf"]');
      if(el && el.value) return el.value;
    } catch(e){}
    return window.__CSRF_TOKEN || '';
  };

  const postJson = async (url, data, csrfToken) => {
    const res = await fetch(url, {
      method: 'POST',
      body: data,
      headers: {
        'X-CSRF-TOKEN': csrfToken || '',
        'Accept': 'application/json'
      }
    });
    return await res.json().catch(() => ({
      success: false,
      message: translate('common.invalid_response', 'Invalid response')
    }));
  };

  const currentTemplateId = () => (selectTemplate ? String(selectTemplate.value || 'all') : 'all');

  /** 状态下拉 → 三态 status（all/unused/used）；unused_only 仅作旧接口兼容 */
  const syncUnusedOnly = () => {
    const status = selectStatus ? String(selectStatus.value || 'all') : 'all';
    const unusedOnly = status === 'unused';
    if(chkUnusedOnly) chkUnusedOnly.checked = unusedOnly;
    return { status, unusedOnly };
  };

  const setStats = (stats) => {
    const total = stats ? (parseInt(stats.total, 10) || 0) : null;
    const unused = stats ? (parseInt(stats.unused, 10) || 0) : null;
    const used = stats ? (parseInt(stats.used, 10) || 0) : null;

    if(statTotal) statTotal.textContent = stats ? String(total) : '-';
    if(statUnused) statUnused.textContent = stats ? String(unused) : '-';
    if(statUsed) statUsed.textContent = stats ? String(used) : '-';

    const pct = (value) => (total && total > 0 ? Math.round((value / total) * 100) + '%' : '');
    if(statUnusedPct) statUnusedPct.textContent = stats && total ? pct(unused) : '';
    if(statUsedPct) statUsedPct.textContent = stats && total ? pct(used) : '';
  };

  function rowMatchesSearch(row, query){
    if(!query) return true;
    const haystack = [
      row.id,
      row.template_name,
      row.code,
      row.used_character_name,
      row.used_ip,
      row.created_at
    ].map((v) => String(v ?? '').toLowerCase()).join(' ');
    return haystack.includes(query);
  }

  function rowHtml(row){
    const used = !!row.used_at;
    const statusLabel = used
      ? translate('codes.status.used', 'Used')
      : translate('codes.status.unused', 'Unused');
    const statusClass = used ? 'cb-badge cb-badge--used' : 'cb-badge cb-badge--unused';

    const usedBy = used
      ? (
        esc(row.used_character_name || '-')
        + (row.used_realm_id ? esc(translate('codes.usage.realm_suffix', ' (realm :id)', { id: row.used_realm_id })) : '')
        + (row.used_ip ? ' <span class="cb-muted">/ ' + esc(row.used_ip) + '</span>' : '')
      )
      : '<span class="cb-muted">-</span>';

    const act = used
      ? '<span class="cb-muted">-</span>'
      : '<button class="btn btn-sm danger js-del-unused" data-id="' + esc(row.id) + '" type="button">'
        + esc(translate('codes.actions.delete_unused', 'Delete'))
        + '</button>';

    return '<tr data-code="' + esc(String(row.code ?? '').toLowerCase()) + '">'
      + '<td>' + esc(row.id) + '</td>'
      + '<td>' + esc(row.template_name || ('#' + row.template_id)) + '</td>'
      + '<td class="cb-code-cell">' + esc(row.code) + '</td>'
      + '<td><span class="' + statusClass + '">' + esc(statusLabel) + '</span></td>'
      + '<td>' + usedBy + '</td>'
      + '<td class="cb-nowrap">' + esc(used ? (row.used_at || '-') : '-') + '</td>'
      + '<td class="cb-nowrap">' + esc(row.created_at || '-') + '</td>'
      + '<td class="cb-col-act">' + act + '</td>'
      + '</tr>';
  }

  /** 客户端过滤 + 空态：区分"服务端无数据"与"筛选无结果" */
  function applyRowFilter(){
    if(!manageTbody) return;

    const query = searchInput ? String(searchInput.value || '').trim().toLowerCase() : '';
    const rows = Array.from(manageTbody.querySelectorAll('tr[data-code]'));
    const emptyKinds = new Set();

    rows.forEach((tr) => {
      const match = !query || (tr.getAttribute('data-code') || '').includes(query) || tr.textContent.toLowerCase().includes(query);
      tr.hidden = !match;
      if(match) emptyKinds.add('visible');
    });

    const noResult = manageTbody.querySelector('.js-filter-none');
    if(noResult){
      noResult.hidden = !(query && !emptyKinds.has('visible') && rows.length > 0);
    }
  }

  const renderRows = (items) => {
    if(!manageTbody) return;

    loadedRows = Array.isArray(items) ? items : [];

    if(loadedRows.length === 0){
      manageTbody.innerHTML = '<tr class="js-empty-row"><td colspan="8" class="cb-empty-cell">'
        + esc(translate('codes.table.empty', 'No redeem codes'))
        + '</td></tr>';
      return;
    }

    manageTbody.innerHTML = loadedRows.map(rowHtml).join('')
      + '<tr class="js-filter-none" hidden><td colspan="8" class="cb-empty-cell">'
      + esc(translate('codes.manage.no_match', 'No codes match the search'))
      + '</td></tr>';

    applyRowFilter();
  };

  const updatePager = (list) => {
    if(!pageInfo) return;
    const page = list ? (parseInt(list.page, 10) || 1) : 1;
    const pages = list ? (parseInt(list.pages, 10) || 1) : 1;
    const total = list ? (parseInt(list.total, 10) || 0) : 0;
    pageInfo.textContent = translate(
      'codes.pager.summary',
      'Page :page / :pages · :total',
      { page, pages, total }
    );
    if(btnPrev) btnPrev.disabled = page <= 1;
    if(btnNext) btnNext.disabled = page >= pages;
  };

  const refreshManage = async () => {
    if(!manageForm) return;
    const csrfToken = csrfFrom(manageForm);
    const tpl = currentTemplateId();
    const { status, unusedOnly } = syncUnusedOnly();

    updateSortUi();

    try {
      const d1 = new FormData();
      d1.set('_csrf', csrfToken);
      d1.set('template_id', tpl);
      const statsJson = await postJson(manageForm.dataset.endpointStats, d1, csrfToken);
      if(statsJson && statsJson.success && statsJson.payload && statsJson.payload.stats){
        setStats(statsJson.payload.stats);
      } else {
        setStats(null);
      }
    } catch(e){
      setStats(null);
    }

    manageTbody.innerHTML = '<tr><td colspan="8" class="cb-empty-cell">'
      + esc(translate('common.loading', 'Loading…'))
      + '</td></tr>';

    const d2 = new FormData();
    d2.set('_csrf', csrfToken);
    d2.set('template_id', tpl);
    d2.set('status', status);
    d2.set('unused_only', unusedOnly ? '1' : '0');
    d2.set('page', String(managePage));
    d2.set('per_page', String(managePerPage));
    d2.set('sort', manageSort);
    d2.set('dir', manageDir);

    const listJson = await postJson(manageForm.dataset.endpointList, d2, csrfToken);
    if(!(listJson && listJson.success && listJson.payload && listJson.payload.list)){
      renderRows([]);
      updatePager(null);
      showManageFlash(
        (listJson && listJson.message) ? listJson.message : translate('common.failed', 'Failed'),
        false
      );
      return;
    }

    const list = listJson.payload.list;
    renderRows(list.items || []);
    updatePager(list);
  };

  /** 结果区收起时让生成卡片占满整行 */
  function syncTopLayout(){
    if(!resultPanel) return;
    const top = resultPanel.closest('.cb-codes__top');
    if(!top) return;
    top.classList.toggle('cb-codes__top--single', resultPanel.hidden === true);
  }

  /** 结果区：生成后展开，可收起 */
  function showResult(text, count){
    if(!output) return;
    output.value = text || '';
    if(resultPanel){
      resultPanel.hidden = !text;
      syncTopLayout();
    }
    if(resultCount){
      resultCount.textContent = count
        ? translate('codes.generated.count', ':count codes', { count })
        : '';
    }
  }

  function buildCodesText(json){
    if(!(json && json.success && json.payload && Array.isArray(json.payload.generated))){
      return { text: '', count: 0 };
    }

    const blocks = [];
    let count = 0;
    json.payload.generated.forEach((g) => {
      const header = g.template_name
        ? translate('codes.generated.template_named', ':name (#:id)', { name: g.template_name, id: g.template_id })
        : translate('codes.generated.template_fallback', 'Template #:id', { id: g.template_id });
      blocks.push('[' + header + ']');
      if(Array.isArray(g.codes)){
        g.codes.forEach((c) => { blocks.push(String(c)); count++; });
      }
      blocks.push('');
    });

    return { text: blocks.join('\n'), count };
  }

  function setBusy(button, busy, busyLabel){
    if(!button) return;
    if(busy){
      if(!button.dataset.idleLabel) button.dataset.idleLabel = button.textContent;
      if(busyLabel) button.textContent = busyLabel;
    } else if(button.dataset.idleLabel){
      button.textContent = button.dataset.idleLabel;
    }
    button.disabled = !!busy;
  }

  function bindGenerate(){
    if(!form) return;
    const endpoint = form.dataset.endpoint;
    if(!endpoint) return;

    // 数量快捷值
    if(quickSet && countInput){
      quickSet.addEventListener('click', (event) => {
        const btn = event.target.closest('.cb-quick');
        if(!btn) return;
        countInput.value = btn.getAttribute('data-count') || countInput.value;
        Array.from(quickSet.querySelectorAll('.cb-quick')).forEach((el) => {
          el.classList.toggle('is-active', el === btn);
        });
      });
    }

    if(collapseBtn && resultPanel){
      collapseBtn.addEventListener('click', () => {
        resultPanel.hidden = true;
        syncTopLayout();
      });
    }

    if(copyBtn && output){
      copyBtn.addEventListener('click', async () => {
        const text = output.value || '';
        if(!text){
          showFlash(translate('codes.generated.copy_empty', 'Nothing to copy yet'), false);
          return;
        }
        try {
          if(navigator.clipboard && navigator.clipboard.writeText){
            await navigator.clipboard.writeText(text);
          } else {
            output.removeAttribute('readonly');
            output.select();
            document.execCommand('copy');
            output.setAttribute('readonly', 'readonly');
          }
          showFlash(translate('codes.generated.copied', 'Copied to clipboard'), true);
        } catch(error){
          showFlash(translate('codes.generated.copy_failed', 'Copy failed, please select manually'), false);
        }
      });
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const count = parseInt(countInput ? countInput.value : '0', 10) || 0;
      if(count <= 0){
        showFlash(translate('codes.errors.invalid_count', 'Enter a valid count'), false);
        return;
      }
      if(count > 10000){
        showFlash(translate('codes.errors.count_too_large', 'At most 10000 per run'), false);
        return;
      }

      const data = new FormData(form);
      if(!data.get('_csrf') && window.__CSRF_TOKEN){
        data.set('_csrf', window.__CSRF_TOKEN);
      }

      setBusy(submitBtn, true, translate('codes.generating', 'Generating…'));

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          body: data,
          headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
        });

        const contentType = (res.headers.get('Content-Type') || '').toLowerCase();

        // 勾选"同时下载"时后端直接回 txt
        if(contentType.includes('text/plain')){
          const blob = await res.blob();
          const text = await blob.text();
          const generatedCount = parseInt(res.headers.get('X-Generated-Count') || '0', 10) || 0;

          showResult(text, generatedCount);

          const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
          const link = document.createElement('a');
          link.href = url;
          const disposition = res.headers.get('Content-Disposition') || '';
          const match = disposition.match(/filename="?([^";]+)"?/i);
          link.download = match ? match[1] : 'boost-redeem-codes.txt';
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(url);

          showFlash(
            generatedCount
              ? translate('codes.generated.download_ok_count', 'Generated :count codes and downloaded', { count: generatedCount })
              : translate('codes.generated.download_ok', 'Generated and downloaded'),
            true
          );
        } else {
          const json = await res.json().catch(() => ({
            success: false,
            message: translate('common.invalid_response', 'Invalid response')
          }));

          if(json && json.success){
            const built = buildCodesText(json);
            showResult(built.text, built.count);
            showFlash(json.message || translate('common.ok', 'OK'), true);
          } else {
            showFlash(
              (json && json.message) ? json.message : translate('common.failed', 'Failed'),
              false
            );
          }
        }

        // 生成后回到第一页并刷新统计/列表
        managePage = 1;
        try { await refreshManage(); } catch(e){}
      } catch(error){
        showFlash(
          (error && error.message) ? error.message : translate('common.network_error', 'Network error'),
          false
        );
      }

      setBusy(submitBtn, false);
    });
  }

  function bindManage(){
    if(!manageForm) return;

    const onChange = () => { managePage = 1; refreshManage().catch(()=>{}); };

    // 筛选表单永远不会真的提交：回车只做当前页过滤，避免整页刷新
    manageForm.addEventListener('submit', (event) => {
      event.preventDefault();
      applyRowFilter();
    });

    if(selectTemplate) selectTemplate.addEventListener('change', onChange);
    if(selectStatus) selectStatus.addEventListener('change', onChange);

    if(perPageSelect){
      perPageSelect.addEventListener('change', () => {
        managePerPage = parseInt(perPageSelect.value, 10) || 50;
        managePage = 1;
        refreshManage().catch(()=>{});
      });
    }

    // 搜索只在当前页做客户端过滤，不打扰服务端
    if(searchInput){
      searchInput.addEventListener('input', applyRowFilter);
    }

    if(btnRefresh) btnRefresh.addEventListener('click', () => { refreshManage().catch(()=>{}); });

    if(sortIdLink){
      sortIdLink.addEventListener('click', (event) => {
        event.preventDefault();
        manageDir = (manageDir === 'asc') ? 'desc' : 'asc';
        managePage = 1;
        refreshManage().catch(()=>{});
      });
    }

    if(btnPrev) btnPrev.addEventListener('click', () => {
      if(managePage > 1){ managePage--; refreshManage().catch(()=>{}); }
    });
    if(btnNext) btnNext.addEventListener('click', () => {
      managePage++;
      refreshManage().catch(()=>{});
    });

    if(btnPurgeUnused){
      btnPurgeUnused.addEventListener('click', async () => {
        if(!confirm(translate('codes.confirm.purge_unused', 'Delete ALL unused redeem codes?'))) return;
        setBusy(btnPurgeUnused, true);
        const csrfToken = csrfFrom(manageForm);
        const data = new FormData();
        data.set('_csrf', csrfToken);
        data.set('template_id', currentTemplateId());
        const json = await postJson(manageForm.dataset.endpointPurgeUnused, data, csrfToken);
        showManageFlash(
          (json && json.message) ? json.message : translate('common.failed', 'Failed'),
          !!(json && json.success)
        );
        setBusy(btnPurgeUnused, false);
        managePage = 1;
        refreshManage().catch(()=>{});
      });
    }

    if(manageTbody){
      manageTbody.addEventListener('click', async (event) => {
        const btn = event.target.closest ? event.target.closest('.js-del-unused') : null;
        if(!btn) return;
        const id = btn.getAttribute('data-id');
        if(!id) return;
        if(!confirm(translate('codes.confirm.delete_unused', 'Delete this unused redeem code?'))) return;

        const csrfToken = csrfFrom(manageForm);
        const data = new FormData();
        data.set('_csrf', csrfToken);
        data.set('id', String(id));
        const json = await postJson(manageForm.dataset.endpointDeleteUnused, data, csrfToken);
        showManageFlash(
          (json && json.message) ? json.message : translate('common.failed', 'Failed'),
          !!(json && json.success)
        );
        refreshManage().catch(()=>{});
      });
    }

    refreshManage().catch(()=>{});
  }

  syncTopLayout();
  bindGenerate();
  bindManage();
})();
