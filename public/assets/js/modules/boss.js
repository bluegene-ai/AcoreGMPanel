(function(){
  if(document.body.dataset.module !== 'boss') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const currentServer = new URLSearchParams(window.location.search).get('server') || '';
  const dom = {
    feedback: document.getElementById('bossFeedback'),
    spawnBtn: document.getElementById('bossSpawnBtn'),
    killBtn: document.getElementById('bossKillBtn'),
    clearBtn: document.getElementById('bossClearBtn'),
    rebaseBtn: document.getElementById('bossRebaseBtn'),
    configReloadBtn: document.getElementById('bossConfigReloadBtn'),
    presetBtn: document.getElementById('bossPresetBtn'),
    presetSelect: document.getElementById('bossPresetSelect'),
    difficultyBtn: document.getElementById('bossDifficultyBtn'),
    difficultySelect: document.getElementById('bossDifficultySelect'),
    tierSelect: document.querySelector('[data-boss-tier-select]'),
    healthMultiplierInput: document.getElementById('bossHealthMultiplierInput'),
    estimatedHp: document.querySelector('[data-boss-hp-preview]'),
    configForm: document.getElementById('bossConfigForm'),
    configSaveBtn: document.getElementById('bossConfigSaveBtn'),
    extConfigForm: document.getElementById('bossExtConfigForm'),
    extConfigSaveBtn: document.getElementById('bossExtConfigSaveBtn')
  };

  function t(path, fallback){
    if(typeof panel.moduleLocale === 'function') return panel.moduleLocale('boss', path, fallback);
    return fallback || path;
  }

  function withServer(path){
    if(!currentServer) return path;
    return path + (path.indexOf('?') >= 0 ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  async function post(path, body){
    const url = withServer(path);
    if(api && typeof api.post === 'function') return api.post(url, body || {});
    const response = await fetch(url, {
      method: 'POST',
      body: JSON.stringify(body || {}),
      headers: { 'Content-Type': 'application/json' }
    });
    return response.json();
  }

  function show(type, message){
    if(feedback && dom.feedback){
      feedback.show(dom.feedback, type, message, { duration: 4000 });
      return;
    }
    if(dom.feedback){
      dom.feedback.hidden = false;
      dom.feedback.textContent = message;
      dom.feedback.classList.add('is-visible');
    }
  }

  function setBusy(disabled){
    [dom.spawnBtn, dom.killBtn, dom.clearBtn, dom.rebaseBtn, dom.configReloadBtn, dom.presetBtn, dom.difficultyBtn, dom.configSaveBtn, dom.extConfigSaveBtn].forEach(function(node){
      if(node) node.disabled = !!disabled;
    });
    if(dom.presetSelect) dom.presetSelect.disabled = !!disabled;
    if(dom.difficultySelect) dom.difficultySelect.disabled = !!disabled;
    if(dom.tierSelect) dom.tierSelect.disabled = !!disabled;
    if(dom.healthMultiplierInput) dom.healthMultiplierInput.disabled = !!disabled;
    [dom.configForm, dom.extConfigForm].forEach(function(form){
      if(!form) return;
      form.querySelectorAll('input, textarea, select').forEach(function(node){
        node.disabled = !!disabled;
      });
    });
  }

  function formatHp(value){
    const rounded = Math.max(0, Math.round(Number(value) || 0));
    if(!isFinite(rounded)) return '0';
    try{
      return rounded.toLocaleString('en-US');
    }catch(error){
      return String(rounded);
    }
  }

  function currentHealthMultiplierScaled(){
    if(!dom.healthMultiplierInput) return null;
    const multiplier = parseFloat(dom.healthMultiplierInput.value);
    if(!isFinite(multiplier) || multiplier < 0) return null;
    const scale = parseFloat(dom.estimatedHp && dom.estimatedHp.dataset.bossHpScale) || 100;
    return multiplier * scale;
  }

  // 预估血量 = base_hp × 模板 HealthModifier × 当前血量倍率（纯前端换算）。
  function updateEstimatedHp(){
    if(!dom.estimatedHp || !dom.tierSelect) return;
    const option = dom.tierSelect.options[dom.tierSelect.selectedIndex];
    if(!option) return;

    const baseHp = parseFloat(dom.estimatedHp.dataset.bossBaseHp) || 0;
    const modifier = parseFloat(option.dataset.bossHealthModifier);
    const multiplierScaled = currentHealthMultiplierScaled();

    if(!isFinite(modifier) || modifier <= 0 || multiplierScaled === null){
      dom.estimatedHp.textContent = '—';
      return;
    }

    const scale = parseFloat(dom.estimatedHp.dataset.bossHpScale) || 100;
    dom.estimatedHp.textContent = formatHp(baseHp * modifier * (multiplierScaled / scale));
  }

  function confirmMessage(action, label){
    const base = t('confirm.' + action, '');
    return base.replace(':value', label || '');
  }

  async function runAction(action, value, label){
    const message = confirmMessage(action, label || value || '');
    if(message && !window.confirm(message)) return;

    setBusy(true);
    const json = await post('/boss/api/action', { action: action, value: value || '' });
    setBusy(false);

    if(!json || !json.success){
      show('error', (json && json.message) || t('feedback.failure', 'Action failed.'));
      return;
    }

    show('success', json.message || t('feedback.success', 'Action completed.'));
    window.setTimeout(function(){ window.location.reload(); }, 600);
  }

  async function saveConfig(){
    if(!dom.configForm) return;

    const payload = Object.fromEntries(new FormData(dom.configForm).entries());

    setBusy(true);
    const json = await post('/boss/api/config', payload);
    setBusy(false);

    if(!json || !json.success){
      show('error', (json && json.message) || t('feedback.config_failure', 'Configuration save failed.'));
      return;
    }

    show('success', json.message || t('feedback.config_success', 'Configuration saved.'));
    window.setTimeout(function(){ window.location.reload(); }, 600);
  }

  // 扩展配置字段多、都在二级 Tab 里：开关型带一个 hidden=0 占位（未勾选时提交 0）；
  // 多选字段（name="字段[]"）会有多个同名值，而 Object.fromEntries 只留最后一个，
  // 所以先合并成逗号串再提交（空串是合法值 = 全部预设，不能丢）。
  function extFormPayload(form){
    const payload = {};
    const multi = {};

    new FormData(form).forEach(function(value, key){
      if(key.length > 2 && key.slice(-2) === '[]'){
        const base = key.slice(0, -2);
        (multi[base] = multi[base] || []).push(value);
        return;
      }
      payload[key] = value;
    });

    Object.keys(multi).forEach(function(base){
      payload[base] = multi[base].filter(function(value){ return String(value).trim() !== ''; }).join(',');
    });

    return payload;
  }

  async function saveExtConfig(){
    if(!dom.extConfigForm) return;

    const payload = extFormPayload(dom.extConfigForm);

    setBusy(true);
    const json = await post('/boss/api/ext-config', payload);
    setBusy(false);

    if(!json || !json.success){
      show('error', (json && json.message) || t('feedback.ext_failure', 'Extended configuration save failed.'));
      return;
    }

    show('success', json.message || t('feedback.ext_success', 'Extended configuration saved.'));
    window.setTimeout(function(){ window.location.reload(); }, 600);
  }

  /* ---- Tab 分页：每个 [data-boss-tabs] 容器管自己的按钮与面板（面板须为容器直接子节点），
     顶层 Tab 与扩展配置的二级 Tab 共用同一套逻辑；当前 Tab 记进 URL hash，刷新/保存后回到同一页。 */
  function tabGroups(){
    return Array.prototype.slice.call(document.querySelectorAll('[data-boss-tabs]'));
  }

  function groupButtons(group){
    return Array.prototype.slice.call(group.querySelectorAll(':scope > .boss-tabs__nav [data-boss-tab]'));
  }

  function groupPanels(group){
    return Array.prototype.slice.call(group.querySelectorAll(':scope > [data-boss-tabpanel]'));
  }

  function hashKeyFor(groupName){
    return groupName === 'main' ? 'tab' : groupName;
  }

  function readHashParams(){
    const params = {};
    String(window.location.hash || '').replace(/^#/, '').split('&').forEach(function(chunk){
      const index = chunk.indexOf('=');
      if(index <= 0) return;
      params[chunk.slice(0, index)] = chunk.slice(index + 1);
    });
    return params;
  }

  function rememberTab(groupName, tabName){
    const params = readHashParams();
    params[hashKeyFor(groupName)] = tabName;
    const hash = Object.keys(params).map(function(key){ return key + '=' + params[key]; }).join('&');
    const url = new URL(window.location.href);
    url.hash = hash;
    // replaceState：避免每次点 Tab 都往浏览器历史里塞一条
    window.history.replaceState(null, '', url.toString());
  }

  function activateTab(group, name, remember){
    const panels = groupPanels(group);
    if(!panels.length) return;

    const available = panels.map(function(panel){ return panel.dataset.bossTabpanel; });
    if(available.indexOf(name) < 0) name = available[0];

    groupButtons(group).forEach(function(button){
      const active = button.dataset.bossTab === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach(function(panel){
      panel.classList.toggle('is-active', panel.dataset.bossTabpanel === name);
    });

    if(remember) rememberTab(group.dataset.bossTabs || 'main', name);
  }

  function initTabs(){
    const params = readHashParams();
    tabGroups().forEach(function(group){
      const groupName = group.dataset.bossTabs || 'main';
      activateTab(group, params[hashKeyFor(groupName)] || '', false);
    });
  }

  initTabs();

  document.addEventListener('click', function(event){
    const button = event.target.closest('[data-boss-tab]');
    if(!button) return;

    const group = button.closest('[data-boss-tabs]');
    if(!group) return;

    event.preventDefault();
    activateTab(group, button.dataset.bossTab, true);
  });

  window.addEventListener('hashchange', initTabs);

  if(dom.spawnBtn){
    dom.spawnBtn.addEventListener('click', function(){ runAction('spawn'); });
  }

  if(dom.killBtn){
    dom.killBtn.addEventListener('click', function(){ runAction('kill'); });
  }

  if(dom.clearBtn){
    dom.clearBtn.addEventListener('click', function(){ runAction('clear'); });
  }

  if(dom.rebaseBtn){
    dom.rebaseBtn.addEventListener('click', function(){ runAction('rebase'); });
  }

  if(dom.configReloadBtn){
    dom.configReloadBtn.addEventListener('click', function(){ runAction('config_reload'); });
  }

  if(dom.tierSelect){
    dom.tierSelect.addEventListener('change', updateEstimatedHp);
  }

  if(dom.healthMultiplierInput){
    dom.healthMultiplierInput.addEventListener('input', updateEstimatedHp);
    dom.healthMultiplierInput.addEventListener('change', updateEstimatedHp);
  }

  updateEstimatedHp();

  if(dom.presetBtn && dom.presetSelect){
    dom.presetBtn.addEventListener('click', function(){
      const option = dom.presetSelect.options[dom.presetSelect.selectedIndex];
      runAction('preset', dom.presetSelect.value, option ? option.text : dom.presetSelect.value);
    });
  }

  if(dom.difficultyBtn && dom.difficultySelect){
    dom.difficultyBtn.addEventListener('click', function(){
      const option = dom.difficultySelect.options[dom.difficultySelect.selectedIndex];
      runAction('difficulty', dom.difficultySelect.value, option ? option.text : dom.difficultySelect.value);
    });
  }

  if(dom.configForm){
    dom.configForm.addEventListener('submit', function(event){
      event.preventDefault();
      saveConfig();
    });
  }

  if(dom.extConfigForm){
    dom.extConfigForm.addEventListener('submit', function(event){
      event.preventDefault();
      saveExtConfig();
    });
  }

  /* ---- 控件"逻辑值"：bool 看 checked，多选看选中集合，其它看 value ---- */
  function controlValue(el){
    if(!el) return '';
    if(el.type === 'checkbox' || el.type === 'radio') return el.checked ? '1' : '0';
    if(el.tagName === 'SELECT' && el.multiple) {
      return Array.prototype.slice.call(el.selectedOptions).map(function(o){ return o.value; }).sort().join(',');
    }
    return String(el.value === undefined || el.value === null ? '' : el.value);
  }

  /* 参与"未保存"计数的控件：跳过 bool 的 hidden=0 占位（它永远跟着复选框走） */
  function trackedControls(form){
    return Array.prototype.slice.call(form.elements).filter(function(el){
      if(!el.name) return false;
      if(el.type === 'hidden' && el.name.indexOf('[]') < 0) return false;
      if(el.type === 'submit' || el.type === 'button' || el.type === 'reset') return false;
      return true;
    });
  }

  function formSignature(form){
    return trackedControls(form).map(function(el){
      return el.name + '=' + controlValue(el);
    }).sort().join('&');
  }

  const dirtyBars = [];
  function setupDirtyBar(form, bar){
    if(!form || !bar) return;
    const label = bar.querySelector('[data-boss-dirty-label]');
    const discard = bar.querySelector('[data-boss-discard]');
    const baseline = new Map();
    trackedControls(form).forEach(function(el){ baseline.set(el, controlValue(el)); });

    function changedCount(){
      let count = 0;
      baseline.forEach(function(value, el){
        if(controlValue(el) !== value) count++;
      });
      return count;
    }

    function refresh(){
      const count = changedCount();
      if(label){
        label.textContent = count === 0
          ? t('ux.no_changes', 'No unsaved changes')
          : t('ux.unsaved', ':n unsaved change(s)').replace(':n', String(count));
      }
      bar.classList.toggle('is-dirty', count > 0);
      if(discard) discard.disabled = count === 0;
    }

    if(discard){
      discard.addEventListener('click', function(){
        baseline.forEach(function(value, el){
          if(el.type === 'checkbox' || el.type === 'radio') el.checked = value === '1';
          else el.value = value;
        });
        refresh();
      });
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);

    const entry = { form: form, refresh: refresh, isDirty: function(){ return changedCount() > 0; } };
    dirtyBars.push(entry);
    refresh();
    return entry;
  }

  setupDirtyBar(dom.configForm, document.querySelector('[data-boss-save-bar="config"]'));
  setupDirtyBar(dom.extConfigForm, document.querySelector('[data-boss-save-bar="ext"]'));

  // 切换 Tab / 关闭页面时提醒未保存（只在真的有改动时拦一下）
  function anyDirty(){
    return dirtyBars.some(function(bar){ return bar.isDirty(); });
  }

  document.addEventListener('click', function(event){
    const button = event.target.closest('[data-boss-tab]');
    if(!button || !anyDirty()) return;
    const group = button.closest('[data-boss-tabs]');
    const target = group ? group.querySelector(':scope > [data-boss-tabpanel][data-boss-tabpanel="' + button.dataset.bossTab + '"]') : null;
    const active = target && target.classList.contains('is-active');
    if(active) return;
    if(!window.confirm(t('ux.unsaved_switch', 'There are unsaved changes. Switch tab anyway?'))){
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);

  window.addEventListener('beforeunload', function(event){
    if(!anyDirty()) return;
    event.preventDefault();
    event.returnValue = '';
  });

  /* ---- 每字段"恢复默认"：由 data-boss-default 驱动（视图不用为 85 个字段各写一个按钮） ---- */
  function wireDefaultResets(form){
    if(!form) return;
    const chips = [];
    Array.prototype.slice.call(form.querySelectorAll('[data-boss-default]')).forEach(function(el){
      if(el.type === 'hidden') return; // bool 的 0 占位
      const holder = el.closest('.boss-field, .boss-check') || el.parentElement;
      if(!holder) return;
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'boss-chip boss-chip--reset';
      chip.textContent = t('ux.reset_field', 'Restore default');
      chip.addEventListener('click', function(){
        const target = el.dataset.bossDefault === undefined ? '' : el.dataset.bossDefault;
        if(el.type === 'checkbox' || el.type === 'radio') el.checked = target === '1';
        else el.value = target;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      holder.appendChild(chip);
      chips.push({ chip: chip, el: el });
    });

    return function refreshResets(){
      chips.forEach(function(item){
        item.chip.hidden = controlValue(item.el) === String(item.el.dataset.bossDefault === undefined ? '' : item.el.dataset.bossDefault);
      });
    };
  }

  const refreshConfigResets = wireDefaultResets(dom.configForm);
  const refreshExtResets = wireDefaultResets(dom.extConfigForm);
  [dom.configForm, dom.extConfigForm].forEach(function(form){
    if(!form) return;
    form.addEventListener('input', function(){ if(refreshConfigResets) refreshConfigResets(); if(refreshExtResets) refreshExtResets(); });
    form.addEventListener('change', function(){ if(refreshConfigResets) refreshConfigResets(); if(refreshExtResets) refreshExtResets(); });
  });
  if(refreshConfigResets) refreshConfigResets();
  if(refreshExtResets) refreshExtResets();

  /* ---- 相对时间（data-boss-ago）：把绝对时间改成"x 分钟前"，绝对时间放进 title ---- */
  function relativeText(seconds){
    if(!seconds || seconds <= 0) return t('ux.time_none', '—');
    const now = Math.floor(Date.now() / 1000);
    const diff = now - seconds;
    if(diff < 0) return t('ux.time_none', '—');
    if(diff < 60) return t('ux.time_just_now', 'just now');
    if(diff < 3600) return t('ux.time_minutes', ':n min ago').replace(':n', String(Math.floor(diff / 60)));
    if(diff < 86400) return t('ux.time_hours', ':n h ago').replace(':n', String(Math.floor(diff / 3600)));
    return t('ux.time_days', ':n d ago').replace(':n', String(Math.floor(diff / 86400)));
  }

  function refreshRelativeTimes(){
    Array.prototype.slice.call(document.querySelectorAll('[data-boss-ago]')).forEach(function(el){
      const at = parseInt(el.dataset.bossAgo || '0', 10);
      if(!at) return;
      if(!el.dataset.bossAgoAbsolute) el.dataset.bossAgoAbsolute = el.textContent.trim();
      el.title = el.dataset.bossAgoAbsolute;
      el.textContent = el.dataset.bossAgoAbsolute + ' · ' + relativeText(at);
    });
  }

  /* ---- 重生倒计时（data-boss-countdown） ---- */
  function durationText(total){
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    if(days > 0) return t('ux.duration_days', ':d d :h h').replace(':d', String(days)).replace(':h', String(hours));
    if(hours > 0) return t('ux.duration_hours', ':h h :m min').replace(':h', String(hours)).replace(':m', String(minutes));
    if(minutes > 0) return t('ux.duration_minutes', ':m min :s s').replace(':m', String(minutes)).replace(':s', String(seconds));
    return t('ux.duration_seconds', ':s s').replace(':s', String(seconds));
  }

  function setupCountdown(){
    const box = document.querySelector('[data-boss-countdown]');
    if(!box) return;
    const at = parseInt(box.dataset.bossCountdownAt || '0', 10);
    const valueEl = box.querySelector('[data-boss-countdown-value]');
    const hintEl = box.querySelector('[data-boss-countdown-hint]');
    if(!valueEl) return;
    const absolute = valueEl.textContent.trim();

    function tick(){
      if(!at || at <= 0){
        valueEl.textContent = t('ux.countdown_none', '—');
        if(hintEl) hintEl.textContent = '';
        return;
      }
      const remain = at - Math.floor(Date.now() / 1000);
      if(remain <= 0){
        valueEl.textContent = t('ux.countdown_due', 'any moment');
        if(hintEl) hintEl.textContent = absolute;
        return;
      }
      valueEl.textContent = durationText(remain);
      if(hintEl) hintEl.textContent = t('ux.countdown_at', 'at :time').replace(':time', absolute);
    }

    tick();
    window.setInterval(tick, 1000);
  }

  refreshRelativeTimes();
  setupCountdown();
  window.setInterval(refreshRelativeTimes, 60000);

  /* ---- 表格筛选 chips（事件按类型 / 贡献快照按"有奖励"“最后一击"） ---- */
  function setupTableFilters(){
    const eventsTable = document.querySelector('[data-boss-events-table]');
    const eventsBar = document.querySelector('[data-boss-table-filter="events"]');
    if(eventsTable && eventsBar){
      const rows = Array.prototype.slice.call(eventsTable.querySelectorAll('tbody tr[data-boss-event-type]'));
      eventsBar.addEventListener('click', function(event){
        const chip = event.target.closest('[data-boss-event-filter]');
        if(!chip) return;
        const wanted = chip.dataset.bossEventFilter;
        Array.prototype.slice.call(eventsBar.querySelectorAll('[data-boss-event-filter]')).forEach(function(other){
          other.classList.toggle('is-active', other === chip);
        });
        rows.forEach(function(row){
          const type = String(row.dataset.bossEventType || '');
          let match = true;
          if(wanted === 'death') match = type.indexOf('death') >= 0 || type.indexOf('kill') >= 0;
          else if(wanted === 'spawn') match = type.indexOf('spawn') >= 0;
          else if(wanted === 'reward') match = type.indexOf('reward') >= 0;
          else if(wanted === 'schedule') match = type.indexOf('schedule') >= 0;
          else if(wanted === 'command') match = type.indexOf('command') >= 0;
          row.classList.toggle('boss-row--hidden', !match);
        });
      });
    }

    const contributorsTable = document.querySelector('[data-boss-contributors-table]');
    const contributorsBar = document.querySelector('[data-boss-table-filter="contributors"]');
    if(contributorsTable && contributorsBar){
      const rows = Array.prototype.slice.call(contributorsTable.querySelectorAll('tbody tr[data-boss-rewarded]'));
      contributorsBar.addEventListener('click', function(event){
        const chip = event.target.closest('[data-boss-contributor-filter]');
        if(!chip) return;
        const wanted = chip.dataset.bossContributorFilter;
        Array.prototype.slice.call(contributorsBar.querySelectorAll('[data-boss-contributor-filter]')).forEach(function(other){
          other.classList.toggle('is-active', other === chip);
        });
        rows.forEach(function(row){
          let match = true;
          if(wanted === 'rewarded') match = row.dataset.bossRewarded === '1';
          else if(wanted === 'killer') match = row.dataset.bossKiller === '1';
          row.classList.toggle('boss-row--hidden', !match);
        });
      });
    }
  }

  setupTableFilters();

  /* ---- 奖池卡片联动：开关 / 人数模式 / 奖品数 → 卡片状态、总览条、目录 ---- */
  function setupPoolCards(){
    const cards = Array.prototype.slice.call(document.querySelectorAll('[data-boss-pool-card]'));
    if(!cards.length) return;

    function summaryFor(index){
      return document.querySelector('[data-boss-pool-summary-item="' + index + '"]');
    }

    function tocFor(index){
      return document.querySelector('.boss-toc__item[data-boss-pool-jump="' + index + '"]');
    }

    function countItems(text){
      return String(text || '').split(/[\s,;]+/).filter(function(token){ return parseInt(token, 10) > 0; }).length;
    }

    function refreshCard(card){
      const index = card.dataset.bossPoolCard;
      const enabled = card.querySelector('[data-boss-pool-enabled]');
      const mode = card.querySelector('[data-boss-pool-mode]');
      const count = card.querySelector('[data-boss-pool-count]');
      const chanceInput = card.querySelector('[data-boss-pool-chance]');
      const items = card.querySelector('[data-boss-pool-items-input]');
      const isOn = !enabled || enabled.checked;

      card.classList.toggle('is-off', !isOn);

      const stateEl = card.querySelector('[data-boss-pool-state]');
      if(stateEl) stateEl.textContent = isOn ? t('ux.pool_state_on', 'On') : t('ux.pool_state_off', 'Off');

      // 人数模式 = 全部有效参战时，人数没有意义 → 置灰禁用（避免误填）
      if(count && mode){
        const countWrap = count.closest('[data-boss-pool-count-wrap]') || count.parentElement;
        const countDisabled = mode.value === 'all';
        count.disabled = countDisabled;
        if(countWrap) countWrap.classList.toggle('is-disabled', countDisabled);
      }

      const itemsCount = countItems(items ? items.value : '');
      const itemsCountEl = card.querySelector('[data-boss-pool-items-count]');
      if(itemsCountEl) itemsCountEl.textContent = t('ux.pool_items', ':n item(s)').replace(':n', String(itemsCount));

      const summary = summaryFor(index);
      if(summary){
        summary.classList.toggle('is-off', !isOn);
        const stateSlot = summary.querySelector('[data-boss-pool-summary-state]');
        const chanceSlot = summary.querySelector('[data-boss-pool-summary-chance]');
        const countSlot = summary.querySelector('[data-boss-pool-summary-count]');
        const itemsSlot = summary.querySelector('[data-boss-pool-summary-items]');
        if(stateSlot) stateSlot.textContent = isOn ? t('ux.pool_state_on', 'On') : t('ux.pool_state_off', 'Off');
        if(chanceSlot) chanceSlot.textContent = String(parseInt(chanceInput ? chanceInput.value : '0', 10) || 0) + '%';
        if(countSlot) {
          countSlot.textContent = (mode && mode.value === 'all')
            ? t('ux.winners_all', 'Every eligible player')
            : t('ux.pool_winners', ':n winner(s)').replace(':n', String(parseInt(count ? count.value : '0', 10) || 0));
        }
        if(itemsSlot) itemsSlot.textContent = t('ux.pool_items', ':n item(s)').replace(':n', String(itemsCount));
      }

      const toc = tocFor(index);
      if(toc) toc.classList.toggle('is-off', !isOn);
    }

    function refreshAll(){
      cards.forEach(refreshCard);
    }

    cards.forEach(function(card){
      card.addEventListener('input', function(){ refreshCard(card); });
      card.addEventListener('change', function(){ refreshCard(card); });
    });

    // 总览条 / 目录 → 跳到对应卡片并高亮
    document.addEventListener('click', function(event){
      const jump = event.target.closest('[data-boss-pool-jump]');
      if(!jump) return;
      const card = document.querySelector('[data-boss-pool-card="' + jump.dataset.bossPoolJump + '"]');
      if(!card) return;
      card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      card.style.outline = '2px solid rgba(220,168,82,.65)';
      window.setTimeout(function(){ card.style.outline = ''; }, 1200);
    });

    // 奖品名折叠/展开
    document.addEventListener('click', function(event){
      const toggle = event.target.closest('[data-boss-pool-items-toggle]');
      if(!toggle) return;
      const wrapper = toggle.closest('[data-boss-pool-items]');
      if(wrapper) wrapper.classList.toggle('is-open');
    });

    refreshAll();
  }

  setupPoolCards();

  /* ---- 奖池工具：模拟一次击杀 / 跨区复制 ---- */
  function setupRewardTools(){
    const simBtn = document.querySelector('[data-boss-simulate]');
    const simBox = document.querySelector('[data-boss-simulate-result]');
    const copyOpen = document.querySelector('[data-boss-copy-open]');
    const copyPanel = document.querySelector('[data-boss-copy-panel]');
    const copyTarget = document.querySelector('[data-boss-copy-target]');
    const copyRun = document.querySelector('[data-boss-copy-run]');
    const copyResult = document.querySelector('[data-boss-copy-result]');
    if(!simBtn && !copyOpen) return;

    function esc(value){
      return String(value === undefined || value === null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function pct(value){
      const number = Number(value || 0) * 100;
      return (number >= 99.95 || number === 0 ? Math.round(number) : Math.round(number * 10) / 10) + '%';
    }

    function className(classId){
      const enums = window.APP_ENUMS || {};
      const classes = enums.classes || {};
      const found = classes[String(classId)];
      if(found && typeof found === 'string') return found;
      if(found && found.name) return found.name;
      return classId ? ('Class ' + classId) : '?';
    }

    // 目标区下拉：复用页面顶部的服务器切换器（面板已经知道有哪些区）
    function fillTargets(){
      if(!copyTarget) return;
      const switcher = document.getElementById('serverSelectBox');
      const current = currentServer || (switcher ? switcher.value : '');
      copyTarget.innerHTML = '';
      let count = 0;
      if(switcher){
        Array.prototype.slice.call(switcher.options).forEach(function(option){
          if(String(option.value) === String(current)) return;
          const opt = document.createElement('option');
          opt.value = option.value;
          opt.textContent = option.textContent;
          copyTarget.appendChild(opt);
          count++;
        });
      }
      if(count === 0){
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = t('ux.copy_no_target', 'No other realm available');
        copyTarget.appendChild(opt);
      }
      if(copyRun) copyRun.disabled = count === 0;
    }

    if(copyOpen && copyPanel){
      copyOpen.addEventListener('click', function(){
        copyPanel.hidden = !copyPanel.hidden;
        if(!copyPanel.hidden) fillTargets();
      });
    }

    if(copyRun && copyPanel){
      copyRun.addEventListener('click', async function(){
        const target = copyTarget ? copyTarget.value : '';
        if(!target){ show('error', t('ux.copy_no_target', 'No other realm available')); return; }

        const targetLabel = copyTarget && copyTarget.selectedIndex >= 0
          ? copyTarget.options[copyTarget.selectedIndex].textContent
          : target;
        const message = t('ux.copy_confirm', 'Copy this realm config to ":server"?').replace(':server', targetLabel);
        if(!window.confirm(message)) return;

        const groupBoxes = Array.prototype.slice.call(copyPanel.querySelectorAll('[data-boss-copy-group]'));
        const allBox = groupBoxes.filter(function(box){ return box.value === 'all'; })[0] || null;
        let groups = [];
        if(!allBox || !allBox.checked){
          groups = groupBoxes.filter(function(box){
            return box.checked && box.value !== 'all';
          }).map(function(box){ return box.value; });
        }
        const includeMain = copyPanel.querySelector('[data-boss-copy-main]');
        copyRun.disabled = true;
        if(copyResult){ copyResult.hidden = false; copyResult.innerHTML = '<span class="muted">' + esc(t('ux.copy_running', 'Copying…')) + '</span>'; }

        const json = await post('/boss/api/ext-config/copy', {
          target_server: target,
          groups: groups,
          include_main_config: includeMain && includeMain.checked ? 1 : 0
        });
        copyRun.disabled = false;

        if(!json || !json.success){
          if(copyResult) copyResult.innerHTML = '<div class="panel-flash panel-flash--error panel-flash--inline is-visible">' + esc((json && json.message) || t('ux.copy_failed', 'Copy failed')) + '</div>';
          show('error', (json && json.message) || t('ux.copy_failed', 'Copy failed'));
          return;
        }

        const result = (json.payload && json.payload.result) || {};
        const lines = [];
        lines.push('<div><strong>' + esc(json.message || '') + '</strong></div>');
        lines.push('<div class="muted">ext ' + esc(result.ext_columns || 0) + ' 列 / main ' + esc(result.main_columns || 0) + ' 列</div>');
        if(result.skipped && result.skipped.length){
          lines.push('<div class="muted">' + esc(t('ux.copy_skipped', 'skipped: ')) + esc(result.skipped.join(', ')) + '</div>');
        }
        if(result.warnings && result.warnings.length){
          lines.push('<div class="muted">' + esc(result.warnings.join(' · ')) + '</div>');
        }
        if(copyResult) copyResult.innerHTML = lines.join('');
        show('success', json.message || t('feedback.success', 'Done'));
      });
    }

    if(simBtn && simBox){
      simBtn.addEventListener('click', async function(){
        simBtn.disabled = true;
        simBox.hidden = false;
        simBox.innerHTML = '<span class="muted">' + esc(t('ux.simulate_running', 'Simulating…')) + '</span>';

        const json = await post('/boss/api/reward-simulate', { rounds: 400 });
        simBtn.disabled = false;

        if(!json || !json.success){
          simBox.innerHTML = '<div class="panel-flash panel-flash--error panel-flash--inline is-visible">' + esc((json && json.message) || t('ux.simulate_failed', 'Simulation failed')) + '</div>';
          return;
        }

        const report = (json.payload && json.payload.report) || {};
        const pools = report.pools || {};
        const rows = [];
        Object.keys(pools).sort(function(a, b){ return Number(a) - Number(b); }).forEach(function(key){
          const pool = pools[key] || {};
          const top = (pool.top_winners || []).slice(0, 3).map(function(winner){
            return esc(winner.name) + ' ' + pct(winner.hits);
          }).join('、');
          rows.push(
            '<tr' + (pool.enabled ? '' : ' class="muted"') + '>'
            + '<td>' + esc(key) + '</td>'
            + '<td>' + (pool.enabled ? '✓' : '—') + '</td>'
            + '<td>' + esc(pool.chance) + '%</td>'
            + '<td>' + esc(pct(pool.trigger_rate)) + '</td>'
            + '<td>' + esc(pool.winner_mode === 'all' ? t('ux.winners_all', 'all') : (pool.winner_count + ' 人')) + '</td>'
            + '<td>' + esc(pool.winners_per_round || 0) + '</td>'
            + '<td>' + (top || '<span class="muted">—</span>') + '</td>'
            + '</tr>'
          );
        });

        const roundLines = [];
        (report.last_round || []).forEach(function(entry){
          if(!entry.triggered || !(entry.grants || []).length){
            return;
          }
          (entry.grants || []).forEach(function(grant){
            roundLines.push('<li>' + esc(t('ux.simulate_pool', 'Pool')) + ' ' + esc(entry.pool) + ' → '
              + esc(grant.name) + '（' + esc(className(grant.class_id)) + '）拿到 '
              + esc(grant.item_id) + ' · ' + esc(grant.item_name) + '</li>');
          });
        });

        const names = (report.participants || []).map(function(member){
          return esc(member.name) + '(' + esc(className(member.class_id)) + ' ' + esc(member.score) + ')';
        }).join('、');

        simBox.innerHTML =
          '<div><strong>' + esc(json.message || '') + '</strong>'
          + ' <span class="muted">' + esc(t('ux.simulate_source', 'roster: ')) + esc(report.participants_source === 'request' ? t('ux.simulate_source_request', 'manual') : t('ux.simulate_source_kill', 'latest kill'))
          + ' · seed ' + esc(report.seed) + '</span></div>'
          + '<div class="muted">' + (names || esc(t('ux.simulate_no_roster', 'No participants to simulate with yet.'))) + '</div>'
          + '<table class="table boss-table"><thead><tr>'
          + '<th>' + esc(t('ux.simulate_col_pool', 'Pool')) + '</th>'
          + '<th>' + esc(t('ux.simulate_col_on', 'On')) + '</th>'
          + '<th>' + esc(t('ux.simulate_col_chance', 'Chance')) + '</th>'
          + '<th>' + esc(t('ux.simulate_col_trigger', 'Trigger rate')) + '</th>'
          + '<th>' + esc(t('ux.simulate_col_mode', 'Winners')) + '</th>'
          + '<th>' + esc(t('ux.simulate_col_avg', 'Avg winners')) + '</th>'
          + '<th>' + esc(t('ux.simulate_col_top', 'Most likely')) + '</th>'
          + '</tr></thead><tbody>' + rows.join('') + '</tbody></table>'
          + '<div class="boss-simulate__round"><strong>' + esc(t('ux.simulate_round', 'This round')) + '</strong>'
          + (roundLines.length ? '<ul>' + roundLines.join('') + '</ul>' : '<div class="muted">' + esc(t('ux.simulate_round_none', 'No pool fired this round.')) + '</div>')
          + '</div>'
          + ((report.notes || []).length ? '<div class="muted">' + esc(report.notes.join(' · ')) + '</div>' : '');
      });
    }
  }

  setupRewardTools();

  /* ---- 「职业过滤映射」自动补全：按奖池物品从核心 item_template 推导职业 ---- */
  function setupClassMapAutofill(){
    const button = document.querySelector('[data-boss-class-autofill]');
    const result = document.querySelector('[data-boss-class-autofill-result]');
    const input = document.querySelector('[data-boss-class-map-input]');
    if(!button || !input) return;

    function classLabel(classId){
      const enums = window.APP_ENUMS || {};
      const classes = enums.classes || {};
      const found = classes[String(classId)];
      if(found && typeof found === 'string') return found;
      if(found && found.name) return found.name;
      return 'Class ' + classId;
    }

    button.addEventListener('click', async function(){
      button.disabled = true;
      const json = await post('/boss/api/class-map/autofill', {});
      button.disabled = false;

      if(!json || !json.success){
        if(result){
          result.hidden = false;
          result.innerHTML = '<div class="panel-flash panel-flash--error panel-flash--inline is-visible">'
            + String((json && json.message) || '').replace(/</g, '&lt;') + '</div>';
        }
        return;
      }

      const payload = json.payload || {};
      const report = payload.report || {};
      input.value = String(payload.text || '');
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));

      if(!result) return;
      const core = (report.from_core || []).map(function(entry){
        return String(entry.id) + '（' + (entry.classes || []).map(classLabel).join('/') + '）';
      });
      const manual = (report.needs_manual || []).map(function(entry){
        return String(entry.id) + ' ' + String(entry.name || '');
      });
      const unknown = (report.unknown_items || []).map(String);

      result.hidden = false;
      result.innerHTML =
        '<div><strong>' + String(json.message || '').replace(/</g, '&lt;') + '</strong></div>'
        + (core.length ? '<div class="muted">' + t('ux.autofill_core', 'Derived from the core: ') + core.join('、') + '</div>' : '')
        + (manual.length ? '<div class="muted">' + t('ux.autofill_manual', 'Needs your call (core lets every class equip it): ') + manual.join('、') + '</div>' : '')
        + (unknown.length ? '<div class="muted">' + t('ux.autofill_unknown', 'Not found in item_template: ') + unknown.join('、') + '</div>' : '')
        + '<div class="muted">' + t('ux.autofill_saved_hint', 'The field is filled in — review it and press “Save extended config” to apply.') + '</div>';
    });
  }

  setupClassMapAutofill();
})();