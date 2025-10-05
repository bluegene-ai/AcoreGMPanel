/**
 * File: public/assets/js/modules/logs.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - boot()
 *   - populateTypeOptions()
 *   - formatServer()
 *   - updateSummary()
 *   - renderTable()
 *   - loadLogs()
 *   - triggerLoad()
 *   - qs()
 *   - getPanelApi()
 *   - summary()
 *   - status()
 *   - action()
 */

const qs = (sel, ctx = document) => ctx.querySelector(sel);
const getPanelApi = () => (window.Panel && window.Panel.api) ? window.Panel.api : null;

function boot(){
  if(document.body.dataset.module !== 'logs') return;
  const config = window.LOGS_DATA || { modules:{}, defaults:{} };
  const modules = config.modules || {};
  const defaults = config.defaults || {};

  const form = qs('#logsForm'); if(!form) return;
  const moduleSelect = qs('#logsModuleSelect', form);
  const typeSelect = qs('#logsTypeSelect', form);
  const limitInput = qs('#logsLimitInput', form);
  const summaryBox = qs('#logsSummaryBox');
  const tableBody = qs('#logsTableBody');
  const rawBox = qs('#logsOutput');
  const loadBtn = qs('#btn-load-logs');
  const autoBtn = qs('#btn-auto-toggle');
  const panelRef = window.Panel || {};
  const moduleTranslate = typeof panelRef.createModuleTranslator === 'function'
    ? panelRef.createModuleTranslator('logs')
    : (path, fallback) => (fallback !== undefined ? fallback : path);
  const summary = (key, fallback) => moduleTranslate(`summary.${key}`, fallback);
  const status = (key, fallback) => moduleTranslate(`status.${key}`, fallback);
  const action = (key, fallback) => moduleTranslate(`actions.${key}`, fallback);
  const summarySeparator = String(summary('separator', ' | ') || ' | ');
  let timer = null;
  let panelReadyAttempts = 0;
  const MAX_PANEL_RETRIES = 12;

  function populateTypeOptions(moduleId){
    const module = modules[moduleId];
    typeSelect.innerHTML = '';
    if(!module){
      return;
    }
    const types = module.types || {};
    const typeIds = Object.keys(types);
    if(typeIds.length === 0){
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'N/A';
      typeSelect.appendChild(opt);
      return;
    }
    const defaultType = defaults.type && types[defaults.type] ? defaults.type : typeIds[0];
    typeIds.forEach(id => {
      const meta = types[id] || {};
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = meta.label || id;
      if(id === defaultType){
        opt.selected = true;
      }
      typeSelect.appendChild(opt);
    });
  }

  if(moduleSelect){
    moduleSelect.addEventListener('change', () => {
      populateTypeOptions(moduleSelect.value);
      updateSummary();
      if(autoBtn.getAttribute('data-on') === '1'){
        triggerLoad();
      }
    });
  }

  function formatServer(server){
    if(server === undefined || server === null){
      return '-';
    }
    const num = Number(server);
    if(Number.isFinite(num)){
      return num === 0 ? '-' : `S${num}`;
    }
    return String(server);
  }

  function updateSummary(payload){
    const moduleId = moduleSelect ? moduleSelect.value : '';
    const typeId = typeSelect ? typeSelect.value : '';
    const moduleMeta = modules[moduleId] || {};
    const typeMeta = (moduleMeta.types || {})[typeId] || {};
    const parts = [];
    parts.push((summary('module', 'Module: ') || 'Module: ') + (moduleMeta.label || moduleId || '-'));
    if(typeId){
      parts.push((summary('type', 'Type: ') || 'Type: ') + (typeMeta.label || typeId));
    }
    if(typeMeta.description){
      parts.push(typeMeta.description);
    } else if(moduleMeta.description){
      parts.push(moduleMeta.description);
    }
    if(payload && payload.file){
      parts.push((summary('source', 'Source: ') || 'Source: ') + payload.file);
    }
    if(payload && Array.isArray(payload.lines)){
      const display = `${payload.lines.length} / ${payload.limit ?? ''}`.trim();
      parts.push((summary('display', 'Showing: ') || 'Showing: ') + display);
    }
    if(summaryBox){
      summaryBox.textContent = parts.filter(Boolean).join(summarySeparator);
    }
  }

  function renderTable(entries){
    if(!tableBody) return;
    tableBody.innerHTML = '';
    if(!Array.isArray(entries) || entries.length === 0){
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 4;
      cell.className = 'muted text-center';
      cell.textContent = status('no_entries', 'No log entries');
      row.appendChild(cell);
      tableBody.appendChild(row);
      return;
    }
    entries.forEach(entry => {
      const row = document.createElement('tr');
      const timeCell = document.createElement('td');
      timeCell.textContent = entry.time || '-';
      row.appendChild(timeCell);

      const serverCell = document.createElement('td');
      serverCell.textContent = formatServer(entry.server);
      row.appendChild(serverCell);

      const actorCell = document.createElement('td');
      actorCell.textContent = entry.actor || '-';
      row.appendChild(actorCell);

      const summaryCell = document.createElement('td');
      summaryCell.textContent = entry.summary || entry.raw || '-';
      if(entry.raw){
        row.title = entry.raw;
      }
      if(entry.data){
        try {
          row.dataset.details = JSON.stringify(entry.data);
        } catch(e){  }
      }
      row.appendChild(summaryCell);

      tableBody.appendChild(row);
    });
  }

  async function loadLogs(){
    if(!moduleSelect || !typeSelect){
      return;
    }
    const PanelApi = getPanelApi();
    if(!PanelApi){
      panelReadyAttempts += 1;
      const message = panelReadyAttempts > MAX_PANEL_RETRIES
        ? status('panel_not_ready', 'Panel API is not ready, please verify panel.js is loaded correctly.')
        : status('panel_waiting', 'Panel API is initializing, please wait…');
      const infoPrefix = status('info_prefix', '[INFO] ');
      if(rawBox){ rawBox.textContent = `${infoPrefix}${message}`; }
      if(tableBody){ tableBody.innerHTML = `<tr><td colspan="4" class="text-center muted">${message}</td></tr>`; }
      if(panelReadyAttempts <= MAX_PANEL_RETRIES){
        setTimeout(triggerLoad, 250);
      } else {
        console.error('Panel.api is not available. Ensure panel.js is loaded before logs.js');
      }
      return;
    }
    panelReadyAttempts = 0;
    const payload = {
      module: moduleSelect.value,
      type: typeSelect.value,
      limit: Number(limitInput ? limitInput.value : defaults.limit) || defaults.limit || 200,
    };
    try {
      const res = await PanelApi.post('/logs/api/list', payload);
      if(!res || !res.success){
        const fallback = status('load_failed', 'Load failed');
        const message = res && res.message ? res.message : fallback;
        const errorPrefix = status('error_prefix', '[ERROR] ');
        if(rawBox){ rawBox.textContent = `${errorPrefix}${message}`; }
        if(tableBody){ tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${message}</td></tr>`; }
        return;
      }
      const lines = res.lines || [];
      if(rawBox){
        rawBox.textContent = lines.length ? lines.join('\n') : status('no_raw', '-- No log --');
        rawBox.scrollTop = rawBox.scrollHeight;
      }
      renderTable(res.entries || []);
      updateSummary(res);
    } catch(error){
      const exceptionPrefix = status('exception_prefix', '[EXCEPTION] ');
      const requestError = status('request_error', 'Request error');
      if(rawBox){ rawBox.textContent = `${exceptionPrefix}${error?.message || error}`; }
      if(tableBody){ tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${requestError}</td></tr>`; }
    }
  }

  function triggerLoad(){
    loadLogs();
  }

  populateTypeOptions(moduleSelect ? moduleSelect.value : '');
  updateSummary();

  if(loadBtn){
    loadBtn.addEventListener('click', triggerLoad);
  }

  if(autoBtn){
    autoBtn.addEventListener('click', () => {
      const active = autoBtn.getAttribute('data-on') === '1';
      if(active){
        autoBtn.setAttribute('data-on', '0');
        autoBtn.textContent = action('auto_on', 'Enable auto refresh');
        if(timer){ clearInterval(timer); timer = null; }
      } else {
        autoBtn.setAttribute('data-on', '1');
        autoBtn.textContent = action('auto_off', 'Disable auto refresh');
        triggerLoad();
        timer = setInterval(triggerLoad, 4000);
      }
    });
  }

  triggerLoad();
}
if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

