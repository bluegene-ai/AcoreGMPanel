(function(){
  if(document.body.dataset.module !== 'aegis') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const data = window.AEGIS_DATA || { options:{}, defaults:{} };
  const opts = data.options || {};
  const defaults = data.defaults || {};
  const capabilities = window.PANEL_CAPABILITIES || {};
  const can = key => capabilities[key] !== false;

  const searchParams = new URLSearchParams(location.search);
  const currentServer = searchParams.get('server') || '';

  const dom = {
    feedback: document.getElementById('aegisFeedback'),
    refreshAll: document.getElementById('aegisRefreshAllBtn'),
    overviewDays: document.getElementById('aegisOverviewDays'),
    statsGrid: document.getElementById('aegisStatsGrid'),
    stageSummary: document.getElementById('aegisStageSummary'),
    cheatSummary: document.getElementById('aegisCheatSummary'),
    topOffenders: document.getElementById('aegisTopOffenders'),
    playerForm: document.getElementById('aegisPlayerForm'),
    playerLookup: document.getElementById('aegisPlayerLookup'),
    playerCard: document.getElementById('aegisPlayerCard'),
    manualForm: document.getElementById('aegisManualForm'),
    manualAction: document.getElementById('aegisManualAction'),
    manualTarget: document.getElementById('aegisManualTarget'),
    offenseForm: document.getElementById('aegisOffenseForm'),
    offenseQuery: document.getElementById('aegisOffenseQuery'),
    offenseStage: document.getElementById('aegisOffenseStage'),
    offenseCheatType: document.getElementById('aegisOffenseCheatType'),
    offenseStatus: document.getElementById('aegisOffenseStatus'),
    offenseTable: document.getElementById('aegisOffenseTableBody'),
    offensePagination: document.getElementById('aegisOffensePagination'),
    eventForm: document.getElementById('aegisEventForm'),
    eventQuery: document.getElementById('aegisEventQuery'),
    eventCheatType: document.getElementById('aegisEventCheatType'),
    eventEvidenceLevel: document.getElementById('aegisEventEvidenceLevel'),
    eventDays: document.getElementById('aegisEventDays'),
    eventTable: document.getElementById('aegisEventTableBody'),
    eventPagination: document.getElementById('aegisEventPagination'),
    logRefresh: document.getElementById('aegisLogRefreshBtn'),
    logMeta: document.getElementById('aegisLogMeta'),
    logBox: document.getElementById('aegisLogBox')
  };

  const state = {
    offensePage: 1,
    eventPage: 1
  };

  function t(path, fallback){
    if(typeof panel.moduleLocale === 'function') return panel.moduleLocale('aegis', path, fallback);
    return fallback || path;
  }

  function esc(value){
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
      '&':'&amp;',
      '<':'&lt;',
      '>':'&gt;',
      '"':'&quot;',
      "'":'&#39;'
    }[char]));
  }

  function withServer(path){
    if(!currentServer) return path;
    return path + (path.includes('?') ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  async function request(path, method, body){
    const url = withServer(path);
    if(api){
      if((method || 'GET').toUpperCase() === 'POST') return api(url, { method:'POST', body: body || {} });
      return api(url, { method:'GET' });
    }
    const init = { method: method || 'GET' };
    if(init.method === 'POST'){
      init.headers = { 'Content-Type': 'application/json' };
      init.body = JSON.stringify(body || {});
    }
    const res = await fetch(url, init);
    return res.json();
  }

  function showMessage(type, message){
    if(feedback && dom.feedback){
      feedback.show(dom.feedback, type, message, { duration: 3500 });
      return;
    }
    if(dom.feedback){
      dom.feedback.textContent = message;
      dom.feedback.classList.remove('aegis-feedback-hidden');
      dom.feedback.classList.add('is-visible');
    }
  }

  function cheatLabel(value){
    const item = (opts.cheat_types || []).find((row) => Number(row.value) === Number(value));
    return item ? item.label : String(value);
  }

  function stageLabel(value){
    const item = (opts.punish_stages || []).find((row) => Number(row.value) === Number(value));
    return item ? item.label : String(value);
  }

  function evidenceLabel(value){
    const item = (opts.evidence_levels || []).find((row) => Number(row.value) === Number(value));
    return item ? item.label : String(value);
  }

  function formatTime(value){
    if(!value) return '-';
    if(typeof value === 'number') return new Date(value * 1000).toLocaleString();
    const date = new Date(value);
    if(Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString();
  }

  function formatPos(row){
    return [row.map_id, row.zone_id, `${Number(row.pos_x || 0).toFixed(1)}, ${Number(row.pos_y || 0).toFixed(1)}, ${Number(row.pos_z || 0).toFixed(1)}`].join(' / ');
  }

  function setLoading(tableBody, colspan){
    if(!tableBody) return;
    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="text-center muted">${esc(t('status.loading', 'Loading...'))}</td></tr>`;
  }

  function renderPagination(host, page, pages, onClick){
    if(!host) return;
    if(!pages || pages <= 1){
      host.innerHTML = '';
      return;
    }
    const prevDisabled = page <= 1 ? 'disabled' : '';
    const nextDisabled = page >= pages ? 'disabled' : '';
    host.innerHTML = `
      <button type="button" class="btn outline btn-sm" data-page="${page - 1}" ${prevDisabled}>${esc(t('pagination.prev', 'Prev'))}</button>
      <span class="aegis-pagination__label">${esc(t('pagination.label', 'Page :page / :pages').replace(':page', page).replace(':pages', pages))}</span>
      <button type="button" class="btn outline btn-sm" data-page="${page + 1}" ${nextDisabled}>${esc(t('pagination.next', 'Next'))}</button>
    `;
    host.querySelectorAll('button[data-page]').forEach((button) => {
      button.addEventListener('click', () => {
        if(button.hasAttribute('disabled')) return;
        onClick(Number(button.getAttribute('data-page') || page));
      });
    });
  }

  async function loadOverview(){
    if(!can('overview') || !dom.statsGrid || !dom.overviewDays) return;
    const days = Number(dom.overviewDays.value || defaults.overview_days || 7);
    const json = await request(`/aegis/api/overview?days=${encodeURIComponent(days)}`, 'GET');
    if(!json || !json.success){
      throw new Error((json && json.message) || t('errors.load_overview', 'Failed to load overview'));
    }
    const payload = json.payload || {};
    const offense = payload.offense || {};
    const events = payload.events || {};
    dom.statsGrid.innerHTML = [
      [t('cards.tracked', 'Tracked players'), offense.tracked || 0],
      [t('cards.debuffed', 'Debuffed'), offense.debuffed || 0],
      [t('cards.jailed', 'Jailed'), offense.jailed || 0],
      [t('cards.banned', 'Banned'), offense.banned || 0],
      [t('cards.last_day', 'Events 24h'), events.last_day || 0],
      [t('cards.window_total', 'Events window'), events.window_total || 0]
    ].map(([label, value]) => `
      <article class="aegis-stat-card">
        <div class="aegis-stat-card__label">${esc(label)}</div>
        <div class="aegis-stat-card__value">${esc(value)}</div>
      </article>
    `).join('');

    dom.stageSummary.innerHTML = (payload.stages || []).map((row) => `<span class="badge">${esc(stageLabel(row.value))}: ${esc(row.total)}</span>`).join('') || `<span class="muted">${esc(t('status.empty', 'No data'))}</span>`;
    dom.cheatSummary.innerHTML = (payload.cheats || []).map((row) => `<span class="badge">${esc(cheatLabel(row.value))}: ${esc(row.total)}</span>`).join('') || `<span class="muted">${esc(t('status.empty', 'No data'))}</span>`;
    dom.topOffenders.innerHTML = (payload.top_offenders || []).map((row) => {
      const inner = `
        <strong>${esc(row.player_name || ('#' + row.guid))}</strong>
        <span>${esc(stageLabel(row.punish_stage))}</span>
        <span>${esc(t('top.offense_count', 'Offenses: :count').replace(':count', row.offense_count || 0))}</span>
      `;
      if(can('player')){
        return `<button type="button" class="aegis-list-item" data-guid="${Number(row.guid || 0)}" data-name="${esc(row.player_name || '')}">${inner}</button>`;
      }
      return `<div class="aegis-list-item">${inner}</div>`;
    }).join('') || `<div class="muted">${esc(t('status.empty', 'No data'))}</div>`;
    if(can('player')){
      dom.topOffenders.querySelectorAll('[data-guid]').forEach((button) => {
        button.addEventListener('click', () => loadPlayer({ guid: Number(button.getAttribute('data-guid') || 0) }));
      });
    }
  }

  async function loadPlayer(input){
    if(!can('player') || !dom.playerCard || !dom.playerLookup) return;
    const guid = input && input.guid ? Number(input.guid) : 0;
    const name = input && input.name ? String(input.name).trim() : '';
    const query = guid > 0 ? `guid=${guid}` : `name=${encodeURIComponent(name)}`;
    const json = await request(`/aegis/api/player?${query}`, 'GET');
    if(!json || !json.success){
      throw new Error((json && json.message) || t('errors.load_player', 'Failed to load player'));
    }
    const payload = json.payload || {};
    const player = payload.player || {};
    const offense = payload.offense || null;
    const recentEvents = payload.recent_events || [];
    dom.playerLookup.value = player.guid ? `${player.name} (#${player.guid})` : (player.name || name || String(guid || ''));
    dom.playerCard.classList.remove('aegis-player-card--empty');
    dom.playerCard.innerHTML = `
      <div class="aegis-player-card__header">
        <strong>${esc(player.name || ('#' + (player.guid || '')))}</strong>
        <span class="badge">${esc(player.online ? t('player.online', 'Online') : t('player.offline', 'Offline'))}</span>
      </div>
      <div class="aegis-player-meta">
        <span>${esc(t('player.guid', 'GUID: :value').replace(':value', player.guid || '-'))}</span>
        <span>${esc(t('player.account', 'Account: :value').replace(':value', player.account_username || ('#' + (player.account || ''))))}</span>
        <span>${esc(t('player.level', 'Level: :value').replace(':value', player.level || '-'))}</span>
      </div>
      ${offense ? `
        <div class="aegis-player-offense">
          <span>${esc(t('player.stage', 'Stage: :value').replace(':value', stageLabel(offense.punish_stage)))}</span>
          <span>${esc(t('player.cheat', 'Cheat: :value').replace(':value', cheatLabel(offense.last_cheat_type)))}</span>
          <span>${esc(t('player.offenses', 'Offenses: :value').replace(':value', offense.offense_count || 0))}</span>
          <span>${esc(t('player.tier', 'Tier: :value').replace(':value', offense.offense_tier || 0))}</span>
        </div>
        <div class="aegis-player-reason">${esc(offense.last_reason || t('player.no_reason', 'No reason'))}</div>
      ` : `<div class="muted">${esc(t('player.no_offense', 'No offense record'))}</div>`}
      <div class="aegis-player-events">
        <h3>${esc(t('player.recent_events', 'Recent events'))}</h3>
        ${(recentEvents.length ? recentEvents.map((row) => `<div class="aegis-player-event"><span>${esc(formatTime(row.created_at))}</span><span>${esc(cheatLabel(row.cheat_type))}</span><span>${esc(evidenceLabel(row.evidence_level))}</span><span>${esc(row.evidence_tag || '')}</span></div>`).join('') : `<div class="muted">${esc(t('status.empty', 'No data'))}</div>`) }
      </div>
    `;
  }

  async function loadOffenses(page){
    if(!can('offenses') || !dom.offenseTable) return;
    state.offensePage = page || 1;
    setLoading(dom.offenseTable, 9);
    const params = new URLSearchParams({
      query: dom.offenseQuery.value || '',
      stage: dom.offenseStage.value || '',
      cheat_type: dom.offenseCheatType.value || '0',
      status: dom.offenseStatus.value || 'all',
      page: String(state.offensePage),
      per_page: String(defaults.per_page || 20)
    });
    const json = await request(`/aegis/api/offenses?${params.toString()}`, 'GET');
    if(!json || !json.success){
      throw new Error((json && json.message) || t('errors.load_offenses', 'Failed to load offenses'));
    }
    const payload = json.payload || {};
    const items = payload.items || [];
    if(!items.length){
      dom.offenseTable.innerHTML = `<tr><td colspan="9" class="text-center muted">${esc(t('status.empty', 'No data'))}</td></tr>`;
    } else {
      dom.offenseTable.innerHTML = items.map((row) => `
        <tr>
          <td><button type="button" class="link-button" data-player-guid="${Number(row.guid || 0)}">${esc(row.player_name || ('#' + row.guid))}</button></td>
          <td>${esc(row.account_username || ('#' + row.account_id))}</td>
          <td>${esc(cheatLabel(row.last_cheat_type))}</td>
          <td>${esc(stageLabel(row.punish_stage))}</td>
          <td>${esc(row.offense_count || 0)}</td>
          <td>${esc(row.offense_tier || 0)}</td>
          <td title="${esc(row.last_reason || '')}">${esc(row.last_reason || '-')}</td>
          <td>${esc(formatTime(Number(row.last_offense_at || 0)))}</td>
          <td>${can('actions')
            ? `<button type="button" class="btn-sm btn outline js-aegis-action" data-action="clear" data-target="${esc(row.player_name || '')}">${esc(t('actions.clear', 'Clear'))}</button>
            <button type="button" class="btn-sm btn danger js-aegis-action" data-action="delete" data-target="${esc(row.player_name || '')}">${esc(t('actions.delete', 'Delete'))}</button>`
            : `<span class="muted">${esc(t('status.read_only', 'Read-only'))}</span>`}</td>
        </tr>
      `).join('');
    }
    if(can('player')){
      dom.offenseTable.querySelectorAll('[data-player-guid]').forEach((button) => {
        button.addEventListener('click', () => loadPlayer({ guid: Number(button.getAttribute('data-player-guid') || 0) }).catch(handleError));
      });
    }
    if(can('actions')){
      dom.offenseTable.querySelectorAll('.js-aegis-action').forEach((button) => {
        button.addEventListener('click', () => submitAction(button.getAttribute('data-action') || '', button.getAttribute('data-target') || ''));
      });
    }
    renderPagination(dom.offensePagination, payload.page || 1, payload.pages || 1, (nextPage) => loadOffenses(nextPage).catch(handleError));
  }

  async function loadEvents(page){
    if(!can('events') || !dom.eventTable) return;
    state.eventPage = page || 1;
    setLoading(dom.eventTable, 9);
    const params = new URLSearchParams({
      query: dom.eventQuery.value || '',
      cheat_type: dom.eventCheatType.value || '0',
      evidence_level: dom.eventEvidenceLevel.value || '',
      days: dom.eventDays.value || String(defaults.events_days || 7),
      page: String(state.eventPage),
      per_page: String(defaults.per_page || 20)
    });
    const json = await request(`/aegis/api/events?${params.toString()}`, 'GET');
    if(!json || !json.success){
      throw new Error((json && json.message) || t('errors.load_events', 'Failed to load events'));
    }
    const payload = json.payload || {};
    const items = payload.items || [];
    if(!items.length){
      dom.eventTable.innerHTML = `<tr><td colspan="9" class="text-center muted">${esc(t('status.empty', 'No data'))}</td></tr>`;
    } else {
      dom.eventTable.innerHTML = items.map((row) => `
        <tr>
          <td>${esc(formatTime(row.created_at))}</td>
          <td><button type="button" class="link-button" data-player-guid="${Number(row.guid || 0)}">${esc(row.player_name || ('#' + row.guid))}</button></td>
          <td>${esc(row.account_username || ('#' + row.account_id))}</td>
          <td>${esc(cheatLabel(row.cheat_type))}</td>
          <td>${esc(evidenceLabel(row.evidence_level))}</td>
          <td>${esc(row.evidence_tag || '-')}</td>
          <td>${esc(Number(row.risk_delta || 0).toFixed(1))} / ${esc(Number(row.total_risk_after || 0).toFixed(1))}</td>
          <td>${esc(formatPos(row))}</td>
          <td title="${esc(row.detail_text || '')}">${esc(row.detail_text || '-')}</td>
        </tr>
      `).join('');
    }
    if(can('player')){
      dom.eventTable.querySelectorAll('[data-player-guid]').forEach((button) => {
        button.addEventListener('click', () => loadPlayer({ guid: Number(button.getAttribute('data-player-guid') || 0) }).catch(handleError));
      });
    }
    renderPagination(dom.eventPagination, payload.page || 1, payload.pages || 1, (nextPage) => loadEvents(nextPage).catch(handleError));
  }

  async function loadLog(){
    if(!can('logs') || !dom.logMeta || !dom.logBox) return;
    const json = await request(`/aegis/api/log?limit=${encodeURIComponent(defaults.log_limit || 80)}`, 'GET');
    if(!json || !json.success){
      throw new Error((json && json.message) || t('errors.load_log', 'Failed to load log'));
    }
    const payload = json.payload || {};
    dom.logMeta.textContent = payload.path || t('log.meta_missing', 'Log file not found');
    dom.logBox.textContent = (payload.lines || []).join('\n') || t('log.empty', '-- No log lines --');
  }

  async function submitAction(action, target){
    if(!can('actions')) return;
    if(!action) return;
    const confirmed = window.confirm(t('manual.confirm', 'Confirm this Aegis operation?'));
    if(!confirmed) return;
    const json = await request('/aegis/api/action', 'POST', { action, target });
    if(!json || !json.success){
      throw new Error((json && json.message) || t('actions.failure', 'Operation failed'));
    }
    showMessage('success', json.message || t('actions.success', 'Operation completed'));
    await Promise.all([loadOverview(), loadOffenses(state.offensePage), loadEvents(state.eventPage), loadLog()]);
    if(target && can('player')){
      await loadPlayer({ name: target });
    }
  }

  function handleError(error){
    showMessage('error', error && error.message ? error.message : t('errors.generic', 'Request failed'));
  }

  if(dom.playerForm && can('player')){
    dom.playerForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const raw = (dom.playerLookup.value || '').trim();
      if(!raw){
        showMessage('error', t('errors.player_required', 'Please enter player name or GUID'));
        return;
      }
      const guidMatch = raw.match(/#(\d+)/);
      const guid = /^\d+$/.test(raw) ? Number(raw) : (guidMatch ? Number(guidMatch[1]) : 0);
      loadPlayer(guid > 0 ? { guid } : { name: raw }).catch(handleError);
    });
  }

  if(dom.manualAction && dom.manualTarget && can('actions')){
    dom.manualAction.addEventListener('change', () => {
      const selected = dom.manualAction.selectedOptions[0];
      const needsTarget = !!(selected && selected.getAttribute('data-needs-target') === '1');
      dom.manualTarget.disabled = !needsTarget;
      if(!needsTarget) dom.manualTarget.value = '';
    });
  }

  if(dom.manualForm && dom.manualAction && dom.manualTarget && can('actions')){
    dom.manualForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const action = dom.manualAction.value || '';
      const selected = dom.manualAction.selectedOptions[0];
      const needsTarget = !!(selected && selected.getAttribute('data-needs-target') === '1');
      const target = (dom.manualTarget.value || '').trim();
      if(needsTarget && !target){
        showMessage('error', t('errors.target_required', 'Target player is required'));
        return;
      }
      submitAction(action, target).catch(handleError);
    });
  }

  if(dom.offenseForm && can('offenses')){
    dom.offenseForm.addEventListener('submit', (event) => {
      event.preventDefault();
      loadOffenses(1).catch(handleError);
    });
  }

  if(dom.eventForm && can('events')){
    dom.eventForm.addEventListener('submit', (event) => {
      event.preventDefault();
      loadEvents(1).catch(handleError);
    });
  }

  if(dom.refreshAll && (can('overview') || can('offenses') || can('events') || can('logs'))){
    dom.refreshAll.addEventListener('click', () => {
      Promise.all([loadOverview(), loadOffenses(state.offensePage), loadEvents(state.eventPage), loadLog()]).catch(handleError);
    });
  }

  if(dom.logRefresh && can('logs')) dom.logRefresh.addEventListener('click', () => loadLog().catch(handleError));
  if(dom.overviewDays && can('overview')) dom.overviewDays.addEventListener('change', () => loadOverview().catch(handleError));

  if(dom.manualAction && can('actions')) dom.manualAction.dispatchEvent(new Event('change'));
  Promise.all([loadOverview(), loadOffenses(1), loadEvents(1), loadLog()]).catch(handleError);
})();