/**
 * File: public/assets/js/modules/logs.js
 * Purpose: 审计日志台（筛选 + 分页 + 明细 + 导出 + 清理），数据来自 panel_audit_log。
 *
 * 整体包在 IIFE 里，避免占用 escapeHtml / boot 之类通用全局名。
 */

(function(){
const qs = (sel, ctx = document) => ctx.querySelector(sel);
const getPanelApi = () => (window.Panel && window.Panel.api) ? window.Panel.api : null;
const escapeHtml = (value) => String(value === null || value === undefined ? '' : value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;');

const POLL_INTERVAL_MS = 10000;

function boot(){
  if(document.body.dataset.module !== 'logs') return;

  const config = window.LOGS_DATA || {};
  const catalog = config.catalog || {};
  const capabilities = window.PANEL_CAPABILITIES || {};
  const canRead = capabilities.read !== false;
  const canPurge = capabilities.purge === true;

  const form = qs('#logsFilters');
  if(!form) return;

  const panelRef = window.Panel || {};
  const moduleTranslate = typeof panelRef.createModuleTranslator === 'function'
    ? panelRef.createModuleTranslator('logs')
    : (path, fallback) => (fallback !== undefined ? fallback : path);
  const t = (key, fallback) => moduleTranslate(key, fallback);

  const els = {
    keyword: qs('#logsKeyword', form),
    channel: qs('#logsChannel', form),
    module: qs('#logsModule', form),
    action: qs('#logsAction', form),
    actor: qs('#logsActor', form),
    status: qs('#logsStatus', form),
    realm: qs('#logsRealm', form),
    range: qs('#logsRange', form),
    from: qs('#logsFrom', form),
    to: qs('#logsTo', form),
    perPage: qs('#logsPerPage', form),
    customFrom: qs('#logsCustomRange'),
    customTo: qs('#logsCustomRangeTo'),
    summary: qs('#logsSummaryBox'),
    tableBody: qs('#logsTableBody'),
    pager: qs('#logsPager'),
    detail: qs('#logsDetail'),
    detailBody: qs('#logsDetailBody'),
    detailClose: qs('#btn-logs-detail-close'),
    exportLink: qs('#logsExport'),
    purgeDays: qs('#logsPurgeDays'),
    purgeButton: qs('#btn-logs-purge'),
    resetButton: qs('#btn-logs-reset'),
    autoButton: qs('#btn-auto-toggle'),
  };

  let page = Number((config.filters || {}).page || 1) || 1;
  let loading = false;
  let activeRow = null;
  let poll = null;
  let lastQueryKey = null;
  const COLSPAN = 8;

  function closeDetail(){
    if(els.detail) els.detail.hidden = true;
    if(activeRow) activeRow.classList.remove('is-active');
    activeRow = null;
  }

  function actionOptionsFor(moduleId){
    const actions = (catalog.actions || {})[moduleId] || [];
    return Array.isArray(actions) ? actions : [];
  }

  function fillActionSelect(moduleId, selected){
    if(!els.action) return;
    const all = t('filters.all', '-- All --');
    const parts = [`<option value="all">${escapeHtml(all)}</option>`];
    actionOptionsFor(moduleId).forEach((action) => {
      const id = String(action.id || '');
      if(!id) return;
      const label = String(action.label || id) + (Number(action.count) > 0 ? ` (${Number(action.count)})` : '');
      parts.push(`<option value="${escapeHtml(id)}"${id === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`);
    });
    els.action.innerHTML = parts.join('');
  }

  function toggleCustomRange(){
    const custom = els.range && els.range.value === 'custom';
    if(els.customFrom) els.customFrom.hidden = !custom;
    if(els.customTo) els.customTo.hidden = !custom;
  }

  function readFilters(){
    const value = (el, fallback) => (el && el.value !== undefined && el.value !== '' ? el.value : fallback);
    const filters = {
      keyword: value(els.keyword, ''),
      channel: value(els.channel, 'all'),
      module: value(els.module, 'all'),
      action: value(els.action, 'all'),
      actor: value(els.actor, 'all'),
      status: value(els.status, 'all'),
      realm: value(els.realm, 'all'),
      range: value(els.range, '7d'),
      per_page: Number(value(els.perPage, 50)) || 50,
      page,
    };
    if(filters.range === 'custom'){
      filters.from = value(els.from, '');
      filters.to = value(els.to, '');
    }
    return filters;
  }

  function queryString(filters){
    const usp = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if(value === undefined || value === null || value === '') return;
      usp.append(key, value);
    });
    return usp.toString();
  }

  function syncExportLink(filters){
    if(!els.exportLink || !panelRef.absoluteUrl) return;
    els.exportLink.href = panelRef.absoluteUrl('/logs/export') + '?' + queryString(filters);
  }

  function statusClass(row){
    const status = String(row.status || 'ok');
    if(status === 'ok') return 'logs-chip logs-chip--ok';
    if(status === 'denied') return 'logs-chip logs-chip--denied';
    return 'logs-chip logs-chip--fail';
  }

  function severityClass(row){
    const severity = Number(row.severity || 1);
    if(severity >= 4) return 'is-error';
    if(severity === 3) return 'is-warning';
    return '';
  }

  function renderRows(rows){
    if(!els.tableBody) return;
    activeRow = null;
    if(!Array.isArray(rows) || rows.length === 0){
      const empty = t('status.no_entries', '-- No log entries --');
      els.tableBody.innerHTML = (window.Panel && typeof window.Panel.emptyRow === 'function')
        ? window.Panel.emptyRow(COLSPAN, empty, { rowClassName: 'js-log-empty', cellClassName: 'muted text-center' })
        : `<tr class="js-log-empty"><td colspan="${COLSPAN}" class="muted text-center">${escapeHtml(empty)}</td></tr>`;
      return;
    }

    els.tableBody.innerHTML = rows.map((row, index) => {
      const realm = row.realm_name
        ? `${escapeHtml(row.realm_name)}<span class="muted small"> #${Number(row.realm_index) || 0}</span>`
        : `S${Number(row.realm_index) || 0}`;
      const moduleCell = `${escapeHtml(row.module_label || row.module)}<span class="muted small"> · ${escapeHtml(row.action_label || row.action)}</span>`;
      return `<tr class="logs-row ${severityClass(row)}" data-index="${index}" tabindex="0">
        <td class="logs-col-time">${escapeHtml(row.ts || '-')}</td>
        <td class="logs-col-realm">${realm}</td>
        <td class="logs-col-actor">${escapeHtml(row.actor || '-')}</td>
        <td class="logs-col-channel">${escapeHtml(row.channel_label || row.channel || '-')}</td>
        <td class="logs-col-module">${moduleCell}</td>
        <td class="logs-col-status"><span class="${statusClass(row)}">${escapeHtml(row.status_label || row.status || '')}</span></td>
        <td class="logs-col-target">${escapeHtml(row.target || '-')}</td>
        <td class="logs-summary-cell" title="${escapeHtml(row.summary || '')}">${escapeHtml(row.summary || '-')}</td>
      </tr>`;
    }).join('');

    Array.prototype.forEach.call(els.tableBody.querySelectorAll('tr[data-index]'), (tr) => {
      const row = rows[Number(tr.dataset.index)];
      const open = () => showDetail(row, tr);
      tr.addEventListener('click', open);
      tr.addEventListener('keydown', (event) => {
        if(event.key === 'Enter' || event.key === ' '){
          event.preventDefault();
          open();
        }
      });
    });
  }

  function detailPairs(row){
    return [
      [t('detail.fields.time', 'Time'), row.ts],
      [t('detail.fields.channel', 'Channel'), row.channel_label || row.channel],
      [t('detail.fields.module', 'Module'), `${row.module_label || row.module} · ${row.action_label || row.action}`],
      [t('detail.fields.status', 'Status'), row.status_label || row.status],
      [t('detail.fields.severity', 'Severity'), String(row.severity || 1)],
      [t('detail.fields.actor', 'Actor'), row.actor],
      [t('detail.fields.realm', 'Realm'), row.realm_name ? `${row.realm_name} (#${row.realm_index})` : `#${row.realm_index}`],
      [t('detail.fields.target', 'Target'), row.target],
      [t('detail.fields.summary', 'Summary'), row.summary],
      [t('detail.fields.ip', 'Source IP'), row.ip],
      [t('detail.fields.request', 'Request'), `${row.method || ''} ${row.uri || ''}`.trim()],
      [t('detail.fields.duration', 'Duration'), row.duration_ms ? `${row.duration_ms} ms` : ''],
      [t('detail.fields.user_agent', 'User-Agent'), row.user_agent],
      [t('detail.fields.record_id', 'Record id'), String(row.id || '')],
    ];
  }

  function showDetail(row, tr){
    if(!els.detail || !els.detailBody || !row) return;

    if(activeRow && activeRow !== tr) activeRow.classList.remove('is-active');
    if(tr) tr.classList.add('is-active');
    activeRow = tr;

    const pairs = detailPairs(row)
      .filter(([, value]) => value !== undefined && value !== null && String(value) !== '')
      .map(([label, value]) => `<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd>`)
      .join('');

    let detailJson = '';
    if(row.detail !== null && row.detail !== undefined){
      const raw = typeof row.detail === 'string' ? row.detail : JSON.stringify(row.detail, null, 2);
      detailJson = `<h4 class="logs-detail__subtitle">${escapeHtml(t('detail.detail_json', 'Detail'))}</h4><pre class="logs-detail__json">${escapeHtml(raw)}</pre>`;
    }

    els.detailBody.innerHTML = `<dl class="logs-detail__list">${pairs}</dl>${detailJson}`;
    els.detail.hidden = false;
  }

  function renderStats(stats, total){
    if(!els.summary) return;
    const byStatus = (stats && stats.status) || {};
    const byChannel = (stats && stats.channel) || {};
    const bits = [];
    bits.push(`${t('summary.total', 'Total')}: <strong>${Number(total) || 0}</strong>`);
    Object.entries(byStatus).forEach(([status, count]) => {
      const label = (catalog.statuses || {})[status] || status;
      bits.push(`${escapeHtml(label)}: ${Number(count) || 0}`);
    });
    Object.entries(byChannel).forEach(([channel, count]) => {
      const label = (catalog.channels || {})[channel] || channel;
      bits.push(`${escapeHtml(label)}: ${Number(count) || 0}`);
    });
    if(stats && stats.range && stats.range.first){
      bits.push(`${escapeHtml(t('summary.range', 'Time span'))}: ${escapeHtml(stats.range.first)} → ${escapeHtml(stats.range.last)}`);
    }
    els.summary.innerHTML = bits.join(' ｜ ');
  }

  function renderPager(payload){
    if(!els.pager) return;
    const pages = Number(payload.pages) || 0;
    const current = Number(payload.page) || 1;
    const total = Number(payload.total) || 0;
    const perPage = Number(payload.per_page) || 50;

    if(pages <= 1){
      els.pager.innerHTML = total > 0
        ? `<span class="muted small">${escapeHtml(t('pager.range', ':from-:to / :total')
            .replace(':from', String((current - 1) * perPage + 1))
            .replace(':to', String(Math.min(current * perPage, total)))
            .replace(':total', String(total)))}</span>`
        : '';
      return;
    }

    const parts = [];
    parts.push(`<button type="button" class="btn-sm btn outline" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>${escapeHtml(t('pager.prev', 'Prev'))}</button>`);
    parts.push(`<span class="logs-pager__meta">${current} / ${pages}</span>`);
    parts.push(`<button type="button" class="btn-sm btn outline" data-page="${current + 1}" ${current >= pages ? 'disabled' : ''}>${escapeHtml(t('pager.next', 'Next'))}</button>`);
    parts.push(`<span class="muted small">${(current - 1) * perPage + 1}–${Math.min(current * perPage, total)} / ${total}</span>`);
    els.pager.innerHTML = parts.join(' ');

    Array.prototype.forEach.call(els.pager.querySelectorAll('button[data-page]'), (button) => {
      button.addEventListener('click', () => {
        const target = Number(button.dataset.page);
        if(!Number.isFinite(target) || target < 1 || target > pages) return;
        page = target;
        load();
      });
    });
  }

  async function load(){
    if(!canRead || loading) return;
    const PanelApi = getPanelApi();
    if(!PanelApi){
      if(els.tableBody){
        els.tableBody.innerHTML = `<tr><td colspan="${COLSPAN}" class="muted text-center">${escapeHtml(t('status.panel_waiting', 'Panel API is initializing, please wait…'))}</td></tr>`;
      }
      return;
    }

    const filters = readFilters();
    syncExportLink(filters);

    // 筛选条件变了就关掉明细：原先那条可能已不在结果集里。
    const queryKey = queryString(filters);
    if(queryKey !== lastQueryKey){
      lastQueryKey = queryKey;
      closeDetail();
    }

    loading = true;

    try {
      const res = await PanelApi.post('/logs/api/list', filters);
      if(!res || !res.success){
        const message = (res && res.message) ? res.message : t('status.load_failed', 'Load failed');
        if(els.tableBody){
          els.tableBody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center text-danger">${escapeHtml(message)}</td></tr>`;
        }
        return;
      }
      page = Number(res.page) || page;
      renderRows(res.rows || []);
      renderStats(res.stats, res.total);
      renderPager(res);
    } catch(error){
      // 原始异常（含请求路径/堆栈）只进控制台
      console.error('[logs] request failed', error);
      if(els.tableBody){
        els.tableBody.innerHTML = `<tr><td colspan="${COLSPAN}" class="text-center text-danger">${escapeHtml(t('status.request_error', 'Request error'))}</td></tr>`;
      }
    } finally {
      loading = false;
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    page = 1;
    load();
  });

  if(els.module){
    els.module.addEventListener('change', () => {
      fillActionSelect(els.module.value, 'all');
      page = 1;
      load();
    });
  }

  ['channel', 'action', 'actor', 'status', 'realm', 'perPage'].forEach((key) => {
    const el = els[key];
    if(el){
      el.addEventListener('change', () => { page = 1; load(); });
    }
  });

  if(els.range){
    els.range.addEventListener('change', () => {
      toggleCustomRange();
      page = 1;
      load();
    });
  }

  if(els.resetButton){
    els.resetButton.addEventListener('click', () => {
      if(els.keyword) els.keyword.value = '';
      ['channel', 'actor', 'status', 'realm'].forEach((key) => { if(els[key]) els[key].value = 'all'; });
      if(els.module) els.module.value = 'all';
      fillActionSelect('all', 'all');
      if(els.range) els.range.value = '7d';
      if(els.perPage) els.perPage.value = String((config.limits || {}).per_page || 50);
      toggleCustomRange();
      page = 1;
      load();
    });
  }

  if(els.autoButton){
    els.autoButton.addEventListener('click', () => {
      const active = els.autoButton.getAttribute('data-on') === '1';
      if(active){
        els.autoButton.setAttribute('data-on', '0');
        els.autoButton.textContent = t('actions.auto_on', 'Enable auto refresh');
        if(poll){ poll.stop(); poll = null; }
      } else {
        els.autoButton.setAttribute('data-on', '1');
        els.autoButton.textContent = t('actions.auto_off', 'Disable auto refresh');
        load();
        poll = (window.Panel && typeof window.Panel.poll === 'function')
          ? window.Panel.poll(() => load(), POLL_INTERVAL_MS, { pauseWhenHidden: true, backoffOnError: true })
          : null;
      }
    });
  }

  if(els.detailClose){
    els.detailClose.addEventListener('click', () => closeDetail());
  }

  if(canPurge && els.purgeButton){
    els.purgeButton.addEventListener('click', async () => {
      const days = Number(els.purgeDays && els.purgeDays.value) || 0;
      if(days < 1){
        window.alert(t('purge.invalid_days', 'Enter a positive number of days.'));
        return;
      }
      const question = t('purge.confirm_text', 'Delete audit rows older than :days days? This cannot be undone.')
        .replace(':days', String(days));
      const confirmed = (window.Panel && typeof window.Panel.confirm === 'function')
        ? await window.Panel.confirm(question)
        : window.confirm(question);
      if(!confirmed) return;

      const PanelApi = getPanelApi();
      if(!PanelApi) return;
      const res = await PanelApi.post('/logs/api/purge', { days });
      if(res && res.success){
        window.alert(res.message || t('purge.done', 'Deleted.'));
        load();
      } else {
        window.alert((res && res.message) ? res.message : t('status.load_failed', 'Load failed'));
      }
    });
  }

  fillActionSelect((config.filters || {}).module || 'all', (config.filters || {}).action || 'all');
  toggleCustomRange();
  if(canRead) load();
}

if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
})();
