/**
 * File: public/assets/js/modules/supervisor.js
 * Purpose: Live state + start/stop/restart controls for acore_supervisor.exe.
 *          One supervisor per realm: the switcher at the top selects which instance this page
 *          talks to (?instance=<id> on every API call).
 * Functions:
 *   - boot()
 *   - withInstance()
 *   - updateInstanceUrl()
 *   - setActiveInstance()
 *   - renderInstances()
 *   - switchInstance()
 *   - refreshStatus()
 *   - renderState()
 *   - renderService()
 *   - sendCommand()
 *   - awaitCommand()
 *   - showResult()
 *   - formatDuration()
 */

const svQs = (sel, ctx = document) => ctx.querySelector(sel);
const svQsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const svEscape = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;');

function formatDuration(seconds){
  const value = Number(seconds);
  if(!Number.isFinite(value) || value < 0) return '--';
  const days = Math.floor(value / 86400);
  const hours = Math.floor((value % 86400) / 3600);
  const minutes = Math.floor((value % 3600) / 60);
  const secs = Math.floor(value % 60);
  const pad = (n) => String(n).padStart(2, '0');
  if(days > 0) return `${days}d ${pad(hours)}h${pad(minutes)}m`;
  if(hours > 0) return `${hours}h${pad(minutes)}m${pad(seconds % 60)}s`;
  return `${minutes}m${pad(secs)}s`;
}

