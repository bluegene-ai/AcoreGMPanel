/**
 * mod-auctionator（拍卖机器人）管理页：请求 /auctionator/api/{status,config,item,action,power,buyout,maxitemlevel}，
 * 写入走 Panel.api（带 CSRF 与基路径），成功后刷新页面以免服务端快照过期。
 *
 * /auctionator/api/power 是单区总开关：只写本区 mod_auctionator.conf 并向本区 worldserver 发
 * ".auctionator start|stop"，不影响其它区。
 *
 * /auctionator/api/buyout 与 /auctionator/api/maxitemlevel 同构：写本区 conf 的键 + 发对应子命令。
 */
(function () {
  if (document.body.dataset.module !== 'auctionator') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const currentServer = new URLSearchParams(window.location.search).get('server') || '';

  const dom = {
    feedback: document.getElementById('auFeedback'),
    output: document.getElementById('auOutput')
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

  /**
   * 只锁触发这次请求的那个控件，而不是整页。
   *
   * 原来 setBusy() 会把 .au-page 里所有按钮和输入框全禁掉：等待的那一秒整页变砖，而且看不出
   * 是哪一步在忙。现在锁的是你点的那个按钮（同时打上 aria-busy），页面其它部分照常可用。
   *
   * 解锁必须还原"锁之前是不是本来就被禁用"：挂单明细的「改价」是按 dirty 禁用的，无条件
   * enabled = true 会把它错误地放开。
   */
  function lockControl(node, locked) {
    if (!node || node.nodeType !== 1) return;
    if (locked) {
      if (node.dataset.auWasDisabled === undefined) node.dataset.auWasDisabled = node.disabled ? '1' : '0';
      if ('disabled' in node) node.disabled = true;
      node.classList.add('is-busy');
      node.setAttribute('aria-busy', 'true');
      return;
    }
    if ('disabled' in node) node.disabled = node.dataset.auWasDisabled === '1';
    delete node.dataset.auWasDisabled;
    node.classList.remove('is-busy');
    node.removeAttribute('aria-busy');
  }

  function beginWork(trigger) {
    const node = trigger && trigger.nodeType === 1 ? trigger : null;
    lockControl(node, true);
    return node;
  }

  function endWork(node) {
    lockControl(node, false);
  }

  /** 兜底：局部刷新拿不到可信页面时（会话过期、请求失败）才整页跳。 */
  function reload(delay) {
    window.setTimeout(function () { window.location.reload(); }, delay || 0);
  }

  /**
   * 写操作按"可能影响哪些分区"分组：只替换这些 [data-au-panel] 区块，其余分区的 DOM（以及
   * 用户正在里面编辑的内容）一个字节都不动。
   *
   * 为什么是"取整页再挑区块"而不是加一个返回 HTML 片段的端点：这样复用的就是 index() 那一份
   * 渲染，不存在第二套要同步的模板。一次渲染的查询量与原整页刷新相同，省下的是浏览器整页重载
   * 的代价——重新下载与解析资源、重新执行 panel.js、丢滚动位置与焦点、以及那个白闪。
   */
  const AU_PANEL_GROUPS = {
    listing: ['overview', 'live'],
    policy: ['overview', 'filters', 'stock'],
    action: ['overview', 'live', 'stock', 'filters'],
    switch: ['overview', 'settings'],
    config: ['overview', 'settings'],
    // 等级上限既显示在"物品筛选"页的控件上，又是设置页里的同一个 conf 键，两处一起换
    itemlevel: ['overview', 'filters', 'settings'],
  };

  async function refreshPanels(group) {
    const panels = AU_PANEL_GROUPS[group] || null;

    let html = '';
    try {
      const response = await fetch(window.location.href, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!response.ok) { reload(); return; }
      html = await response.text();
    } catch (error) {
      reload();
      return;
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');
    // 会话过期时服务端返回的是登录页：绝不能拿它去替换页面区块
    if (!doc.querySelector('[data-au-page]')) { reload(); return; }

    Array.prototype.forEach.call(document.querySelectorAll('[data-au-panel]'), function (node) {
      const tab = node.dataset.auPanel;
      if (panels && panels.indexOf(tab) < 0) return;
      // id 唯一，用它配对；只靠 data-au-panel 会在一区多段时配错（stock / maintenance 各两段）
      const fresh = node.id
        ? doc.querySelector('[data-au-panel="' + tab + '"]#' + node.id)
        : doc.querySelector('[data-au-panel="' + tab + '"]');
      if (!fresh) return;
      node.replaceWith(document.importNode(fresh, true));
    });

    // 警告条在分区之外，但它会随配置变化（例如"模块未启用"），所以一并换掉
    const warnings = document.getElementById('auWarnings');
    const freshWarnings = doc.getElementById('auWarnings');
    if (warnings && freshWarnings) {
      warnings.replaceWith(document.importNode(freshWarnings, true));
    } else if (warnings && !freshWarnings) {
      warnings.remove();
    } else if (!warnings && freshWarnings && dom.feedback) {
      dom.feedback.insertAdjacentElement('afterend', document.importNode(freshWarnings, true));
    }

    // 新 DOM 需要重新"增强"一次（搜索框、物品选择器、子类级联、行级 dirty 初值），
    // 但事件绑定全是委托的，所以不用重绑。
    enhance();
    // 服务端渲染出来的新面板默认带 hidden（默认 tab 是 overview），把当前 tab 重新激活
    if (tabsInstance) tabsInstance.activate(tabsInstance.active() || 'overview', {});
  }

  function printOutput(text) {
    if (!dom.output) return;
    const body = text && String(text).trim() !== '' ? String(text) : '';
    const panelNode = document.getElementById('auOutputPanel');
    if (!body) {
      // 没有输出就不占页面底部的位置：空面板常驻只是噪音。
      if (panelNode) panelNode.hidden = true;
      dom.output.textContent = t('actions.output_empty', '(no output)');
      return;
    }

    if (panelNode) {
      panelNode.hidden = false;
      // 输出面板在分区之外、位于页面最底部，所以从页面靠上的分区发命令时它其实在视口外——
      // 命令跑了却"没有任何反应"就是这么来的。只在它不在视口内时滚过去，别打断正在看页面的人。
      const rect = panelNode.getBoundingClientRect();
      if (rect.top < 0 || rect.bottom > (window.innerHeight || 0)) {
        if (typeof panelNode.scrollIntoView === 'function') {
          panelNode.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      }
    }
    dom.output.textContent = body;
  }

  // ---- tabs ----
  // 键盘可达的标签页行为（左右方向键 / Home / End / roving tabindex / aria-selected）来自
  // panel.js 的 Panel.tabs()：这是页面级共用件，不在本模块再手写一份。
  // 面板由各 tab 的 aria-controls 解析，所以"上架与补货"可以由页面上不相邻的两段组成。
  // tab 栏本身在分区之外、不会被局部刷新替换，所以实例只建一次。
  const tabsInstance = (typeof panel.tabs === 'function') ? panel.tabs({
    root: '[data-au-page]',
    tabSelector: '[data-au-tab]',
    tabNameAttr: 'data-au-tab',
    activeClass: 'au-tab--active',
    defaultTab: 'overview',
    hash: true
  }) : null;

  function pickerIn(scope) {
    const node = scope.querySelector('[data-au-picker]');
    return node && node.__itemPicker ? node.__itemPicker : null;
  }

  /**
   * 选择器的值存在隐藏字段里，而隐藏字段不参与 HTML 的 required 校验，
   * 所以"没选物品就提交"必须在这里拦下来，否则会把空 item 发给后端换一个 422。
   */
  function requirePicked(scope) {
    const picker = pickerIn(scope);
    if (!picker || picker.value() !== '') return true;
    show('error', t('picker.need_item', 'Search and pick an item first.'));
    if (typeof picker.focus === 'function') picker.focus();
    return false;
  }

  // ---- 类别 → 子类级联 ----
  /**
   * 子类随类别变化（武器下面才有"单手剑"），整张表由服务端以 AU_SUBCLASSES 发布。
   * 服务端已渲染默认类别的那一份，所以禁用 JS 时这个表单仍然可用。
   * 用委托，因为这张表会因为局部刷新而被整块替换。
   */
  function fillSubclassSelect(classSelect, subclassSelect) {
    const map = window.AU_SUBCLASSES;
    if (!map) return;
    const options = map[classSelect.value] || {};
    const previous = subclassSelect.value;
    subclassSelect.innerHTML = '';
    Object.keys(options).forEach(function (subId) {
      const option = document.createElement('option');
      option.value = subId;
      option.textContent = options[subId];
      subclassSelect.appendChild(option);
    });
    // 换类别后原来的子类通常不存在了；还在就保留，省得每次重选
    if (options[previous] !== undefined) subclassSelect.value = previous;
  }

  // ---- settings ----
  function collectFields() {
    const fields = {};
    document.querySelectorAll('[data-au-field]').forEach(function (node) {
      fields[node.dataset.auField] = node.value;
    });
    return fields;
  }

  /** 保存配置。委托绑定：设置分区会因为局部刷新被整块替换。 */
  async function submitConfigForm(form, trigger) {
    const fields = collectFields();
    if (!Object.keys(fields).length) return;

    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/config', { fields: fields });
    } finally {
      endWork(locked);
    }

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.config_failure', 'Configuration save failed.'));
      return;
    }

    const restartNote = json.payload && json.payload.restart_required
      ? ' ' + t('feedback.restart_required', 'Restart the worldserver for it to take effect.')
      : '';
    show('success', (json.message || t('feedback.config_success', 'Configuration saved.')) + restartNote);
    refreshPanels('config');
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

  /**
   * @param {object} payload
   * @param {string} [confirmMessage] 需要确认时给出后果说明
   * @param {object} [confirmOptions] 追加到 Panel.confirm 的选项（danger / requireText / title …）
   * @param {Element} [trigger] 触发这次写入的控件，只锁它
   */
  async function runPolicy(payload, confirmMessage, confirmOptions, trigger) {
    if (confirmMessage) {
      const ok = await confirmAction(Object.assign({ message: confirmMessage, danger: true }, confirmOptions || {}));
      if (!ok) return;
    }

    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/item', payload);
    } finally {
      endWork(locked);
    }

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.policy_failure', 'The item policy update failed.'));
      return;
    }

    show('success', json.message || t('feedback.policy_success', 'Item policy updated.'));
    refreshPanels('policy');
  }

  function submitPolicyForm(form, trigger) {
    const action = form.dataset.auPolicy;
    // 新增行要先选中一个物品；选择器是隐藏字段，HTML 的 required 拦不住
    if ((action === 'disabled_add' || action === 'gm_save') && !requirePicked(form)) return;
    runPolicy(formPayload(form, action), '', {}, trigger);
  }

  function clickPolicyButton(node) {
    const action = node.dataset.auPolicy;
    let payload = policyPayload(node);
    const rowScoped = action === 'itemclass_save' || action === 'itemclass_class_save';
    if (rowScoped) {
      payload = rowValues(node, payload);
    }

    // 破坏性操作一律走带后果说明的确认框，而不是原生 confirm
    let message = '';
    if (action === 'disabled_remove') {
      message = t('confirm.disabled_remove', 'Remove this item from the blacklist?');
    } else if (action === 'itemclass_delete') {
      message = t('confirm.itemclass_delete', 'Delete this class/subclass row? The class becomes unlisted for the seller.');
    } else if (action === 'itemclass_class_save') {
      // 一次点击会改动整类，所以要说清往哪个方向走
      const quota = parseInt(payload.max_count, 10) || 0;
      message = quota > 0
        ? t('confirm.itemclass_class_enable', 'Give every subclass of this type this quota?')
        : t('confirm.itemclass_class_disable', 'Set every subclass of this type to "not listed"? The seller stops restocking it on its next run.');
    } else if (action === 'gm_delete') {
      message = t('confirm.gm_delete', 'Delete this gm_list row?');
    }

    runPolicy(payload, message, {}, node);
  }

  // ---- listing mode + price preview ----
  /**
   * 两种 GM 上架入口（addlist 表单与 ".auctionator add"）都用"模式 + 两个单价"，模式决定用哪个单价，
   * 所以字段跟着模式切换，预览要写出整组价格（单价 × 堆叠）。
   */
  // 金额显示：页面里所有铜币数值都按"金/银/铜"呈现，单位取模块语言文件（英文界面是 g/s/c）。
  function moneyUnit(key, fallback) {
    return t('money.' + key, fallback);
  }

  function copperText(value) {
    const copper = Math.max(0, Math.floor(Number(value) || 0));
    return Math.floor(copper / 10000) + moneyUnit('gold', '金')
      + Math.floor((copper % 10000) / 100) + moneyUnit('silver', '银')
      + (copper % 100) + moneyUnit('copper', '铜');
  }

  /**
   * 价格输入框旁的换算提示，随输入实时刷新。
   *
   * 两种情境的"同一个数字"含义不同，提示必须跟着变，否则又是那个老坑：
   *  - 挂单明细行：输入框收的是**整组总价**，所以补一句等价单价（总价 ÷ 堆叠）；
   *  - 精选清单 / 临时上架表单：输入框收的是**单价**，整组价由表单底部的预览给出。
   */
  function syncCopperHints(scope) {
    const root = scope || document;
    Array.prototype.forEach.call(root.querySelectorAll('[data-au-copper-hint]'), function (hint) {
      const row = hint.closest('[data-au-listing-row]');
      const container = row || hint.closest('tr') || root;
      const input = container.querySelector('[data-au-field-name="' + hint.dataset.auCopperHint + '"]');
      if (!input) return;

      const copper = Math.max(0, Math.floor(Number(input.value) || 0));
      if (copper <= 0) {
        hint.textContent = '';
        return;
      }

      if (!row) {
        hint.textContent = copperText(copper);
        return;
      }

      // 整组总价 → 顺带写出单价，省得 GM 自己去除
      const stack = Math.max(1, Math.floor(Number(row.dataset.auStack) || 1));
      const total = copperText(copper);
      if (stack <= 1) {
        hint.textContent = tt('listing.total_only', { total: total }, 'stack total :total');
        return;
      }

      hint.textContent = tt('listing.total_and_unit', {
        total: total,
        unit: copperText(Math.floor(copper / stack)),
        stack: stack
      }, 'stack total :total (unit :unit × :stack)');
    });
  }

  document.addEventListener('input', function (event) {
    const node = event.target;
    if (!node || !node.dataset || !node.dataset.auFieldName) return;
    syncCopperHints(node.closest('tr') || document);
    // 挂单明细行：改一下就重算 dirty，按钮状态与批量条跟着变
    const row = node.closest('[data-au-listing-row]');
    if (row) {
      refreshRow(row);
      refreshBulkBar();
    }
  });
  syncCopperHints(document);

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

  // ---- 机器人挂单明细：逐条下架 / 改价 ----
  /**
   * 这两件事都不能靠直接改 auctionhouse 表：那张表只是 worldserver 启动时的缓存，真正的状态在内存里。
   * 所以请求交给 /auctionator/api/listing，由控制器转成模块的 ".auctionator delist|reprice"，
   * 在内存中的那条挂单上执行。
   *
   * 价格是整组总价（copper），就是表格里两个输入框显示的值——不是 ".auctionator add" 的单位价。
   */
  async function runListing(payload, trigger) {
    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/listing', payload);
    } finally {
      endWork(locked);
    }

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.listing_failure', 'The listing update failed.'));
      return;
    }

    show('success', json.message || t('feedback.listing_success', 'Listing updated.'));
    refreshPanels('listing');
  }

  /** 带后果说明的确认框；panel.js 缺失时退回原生 confirm，至少不会静默执行破坏性操作。 */
  function confirmAction(options) {
    if (panel.confirm) return panel.confirm(options);
    return Promise.resolve(window.confirm(String((options && options.message) || '')));
  }

  // ---- 行级 dirty：改过价格的行才亮起「改价」，并给一个「撤销」 ----
  function listingRows() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-au-listing-row]'));
  }

  function rowInputs(row) {
    return Array.prototype.slice.call(row.querySelectorAll('[data-au-field-name]'));
  }

  function readRow(row) {
    const values = {};
    rowInputs(row).forEach(function (input) { values[input.dataset.auFieldName] = input.value; });
    return values;
  }

  function isDirty(row) {
    return rowInputs(row).some(function (input) {
      return String(input.value) !== String(input.dataset.auInitial);
    });
  }

  function refreshRow(row) {
    const dirty = isDirty(row);
    row.classList.toggle('is-dirty', dirty);
    const reprice = row.querySelector('[data-au-listing="reprice"]');
    const reset = row.querySelector('[data-au-listing="reset"]');
    // 没改过就没什么可改的：按钮点不动，比点下去才发现"没有任何变化"诚实
    if (reprice) reprice.disabled = !dirty;
    if (reset) reset.hidden = !dirty;
  }

  function resetRow(row) {
    rowInputs(row).forEach(function (input) { input.value = input.dataset.auInitial; });
    syncCopperHints(row);
    refreshRow(row);
    refreshBulkBar();
  }

  function initListingRows() {
    listingRows().forEach(function (row) {
      rowInputs(row).forEach(function (input) {
        // 记住初始值：dirty 与「撤销」都以它为准
        if (input.dataset.auInitial === undefined) input.dataset.auInitial = input.value;
      });
      refreshRow(row);
    });
  }

  // ---- 多选与批量 ----
  const selectedListingIds = new Set();

  function rowCheckbox(row) {
    return row.querySelector('[data-au-row-select]');
  }

  function selectableRows() {
    return listingRows().filter(function (row) { return !!rowCheckbox(row); });
  }

  function selectedRows() {
    return selectableRows().filter(function (row) {
      return selectedListingIds.has(String(row.dataset.auListingRow));
    });
  }

  function refreshBulkBar() {
    const bar = document.querySelector('[data-au-bulk-bar]');
    const rows = selectedRows();
    const selectAll = document.querySelector('[data-au-select-all]');

    if (selectAll) {
      const boxes = selectableRows().map(rowCheckbox);
      const checked = boxes.filter(function (box) { return box.checked; }).length;
      selectAll.checked = boxes.length > 0 && checked === boxes.length;
      selectAll.indeterminate = checked > 0 && checked < boxes.length;   // 部分选中要说出来
    }

    if (!bar) return;
    bar.hidden = rows.length === 0;
    if (rows.length === 0) return;

    const label = bar.querySelector('[data-au-bulk-count]');
    if (label) label.textContent = tt('listing_detail.bulk_selected', { count: rows.length }, ':count selected');

    const dirtyCount = rows.filter(isDirty).length;
    const save = bar.querySelector('[data-au-bulk="save"]');
    const delist = bar.querySelector('[data-au-bulk="delist"]');
    if (save) save.disabled = dirtyCount === 0;
    if (delist) delist.disabled = rows.length === 0;
  }

  function clearSelection() {
    selectedListingIds.clear();
    selectableRows().forEach(function (row) { rowCheckbox(row).checked = false; });
    refreshBulkBar();
  }

  /**
   * 批量操作。刻意逐条发命令而不是加一个批量端点：模块的 delist / reprice 本来就作用在
   * 内存里的单条挂单上，逐条走既不用改后端契约，每条也会各自进审计日志——批量下架几十条
   * 是不可逆的，留下可追溯的逐条记录比省几次请求重要。
   */
  async function runBulk(action, trigger) {
    const rows = selectedRows();
    if (rows.length === 0) return;

    if (action === 'delist') {
      const ok = await confirmAction({
        title: t('listing_detail.bulk_delist', 'Delist selected'),
        message: tt('confirm.bulk_delist', { count: rows.length },
          'Delist :count auctions? Each item is mailed back to its owner on the next auction house tick.'),
        confirmLabel: t('listing_detail.bulk_delist', 'Delist selected'),
        danger: true
      });
      if (!ok) return;
    }

    const targets = action === 'delist' ? rows : rows.filter(isDirty);
    if (targets.length === 0) {
      show('error', t('listing_detail.bulk_save_none', 'None of the selected rows has a change. Edit a price first.'));
      return;
    }

    const locked = beginWork(trigger);
    let ok = 0;
    let failed = 0;
    try {
      for (let index = 0; index < targets.length; index++) {
        const row = targets[index];
        const id = String(row.dataset.auListingRow);
        show('info', tt('listing_detail.bulk_progress', { done: index + 1, total: targets.length }, 'Working…'));

        const payload = { action: action === 'delist' ? 'delist' : 'reprice', id: id };
        if (action !== 'delist') {
          Object.assign(payload, readRow(row));
          const startbid = parseInt(payload.startbid, 10) || 0;
          const buyout = parseInt(payload.buyout, 10) || 0;
          if (startbid <= 0 || (buyout !== 0 && buyout < startbid)) {
            failed++;
            continue;
          }
        }

        let json = null;
        try {
          json = await post('/auctionator/api/listing', payload);
        } catch (error) {
          json = null;
        }
        if (json && json.success) ok++;
        else failed++;
      }
    } finally {
      endWork(locked);
    }

    show(failed === 0 ? 'success' : 'error',
      tt('listing_detail.bulk_done', { ok: ok, failed: failed }, 'Done.'));
    // 数据变了，之前的选择不再对应任何一行
    clearSelection();
    refreshPanels('listing');
  }

  // 事件委托：行内按钮与复选框都由 page 级监听处理，DOM 被局部替换后依然有效
  document.addEventListener('click', function (event) {
    const bulkNode = event.target.closest ? event.target.closest('[data-au-bulk]') : null;
    if (bulkNode) {
      const bulkAction = bulkNode.dataset.auBulk;
      if (bulkAction === 'clear') clearSelection();
      else runBulk(bulkAction, bulkNode);
      return;
    }

    const node = event.target.closest ? event.target.closest('[data-au-listing]') : null;
    if (!node) return;

    const action = node.dataset.auListing;
    const id = node.dataset.auId;
    const row = node.closest('[data-au-listing-row]');
    if (!row) return;

    if (action === 'reset') {
      resetRow(row);
      return;
    }

    if (action === 'reprice') {
      const payload = Object.assign({ action: 'reprice', id: id }, readRow(row));
      const startbid = parseInt(payload.startbid, 10) || 0;
      const buyout = parseInt(payload.buyout, 10) || 0;

      if (startbid <= 0) {
        show('error', t('listing.startbid_required', 'The start bid must be at least 1 copper.'));
        return;
      }

      // 0 = 不带一口价（纯竞拍）；非 0 就必须不低于起拍价，否则这条挂单自相矛盾
      if (buyout !== 0 && buyout < startbid) {
        show('error', t('listing.buyout_below_startbid', 'The buyout must not be below the start bid.'));
        return;
      }

      confirmAction({
        title: t('listing_detail.reprice', 'Reprice'),
        message: tt('confirm.reprice', {
          id: id,
          startbid: copperText(startbid),
          buyout: buyout === 0 ? t('listing.no_buyout', 'none') : copperText(buyout)
        }, 'Reprice auction :id to :startbid / :buyout?'),
        confirmLabel: t('listing_detail.reprice', 'Reprice')
      }).then(function (ok) { if (ok) runListing(payload); });
      return;
    }

    // 下架走核心的到期流程：物品按邮件退回所有者，机器人自己的邮件会被回收（等于销毁）
    confirmAction({
      title: t('listing_detail.delist', 'Delist'),
      message: tt('confirm.delist', { id: id },
        'Take auction :id down? Its item is mailed back to the owner on the next auction house tick.'),
      confirmLabel: t('listing_detail.delist', 'Delist'),
      danger: true
    }).then(function (ok) { if (ok) runListing({ action: 'delist', id: id }); });
  });

  document.addEventListener('change', function (event) {
    const node = event.target;
    if (!node || !node.dataset) return;

    if (node.matches && node.matches('[data-au-row-select]')) {
      const row = node.closest('[data-au-listing-row]');
      if (!row) return;
      if (node.checked) selectedListingIds.add(String(row.dataset.auListingRow));
      else selectedListingIds.delete(String(row.dataset.auListingRow));
      refreshBulkBar();
      return;
    }

    if (node.matches && node.matches('[data-au-select-all]')) {
      selectableRows().forEach(function (row) {
        const box = rowCheckbox(row);
        if (!box) return;
        box.checked = node.checked;
        if (node.checked) selectedListingIds.add(String(row.dataset.auListingRow));
        else selectedListingIds.delete(String(row.dataset.auListingRow));
      });
      refreshBulkBar();
    }
  });

  initListingRows();
  refreshBulkBar();

  // ---- GM actions ----
  function actionExtraFields() {
    const extra = {};
    document.querySelectorAll('[data-au-action-field]').forEach(function (node) {
      const key = node.dataset.auActionField;
      extra[key] = node.type === 'checkbox' ? (node.checked ? '1' : '0') : node.value;
    });
    return extra;
  }

  async function runAction(action, extra, trigger) {
    const fields = Object.assign({}, actionExtraFields(), extra || {});
    const confirmMessage = t('confirm.' + action, '');
    const options = { message: confirmMessage, danger: true };

    // expireall 勾上"包含玩家条目"会连全服玩家的挂单一起取消：这种不可逆的波及面，
    // 值得要求操作人照打一段字，而不是点一下"确定"。
    if (action === 'expireall' && String(fields.all || '') === '1') {
      options.requireText = 'EXPIRE';
      options.requireLabel = tt('type_to_confirm', { text: 'EXPIRE' }, 'Type :text to confirm.');
      options.message = t('confirm.expireall_all', confirmMessage);
    }

    if (confirmMessage) {
      const ok = await confirmAction(options);
      if (!ok) return;
    }

    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/action', Object.assign({ action: action }, fields));
    } finally {
      endWork(locked);
    }

    const output = json && json.payload ? json.payload.output : '';
    printOutput(output || (json && json.message) || '');

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.action_failure', 'The command failed.'));
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
    // GM 命令可能改动几乎所有东西（补货、强制过期、市场维护），所以刷新面最宽
    refreshPanels('action');
  }

  // ---- master switch (this realm) ----
  async function runPower(enable, trigger) {
    const confirmMessage = t(enable ? 'confirm.power_start' : 'confirm.power_stop', '');
    if (confirmMessage && !(await confirmAction({ message: confirmMessage, danger: true }))) return;

    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/power', { enable: enable ? 1 : 0 });
    } finally {
      endWork(locked);
    }

    const output = json && json.payload ? json.payload.output : '';
    if (output) printOutput(output);

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.power_failure', 'The master switch could not be applied.'));
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
    refreshPanels('switch');
  }

  // ---- buyout mode (this realm) ----
  /**
   * 快速买断开关是总开关的孪生体：把 Auctionator.Seller.BidOnly 写进本区 conf（重启后仍生效）并发
   * ".auctionator buyout 0|1"（卖家下一次运行即生效）。注意是反向的：buyout 关闭 = BidOnly 1。
   */
  async function runBuyout(enable, trigger) {
    const confirmMessage = t(enable ? 'confirm.buyout_enable' : 'confirm.buyout_disable', '');
    if (confirmMessage && !(await confirmAction({ message: confirmMessage, danger: !enable }))) return;

    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/buyout', { enable: enable ? 1 : 0 });
    } finally {
      endWork(locked);
    }

    const output = json && json.payload ? json.payload.output : '';
    if (output) printOutput(output);

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.buyout_failure', 'The buyout switch could not be applied.'));
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
    refreshPanels('switch');
  }

  // ---- 物品等级上限（本区，只作用于自动卖家） ----
  /**
   * 写本区 Auctionator.Seller.MaxItemLevel 并发 ".auctionator maxitemlevel <值|off>"（0 = 不限）。
   */
  async function runMaxItemLevel(value, trigger) {
    const level = value > 0 ? String(value) : '';
    const confirmMessage = level
      ? tt('confirm.max_item_level', { level: level },
          'Stop the automatic seller from listing items above item level :level?')
      : t('confirm.max_item_level_off', 'Remove the item level limit?');
    if (confirmMessage && !(await confirmAction({ message: confirmMessage, danger: false }))) return;

    const locked = beginWork(trigger);
    let json = null;
    try {
      json = await post('/auctionator/api/maxitemlevel', { value: value });
    } finally {
      endWork(locked);
    }

    const output = json && json.payload ? json.payload.output : '';
    if (output) printOutput(output);

    if (!json || !json.success) {
      show('error', (json && json.message) || t('feedback.max_item_level_failure', 'The item level limit could not be applied.'));
      // conf 可能已写入而命令未送到：失败也要刷新，否则「当前生效」停在旧值。
      refreshPanels('itemlevel');
      return;
    }

    show('success', json.message || t('feedback.action_success', 'Command executed.'));
    refreshPanels('itemlevel');
  }

  // ---- 页面增强：服务端每次渲染后都要跑一遍 ----
  /**
   * 这些不是"事件绑定"（绑定全部走委托，局部刷新后自然继续有效），而是对服务端渲染出来的
   * DOM 做增强：注入搜索框、把物品输入换成选择器、给新行记下 dirty 初值……
   * 局部刷新替换了区块之后必须再跑一次，否则新表格没有搜索框、新选择器是死的。
   */
  function enhance() {
    // 六张表都挂上客户端筛选框（纯前端过滤，不发请求）
    if (typeof panel.tableFilter === 'function') {
      panel.tableFilter(document, {
        emptyLabel: t('table.no_match', 'No matching row.'),
        placeholder: t('table.filter', 'Filter this page…')
      });
    }

    // 物品选择器（黑名单新增 / 精选清单新增 / 临时上架多选）
    if (typeof panel.itemPicker === 'function') {
      Array.prototype.forEach.call(document.querySelectorAll('[data-au-picker]'), function (node) {
        panel.itemPicker(node, {
          endpoint: '/auctionator/api/items',
          params: currentServer ? { server: currentServer } : {},
          limit: 20
        });
      });
    }

    // 类别 → 子类级联：新渲染出来的那一份要按当前类别填一次
    const classSelect = document.querySelector('[data-au-class-select]');
    const subclassSelect = document.querySelector('[data-au-subclass-select]');
    if (classSelect && subclassSelect && !subclassSelect.dataset.auFilled) {
      subclassSelect.dataset.auFilled = '1';
      fillSubclassSelect(classSelect, subclassSelect);
    }

    // 上架模式的实时预览
    Array.prototype.forEach.call(document.querySelectorAll('[data-au-listing-form]'), function (form) {
      syncListingForm(form);
    });

    syncCopperHints(document);
    initListingRows();
    refreshBulkBar();
  }

  // ---- 全部事件走委托 ----
  // 页面区块会被局部刷新整块替换，逐节点绑定（node.addEventListener）一换就失效；
  // 委托到 document 之后，替换多少次都不用重绑，也顺手修掉了"加载后新增的节点不响应"。
  document.addEventListener('click', function (event) {
    const target = event.target;
    if (!target || !target.closest) return;

    const power = target.closest('[data-au-power]');
    if (power) { runPower(power.dataset.auPower === 'start', power); return; }

    const buyout = target.closest('[data-au-buyout]');
    if (buyout) { runBuyout(buyout.dataset.auBuyout === '1', buyout); return; }

    const action = target.closest('[data-au-action]');
    if (action) { runAction(action.dataset.auAction, {}, action); return; }

    const policy = target.closest('[data-au-policy]');
    if (policy && policy.tagName !== 'FORM') { clickPolicyButton(policy); }
  });

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!form || !form.matches) return;

    if (form.id === 'auConfigForm') {
      event.preventDefault();
      submitConfigForm(form, event.submitter || form.querySelector('[type="submit"]'));
      return;
    }

    if (form.id === 'auAddForm') {
      event.preventDefault();
      if (!requirePicked(form)) return;
      // runAction 先合并页面级 [data-au-action-field]，再叠加本表单 payload，冲突时表单优先（house）：
      // 那些共享字段只是给 addlist/expireall 兜底的。
      runAction('add', formPayload(form, 'add'), event.submitter || form.querySelector('[type="submit"]'));
      return;
    }

    if (form.matches('[data-au-policy]')) {
      event.preventDefault();
      submitPolicyForm(form, event.submitter || form.querySelector('[type="submit"]'));
      return;
    }

    if (form.matches('[data-au-max-item-level]')) {
      event.preventDefault();
      const input = form.querySelector('[name="value"]');
      const parsed = input ? parseInt(input.value, 10) : 0;
      const value = Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
      runMaxItemLevel(value, event.submitter || form.querySelector('[type="submit"]'));
    }
  });

  // 类别下拉变了就重算子类；上架表单变了就重算预览（含输入，数字是手打的）
  document.addEventListener('change', function (event) {
    const target = event.target;
    if (!target || !target.closest) return;

    if (target.matches && target.matches('[data-au-class-select]')) {
      const subclassSelect = document.querySelector('[data-au-subclass-select]');
      if (subclassSelect) fillSubclassSelect(target, subclassSelect);
      return;
    }

    const form = target.closest('[data-au-listing-form]');
    if (form) syncListingForm(form);
  });

  document.addEventListener('input', function (event) {
    const form = event.target && event.target.closest ? event.target.closest('[data-au-listing-form]') : null;
    if (form) syncListingForm(form);
  });

  enhance();
  printOutput(null);
})();
