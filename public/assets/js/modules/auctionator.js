/**
 * mod-auctionator (拍卖机器人) management page.
 *
 * Talks to /auctionator/api/{status,config,item,action}. Every POST goes through
 * Panel.api (csrf + base path aware); the page reloads after a successful write so the
 * server-rendered snapshot is never stale.
 */
(function () {
  if (document.body.dataset.module !== 'auctionator') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const currentServer = new URLSearchParams(window.location.search).get('server') || '';

  const dom = {
    feedback: document.getElementById('auFeedback'),
    configForm: document.getElementById('auConfigForm'),
    configSaveBtn: document.getElementById('auConfigSaveBtn'),
    addForm: document.getElementById('auAddForm'),
    output: document.getElementById('auOutput'),
    tabs: Array.from(document.querySelectorAll('[data-au-tab]')),
    panels: Array.from(document.querySelectorAll('[data-au-panel]'))
  };

  function t(path, fallback) {
    if (typeof panel.moduleLocale === 'function') return panel.moduleLocale('auctionator', path, fallback);
    return fallback || path;
  }

  function withServer(path) {
    if (!currentServer) return path;
    return path + (path.indexOf('?') >= 0 ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  function show(type, message) {
    if (!dom.feedback || !message) return;
    if (feedback && typeof feedback.show === 'function') {
      dom.feedback.hidden = false;
      feedback.show(dom.feedback, type, message, { duration: 5000 });
      return;
    }
    dom.feedback.hidden = false;
    dom.feedback.textContent = message;
    dom.feedback.className = 'au-feedback panel-flash panel-flash--' + (type === 'error' ? 'error' : 'info') + ' is-visible';
  }

  async function post(path, body) {
    const url = withServer(path);
    if (api && typeof api.post === 'function') return api.post(url, body || {});
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    });
    return response.json();
  }

  function setBusy(disabled) {
    document.querySelectorAll('.au-page button, .au-page input, .au-page select').forEach(function (node) {
      if (node.dataset.auKeepEnabled === '1') return;
      node.disabled = !!disabled;
    });
  }

  function reload(delay) {
    window.setTimeout(function () { window.location.reload(); }, delay || 700);
  }

  function printOutput(text) {
    if (dom.output) dom.output.textContent = text && String(text).trim() !== '' ? String(text) : t('actions.output_empty', '(no output)');
  }

  // ------------------------------------------------------------------ tabs
  function activateTab(name, pushHash) {
    dom.tabs.forEach(function (tab) {
      const active = tab.dataset.auTab === name;
      tab.classList.toggle('au-tab--active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    dom.panels.forEach(function (panelNode) {
      panelNode.hidden = panelNode.dataset.auPanel !== name;
    });
    if (pushHash && window.history && typeof window.history.replaceState === 'function') {
      window.history.replaceState(null, '', name === 'status' ? window.location.pathname + window.location.search : '#' + name);
    }
  }

  dom.tabs.forEach(function (tab) {
    tab.addEventListener('click', function () { activateTab(tab.dataset.auTab, true); });
  });

  const initialTab = (window.location.hash || '').replace('#', '');
  if (initialTab && dom.tabs.some(function (tab) { return tab.dataset.auTab === initialTab; })) {
    activateTab(initialTab, false);
  }

  // ------------------------------------------------------------------ settings
  function collectFields() {
    const fields = {};
    document.querySelectorAll('[data-au-field]').forEach(function (node) {
      fields[node.dataset.auField] = node.value;
    });
    return fields;
  }

  if (dom.configForm) {
    dom.configForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      const fields = collectFields();
      if (!Object.keys(fields).length) return;

      setBusy(true);
      let json = null;
      try {
        json = await post('/auctionator/api/config', { fields: fields });
      } finally {
        setBusy(false);
      }

      if (!json || !json.success) {
        show('error', (json && json.message) || t('feedback.config_failure', 'Configuration save failed.'));
        return;
      }

      const restartNote = json.payload && json.payload.restart_required
        ? ' ' + t('feedback.restart_required', 'Restart the worldserver for it to take effect.')
        : '';
      show('success', (json.message || t('feedback.config_success', 'Configuration saved.')) + restartNote);
      reload(json.payload && json.payload.restart_required ? 1400 : 700);
    });
  }

  // ------------------------------------------------------------------ item policy
  function policyPayload(source) {
    const payload = { action: source.dataset.auPolicy || '' };
    if (source.dataset.auItem !== undefined) payload.item = source.dataset.auItem;
    if (source.dataset.auClass !== undefined) payload['class'] = source.dataset.auClass;
    if (source.dataset.auSubclass !== undefined) payload.subclass = source.dataset.auSubclass;
    if (source.dataset.auEnabled !== undefined) payload.enabled = source.dataset.auEnabled;
    return payload;
  }

  function formPayload(formNode, action) {
    const payload = { action: action };
    new FormData(formNode).forEach(function (value, key) { payload[key] = value; });
    return payload;
  }

  function rowValues(button, payload) {
    const row = button.closest('tr');
    if (!row) return payload;
    row.querySelectorAll('[data-au-field-name]').forEach(function (input) {
      payload[input.dataset.auFieldName] = input.value;
    });
    return payload;
  }

  async function runPolicy(payload, confirmMessage) {
    if (confirmMessage && !window.confirm(confirmMessage)) return;

    setBusy(true);
    let json = null;
    try {
      json = await post('/auctionator/api/item', payload);
    } finally {
      setBusy(false);
    }

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.policy_failure', 'The item policy update failed.'));
      return;
    }

    show('success', json.message || t('feedback.policy_success', 'Item policy updated.'));
    reload();
  }

  document.querySelectorAll('[data-au-policy]').forEach(function (node) {
    const action = node.dataset.auPolicy;

    if (node.tagName === 'FORM') {
      node.addEventListener('submit', function (event) {
        event.preventDefault();
        runPolicy(formPayload(node, action));
      });
      return;
    }

    node.addEventListener('click', function () {
      let payload = policyPayload(node);
      if (action === 'itemclass_save') {
        payload = rowValues(node, payload);
      }
      if (action === 'disabled_remove') {
        if (!window.confirm(t('confirm.disabled_remove', 'Remove this item from the blacklist?'))) return;
      }
      if (action === 'itemclass_delete') {
        if (!window.confirm(t('confirm.itemclass_delete', 'Delete this class/subclass row? The class becomes unlisted for the seller.'))) return;
      }
      if (action === 'gm_delete') {
        if (!window.confirm(t('confirm.gm_delete', 'Delete this gm_list row?'))) return;
      }
      runPolicy(payload);
    });
  });

  // ------------------------------------------------------------------ GM actions
  function actionExtraFields() {
    const extra = {};
    document.querySelectorAll('[data-au-action-field]').forEach(function (node) {
      const key = node.dataset.auActionField;
      extra[key] = node.type === 'checkbox' ? (node.checked ? '1' : '0') : node.value;
    });
    return extra;
  }

  async function runAction(action, extra) {
    const confirmMessage = t('confirm.' + action, '');
    if (confirmMessage && !window.confirm(confirmMessage)) return;

    setBusy(true);
    let json = null;
    try {
      json = await post('/auctionator/api/action', Object.assign({ action: action }, actionExtraFields(), extra || {}));
    } finally {
      setBusy(false);
    }

    const output = json && json.payload ? json.payload.output : '';
    printOutput(output || (json && json.message) || '');

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.action_failure', 'The command failed.'));
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
  }

  document.querySelectorAll('[data-au-action]').forEach(function (node) {
    node.addEventListener('click', function () {
      runAction(node.dataset.auAction, {});
    });
  });

  if (dom.addForm) {
    dom.addForm.addEventListener('submit', function (event) {
      event.preventDefault();
      runAction('add', formPayload(dom.addForm, 'add'));
    });
  }

  printOutput(null);
})();