function boot(){
  if(!document.body || document.body.dataset.module !== 'supervisor') return;

  const config = window.SUPERVISOR_DATA || {};
  const capabilities = window.PANEL_CAPABILITIES || {};
  const canControl = capabilities.control === true || config.canControl === true;
  const panelRef = window.Panel || {};
  const PanelApi = panelRef.api || null;
  if(!PanelApi) return;

  const moduleTranslate = typeof panelRef.createModuleTranslator === 'function'
    ? panelRef.createModuleTranslator('supervisor')
    : (path, fallback) => fallback;
  const t = (key, fallback) => moduleTranslate(key, fallback);

  const servicesBox = svQs('#sv-services');
  const logBox = svQs('#sv-log');
  const resultBox = svQs('#sv-result');
  const refreshBtn = svQs('#sv-refresh');
  const autoBox = svQs('#sv-autorefresh');
  const instancesBox = svQs('#sv-instances');
  const statusUrl = config.statusUrl || '/supervisor/api/status';
  const logUrl = config.logUrl || '/supervisor/api/log';
  const commandUrl = config.commandUrl || '/supervisor/api/command';

  // ---- which supervisor instance this page talks to (one acore_supervisor.exe per realm) --------
  let currentInstance = String(config.instance || '');

  function withInstance(url, params){
    const query = new URLSearchParams(params || {});
    if(currentInstance !== '') query.set('instance', currentInstance);
    const suffix = query.toString();
    return suffix === '' ? url : `${url}${url.includes('?') ? '&' : '?'}${suffix}`;
  }

  function updateInstanceUrl(id){
    if(!window.history || typeof window.history.replaceState !== 'function') return;
    try{
      const url = new URL(window.location.href);
      if(id === '') url.searchParams.delete('instance');
      else url.searchParams.set('instance', id);
      window.history.replaceState({}, '', url.toString());
    }catch(error){ /* ignore */ }
  }

  function setActiveInstance(id){
    svQsa('[data-sv-instance]').forEach((node) => {
      const active = node.dataset.svInstance === id;
      node.classList.toggle('sv-instance--active', active);
      node.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    const labelNode = svQs('[data-sv-field="panel_instance"]');
    if(labelNode){
      const entry = (config.instances || []).find((item) => item.id === id);
      labelNode.textContent = entry && entry.label ? entry.label : id;
    }
  }

  function renderInstances(list){
    if(!Array.isArray(list) || list.length === 0) return;

    // keep the labels/tone of the switcher in sync with the polled state
    config.instances = list;
    list.forEach((item) => {
      const node = svQs(`[data-sv-instance="${String(item.id).replace(/"/g, '')}"]`, instancesBox || document);
      if(!node) return;
      const dot = svQs('[data-sv-instance-field="dot"]', node);
      if(dot){
        dot.className = 'sv-badge sv-badge--' + String(item.tone || 'muted').replace(/[^a-z]/g, '');
      }
      const stateNode = svQs('[data-sv-instance-field="state"]', node);
      if(stateNode){
        stateNode.textContent = item.running
          ? t('supervisor.running', 'supervisor running')
          : t('supervisor.not_running', 'supervisor is not running');
      }
    });
    setActiveInstance(currentInstance);
  }

  async function switchInstance(id){
    if(id === currentInstance) return;
    currentInstance = id;
    setActiveInstance(id);
    updateInstanceUrl(id);
    clearResult();
    showResult(t('messages.switching', 'loading :instance…').replace(':instance', id), 'info');
    await refreshStatus(true);
  }

  let pollTimer = null;
  let busy = false;

  function showResult(message, tone){
    if(!resultBox) return;
    resultBox.hidden = false;
    resultBox.className = 'sv-result sv-result--' + (tone || 'info');
    resultBox.textContent = message;
  }

  function clearResult(){
    if(!resultBox) return;
    resultBox.hidden = true;
    resultBox.textContent = '';
  }

  function setText(root, field, value){
    const node = svQs(`[data-sv-field="${field}"]`, root);
    if(node) node.textContent = value;
  }

  /**
   * The heartbeat cell explains itself: effective timeout, the configured one when the supervisor
   * raised it (1.1.2: max(configured, RecordUpdateTimeDiffInterval + 120s)) and the observed
   * cadence. Must stay in sync with $heartbeatNote() in views/supervisor/index.php.
   */
  function heartbeatNote(service, t){
    const effective = Number(service.heartbeat_timeout_seconds) || 0;
    const configured = Number(service.heartbeat_timeout_configured_seconds) || effective;
    const interval = Number(service.heartbeat_interval_seconds) || 0;
    const cadence = Number(service.heartbeat_cadence_seconds) || 0;
    const parts = [];
    if(configured > 0 && configured !== effective){
      parts.push(t('fields.heartbeat_raised', 'configured :configured s, raised to :effective s')
        .replace(':configured', configured).replace(':effective', effective));
    }else if(interval > 0){
      parts.push(t('fields.heartbeat_interval', 'server writes one line every :seconds s')
        .replace(':seconds', interval));
    }
    if(cadence > 0){
      parts.push(t('fields.heartbeat_cadence', 'measured cadence :seconds s').replace(':seconds', cadence));
    }
    return parts.join(' · ');
  }

  function renderService(node, service){
    node.className = 'sv-card sv-card--' + (service.tone || 'muted');
    const healthBadge = svQs('[data-sv-field="health_label"]', node);
    if(healthBadge){
      healthBadge.className = 'sv-badge sv-badge--' + String(service.tone || 'muted').replace(/[^a-z]/g, '');
      healthBadge.textContent = service.health_label || '';
    }
    setText(node, 'state_label', service.state_label || service.state || '');
    setText(node, 'pid', service.pid > 0 ? service.pid : '--');
    setText(node, 'uptime', formatDuration(service.uptime_seconds));

    // write the CELLS OF THE CELL, never the whole cell: the age, the limit and the explanation are
    // separate spans in the server-rendered markup and a whole-cell overwrite dropped them
    const heartbeatAge = Number(service.heartbeat_age_seconds);
    setText(node, 'heartbeat_age', heartbeatAge >= 0
      ? t('fields.heartbeat_ago', 'heartbeat :seconds s ago').replace(':seconds', heartbeatAge)
      : t('fields.not_available', 'n/a'));
    const timeoutNode = svQs('[data-sv-field="heartbeat_timeout"]', node);
    if(timeoutNode) timeoutNode.textContent = String(Number(service.heartbeat_timeout_seconds) || 0);
    const noteNode = svQs('[data-sv-field="heartbeat_note"]', node);
    if(noteNode){
      const note = heartbeatNote(service, t);
      noteNode.textContent = note;
      noteNode.hidden = note === '';
    }

    const probeState = svQs('[data-sv-field="probe_state"]', node);
    if(probeState){
      probeState.className = 'sv-badge sv-badge--' + (service.probe_ok ? 'ok' : 'error');
      probeState.textContent = service.probe_ok ? t('fields.probe_ok', 'reachable') : t('fields.probe_failed', 'no answer');
    }
    const probeDetail = svQs('[data-sv-field="probe_detail"]', node);
    if(probeDetail){
      const detail = String(service.probe_detail || '');
      probeDetail.textContent = detail;
      probeDetail.hidden = detail === '';
    }

    setText(node, 'restarts', String(service.restarts ?? 0));
    setText(node, 'memory', `${Math.round(Number(service.working_set_mb) || 0)} MB`);
    setText(node, 'last_event', service.last_event || '');

    // a service this supervisor does not run must never offer controls - not even on a page that was
    // rendered while it still was enabled (its ini can be edited under a running page)
    const actions = svQs('.sv-card__actions', node);
    if(actions) actions.hidden = service.enabled === false;

    // the start/stop button pair depends on the state
    if(actions && canControl && service.enabled !== false){
      const restartBtn = svQs('[data-sv-action="restart"]', actions);
      const otherBtn = svQs('[data-sv-action="start"], [data-sv-action="stop"]', actions);
      if(otherBtn){
        const isStopped = service.state === 'stopped';
        const nextAction = isStopped ? 'start' : 'stop';
        otherBtn.dataset.svAction = nextAction;
        otherBtn.textContent = isStopped ? t('actions.start', 'Start') : t('actions.stop', 'Stop');
        otherBtn.className = isStopped ? 'btn outline' : 'btn outline danger';
        if(!isStopped){
          otherBtn.dataset.svConfirm = t('confirm.stop_service', 'Stop this service?').replace(':service', service.name || '');
        }else{
          delete otherBtn.dataset.svConfirm;
        }
      }
      if(restartBtn) restartBtn.disabled = false;
    }
  }

  function renderState(state){
    if(!state) return;
    svQsa('.sv-card[data-sv-service]').forEach((node) => {
      const name = node.dataset.svService;
      const service = (state.services || []).find((item) => item.name === name);
      if(service) renderService(node, service);
    });

    const last = state.supervisor && state.supervisor.last_command ? state.supervisor.last_command : null;
    const lastBox = svQs('#sv-last-command');
    if(lastBox){
      if(!last){
        lastBox.innerHTML = `<span class="sv-muted">${svEscape(t('meta.no_command', 'no command yet'))}</span>`;
      }else{
        const tone = last.result === 'ok' ? 'ok' : 'warn';
        const age = (last.age_seconds === null || last.age_seconds === undefined)
          ? ''
          : ` <span class="sv-muted sv-small">· ${svEscape(t('meta.command_age', ':seconds s ago').replace(':seconds', last.age_seconds))}</span>`;
        lastBox.innerHTML = `<span class="sv-badge sv-badge--${tone}">${svEscape(last.action)} / ${svEscape(last.target)}</span> `
          + svEscape(last.message || '') + age;
      }
    }

    const toolbar = svQs('.sv-toolbar__status');
    if(toolbar){
      const running = state.running === true;
      const badge = svQs('.sv-badge', toolbar);
      if(badge){
        badge.className = 'sv-badge sv-badge--' + (running ? 'ok' : 'error');
        badge.textContent = running
          ? t('supervisor.running', 'supervisor running')
          : t('supervisor.not_running', 'supervisor is not running');
      }
      const muted = svQs('.sv-muted', toolbar);
      if(muted){
        const parts = [state.reason_label || ''];
        if(state.status_age_seconds !== null && state.status_age_seconds !== undefined){
          parts.push(t('supervisor.status_age', 'updated :seconds s ago').replace(':seconds', state.status_age_seconds));
        }
        muted.textContent = parts.filter(Boolean).join(' · ');
      }
    }
  }

  async function refreshStatus(withLog){
    try{
      const params = withLog ? { with_log: 1, lines: 200 } : {};
      const res = await PanelApi.get(withInstance(statusUrl, params));
      if(res && res.success){
        renderState(res.state);
        if(Array.isArray(res.instances)) renderInstances(res.instances);
        if(withLog && Array.isArray(res.log)){
          if(logBox) logBox.textContent = res.log.length ? res.log.join('\n') : t('log.empty', 'no log output yet');
        }
      }else if(res && res.message){
        showResult(res.message, 'error');
      }
    }catch(error){
      showResult(t('errors.refresh_failed', 'cannot read the supervisor state'), 'error');
    }
  }

  async function refreshLog(){
    try{
      const res = await PanelApi.get(withInstance(logUrl, { lines: 200 }));
      if(res && res.success && Array.isArray(res.lines) && logBox){
        logBox.textContent = res.lines.length ? res.lines.join('\n') : t('log.empty', 'no log output yet');
      }
    }catch(error){ /* ignore */ }
  }

  async function awaitCommand(id, timeoutMs){
    const deadline = Date.now() + (timeoutMs || 25000);
    while(Date.now() < deadline){
      await new Promise((resolve) => setTimeout(resolve, 700));
      try{
        const res = await PanelApi.get(withInstance(statusUrl));
        const last = res && res.state && res.state.supervisor ? res.state.supervisor.last_command : null;
        if(last && last.id === id){
          renderState(res.state);
          if(Array.isArray(res.instances)) renderInstances(res.instances);
          return last;
        }
      }catch(error){ /* keep polling */ }
    }
    return null;
  }

  async function sendCommand(action, target, confirmation){
    if(busy) return;
    if(confirmation && !window.confirm(confirmation)) return;

    busy = true;
    svQsa('button[data-sv-action]').forEach((btn) => { btn.disabled = true; });
    clearResult();
    showResult(t('messages.sending', 'sending command…'), 'info');

    try{
      const payload = { action, target };
      if(currentInstance !== '') payload.instance = currentInstance;
      const res = await PanelApi.post(commandUrl, payload);
      if(!res || !res.success){
        showResult((res && res.message) || t('errors.command_failed', 'command failed'), 'error');
        return;
      }
      showResult(res.message || t('messages.command_sent', 'command sent'), 'info');

      if(action === 'start_supervisor'){
        return;
      }

      const finished = await awaitCommand(res.id, 30000);
      if(finished){
        const tone = finished.result === 'ok' ? 'ok' : 'warn';
        showResult(`${finished.action} / ${finished.target}: ${finished.message}`, tone);
      }else{
        showResult(t('messages.timeout', 'no confirmation from the supervisor yet - refresh in a moment'), 'warn');
      }
      await refreshStatus(true);
    }catch(error){
      showResult(t('errors.command_failed', 'command failed'), 'error');
    }finally{
      busy = false;
      svQsa('button[data-sv-action]').forEach((btn) => { btn.disabled = false; });
    }
  }

  function bind(){
    document.addEventListener('click', (event) => {
      const instanceBtn = event.target.closest('[data-sv-instance]');
      if(instanceBtn){
        event.preventDefault();
        switchInstance(instanceBtn.dataset.svInstance || '');
        return;
      }

      const btn = event.target.closest('[data-sv-action]');
      if(!btn || !servicesBox) return;
      event.preventDefault();
      const action = btn.dataset.svAction;
      const target = btn.dataset.svTarget || 'all';
      if(!canControl) return;
      sendCommand(action, target, btn.dataset.svConfirm || null);
    });

    if(refreshBtn){
      refreshBtn.addEventListener('click', () => refreshStatus(true));
    }

    if(autoBox){
      const start = () => {
        if(pollTimer) return;
        const seconds = Math.max(2, Number(config.pollSeconds) || 5);
        pollTimer = setInterval(() => refreshStatus(false), seconds * 1000);
      };
      const stop = () => {
        if(!pollTimer) return;
        clearInterval(pollTimer);
        pollTimer = null;
      };
      autoBox.addEventListener('change', () => { autoBox.checked ? start() : stop(); });
      if(autoBox.checked) start();
    }

    setInterval(refreshLog, 15000);
  }

  bind();
  setActiveInstance(currentInstance);
  refreshStatus(true);
}

if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', boot);
}else{
  boot();
}
