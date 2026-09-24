/**
 * File: public/assets/js/modules/trivia.js
 * Purpose: 聊天答题管理页交互：状态轮询、运行控制、设置保存、题库/预设 CRUD、排行清空。
 */
(function () {
  if (document.body.dataset.module !== 'trivia') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const data = window.TRIVIA_DATA || {};
  const currentServer = new URLSearchParams(window.location.search).get('server') || '';

  const dom = {
    tabsWrap: document.getElementById('tvTabs'),
    statusPanel: document.getElementById('tvStatusPanel'),
    refreshedAt: document.getElementById('tvRefreshedAt'),
    refreshStatus: document.getElementById('tvRefreshStatus'),
    autoRefresh: document.getElementById('tvAutoRefresh'),
    startIndex: document.getElementById('tvStartIndex'),
    pauseToggle: document.getElementById('tvPauseToggle'),
    powerToggle: document.getElementById('tvPowerToggle'),
    scheduleWarn: document.getElementById('tvScheduleWarn'),
    settingsForm: document.getElementById('tvSettingsForm'),
    saveSettings: document.getElementById('tvSaveSettings'),
    channelIds: document.getElementById('tvChannelIds'),
    customChannels: document.getElementById('tvCustomChannels'),
    questionTable: document.getElementById('tvQuestionTable'),
    questionFilters: document.getElementById('tvQuestionFilters'),
    questionForm: document.getElementById('tvQuestionForm'),
    questionFormTitle: document.getElementById('tvQuestionFormTitle'),
    questionSave: document.getElementById('tvQuestionSave'),
    questionCancel: document.getElementById('tvQuestionCancel'),
    questionCancelBottom: document.getElementById('tvQuestionCancelBottom'),
    newQuestion: document.getElementById('tvNewQuestion'),
    qstats: document.querySelector('[data-tv-qstats]'),
    presetTable: document.getElementById('tvPresetTable'),
    presetForm: document.getElementById('tvPresetForm'),
    presetFormTitle: document.getElementById('tvPresetFormTitle'),
    presetSave: document.getElementById('tvPresetSave'),
    presetCancel: document.getElementById('tvPresetCancel'),
    presetCancelBottom: document.getElementById('tvPresetCancelBottom'),
    newPreset: document.getElementById('tvNewPreset'),
    winnerTable: document.getElementById('tvWinnerTable'),
    winnerStats: document.querySelector('[data-tv-wstats]'),
    clearWinners: document.getElementById('tvClearWinners'),
    importFile: document.getElementById('tvImportFile'),
    importText: document.getElementById('tvImportText'),
    importPreview: document.getElementById('tvImportPreview'),
    importCommit: document.getElementById('tvImportCommit'),
    importReset: document.getElementById('tvImportReset'),
    importResult: document.getElementById('tvImportResult')
  };

  const state = {
    questionPage: 1,
    winnerPage: 1,
    search: '',
    status: 'all',
    pollTimer: null,
    busy: false,
    tab: 'status',
    /** 最近一次实时状态（运行控制按钮的文案/可用性依赖它） */
    live: null,
    liveAvailable: false,
    /** 「下一题」倒计时：本地每秒自减，每次轮询再与服务端对齐 */
    countdownKind: 'none',
    countdownAt: 0,
    countdownZeroPinged: false
  };

  function t(path, fallback) {
    if (typeof panel.moduleLocale === 'function') return panel.moduleLocale('trivia', path, fallback);
    return fallback || path;
  }

  // 带占位符的文案：把 :name 替换成实际值（面板的 moduleLocale 不做替换）
  function tt(path, replace, fallback) {
    let text = t(path, fallback);
    if (replace) {
      Object.keys(replace).forEach(function (key) {
        text = text.split(':' + key).join(String(replace[key]));
      });
    }
    return text;
  }

  function withServer(path) {
    if (!currentServer) return path;
    return path + (path.indexOf('?') >= 0 ? '&' : '?') + 'server=' + encodeURIComponent(currentServer);
  }

  /**
   * 固定在右上角的提示区。
   *
   * 题库/奖励预设面板在页面很靠下的位置，行内提示留在页面顶部（运行状态卡下面）时，
   * 在下面点「启用/保存」根本看不到反馈，失败看起来就是「点了没反应」。
   */
  function flashHost() {
    let zone = document.querySelector('.flash-zone');
    if (!zone) {
      zone = document.createElement('div');
      zone.className = 'flash-zone';
      document.body.appendChild(zone);
    }
    return zone;
  }

  function show(type, message) {
    if (feedback && typeof feedback.show === 'function') {
      feedback.show(flashHost(), type, message, { duration: 5000 });
      return;
    }
    const host = flashHost();
    const node = document.createElement('div');
    node.className = 'flash flash-' + type;
    node.textContent = message;
    host.appendChild(node);
    window.setTimeout(function () {
      if (node.parentNode === host) node.remove();
    }, 5000);
  }

  async function request(method, path, payload) {
    const url = withServer(path);
    try {
      if (api && typeof api.get === 'function' && method === 'GET') return await api.get(url, payload || {});
      if (api && typeof api.post === 'function' && method === 'POST') return await api.post(url, payload || {});
      const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: method === 'POST' ? JSON.stringify(payload || {}) : undefined
      });
      return await response.json();
    } catch (error) {
      return { success: false, message: String(error) };
    }
  }

  function setText(name, value) {
    const node = dom.statusPanel ? dom.statusPanel.querySelector('[data-tv-field="' + name + '"]') : null;
    if (node) node.textContent = value;
  }

  /**
   * 「下一题」字段的渲染：暂停/关闭时给出原因，否则显示倒计时。
   *
   * 每秒本地自减，让"到底还有多久出下一题"可见——这正是之前"恢复自动出题点了没反应"
   * 的关键：脚本当时其实已经排好队了，只是面板上没有任何倒计时，看不出还在等。
   */
  function renderCountdown() {
    const live = state.live;
    if (!live || !state.liveAvailable) return;

    if (state.countdownKind === 'none') {
      setText('next', live.enabled === false
        ? t('status.next_disabled', '—')
        : t('status.next_paused', '—'));
      return;
    }

    const left = Math.max(0, Math.round((state.countdownAt - Date.now()) / 1000));
    if (state.countdownKind === 'remaining') {
      setText('next', tt('status.remaining', { seconds: left }, left + 's'));
    } else {
      setText('next', tt('status.next_in', { seconds: left }, left + 's'));
    }

    // 倒计时归零后再拉一次状态：下一题多半已经开始了（轮询间隔 10 秒，等下一轮太迟钝）
    if (left === 0 && !state.countdownZeroPinged) {
      state.countdownZeroPinged = true;
      refreshStatus();
    }
  }

  function startCountdown() {
    window.setInterval(renderCountdown, 1000);
  }

  function stateLabel(key) {
    return t('status.states.' + key, key);
  }

  /**
   * 「暂停/恢复」与「开启/关闭」各自只有一个按钮：按实时状态切换文案与动作。
   *
   * 之前是四个按钮常驻，看不出当前到底是暂停还是恢复、开着还是关着；
   * 系统关闭时点「暂停自动出题」更是完全没有反馈（tick 在 enabled 判定处就返回了，
   * 根本走不到 paused 分支），现在这种无意义的按钮会被禁用并给出原因。
   */
  function applyControls(live, available) {
    const enabled = available ? live.enabled !== false : false;
    const paused = available ? live.paused === true : false;

    if (dom.powerToggle) {
      if (enabled) {
        dom.powerToggle.dataset.tvAction = 'disable';
        dom.powerToggle.textContent = t('actions.disable', 'Disable system');
        dom.powerToggle.classList.add('danger');
        dom.powerToggle.dataset.tvConfirm = t('confirm.disable', '');
        dom.powerToggle.disabled = !available;
      } else {
        dom.powerToggle.dataset.tvAction = 'enable';
        dom.powerToggle.textContent = t('actions.enable', 'Enable system');
        dom.powerToggle.classList.remove('danger');
        dom.powerToggle.dataset.tvConfirm = t('confirm.enable', '');
        dom.powerToggle.disabled = !available;
      }
      if (live.schedule_enabled) {
        dom.powerToggle.title = t('actions.power_hint_scheduled', '');
      } else {
        dom.powerToggle.removeAttribute('title');
      }
    }

    if (dom.pauseToggle) {
      if (paused) {
        dom.pauseToggle.dataset.tvAction = 'resume';
        dom.pauseToggle.textContent = t('actions.resume', 'Resume');
      } else {
        dom.pauseToggle.dataset.tvAction = 'pause';
        dom.pauseToggle.textContent = t('actions.pause', 'Pause');
      }
      // 系统关闭时暂停/恢复没有意义（脚本 tick 会在 enabled 处直接返回）
      dom.pauseToggle.disabled = !available || !enabled;
      if (!enabled && available) {
        dom.pauseToggle.title = t('actions.pause_hint_disabled', '');
      } else {
        dom.pauseToggle.removeAttribute('title');
      }
    }

    if (dom.scheduleWarn) {
      dom.scheduleWarn.hidden = !live.schedule_enabled;
    }
  }

  function applyStatus(json) {
    if (!json || !json.success) return;
    const live = json.data || {};
    const available = !!json.available;
    const key = available ? (live.state || 'idle') : 'offline';

    state.live = live;
    state.liveAvailable = available;

    if (dom.statusPanel) {
      dom.statusPanel.dataset.tvState = key;
      dom.statusPanel.dataset.tvAvailable = available ? '1' : '0';
      const badge = dom.statusPanel.querySelector('[data-tv-field="state_label"]');
      if (badge) {
        badge.textContent = stateLabel(key);
        badge.className = 'tv-badge tv-badge--' + key;
      }
    }

    const tabBadge = document.querySelector('[data-tv-tabstate]');
    if (tabBadge) {
      tabBadge.textContent = stateLabel(key);
      tabBadge.className = 'tv-tab__badge tv-badge tv-badge--' + key;
    }

    if (!available) {
      applyControls({}, false);
      state.countdownKind = 'none';
      state.countdownAt = 0;
      setText('next', '—');
      setText('auto', '—');
      setText('wait_reason', '—');
      setText('online_min', '');
      return;
    }

    applyControls(live, true);

    setText('question', live.question || t('status.no_question', '—'));
    setText('answer', live.answer_text ? tt('status.answer_suffix', { answer: live.answer_text }, '(answer: ' + live.answer_text + ')') : '');
    setText('remaining', live.round_active ? '· ' + tt('status.remaining', { seconds: live.remaining }, live.remaining + 's') : '');

    // 下一题：出题中显示本题剩余作答时间，否则显示倒计时 / 暂停 / 关闭 三种说明
    if (live.round_active) {
      state.countdownKind = 'remaining';
      state.countdownAt = Date.now() + Math.max(0, Number(live.remaining) || 0) * 1000;
    } else if (live.enabled === false || live.paused === true) {
      state.countdownKind = 'none';
      state.countdownAt = 0;
    } else {
      state.countdownKind = 'next';
      state.countdownAt = Date.now() + Math.max(0, Number(live.next_in) || 0) * 1000;
    }
    state.countdownZeroPinged = false;
    renderCountdown();

    // 「自动出题」这一列独立于"有没有题在跑"：出题途中按下暂停也能立刻看到生效
    setText('auto', live.enabled === false
      ? t('status.auto_disabled', 'Off')
      : (live.paused === true ? t('status.auto_paused', 'Paused') : t('status.auto_on', 'On')));

    // 定时计划
    if (live.schedule_enabled) {
      setText('schedule_state', live.schedule_active
        ? t('status.schedule_active', 'Active')
        : t('status.schedule_waiting', 'Waiting'));
      const schedBadge = dom.statusPanel ? dom.statusPanel.querySelector('[data-tv-field="schedule_state"]') : null;
      if (schedBadge) {
        schedBadge.className = 'tv-badge tv-badge--' + (live.schedule_active ? 'running' : 'paused');
      }
      const parts = [];
      if (live.schedule_valid) {
        parts.push(String(live.schedule_windows || ''));
        if (live.schedule_next_change_text) {
          parts.push(tt('status.schedule_next_change', { time: live.schedule_next_change_text }, ''));
        }
      } else {
        parts.push(t('status.schedule_no_windows', ''));
      }
      setText('schedule_detail', parts.filter(Boolean).join(' · '));
    } else {
      setText('schedule_state', t('status.schedule_off', 'Off'));
      const schedBadge = dom.statusPanel ? dom.statusPanel.querySelector('[data-tv-field="schedule_state"]') : null;
      if (schedBadge) {
        schedBadge.className = 'tv-badge tv-badge--muted';
      }
      setText('schedule_detail', t('status.schedule_hint_off', ''));
    }

    setText('bank', String(live.bank || 0));
    setText('bank_detail', tt('status.bank_detail_template', {
      builtin: live.builtin || 0,
      custom: live.custom || 0,
      db: live.from_db || 0
    }, 'built-in ' + (live.builtin || 0) + ' | file ' + (live.custom || 0) + ' | db ' + (live.from_db || 0)));
    setText('online', String(live.online || 0));
    // 脚本的未出题原因（在线人数不足 / 题库为空 / 已暂停 / 不在计划时段…），面板靠它解释"为什么一直空转"
    setText('wait_reason', live.wait_reason_text || '—');
    setText('online_min', typeof live.min_players_online === 'number'
      ? tt('status.online_min', { min: live.min_players_online }, '')
      : '');
    setText('rounds', String(live.rounds || 0));
    setText('sources', live.sources || '-');
    setText('labels', live.labels || '-');
    setText('interval', String(live.interval || 0));
    setText('answer_seconds', String(live.answer_seconds || 0));
    setText('presets', String(live.presets || 0));
  }

  async function refreshStatus() {
    const json = await request('GET', data.statusUrl || '/trivia/api/status');
    applyStatus(json);
    if (dom.refreshedAt) {
      dom.refreshedAt.textContent = t('status.refreshed', 'Refreshed') + ' ' + new Date().toLocaleTimeString();
    }  }

  function startPolling() {
    if (state.pollTimer) window.clearInterval(state.pollTimer);
    const seconds = Math.max(5, parseInt(data.pollSeconds, 10) || 10);
    if (dom.autoRefresh && !dom.autoRefresh.checked) return;
    state.pollTimer = window.setInterval(refreshStatus, seconds * 1000);
  }

  async function runAction(action, extra) {
    if (state.busy) return;
    state.busy = true;
    const payload = Object.assign({ action: action }, extra || {});
    const json = await request('POST', data.actionUrl || '/trivia/api/action', payload);
    state.busy = false;

    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.command_failed', 'Command failed.'));
      return;
    }
    show('success', json.message || t('feedback.action_done', 'Done.'));
    if (json.payload && json.payload.status) applyStatus(Object.assign({ success: true }, json.payload.status));
    else refreshStatus();
  }

  async function reloadQuestions() {
    const params = new URLSearchParams();
    params.set('page', String(state.questionPage));
    params.set('search', state.search);
    params.set('status', state.status);
    const json = await request('GET', (data.questionsUrl || '/trivia/api/questions') + '?' + params.toString());
    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.load_failed', 'Load failed.'));
      return;
    }
    if (dom.questionTable) dom.questionTable.innerHTML = json.html || '';
    if (dom.qstats && json.stats) {
      dom.qstats.textContent = 'enabled ' + (json.stats.enabled || 0) + ' | disabled ' + (json.stats.disabled || 0) + ' | reward ' + (json.stats.with_reward || 0);
    }
  }

  async function reloadPresets() {
    const json = await request('GET', data.presetsUrl || '/trivia/api/presets');
    if (!json || !json.success) return;
    if (dom.presetTable) dom.presetTable.innerHTML = json.html || '';
  }

  async function reloadWinners() {
    const params = new URLSearchParams();
    params.set('winner_page', String(state.winnerPage));
    const json = await request('GET', (data.winnersUrl || '/trivia/api/winners') + '?' + params.toString());
    if (!json || !json.success) return;
    if (dom.winnerTable) dom.winnerTable.innerHTML = json.html || '';
    if (dom.winnerStats && json.stats) {
      dom.winnerStats.textContent = 'players ' + (json.stats.players || 0) + ' / wins ' + (json.stats.wins || 0);
    }
  }

  function syncChannelIds() {
    if (!dom.channelIds) return;
    const values = [];
    document.querySelectorAll('[data-tv-channels] input[type="checkbox"]:checked').forEach(function (node) {
      values.push(String(node.value));
    });
    const custom = (dom.customChannels && dom.customChannels.value ? dom.customChannels.value : '')
      .split(',')
      .map(function (v) { return v.trim(); })
      .filter(function (v) { return /^-?\d+$/.test(v); });
    custom.forEach(function (v) {
      if (values.indexOf(v) < 0) values.push(v);
    });
    dom.channelIds.value = values.join(',');
  }

  function settingsPayload() {
    if (!dom.settingsForm) return {};
    syncChannelIds();
    const payload = {};
    dom.settingsForm.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (node) {
      if (node.name.indexOf('tv_') === 0) return;
      if (node.type === 'checkbox') {
        payload[node.name] = node.checked ? 1 : 0;
      } else {
        payload[node.name] = node.value;
      }
    });
    return payload;
  }

  async function saveSettings() {
    if (state.busy) return;
    state.busy = true;
    if (dom.saveSettings) dom.saveSettings.disabled = true;
    const json = await request('POST', data.settingsUrl || '/trivia/api/settings', settingsPayload());
    state.busy = false;
    if (dom.saveSettings) dom.saveSettings.disabled = false;

    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.settings_save_failed', 'Save failed.'));
      return;
    }
    show('success', json.message || t('feedback.settings_saved', 'Saved.'));
    if (json.payload && json.payload.status) applyStatus(Object.assign({ success: true }, json.payload.status));
  }

  /**
   * 把编辑表单所在面板的标题栏滚到视口顶部。
   *
   * 不能用 scrollIntoView({block:'center'})：编辑表单比很多笔记本视口还高（约 520-670px），
   * 居中会把表单自己的标题与「取消」按钮推到视口上边缘之外，看起来就是"顶部被覆盖、没有返回按钮"。
   * 标题栏对齐到顶部则始终能看到「题库 / 奖励预设」上下文，表单头部紧跟在下面。
   */
  function revealEditorPanel(form) {
    if (!form) return;
    const panel = form.closest('.tv-panel') || form;
    const head = panel.querySelector(':scope > .tv-panel__head') || form;
    head.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function openQuestionForm(row) {
    if (!dom.questionForm) return;
    dom.questionForm.hidden = false;
    dom.questionForm.classList.add('is-open');
    dom.questionForm.querySelector('[name="id"]').value = row && row.id ? row.id : 0;
    dom.questionForm.querySelector('[name="question"]').value = row ? row.question || '' : '';
    [1, 2, 3, 4].forEach(function (index) {
      dom.questionForm.querySelector('[name="option' + index + '"]').value = row ? row['option' + index] || '' : '';
    });
    dom.questionForm.querySelector('[name="answer_index"]').value = String(row ? row.answer_index || 1 : 1);
    dom.questionForm.querySelector('[name="labels"]').value = row ? row.labels || '' : '';
    dom.questionForm.querySelector('[name="reward_preset"]').value = row ? row.reward_preset || '' : '';
    dom.questionForm.querySelector('[name="reward_items"]').value = row ? row.reward_items || '' : '';
    dom.questionForm.querySelector('[name="reward_money"]').value = String(row ? row.reward_money || 0 : 0);
    dom.questionForm.querySelector('[name="sort_order"]').value = String(row ? row.sort_order || 0 : 0);
    // 表单里每个复选框前面都有一个 hidden 0，这里必须显式取 checkbox，否则会命中那个 hidden
    dom.questionForm.querySelector('[name="enabled"][type="checkbox"]').checked = row ? !!row.enabled : true;
    if (dom.questionFormTitle) {
      dom.questionFormTitle.textContent = row && row.id
        ? tt('actions.edit_title', { id: row.id }, t('actions.edit', 'Edit') + ' #' + row.id)
        : t('actions.new_question', 'New question');
    }
    // 先聚焦再滚动：preventScroll 避免焦点抢走平滑滚动的位置
    const questionField = dom.questionForm.querySelector('[name="question"]');
    if (questionField) {
      try { questionField.focus({ preventScroll: true }); } catch (error) { questionField.focus(); }
    }
    revealEditorPanel(dom.questionForm);
  }

  function closeQuestionForm() {
    if (!dom.questionForm) return;
    dom.questionForm.hidden = true;
    dom.questionForm.classList.remove('is-open');
    revealEditorPanel(dom.questionForm);
  }

  async function saveQuestion() {
    if (!dom.questionForm || state.busy) return;
    state.busy = true;
    if (dom.questionSave) dom.questionSave.disabled = true;
    const payload = {};
    new FormData(dom.questionForm).forEach(function (value, key) { payload[key] = value; });
    payload.enabled = dom.questionForm.querySelector('[name="enabled"][type="checkbox"]').checked ? 1 : 0;

    const json = await request('POST', data.questionSaveUrl || '/trivia/api/question/save', payload);
    state.busy = false;
    if (dom.questionSave) dom.questionSave.disabled = false;

    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.question_save_failed', 'Save failed.'));
      return;
    }
    show('success', json.message || t('feedback.question_saved', 'Saved.'));
    closeQuestionForm();
    await reloadQuestions();
    await reloadPresets();
  }

  async function deleteQuestion(id) {
    const json = await request('POST', data.questionDeleteUrl || '/trivia/api/question/delete', { id: id });
    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.question_not_found', 'Not found.'));
      return;
    }
    show('success', json.message || t('feedback.question_deleted', 'Deleted.'));
    await reloadQuestions();
  }

  async function toggleQuestion(id, enabled) {
    // 必须发 1/0：Panel.api.post 会把普通对象转成 FormData，JS 布尔值到服务端就是字符串
    // "true"/"false"，而服务端只认 1（'true' 会被当成 false），结果是"停用"能用、"启用"无效。
    const json = await request('POST', data.questionToggleUrl || '/trivia/api/question/toggle',
      { id: id, enabled: enabled ? 1 : 0 });
    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.question_not_found', 'Not found.'));
      return;
    }
    show('success', json.message || t('feedback.question_enabled', 'Updated.'));
    await reloadQuestions();
  }

  function openPresetForm(row) {
    if (!dom.presetForm) return;
    dom.presetForm.hidden = false;
    dom.presetForm.classList.add('is-open');
    dom.presetForm.querySelector('[name="name"]').value = row ? row.name || '' : '';
    dom.presetForm.querySelector('[name="original_name"]').value = row ? row.name || '' : '';
    dom.presetForm.querySelector('[name="items"]').value = row ? row.items || '' : '';
    dom.presetForm.querySelector('[name="money"]').value = String(row ? row.money || 0 : 0);
    dom.presetForm.querySelector('[name="enabled"][type="checkbox"]').checked = row ? !!row.enabled : true;
    if (dom.presetFormTitle) {
      dom.presetFormTitle.textContent = row && row.name
        ? tt('actions.edit_preset_title', { name: row.name }, t('actions.edit', 'Edit') + ' ' + row.name)
        : t('actions.new_preset', 'New preset');
    }
    const nameField = dom.presetForm.querySelector('[name="name"]');
    if (nameField) {
      try { nameField.focus({ preventScroll: true }); } catch (error) { nameField.focus(); }
    }
    revealEditorPanel(dom.presetForm);
  }

  function closePresetForm() {
    if (!dom.presetForm) return;
    dom.presetForm.hidden = true;
    dom.presetForm.classList.remove('is-open');
    revealEditorPanel(dom.presetForm);
  }

  async function savePreset() {
    if (!dom.presetForm || state.busy) return;
    state.busy = true;
    if (dom.presetSave) dom.presetSave.disabled = true;
    const payload = {};
    new FormData(dom.presetForm).forEach(function (value, key) { payload[key] = value; });
    payload.enabled = dom.presetForm.querySelector('[name="enabled"][type="checkbox"]').checked ? 1 : 0;

    const json = await request('POST', data.presetSaveUrl || '/trivia/api/preset/save', payload);
    state.busy = false;
    if (dom.presetSave) dom.presetSave.disabled = false;

    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.preset_save_failed', 'Save failed.'));
      return;
    }
    show('success', json.message || t('feedback.preset_saved', 'Saved.'));
    closePresetForm();
    await reloadPresets();
  }

  async function deletePreset(name) {
    const json = await request('POST', data.presetDeleteUrl || '/trivia/api/preset/delete', { name: name });
    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.preset_not_found', 'Not found.'));
      return;
    }
    show('success', json.message || t('feedback.preset_deleted', 'Deleted.'));
    await reloadPresets();
  }

  async function clearWinners() {
    const json = await request('POST', data.winnersClearUrl || '/trivia/api/winners/clear', {});
    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.winners_clear_failed', 'Failed.'));
      return;
    }
    show('success', json.message || t('feedback.winners_cleared', 'Cleared.'));
    await reloadWinners();
  }

  // ---------------------------------------------------------------- 模板导入
  function renderImportResult(json) {
    if (!dom.importResult) return;
    dom.importResult.innerHTML = (json && json.html) || '';
    if (dom.importCommit) dom.importCommit.disabled = !(json && json.can_commit);
  }

  async function previewImport() {
    if (!dom.importText) return;
    const template = dom.importText.value || '';
    if (template.trim() === '') {
      show('error', t('errors.import_empty', 'Empty template.'));
      return;
    }

    if (dom.importPreview) dom.importPreview.disabled = true;
    const json = await request('POST', data.importUrl || '/trivia/api/questions/import', { mode: 'preview', template: template });
    if (dom.importPreview) dom.importPreview.disabled = false;

    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.import_failed', 'Parse failed.'));
      return;
    }

    renderImportResult(json);
    show('success', tt('import.preview_ok', { valid: json.valid || 0, invalid: json.invalid || 0 },
      'Parsed ' + (json.valid || 0) + ' valid / ' + (json.invalid || 0) + ' invalid'));
  }

  async function commitImport() {
    if (!dom.importText) return;
    const template = dom.importText.value || '';
    if (template.trim() === '') {
      show('error', t('errors.import_empty', 'Empty template.'));
      return;
    }
    const confirmText = t('confirm.import', 'Import these questions into the database?');
    if (confirmText && !window.confirm(confirmText)) return;

    if (dom.importCommit) dom.importCommit.disabled = true;
    const json = await request('POST', data.importUrl || '/trivia/api/questions/import', { mode: 'commit', template: template });

    if (!json || !json.success) {
      show('error', (json && json.message) || t('errors.import_failed', 'Import failed.'));
      if (dom.importCommit) dom.importCommit.disabled = false;
      return;
    }

    show('success', json.message || tt('feedback.import_done', { inserted: json.inserted || 0, skipped: json.skipped || 0 },
      'Imported ' + (json.inserted || 0) + ' questions.'));
    if (dom.importResult) dom.importResult.innerHTML = '';
    await reloadQuestions();
    await previewImport();   // 再跑一次预览，把剩下的错误显示出来
  }

  // ---------------------------------------------------------------- 事件绑定
  document.addEventListener('click', function (event) {
    const actionBtn = event.target.closest('[data-tv-action]');
    if (actionBtn && data.canControl) {
      const action = actionBtn.dataset.tvAction;
      if (actionBtn.dataset.tvConfirm && !window.confirm(actionBtn.dataset.tvConfirm)) return;
      const extra = {};
      if (action === 'start_index') {
        const index = parseInt(dom.startIndex && dom.startIndex.value, 10);
        if (!index || index <= 0) {
          show('error', t('errors.index_required', 'Enter a bank index greater than 0.'));
          return;
        }
        extra.index = index;
      }
      runAction(action === 'start_index' ? 'start' : action, extra);
      return;
    }

    const editBtn = event.target.closest('[data-tv-question-edit]');
    if (editBtn) {
      try {
        openQuestionForm(JSON.parse(editBtn.dataset.tvQuestion || '{}'));
      } catch (error) {
        show('error', String(error));
      }
      return;
    }

    const toggleBtn = event.target.closest('[data-tv-question-toggle]');
    if (toggleBtn) {
      toggleQuestion(parseInt(toggleBtn.dataset.tvId, 10), toggleBtn.dataset.tvEnabled === '1');
      return;
    }

    const deleteBtn = event.target.closest('[data-tv-question-delete]');
    if (deleteBtn) {
      if (deleteBtn.dataset.tvConfirm && !window.confirm(deleteBtn.dataset.tvConfirm)) return;
      deleteQuestion(parseInt(deleteBtn.dataset.tvId, 10));
      return;
    }

    const pageBtn = event.target.closest('[data-tv-question-page]');
    if (pageBtn) {
      state.questionPage = parseInt(pageBtn.dataset.tvQuestionPage, 10) || 1;
      reloadQuestions();
      return;
    }

    const winnerPageBtn = event.target.closest('[data-tv-winner-page]');
    if (winnerPageBtn) {
      state.winnerPage = parseInt(winnerPageBtn.dataset.tvWinnerPage, 10) || 1;
      reloadWinners();
      return;
    }

    const presetEdit = event.target.closest('[data-tv-preset-edit]');
    if (presetEdit) {
      try {
        openPresetForm(JSON.parse(presetEdit.dataset.tvPreset || '{}'));
      } catch (error) {
        show('error', String(error));
      }
      return;
    }

    const presetDelete = event.target.closest('[data-tv-preset-delete]');
    if (presetDelete) {
      if (presetDelete.dataset.tvConfirm && !window.confirm(presetDelete.dataset.tvConfirm)) return;
      deletePreset(presetDelete.dataset.tvName || '');
      return;
    }

    if (dom.clearWinners && event.target.closest('#tvClearWinners')) {
      if (dom.clearWinners.dataset.tvConfirm && !window.confirm(dom.clearWinners.dataset.tvConfirm)) return;
      clearWinners();
    }
  });

  // ---------------------------------------------------------------- Tab 分页
  function availableTabs() {
    const names = [];
    document.querySelectorAll('[data-tv-tabpanel]').forEach(function (node) {
      names.push(node.dataset.tvTabpanel);
    });
    return names;
  }

  /**
   * 切换 Tab 并把当前 Tab 记到 URL hash（刷新/换服后回到同一页）。
   *
   * 用 replaceState 而不是 location.hash = …，避免浏览器把每次点 Tab 都塞进历史记录
   * （在标签页之间来回点要按很多次"后退"才能离开这个页面）。
   */
  function activateTab(name, remember) {
    const names = availableTabs();
    if (names.indexOf(name) < 0) name = names[0] || 'status';
    state.tab = name;

    document.querySelectorAll('[data-tv-tab]').forEach(function (node) {
      const active = node.dataset.tvTab === name;
      node.classList.toggle('is-active', active);
      node.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-tv-tabpanel]').forEach(function (node) {
      node.classList.toggle('is-active', node.dataset.tvTabpanel === name);
    });

    if (remember) {
      const url = new URL(window.location.href);
      url.hash = 'tab=' + name;
      window.history.replaceState(null, '', url.toString());
    }
  }

  function initialTab() {
    const match = /(?:^|[#&])tab=([a-z_]+)/.exec(window.location.hash || '');
    if (match && availableTabs().indexOf(match[1]) >= 0) return match[1];
    return data.defaultTab || 'status';
  }

  if (dom.tabsWrap) {
    dom.tabsWrap.addEventListener('click', function (event) {
      const tab = event.target.closest('[data-tv-tab]');
      if (tab) activateTab(tab.dataset.tvTab, true);
    });

    window.addEventListener('hashchange', function () {
      activateTab(initialTab(), false);
    });
  }

  if (dom.refreshStatus) {
    dom.refreshStatus.addEventListener('click', refreshStatus);
  }

  if (dom.autoRefresh) {
    dom.autoRefresh.addEventListener('change', function () {
      if (dom.autoRefresh.checked) startPolling();
      else if (state.pollTimer) window.clearInterval(state.pollTimer);
    });
  }

  if (dom.settingsForm) {
    dom.settingsForm.addEventListener('submit', function (event) {
      event.preventDefault();
      saveSettings();
    });
  }

  if (dom.customChannels) {
    dom.customChannels.addEventListener('change', syncChannelIds);
  }

  if (dom.newQuestion) {
    dom.newQuestion.addEventListener('click', function () { openQuestionForm(null); });
  }

  if (dom.questionCancel) {
    dom.questionCancel.addEventListener('click', closeQuestionForm);
  }

  if (dom.questionCancelBottom) {
    dom.questionCancelBottom.addEventListener('click', closeQuestionForm);
  }

  if (dom.questionForm) {
    dom.questionForm.addEventListener('submit', function (event) {
      event.preventDefault();
      saveQuestion();
    });
  }

  if (dom.questionFilters) {
    dom.questionFilters.addEventListener('submit', function (event) {
      event.preventDefault();
      const formData = new FormData(dom.questionFilters);
      state.search = String(formData.get('search') || '');
      state.status = String(formData.get('status') || 'all');
      state.questionPage = 1;
      reloadQuestions();
    });
  }

  if (dom.newPreset) {
    dom.newPreset.addEventListener('click', function () { openPresetForm(null); });
  }

  if (dom.presetCancel) {
    dom.presetCancel.addEventListener('click', closePresetForm);
  }

  if (dom.presetCancelBottom) {
    dom.presetCancelBottom.addEventListener('click', closePresetForm);
  }

  if (dom.presetForm) {
    dom.presetForm.addEventListener('submit', function (event) {
      event.preventDefault();
      savePreset();
    });
  }

  // ------------------------------------------------------------------ 导入文件解码
  // 浏览器 readAsText(file, 'utf-8') 会把非 UTF-8 的字节直接替换成 U+FFFD（�），
  // 这一步是不可逆的：中文 Windows 上 Excel「另存为 CSV」写的是 ANSI/GB18030，
  // 「Unicode 文本」写的是 UTF-16，两者都会在预览框里变成一堆 �。
  // 所以按字节读进来，自己判断编码再解码，解出来的是正确的字符串（提交时就是 UTF-8 了）。
  function decodeImportBytes(buffer) {
    const bytes = new Uint8Array(buffer);
    const decode = function (label, fatal) {
      try {
        return new TextDecoder(label, { fatal: !!fatal }).decode(bytes);
      } catch (error) {
        return null;
      }
    };

    if (typeof TextDecoder !== 'function') return null;

    // 1) BOM 最可靠（UTF-16 的 ASCII 部分含 NUL，不能靠"是不是合法 UTF-8"来判断）
    if (bytes.length >= 2 && bytes[0] === 0xFF && bytes[1] === 0xFE) return decode('utf-16le');
    if (bytes.length >= 2 && bytes[0] === 0xFE && bytes[1] === 0xFF) return decode('utf-16be');

    // 2) 严格 UTF-8：成功说明本来就是 UTF-8
    const utf8 = decode('utf-8', true);
    if (utf8 !== null) return utf8;

    // 3) 中文 Windows 的 Excel ANSI 另存 = GB18030（GBK 的超集）
    const gb = decode('gb18030') || decode('gbk');
    if (gb !== null) return gb;

    // 4) 兜底：宽松 UTF-8（把坏字节替换掉，至少能预览出问题在哪）
    return decode('utf-8');
  }

  if (dom.importFile && dom.importText) {
    dom.importFile.addEventListener('change', function () {
      const file = dom.importFile.files && dom.importFile.files[0];
      if (!file) return;
      if (file.size > 1024 * 1024) {
        show('error', t('errors.import_too_large', 'Template is too large (max 1 MB).'));
        return;
      }
      const reader = new FileReader();
      reader.onload = function () {
        const decoded = decodeImportBytes(reader.result);
        dom.importText.value = decoded === null ? String(reader.result || '') : decoded;
        if (dom.importCommit) dom.importCommit.disabled = true;
        if (dom.importResult) dom.importResult.innerHTML = '';
        previewImport();
      };
      reader.onerror = function () { show('error', t('errors.import_read_failed', 'Failed to read the file.')); };
      // 按字节读：编码由 decodeImportBytes 判断（readAsText 的默认行为会把 GBK/UTF-16 弄坏）
      if (typeof TextDecoder === 'function') {
        reader.readAsArrayBuffer(file);
      } else {
        reader.readAsText(file, 'utf-8');
      }
    });
  }

  if (dom.importPreview) {
    dom.importPreview.addEventListener('click', previewImport);
  }

  if (dom.importCommit) {
    dom.importCommit.addEventListener('click', commitImport);
  }

  if (dom.importReset) {
    dom.importReset.addEventListener('click', function () {
      if (dom.importText) dom.importText.value = '';
      if (dom.importFile) dom.importFile.value = '';
      if (dom.importResult) dom.importResult.innerHTML = '';
      if (dom.importCommit) dom.importCommit.disabled = true;
    });
  }

  syncChannelIds();
  activateTab(initialTab(), false);
  startPolling();
  startCountdown();
  refreshStatus();
})();
