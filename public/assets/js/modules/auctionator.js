/**
 * mod-auctionator（拍卖机器人）管理页：请求 /auctionator/api/{status,config,item,action,power}，
 * 写入走 Panel.api（带 CSRF 与基路径），成功后刷新页面以免服务端快照过期。
 *
 * /auctionator/api/power 是单区总开关：只写本区 mod_auctionator.conf 并向本区 worldserver 发
 * ".auctionator start|stop"，不影响其它区。
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

  // 带占位符的文案：把 :name 替换成实际值（面板的 moduleLocale 不做替换）。
  // 占位符按长度从长到短替换，否则 :bid 会先把 :bid_total 咬掉一半。
  function tt(path, replace, fallback) {
    let text = t(path, fallback);
    if (replace) {
      Object.keys(replace).sort(function (a, b) { return b.length - a.length; }).forEach(function (key) {
        text = text.split(':' + key).join(String(replace[key]));
      });
    }
    return text;
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

  // ---- tabs ----
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

  // ---- settings ----
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

  // ---- item policy ----
  function policyPayload(source) {
    const payload = { action: source.dataset.auPolicy || '' };
    if (source.dataset.auItem !== undefined) payload.item = source.dataset.auItem;
    if (source.dataset.auClass !== undefined) payload['class'] = source.dataset.auClass;
    if (source.dataset.auSubclass !== undefined) payload.subclass = source.dataset.auSubclass;
    if (source.dataset.auQuality !== undefined) payload.quality = source.dataset.auQuality;
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
      const rowScoped = action === 'itemclass_save' || action === 'itemclass_class_save';
      if (rowScoped) {
        payload = rowValues(node, payload);
      }
      if (action === 'disabled_remove') {
        if (!window.confirm(t('confirm.disabled_remove', 'Remove this item from the blacklist?'))) return;
      }
      if (action === 'itemclass_delete') {
        if (!window.confirm(t('confirm.itemclass_delete', 'Delete this class/subclass row? The class becomes unlisted for the seller.'))) return;
      }
      if (action === 'itemclass_class_save') {
        // One click can flip a whole item type, so say out loud which way it is about to go.
        const quota = parseInt(payload.max_count, 10) || 0;
        const message = quota > 0
          ? t('confirm.itemclass_class_enable', 'Give every subclass of this type this quota?')
          : t('confirm.itemclass_class_disable', 'Set every subclass of this type to "not listed"? The seller stops restocking it on its next run.');
        if (!window.confirm(message)) return;
      }
      if (action === 'gm_delete') {
        if (!window.confirm(t('confirm.gm_delete', 'Delete this gm_list row?'))) return;
      }
      runPolicy(payload);
    });
  });

  // ---- listing mode + price preview ----
  /**
   * 两种 GM 上架入口（addlist 表单与 ".auctionator add"）都用"模式 + 两个单价"，模式决定用哪个单价，
   * 所以字段跟着模式切换，预览要写出整组价格（单价 × 堆叠）。
   */
  function copperText(value) {
    const copper = Math.max(0, Math.floor(Number(value) || 0));
    const gold = Math.floor(copper / 10000);
    const silver = Math.floor((copper % 10000) / 100);
    const rest = copper % 100;
    const parts = [];
    if (gold > 0) parts.push(gold + 'g');
    if (silver > 0) parts.push(silver + 's');
    if (rest > 0 || parts.length === 0) parts.push(rest + 'c');
    return parts.join(' ') + ' (' + copper + 'c)';
  }

  function unitText(copper) {
    return tt('listing.unit', { copper: copperText(copper) }, ':copper copper/unit');
  }

  function syncListingForm(form) {
    const modeNode = form.querySelector('[name="mode"]');
    if (!modeNode) return;

    const mode = modeNode.value;
    const bidMode = mode === 'bid';
    const buyoutMode = mode === 'buyout';
    const bidWrap = form.querySelector('[data-au-listing-field="bid"]');
    const bidInput = form.querySelector('[name="bid"]');
    const priceInput = form.querySelector('[name="price"]');
    const stackInput = form.querySelector('[name="stack"]');
    const preview = form.querySelector('[data-au-listing-preview]');

    // 固定价没有自己的起拍价（模块把它钉在买断价），字段被隐藏后不能再用 required 挡住提交
    if (bidWrap) bidWrap.hidden = !bidMode;
    if (bidInput) bidInput.required = bidMode;
    if (priceInput) priceInput.required = buyoutMode;

    if (!preview) return;

    const stack = Math.max(1, Math.floor(Number(stackInput && stackInput.value) || 1));
    const bid = Math.max(0, Math.floor(Number(bidInput && bidInput.value) || 0));
    const price = Math.max(0, Math.floor(Number(priceInput && priceInput.value) || 0));
    const stackNote = ' ' + tt('listing.per_stack', { stack: stack }, ':stack per stack');

    if (mode === '') {
      preview.textContent = t('listing.choose_mode', 'Choose a listing mode first.');
      return;
    }

    if (buyoutMode) {
      preview.textContent = price <= 0
        ? t('listing.missing_buyout', 'Provide a buyout above 0.')
        : tt('listing.buyout', {
            unit: unitText(price),
            total: copperText(price * stack)
          }, 'Fixed price: buyout :unit, whole stack :total.') + stackNote;
      return;
    }

    if (bid <= 0) {
      preview.textContent = t('listing.missing_bid', 'Provide a start bid above 0.');
      return;
    }

    preview.textContent = price > 0
      ? tt('listing.bid_with_buyout', {
          bid: unitText(bid),
          bid_total: copperText(bid * stack),
          buyout: unitText(price),
          buyout_total: copperText(price * stack)
        }, 'Auction: start bid :bid, buyout :buyout.') + stackNote
      : tt('listing.bid_no_buyout', {
          bid: unitText(bid),
          bid_total: copperText(bid * stack)
        }, 'Auction: start bid :bid, no buyout.') + stackNote;
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-au-listing-form]'), function (form) {
    form.addEventListener('change', function () { syncListingForm(form); });
    form.addEventListener('input', function () { syncListingForm(form); });
    syncListingForm(form);
  });

  // ---- GM actions ----
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

  // ---- master switch (this realm) ----
  async function runPower(enable) {
    const confirmMessage = t(enable ? 'confirm.power_start' : 'confirm.power_stop', '');
    if (confirmMessage && !window.confirm(confirmMessage)) return;

    setBusy(true);
    let json = null;
    try {
      json = await post('/auctionator/api/power', { enable: enable ? 1 : 0 });
    } finally {
      setBusy(false);
    }

    const output = json && json.payload ? json.payload.output : '';
    if (output) printOutput(output);

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.power_failure', 'The master switch could not be applied.'));
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
    reload(1200);
  }

  document.querySelectorAll('[data-au-power]').forEach(function (node) {
    node.addEventListener('click', function () {
      runPower(node.dataset.auPower === 'start');
    });
  });

  // ---- buyout mode (this realm) ----
  /**
   * 快速买断开关是总开关的孪生体：把 Auctionator.Seller.BidOnly 写进本区 conf（重启后仍生效）并发
   * ".auctionator buyout 0|1"（卖家下一次运行即生效）。注意是反向的：buyout 关闭 = BidOnly 1。
   */
  async function runBuyout(enable) {
    const confirmMessage = t(enable ? 'confirm.buyout_enable' : 'confirm.buyout_disable', '');
    if (confirmMessage && !window.confirm(confirmMessage)) return;

    setBusy(true);
    let json = null;
    try {
      json = await post('/auctionator/api/buyout', { enable: enable ? 1 : 0 });
    } finally {
      setBusy(false);
    }

    const output = json && json.payload ? json.payload.output : '';
    if (output) printOutput(output);

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.buyout_failure', 'The buyout switch could not be applied.'));
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
    reload(1200);
  }

  document.querySelectorAll('[data-au-buyout]').forEach(function (node) {
    node.addEventListener('click', function () {
      runBuyout(node.dataset.auBuyout === '1');
    });
  });

  if (dom.addForm) {
    dom.addForm.addEventListener('submit', function (event) {
      event.preventDefault();
      // runAction 先合并页面级 [data-au-action-field]，再叠加本表单 payload，冲突时表单优先（house）：
      // 那些共享字段只是给 addlist/expireall 兜底的。
      runAction('add', formPayload(dom.addForm, 'add'));
    });
  }

  printOutput(null);
})();
