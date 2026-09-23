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
    statusPanel: document.getElementById('tvStatusPanel'),
    refreshedAt: document.getElementById('tvRefreshedAt'),
    refreshStatus: document.getElementById('tvRefreshStatus'),
    autoRefresh: document.getElementById('tvAutoRefresh'),
    startIndex: document.getElementById('tvStartIndex'),
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
    busy: false
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

  function stateLabel(key) {
    return t('status.states.' + key, key);
  }

  function applyStatus(json) {
    if (!json || !json.success) return;
    const live = json.data || {};
    const available = !!json.available;
    const key = available ? (live.state || 'idle') : 'offline';

    if (dom.statusPanel) {
      dom.statusPanel.dataset.tvState = key;
      dom.statusPanel.dataset.tvAvailable = available ? '1' : '0';
      const badge = dom.statusPanel.querySelector('[data-tv-field="state_label"]');
      if (badge) {
        badge.textContent = stateLabel(key);
        badge.className = 'tv-badge tv-badge--' + key;
      }
    }

    if (!available) return;

    setText('question', live.question || t('status.no_question', '—'));
    setText('answer', live.answer_text ? tt('status.answer_suffix', { answer: live.answer_text }, '(answer: ' + live.answer_text + ')') : '');
    setText('remaining', live.round_active ? '· ' + live.remaining + 's' : '');
    setText('bank', String(live.bank || 0));
    setText('bank_detail', tt('status.bank_detail_template', {
      builtin: live.builtin || 0,
      custom: live.custom || 0,
      db: live.from_db || 0
    }, 'built-in ' + (live.builtin || 0) + ' | file ' + (live.custom || 0) + ' | db ' + (live.from_db || 0)));
    setText('online', String(live.online || 0));
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
        dom.importText.value = String(reader.result || '');
        if (dom.importCommit) dom.importCommit.disabled = true;
        if (dom.importResult) dom.importResult.innerHTML = '';
        previewImport();
      };
      reader.onerror = function () { show('error', t('errors.import_read_failed', 'Failed to read the file.')); };
      reader.readAsText(file, 'utf-8');
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
  startPolling();
  refreshStatus();
})();
