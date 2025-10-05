/**
 * File: public/assets/js/modules/mail.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - translateStatus()
 *   - mailNotify()
 *   - apiPost()
 *   - escapeHtml()
 *   - truncate()
 *   - formatExpire()
 *   - formatStatus()
 *   - formatMoney()
 *   - selectedIds()
 *   - updateBulkState()
 *   - buildPayload()
 *   - fetchList()
 *   - renderTable()
 *   - updateTotal()
 *   - renderPagination()
 *   - enhanceInitialPagination()
 *   - bindSorting()
 *   - applySortIndicators()
 *   - refreshMailLogs()
 *   - bindFilters()
 *   - markReadOne()
 *   - deleteOne()
 *   - bulkMark()
 *   - bulkDelete()
 *   - clearDetail()
 *   - renderMailDetail()
 *   - renderMailItems()
 *   - openDetail()
 *   - loadStats()
 *   - bindToolbar()
 *   - bindTableDelegates()
 *   - resolveModal()
 *   - openModal()
 *   - hideModal()
 *   - hideAllModals()
 *   - init()
 *   - qs()
 *   - qsa()
 *   - nowSeconds()
 *   - addLink()
 */

(function(){
  if(!document.body || document.body.getAttribute('data-module') !== 'mail') return;

  const doc = document;
  const qs = (sel, ctx = doc) => ctx.querySelector(sel);
  const qsa = (sel, ctx = doc) => Array.from(ctx.querySelectorAll(sel));
  const FEEDBACK_TARGET = '#mail-feedback';
  const panelApi = window.Panel?.api;
  const panelFeedback = window.Panel?.feedback;
  const panelUrl = typeof window.Panel?.url === 'function' ? window.Panel.url : (path => path);
  const basePath = (window.Panel?.base || window.APP_BASE || '').replace(/\/$/, '');
  const panelLocale = window.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function' ? panelLocale.moduleLocale.bind(panelLocale) : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('mail')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.mail.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('mail', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    if(typeof text === 'string' && text === `modules.mail.${path}` && fallback){
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

  function translateStatus(path, fallback, replacements){
    return translate(`status.${path}`, fallback, replacements);
  }

  const state = {
    sort: window.MAIL_STATE?.sort || 'id',
    dir: (window.MAIL_STATE?.dir || 'DESC').toUpperCase(),
    limit: Number(window.MAIL_STATE?.limit) || 50,
    page: Number(window.MAIL_STATE?.page) || 1
  };

  const filterForm = qs('#mail-filter-form');
  const table = qs('#mailTable');
  const tbody = table ? table.querySelector('tbody') : null;
  if(table){
    table.dataset.sort = state.sort;
    table.dataset.dir = state.dir;
  }

  function mailNotify(message, type = 'info', opts){
    const target = qs(FEEDBACK_TARGET);
    if(panelFeedback?.show && target){
      const severity = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
      const duration = typeof opts?.duration === 'number' ? opts.duration : (severity === 'error' ? 6000 : 3200);
      panelFeedback.show(target, severity, message, { duration });
      return;
    }
    if(type === 'error'){
      console.error(message);
      if(opts?.silent !== true) alert(message);
    } else {
      console.log(message);
    }
  }

  function apiPost(path, body){
    if(panelApi?.post) return panelApi.post(path, body || {});
    if(typeof panelApi === 'function') return panelApi(path, { method: 'POST', body: body || {} });
    const fd = new FormData();
    Object.entries(body || {}).forEach(([k, v])=> fd.append(k, v));
    const token = window.__CSRF_TOKEN || window.__csrf;
    if(token){
      fd.append('_token', token);
      fd.append('_csrf', token);
    }
    return fetch(basePath + path, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(res=>{
      if(!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    });
  }

  const escapeMap = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' };
  function escapeHtml(value){
    return String(value ?? '').replace(/[&<>"']/g, ch => escapeMap[ch]);
  }
  function truncate(str, max){
    const chars = Array.from(String(str ?? ''));
    if(chars.length <= max) return chars.join('');
    return chars.slice(0, max).join('') + '…';
  }
  const nowSeconds = () => Math.floor(Date.now() / 1000);
  function formatExpire(ts){
    const int = Number(ts || 0);
    if(!int) return '-';
    const now = nowSeconds();
    if(int <= now) return translate('detail.expire.expired', 'Expired');
    const days = Math.max(0, Math.floor((int - now) / 86400));
    if(days === 0) return translate('detail.expire.today', 'Expires today');
    const key = days === 1 ? 'detail.expire.day_singular' : 'detail.expire.day_plural';
    return translate(key, days === 1 ? 'Expires in :days day' : 'Expires in :days days', { days });
  }
  function formatStatus(unread){
    const label = unread
      ? translate('detail.status.unread', 'Unread')
      : translate('detail.status.read', 'Read');
    const cls = unread ? 'badge primary' : 'badge';
    return `<span class="${cls}">${escapeHtml(label)}</span>`;
  }
  function formatMoney(value){
    return Number(value || 0);
  }

  function selectedIds(){
    return qsa('.mail-select:checked', table).map(cb => cb.value);
  }

  function updateBulkState(){
    const ids = selectedIds();
    ['bulkMarkReadBtn','bulkDeleteBtn'].forEach(id=>{
      const btn = qs('#' + id);
      if(btn) btn.disabled = ids.length === 0;
    });
    const all = qs('#mailSelectAll');
    if(all){
      const boxes = qsa('.mail-select', table);
      const checked = boxes.filter(cb => cb.checked);
      all.checked = boxes.length > 0 && checked.length === boxes.length;
      all.indeterminate = checked.length > 0 && checked.length < boxes.length;
    }
  }

  function buildPayload(overrides){
    const payload = {};
    if(filterForm){
      const formData = new FormData(filterForm);
      formData.forEach((value, key)=>{
        if(value !== '' && value !== null) payload[key] = value;
      });
      payload.limit = formData.get('limit') || state.limit;
    }
    payload.sort = table?.dataset.sort || state.sort;
    payload.dir = table?.dataset.dir || state.dir;
    payload.page = overrides?.page || state.page || 1;
    if(overrides){
      Object.entries(overrides).forEach(([key, val])=>{
        if(val !== undefined && val !== null) payload[key] = val;
      });
    }
    state.sort = payload.sort;
    state.dir = String(payload.dir || state.dir).toUpperCase();
    state.limit = parseInt(payload.limit, 10) || state.limit;
    state.page = parseInt(payload.page, 10) || 1;
    if(filterForm){
      const sortInput = filterForm.querySelector('input[name="sort"]');
      const dirInput = filterForm.querySelector('input[name="dir"]');
      if(sortInput) sortInput.value = state.sort;
      if(dirInput) dirInput.value = state.dir;
    }
    return payload;
  }

  async function fetchList(overrides){
    const payload = buildPayload(overrides);
    if(tbody){
      const loadingText = escapeHtml(translate('table.loading', 'Loading…'));
      tbody.innerHTML = `<tr><td colspan="10" class="text-center muted">${loadingText}</td></tr>`;
    }
    try{
      const res = await apiPost('/mail/api/list', payload);
      if(res && res.success){
        renderTable(res.rows || []);
        updateTotal(res.total ?? 0);
        renderPagination(res.page ?? state.page, res.pages ?? 1, res.limit ?? state.limit);
        applySortIndicators();
        updateBulkState();
      } else {
        if(tbody){
          const failedText = escapeHtml(translateStatus('load_failed', 'Failed to load list'));
          tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">${failedText}</td></tr>`;
        }
        mailNotify(res?.message || translateStatus('load_failed', 'Failed to load list'), 'error');
      }
    } catch(err){
      if(tbody){
        const failedText = escapeHtml(translateStatus('load_failed', 'Failed to load list'));
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">${failedText}</td></tr>`;
      }
      const errorText = `${translateStatus('load_failed', 'Failed to load list')}: ${err?.message || err}`;
      mailNotify(errorText, 'error', { duration: 6000 });
    }
  }

  function renderTable(rows){
    if(!tbody) return;
    if(!rows || !rows.length){
      const emptyText = escapeHtml(translate('table.empty', 'No records'));
      tbody.innerHTML = `<tr><td colspan="10" class="text-center muted">${emptyText}</td></tr>`;
      return;
    }
    const now = nowSeconds();
    const html = rows.map(row=>{
      const id = Number(row.id);
      const unread = !row.checked || Number(row.checked) === 0;
      const expireTs = Number(row.expire_time || 0);
      const expired = Number(row.is_expired || 0) === 1 || (expireTs && expireTs < now);
      const remain = formatExpire(expireTs);
      const sender = row.sender_name ? escapeHtml(row.sender_name) : ('#' + (row.sender ?? ''));
      const receiver = row.receiver_name ? escapeHtml(row.receiver_name) : ('#' + (row.receiver ?? ''));
      const subj = row.subject
        ? escapeHtml(truncate(row.subject, 50))
        : `<span class="muted">${escapeHtml(translate('detail.no_subject', '(No subject)'))}</span>`;
      const money = formatMoney(row.money);
      const hasItems = Number(row.has_items || 0) === 1;
      const attachmentsYes = escapeHtml(translate('detail.attachments_yes', 'Yes'));
      const classes = [unread ? 'is-unread' : '', expired ? 'is-expired' : ''].filter(Boolean).join(' ');
      const unreadDisabled = unread ? '' : 'disabled';
      const unreadClass = unread ? 'mark-btn-active' : 'mark-btn-disabled';
      const viewLabel = escapeHtml(translate('actions.view', 'View'));
      const markReadLabel = escapeHtml(translate('actions.mark_read', 'Mark as read'));
      const deleteLabel = escapeHtml(translate('actions.delete', 'Delete'));
      return [
        `<tr data-mail-id="${id}" class="${classes}">`,
        `<td><input type="checkbox" class="mail-select" value="${id}"></td>`,
        `<td>${id}</td>`,
        `<td>${sender}</td>`,
        `<td>${receiver}</td>`,
        `<td>${subj}</td>`,
        `<td>${money}</td>`,
        `<td>${hasItems ? `<span class="badge">${attachmentsYes}</span>` : ''}</td>`,
        `<td>${escapeHtml(remain)}</td>`,
        `<td>${formatStatus(unread)}</td>`,
        `<td class="nowrap">` +
          `<button class="btn-sm btn action-view" data-id="${id}">${viewLabel}</button>` +
          `<button class="btn-sm btn action-mark-read ${unreadClass}" data-id="${id}" ${unreadDisabled}>${markReadLabel}</button>` +
          `<button class="btn-sm btn danger action-delete" data-id="${id}">${deleteLabel}</button>` +
        `</td>`,
        `</tr>`
      ].join('');
    }).join('');
    tbody.innerHTML = html;
  }

  function updateTotal(total){
    const span = qs('#mailTotalSpan');
    if(span) span.textContent = Number(total || 0);
  }

  function renderPagination(page, pages, limit){
    state.page = page;
    state.limit = limit;
    let nav = qs('.pagination-bar');
    if(pages <= 1){
      if(nav) nav.style.display = 'none';
      return;
    }
    if(!nav){
      nav = document.createElement('nav');
      nav.className = 'pagination-bar';
      if(table && table.parentNode){
        table.parentNode.insertBefore(nav, table.nextSibling);
      } else {
        document.body.appendChild(nav);
      }
    }
    nav.style.display = '';
    const ul = document.createElement('ul');
    ul.className = 'pagination-list';
    const windowSize = 3;
    const start = Math.max(1, page - windowSize);
    const end = Math.min(pages, page + windowSize);
    const addLink = (pg, label, disabled)=>{
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'pg' + (disabled ? ' disabled' : '');
      a.dataset.page = String(pg);
      a.textContent = label;
      if(pg === page && !disabled) a.classList.add('active');
      a.addEventListener('click', e=>{
        e.preventDefault();
        if(disabled || pg === page) return;
        fetchList({ page: pg });
      });
      li.appendChild(a);
      ul.appendChild(li);
    };
    addLink(Math.max(1, page - 1), '«', page <= 1);
    for(let i = start; i <= end; i++) addLink(i, String(i), false);
    addLink(Math.min(pages, page + 1), '»', page >= pages);

    nav.innerHTML = '';
    nav.appendChild(ul);
  }

  function enhanceInitialPagination(){
    const nav = qs('.pagination-bar');
    if(!nav) return;
    nav.querySelectorAll('a.pg').forEach(link=>{
      if(link.dataset.bound === '1') return;
      link.dataset.bound = '1';
      link.addEventListener('click', e=>{
        const url = new URL(link.href, window.location.href);
        const page = parseInt(url.searchParams.get('page') || '1', 10);
        if(!Number.isFinite(page)) return;
        e.preventDefault();
        fetchList({ page });
      });
    });
  }

  function bindSorting(){
    qsa('th.sortable', table?.tHead || table).forEach(th=>{
      th.style.cursor = 'pointer';
      th.addEventListener('click', ()=>{
        const col = th.getAttribute('data-sort');
        if(!col) return;
        const current = table?.dataset.sort || state.sort;
        const dir = table?.dataset.dir || state.dir;
        const next = (col === current && dir === 'ASC') ? 'DESC' : 'ASC';
        if(table){
          table.dataset.sort = col;
          table.dataset.dir = next;
        }
        fetchList({ page: 1, sort: col, dir: next });
      });
    });
  }

  function applySortIndicators(){
    qsa('th.sortable', table?.tHead || table).forEach(th=>{
      const base = th.getAttribute('data-label') || th.textContent.replace(/\s*[▲▼]$/, '').trim();
      th.setAttribute('data-label', base);
      const col = th.getAttribute('data-sort');
      let text = base;
      if(col && col === (table?.dataset.sort || state.sort)){
        text = base + ' ' + ((table?.dataset.dir || state.dir) === 'ASC' ? '▲' : '▼');
      }
      th.textContent = text;
    });
  }

  async function refreshMailLogs(){
    const box = qs('#mailLogBox');
    const typeSel = qs('#mailLogType');
    const limitSel = qs('#mailLogLimit');
    const meta = qs('#mailLogMeta');
    const type = typeSel ? typeSel.value : 'sql';
    const limit = limitSel ? parseInt(limitSel.value, 10) || 50 : 50;
    if(box) box.textContent = translate('logs.loading', '-- Loading --');
    if(meta) meta.textContent = '';
    try{
      const res = await apiPost('/mail/api/logs', { type, limit });
      if(res && res.success){
        const lines = Array.isArray(res.logs) ? res.logs : [];
        if(box) box.textContent = lines.length ? lines.join('\n') : translate('logs.empty', '-- No logs --');
        if(meta){
          const count = lines.length;
          const file = res.file || type;
          const srv = res.entries && res.entries.length ? (res.entries[0].server ?? '') : '';
          if(srv){
            meta.textContent = translate('logs.meta_with_server', ':file | Lines: :count | Server: :server', {
              file,
              count,
              server: srv
            });
          } else {
            meta.textContent = translate('logs.meta', ':file | Lines: :count', {
              file,
              count
            });
          }
        }
      } else {
        if(box) box.textContent = translate('logs.failed', '-- Load failed --');
        mailNotify(res?.message || translateStatus('logs_failed', 'Failed to load mail logs'), 'error', { duration: 6000 });
      }
    } catch(err){
      if(box) box.textContent = translate('logs.failed', '-- Load failed --');
      const errMsg = `${translateStatus('logs_failed', 'Failed to load mail logs')}: ${err?.message || err}`;
      mailNotify(errMsg, 'error', { duration: 6000 });
    }
  }

  function bindFilters(){
    filterForm?.addEventListener('submit', e=>{
      e.preventDefault();
      state.page = 1;
      fetchList({ page: 1 });
    });
    const resetBtn = qs('#btn-mail-reset');
    if(resetBtn){
      resetBtn.addEventListener('click', ()=>{
        if(!filterForm) return;
        filterForm.reset();
        ['filter_sender','filter_receiver','filter_subject','filter_expiring'].forEach(name=>{
          const input = filterForm.querySelector(`[name="${name}"]`);
          if(input) input.value = '';
        });
  const unreadSelect = filterForm.querySelector('select[name="filter_unread"]');
  if(unreadSelect) unreadSelect.value = '';
  const hasItemsSelect = filterForm.querySelector('select[name="filter_has_items"]');
  if(hasItemsSelect) hasItemsSelect.value = '';
        const limitInput = filterForm.querySelector('input[name="limit"]');
        if(limitInput) limitInput.value = String(window.MAIL_STATE?.limit || 50);
        if(table){
          table.dataset.sort = 'id';
          table.dataset.dir = 'DESC';
        }
        state.sort = 'id';
        state.dir = 'DESC';
        state.page = 1;
        fetchList({ page: 1, sort: 'id', dir: 'DESC' });
      });
    }
    qs('#btn-mail-refresh')?.addEventListener('click', ()=> fetchList({ page: state.page || 1 }));
    const logBtn = qs('#btn-mail-log');
    if(logBtn){
      logBtn.addEventListener('click', ()=>{
        openModal('#modal-mail-log');
        refreshMailLogs();
      });
    }
    qs('#mailLogType')?.addEventListener('change', ()=> refreshMailLogs());
    qs('#mailLogLimit')?.addEventListener('change', ()=> refreshMailLogs());
    qs('#btn-refresh-mail-log')?.addEventListener('click', ()=> refreshMailLogs());
  }

  async function markReadOne(id){
    if(!id) return;
    const btn = table?.querySelector(`.action-mark-read[data-id="${id}"]`);
    if(btn) btn.disabled = true;
    try{
      const res = await apiPost('/mail/api/mark-read', { mail_id: id });
      if(res && res.success){
        mailNotify(res?.message || translateStatus('mark_read_done', 'Mail marked as read'), 'success');
        fetchList({ page: state.page });
      } else {
        mailNotify(res?.message || translateStatus('mark_failed', 'Failed to mark as read'), 'error');
        if(btn) btn.disabled = false;
      }
    } catch(err){
      const errMsg = `${translateStatus('mark_failed', 'Failed to mark as read')}: ${err?.message || err}`;
      mailNotify(errMsg, 'error');
      if(btn) btn.disabled = false;
    }
  }

  async function deleteOne(id){
    if(!id) return;
    const confirmDelete = translate('confirm.delete_one', 'Delete this mail (system/GM)?');
    if(!confirm(confirmDelete)) return;
    try{
      const res = await apiPost('/mail/api/delete', { mail_id: id });
      if(res && res.success){
        mailNotify(res.message || translateStatus('delete_done', 'Mail deleted'), 'success');
        fetchList({ page: state.page });
      } else {
        mailNotify(res?.message || translateStatus('delete_failed', 'Delete failed'), 'error');
      }
    } catch(err){
      const errMsg = `${translateStatus('delete_failed', 'Delete failed')}: ${err?.message || err}`;
      mailNotify(errMsg, 'error');
    }
  }

  async function bulkMark(){
    const ids = selectedIds();
    if(!ids.length) return;
    try{
      const res = await apiPost('/mail/api/mark-read-bulk', { ids: ids.join(',') });
      if(res && res.success){
  const fallback = translateStatus('bulk_mark_done', 'Marked :count mails as read', { count: ids.length });
  mailNotify(res?.message || fallback, 'success');
        fetchList({ page: state.page });
      } else {
        mailNotify(res?.message || translateStatus('bulk_mark_failed', 'Bulk mark failed'), 'error');
      }
    } catch(err){
      const errMsg = `${translateStatus('bulk_mark_failed', 'Bulk mark failed')}: ${err?.message || err}`;
      mailNotify(errMsg, 'error');
    }
  }

  async function bulkDelete(){
    const ids = selectedIds();
    if(!ids.length) return;
    const confirmDelete = translate('confirm.delete_selected', 'Delete selected mails?');
    if(!confirm(confirmDelete)) return;
    try{
      const res = await apiPost('/mail/api/delete-bulk', { ids: ids.join(',') });
      if(res && res.success){
  const fallback = translateStatus('bulk_delete_done', 'Deleted :count mails', { count: ids.length });
  mailNotify(res?.message || fallback, 'success');
        fetchList({ page: state.page });
      } else {
        mailNotify(res?.message || translateStatus('bulk_delete_failed', 'Bulk delete failed'), 'error');
      }
    } catch(err){
      const errMsg = `${translateStatus('bulk_delete_failed', 'Bulk delete failed')}: ${err?.message || err}`;
      mailNotify(errMsg, 'error');
    }
  }

  function clearDetail(){
    const wrap = qs('#mailDetailBody');
    if(!wrap) return;
    const loading = wrap.querySelector('.mail-detail-loading');
    if(loading) loading.style.display = '';
    const content = wrap.querySelector('.mail-detail-content');
    if(content) content.style.display = 'none';
    const idBadge = qs('#mdMailId');
    if(idBadge){
      idBadge.textContent = '';
      idBadge.hidden = true;
    }
    ['#mdSender','#mdReceiver','#mdMoney','#mdExpire','#mdStatus','#mdItemCount','#mdSubject','#mdBody'].forEach(sel=>{
      const el = qs(sel);
      if(el) el.textContent = '';
    });
    const items = qs('#mdItems');
    if(items) items.innerHTML = '';
  }

  function renderMailDetail(mail){
    const wrap = qs('#mailDetailBody');
    if(!wrap) return;
    const loading = wrap.querySelector('.mail-detail-loading');
    if(loading) loading.style.display = 'none';
    const content = wrap.querySelector('.mail-detail-content');
    if(content) content.style.display = '';

    const idBadge = qs('#mdMailId');
    if(idBadge){
      idBadge.textContent = '#' + (mail.id ?? '');
      idBadge.hidden = false;
    }
    const unread = !mail.checked || Number(mail.checked) === 0;
    const expire = formatExpire(mail.expire_time);
    const money = formatMoney(mail.money);
    const items = Array.isArray(mail.items) ? mail.items : [];

    const senderEl = qs('#mdSender');
    if(senderEl) senderEl.textContent = mail.sender_name || ('#' + (mail.sender ?? ''));
    const receiverEl = qs('#mdReceiver');
    if(receiverEl) receiverEl.textContent = mail.receiver_name || ('#' + (mail.receiver ?? ''));
    const moneyEl = qs('#mdMoney');
    if(moneyEl) moneyEl.textContent = money;
    const expireEl = qs('#mdExpire');
    if(expireEl) expireEl.textContent = expire;
    const statusEl = qs('#mdStatus');
    if(statusEl) statusEl.innerHTML = formatStatus(unread);
    const itemCountEl = qs('#mdItemCount');
    if(itemCountEl) itemCountEl.textContent = items.length;
    const subjectEl = qs('#mdSubject');
    if(subjectEl) subjectEl.textContent = mail.subject || translate('detail.no_subject', '(No subject)');
    const bodyEl = qs('#mdBody');
    if(bodyEl){
      const text = mail.body || mail.message || '';
      bodyEl.textContent = text || translate('detail.no_body', '(No content)');
    }
    renderMailItems(items);
  }

  function renderMailItems(items){
    const wrap = qs('#mdItems');
    if(!wrap) return;
    if(!items || !items.length){
      const noneText = escapeHtml(translate('detail.attachments_none', 'No attachments'));
      wrap.innerHTML = `<span class="muted small">${noneText}</span>`;
      return;
    }
    wrap.innerHTML = items.map(item=>{
      const entry = item.item_template || item.itemEntry || item.entry || 0;
      const name = item.item_name || item.item_name_cn || item.item_name_en || ('#' + entry);
      const qty = item.count || item.qty || item.quantity;
      const title = qty ? `${name} x${qty}` : name;
      const href = entry ? panelUrl('/item?edit_id=' + entry) : '#';
      const qualityClass = item.item_quality_class ? ' ' + item.item_quality_class : '';
      return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener" class="item-link${qualityClass}">#${entry} ${escapeHtml(title)}</a>`;
    }).join('');
  }

  async function openDetail(id){
    if(!id) return;
    openModal('#modal-mail-detail');
    clearDetail();
    try{
      const res = await apiPost('/mail/api/view', { mail_id: id });
      if(res && res.success && res.mail){
        renderMailDetail(res.mail);
      } else {
        mailNotify(res?.message || translateStatus('detail_failed', 'Failed to load mail detail'), 'error');
        hideModal('#modal-mail-detail');
      }
    } catch(err){
      const errMsg = `${translateStatus('detail_failed', 'Failed to load mail detail')}: ${err?.message || err}`;
      mailNotify(errMsg, 'error');
      hideModal('#modal-mail-detail');
    }
  }

  async function loadStats(){
    try{
      const res = await apiPost('/mail/api/stats', {});
      if(res && res.success){
        const span = qs('#mailStatsSpan');
        if(span){
          const summary = translate('stats.summary', 'Unread estimate: :unread | Expiring in 7 days: :expiring', {
            unread: res.unread_estimate ?? '-',
            expiring: res.expiring_7d ?? '-'
          });
          span.textContent = summary;
        }
      }
    } catch(err){
      console.warn('Mail stats unavailable', err);
    }
  }

  function bindToolbar(){
    const selectAll = qs('#mailSelectAll');
    if(selectAll){
      selectAll.addEventListener('change', ()=>{
        const checked = selectAll.checked;
        qsa('.mail-select', table).forEach(cb => cb.checked = checked);
        updateBulkState();
      });
    }
    qs('#bulkMarkReadBtn')?.addEventListener('click', bulkMark);
    qs('#bulkDeleteBtn')?.addEventListener('click', bulkDelete);
  }

  function bindTableDelegates(){
    if(!table) return;
    table.addEventListener('click', e=>{
      const viewBtn = e.target.closest('.action-view');
      if(viewBtn){
        e.preventDefault();
        openDetail(viewBtn.dataset.id);
        return;
      }
      const markBtn = e.target.closest('.action-mark-read');
      if(markBtn){
        e.preventDefault();
        if(markBtn.disabled) return;
        markReadOne(markBtn.dataset.id);
        return;
      }
      const delBtn = e.target.closest('.action-delete');
      if(delBtn){
        e.preventDefault();
        deleteOne(delBtn.dataset.id);
      }
    });
    table.addEventListener('change', e=>{
      if(e.target.classList.contains('mail-select')) updateBulkState();
    });
  }

  function resolveModal(ref){
    if(!ref) return null;
    if(typeof ref === 'string'){
      if(ref.startsWith('#')) return document.querySelector(ref);
      return document.getElementById(ref);
    }
    if(ref && ref.nodeType === 1) return ref;
    return null;
  }

  function openModal(ref){
    const el = resolveModal(ref);
    if(!el) return null;
    el.style.display = 'block';
    requestAnimationFrame(()=> el.classList.add('active'));
    document.body.style.overflow = 'hidden';
    return el;
  }

  function hideModal(ref){
    const el = resolveModal(ref);
    if(!el) return;
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

  if(!window.__mailModalBound){
    window.__mailModalBound = true;
    document.addEventListener('click', e=>{
      const closer = e.target.closest('[data-close]');
      if(closer){
        hideModal(closer.closest('.modal-backdrop'));
        return;
      }
      if(e.target.classList.contains('modal-backdrop')) hideModal(e.target);
    });
    document.addEventListener('keydown', e=>{
      if(e.key === 'Escape') hideAllModals();
    });
  }

  function init(){
    bindFilters();
    bindToolbar();
    bindTableDelegates();
    bindSorting();
    enhanceInitialPagination();
    applySortIndicators();
    updateBulkState();
    loadStats();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

