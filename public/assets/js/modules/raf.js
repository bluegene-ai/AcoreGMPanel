(function(){
  if(document.body.dataset.module !== 'raf') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const modal = window.Modal || null;
  const capabilities = window.PANEL_CAPABILITIES || {};
  const page = document.querySelector('.raf-page');

  const dom = {
    feedback: document.getElementById('rafFeedback'),
    bindBtn: document.getElementById('rafBindBtn'),
    stats: document.querySelector('.raf-stats'),
    bindingsSection: document.querySelector('[data-raf-section="bindings"]'),
    rewardLogSection: document.querySelector('[data-raf-section="reward-log"]')
  };

  const state = {
    card: null,
    cardPage: 1
  };

  const SECTION_ENDPOINT = {
    'bindings': '/raf/api/bindings',
    'reward-log': '/raf/api/reward-logs'
  };

  function t(path, fallback){
    if(typeof panel.moduleLocale === 'function') return panel.moduleLocale('raf', path, fallback);
    return fallback || path;
  }

  function esc(value){
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(char){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }

  function can(key){
    return capabilities[key] !== false;
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

  function sectionEl(group){
    return group === 'reward-log' ? dom.rewardLogSection : dom.bindingsSection;
  }

  function formEl(group){
    const section = sectionEl(group);
    return section ? section.querySelector('[data-raf-form="' + group + '"]') : null;
  }

  function resultsEl(group){
    const section = sectionEl(group);
    return section ? section.querySelector('[data-raf-results="' + group + '"]') : null;
  }

  function sectionQuery(group){
    const form = formEl(group);
    const query = new URLSearchParams();
    if(!form){
      return query;
    }

    new FormData(form).forEach(function(value, key){
      const text = String(value == null ? '' : value).trim();
      if(text !== ''){
        query.set(key, text);
      }
    });

    return query;
  }

  function currentServer(){
    const query = new URLSearchParams(window.location.search);
    return query.get('server') || '';
  }

  function withServer(path, query){
    const params = query instanceof URLSearchParams ? query : new URLSearchParams(query || '');
    const server = currentServer();
    if(server && !params.has('server')){
      params.set('server', server);
    }

    const qs = params.toString();
    return path + (qs ? '?' + qs : '');
  }

  async function getJson(path, query){
    const url = withServer(path, query);
    if(api && typeof api.get === 'function'){
      return api.get(url);
    }

    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
    return response.json();
  }

  async function getJsonSafe(path, query){
    try{
      return await getJson(path, query);
    }catch(error){
      console.warn('RAF request failed', error);
      return { success: false, message: t('errors.request_failed', 'Request failed.') };
    }
  }

  function setLoading(group, loading){
    const section = sectionEl(group);
    if(!section) return;

    const submit = section.querySelector('[data-raf-submit]');
    if(submit){
      submit.disabled = loading;
      submit.classList.toggle('is-loading', loading);
    }
    section.classList.toggle('is-loading', loading);
  }

  // 用新片段替换区块，并把服务端返回的统计值同步到顶部卡片
  function applySection(group, json){
    const section = sectionEl(group);
    if(!section || !json || !json.html){
      return false;
    }

    section.outerHTML = json.html;
    refreshSectionRefs();
    syncStats(json.stats || {});
    if(group === 'bindings'){
      bindActionButtons();
    }

    return true;
  }

  function refreshSectionRefs(){
    dom.bindingsSection = document.querySelector('[data-raf-section="bindings"]');
    dom.rewardLogSection = document.querySelector('[data-raf-section="reward-log"]');
    dom.bindBtn = document.getElementById('rafBindBtn');
  }

  function statNode(group, key){
    return document.querySelector('[data-raf-stat="' + group + '.' + key + '"]');
  }

  function setStat(group, key, value, textValue){
    const node = statNode(group, key);
    if(!node) return;

    // 时间型卡片必须优先使用服务端格式化好的文案：
    // 前端按时间戳自行格式化会使用浏览器时区，与面板时区不一致
    if(node.getAttribute('data-raf-stat-format') === 'time'){
      node.textContent = (textValue == null || textValue === '') ? '-' : String(textValue);
      return;
    }

    node.textContent = String(value == null ? 0 : value);
  }

  function syncStats(stats){
    const bindings = stats.bindings;
    if(bindings && typeof bindings === 'object'){
      Object.keys(bindings).forEach(function(key){ setStat('bindings', key, bindings[key]); });
    }

    const rewardLog = stats.reward_log;
    if(rewardLog && typeof rewardLog === 'object'){
      const keyMap = { latest_granted_at: 'latest' };
      Object.keys(rewardLog).forEach(function(key){
        // 只处理数值字段，*_text 作为时间卡片的展示文案
        if(key.slice(-5) === '_text') return;
        setStat('reward-log', keyMap[key] || key, rewardLog[key], rewardLog[key + '_text']);
      });
    }
  }

  async function loadSection(group, extraParams){
    const query = sectionQuery(group);
    if(extraParams && typeof extraParams === 'object'){
      Object.keys(extraParams).forEach(function(key){
        query.set(key, extraParams[key]);
      });
    }

    setLoading(group, true);
    const json = await getJsonSafe(SECTION_ENDPOINT[group], query);
    setLoading(group, false);

    if(!json || !json.success){
      show('error', (json && json.message) || t('action_failure', 'Action failed.'));
      return false;
    }

    if(!applySection(group, json)){
      show('error', t('action_failure', 'Action failed.'));
      return false;
    }

    return true;
  }

  function updateUrl(group){
    const query = sectionQuery(group);
    query.delete('page');
    query.delete('log_page');
    const params = new URLSearchParams(window.location.search);
    if(params.get('server')){
      query.set('server', params.get('server'));
    }

    const url = window.location.pathname + (query.toString() ? '?' + query.toString() : '');
    window.history.replaceState(null, '', url);
  }

  async function submitSection(group, options){
    const opts = options || {};
    const ok = await loadSection(group, opts.extraParams);
    if(ok && opts.pushUrl !== false){
      updateUrl(group);
    }
    return ok;
  }

  // ---- 筛选表单 / 分页：局部刷新，不再整页跳转 ----
  function bindSectionInteractions(){
    if(!page) return;

    page.addEventListener('submit', function(event){
      const form = event.target.closest('[data-raf-form]');
      if(!form) return;
      event.preventDefault();
      submitSection(form.getAttribute('data-raf-form'));
    });

    page.addEventListener('click', function(event){
      const reset = event.target.closest('[data-raf-reset]');
      if(reset){
        event.preventDefault();
        const form = reset.closest('[data-raf-form]');
        if(!form) return;
        form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]').forEach(function(input){
          input.value = '';
        });
        form.querySelectorAll('select').forEach(function(select){
          select.selectedIndex = 0;
        });
        submitSection(form.getAttribute('data-raf-form'));
        return;
      }

      const toggle = event.target.closest('.js-raf-toggle-filters');
      if(toggle){
        event.preventDefault();
        const target = document.querySelector(toggle.getAttribute('data-raf-target'));
        if(!target) return;
        const collapsed = target.hasAttribute('hidden');
        if(collapsed){
          target.removeAttribute('hidden');
        }else{
          target.setAttribute('hidden', 'hidden');
        }
        toggle.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
        return;
      }

      const pageBtn = event.target.closest('[data-raf-page]');
      if(pageBtn){
        event.preventDefault();
        const group = pageBtn.getAttribute('data-raf-page');
        const key = pageBtn.getAttribute('data-raf-page-key') || (group === 'reward-log' ? 'log_page' : 'page');
        const target = pageBtn.getAttribute('data-raf-page-target');
        const extra = {};
        extra[key] = target;
        submitSection(group, { extraParams: extra });
      }
    });
  }

  // ---- 统计卡下钻弹窗 ----
  function closeCardModal(){
    if(modal && typeof modal.hide === 'function') modal.hide('raf-card-detail');
    state.card = null;
  }

  function displayValue(json){
    if(json && json.value_text != null && json.value_text !== ''){
      return json.value_text;
    }

    return json ? json.value : '';
  }

  function modalBodyHtml(json, pageNumber){
    const chunks = [];
    chunks.push('<div class="raf-card-detail">');
    chunks.push('  <p class="raf-card-detail__meta">');
    chunks.push('    <span class="raf-card-detail__value">' + esc(displayValue(json)) + '</span>');
    if(json.hint){
      chunks.push('    <span class="raf-card-detail__hint">' + esc(json.hint) + '</span>');
    }
    chunks.push('  </p>');

    if(json.html){
      chunks.push('  <div class="raf-table-wrap raf-table-wrap--modal">' + json.html + '</div>');
    }else{
      chunks.push('  <p class="muted raf-card-detail__empty">' + esc(json.empty || t('empty', 'No data.')) + '</p>');
    }

    const total = parseInt(json.total, 10) || 0;
    const shown = parseInt(json.shown, 10) || 0;
    const pages = Math.max(1, Math.ceil(total / Math.max(shown, 1)));
    if(pages > 1){
      chunks.push('  <div class="raf-card-detail__pager">');
      chunks.push('    <button type="button" class="btn outline btn-sm" data-raf-card-page="' + Math.max(1, pageNumber - 1) + '"'
        + (pageNumber <= 1 ? ' disabled' : '') + '>' + esc(t('card_prev', 'Previous')) + '</button>');
      chunks.push('    <span class="raf-pagination__label">' + esc(t('card_page_of', 'Page :page / :pages')
        .replace(':page', String(pageNumber)).replace(':pages', String(pages))) + '</span>');
      chunks.push('    <button type="button" class="btn outline btn-sm" data-raf-card-page="' + Math.min(pages, pageNumber + 1) + '"'
        + (pageNumber >= pages ? ' disabled' : '') + '>' + esc(t('card_next', 'Next')) + '</button>');
      chunks.push('  </div>');
    }

    chunks.push('</div>');
    return chunks.join('\n');
  }

  function modalFooterHtml(json){
    const actions = Array.isArray(json.actions) ? json.actions : [];
    const chunks = ['<button type="button" class="btn outline" data-raf-card-close>'
      + esc(t('cancel', 'Close')) + '</button>'];

    actions.forEach(function(action){
      if(!action || !action.href) return;
      chunks.push('<a class="btn" href="' + esc(action.href) + '">' + esc(action.label) + '</a>');
    });

    return chunks.join('');
  }

  async function openCard(card){
    if(!modal || typeof modal.show !== 'function') return;
    if(card.disabled) return;

    const group = card.getAttribute('data-raf-card-group');
    const key = card.getAttribute('data-raf-card-key');
    const label = card.querySelector('.raf-stat-card__label');
    state.card = { group: group, key: key };
    state.cardPage = 1;

    modal.show({
      id: 'raf-card-detail',
      title: label ? label.textContent.trim() : '',
      content: '<p class="muted raf-card-detail__loading">' + esc(t('loading', 'Loading...')) + '</p>',
      footer: '<button type="button" class="btn outline" data-raf-card-close>'
        + esc(t('cancel', 'Close')) + '</button>'
    });

    await loadCardPage(1);
  }

  async function loadCardPage(pageNumber){
    if(!state.card) return;

    const query = sectionQuery(state.card.group);
    query.set('card', state.card.group);
    query.set('key', state.card.key);
    query.set('detail_page', String(pageNumber));

    if(modal && typeof modal.updateContent === 'function'){
      modal.updateContent('raf-card-detail', '<p class="muted raf-card-detail__loading">'
        + esc(t('loading', 'Loading...')) + '</p>');
    }

    const json = await getJsonSafe('/raf/api/card', query);
    if(!json || !json.success){
      if(modal && typeof modal.updateContent === 'function'){
        modal.updateContent('raf-card-detail', '<p class="panel-flash panel-flash--error panel-flash--inline is-visible">'
          + esc((json && json.message) || t('action_failure', 'Action failed.')) + '</p>');
      }
      return;
    }

    state.cardPage = pageNumber;
    if(modal && typeof modal.updateContent === 'function'){
      modal.updateContent('raf-card-detail', modalBodyHtml(json, pageNumber));
    }
    const footer = document.querySelector('#modal-raf-card-detail [data-role="footer"]');
    if(footer){
      footer.innerHTML = modalFooterHtml(json);
    }
  }

  function bindStatCards(){
    if(!dom.stats) return;

    dom.stats.addEventListener('click', function(event){
      const card = event.target.closest('[data-raf-card="open"]');
      if(card) openCard(card);
    });
  }

  function bindModalInteractions(){
    document.addEventListener('click', function(event){
      const close = event.target.closest('[data-raf-card-close]');
      if(close){
        closeCardModal();
        return;
      }

      const pageBtn = event.target.closest('[data-raf-card-page]');
      if(pageBtn && !pageBtn.disabled){
        const target = parseInt(pageBtn.getAttribute('data-raf-card-page'), 10);
        if(target > 0) loadCardPage(target);
        return;
      }

      const viewAll = event.target.closest('#modal-raf-card-detail a.btn');
      if(viewAll){
        closeCardModal();
      }
    });
  }

  // ---- 写操作：绑定 / 解绑 / 备注 ----
  function closeBindModal(){
    if(modal && typeof modal.hide === 'function') modal.hide('raf-bind');
  }

  function bindModalHtml(){
    return [
      '<p class="raf-modal-note">' + esc(t('bind_help', '')) + '</p>',
      '<div class="raf-modal-grid">',
      '  <label class="raf-modal-field">',
      '    <span>' + esc(t('account_id', 'Account ID')) + '</span>',
      '    <input type="number" min="1" id="rafBindAccountId">',
      '  </label>',
      '  <label class="raf-modal-field">',
      '    <span>' + esc(t('recruiter_guid', 'Recruiter GUID')) + '</span>',
      '    <input type="number" min="1" id="rafBindRecruiterGuid">',
      '  </label>',
      '</div>',
      '<label class="raf-modal-check">',
      '  <input type="checkbox" id="rafBindForce">',
      '  <span>' + esc(t('force', 'Force overwrite')) + '</span>',
      '</label>'
    ].join('');
  }

  function openBindModal(){
    if(!can('bind')) return;
    if(!modal || typeof modal.show !== 'function'){
      const accountId = window.prompt(t('account_id', 'Account ID'), '');
      if(accountId == null) return;
      const recruiterGuid = window.prompt(t('recruiter_guid', 'Recruiter GUID'), '');
      if(recruiterGuid == null) return;
      executeBind({ account_id: accountId, recruiter_guid: recruiterGuid, force: 0 });
      return;
    }

    modal.show({
      id: 'raf-bind',
      title: t('bind_title', 'Create binding'),
      width: '760px',
      content: bindModalHtml(),
      footer: [
        '<button type="button" class="btn outline" id="rafBindCancel">' + esc(t('cancel', 'Close')) + '</button>',
        '<button type="button" class="btn" id="rafBindSubmit">' + esc(t('submit', 'Submit')) + '</button>'
      ].join('')
    });

    const cancelBtn = document.getElementById('rafBindCancel');
    const submitBtn = document.getElementById('rafBindSubmit');
    if(cancelBtn) cancelBtn.addEventListener('click', closeBindModal);
    if(submitBtn){
      submitBtn.addEventListener('click', function(){
        executeBind({
          account_id: document.getElementById('rafBindAccountId')?.value || '',
          recruiter_guid: document.getElementById('rafBindRecruiterGuid')?.value || '',
          force: document.getElementById('rafBindForce')?.checked ? 1 : 0
        });
      });
    }
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

  // 网络层异常（断线、超时、5xx）不应让交互静默失败：合成一个失败响应交给既有分支提示
  async function postSafe(path, body){
    try{
      return await post(path, body);
    }catch(error){
      console.warn('RAF request failed', error);
      return { success: false, message: t('errors.request_failed', 'Request failed.') };
    }
  }

  // SOAP → Lua 的命令是异步生效的，稍等片刻再拉取列表，避免读到旧数据
  function refreshAfterCommand(){
    window.setTimeout(function(){ loadSection('bindings'); }, 600);
    window.setTimeout(function(){ loadSection('bindings'); }, 2000);
  }

  async function executeBind(payload){
    const accountId = parseInt(payload.account_id, 10);
    const recruiterGuid = parseInt(payload.recruiter_guid, 10);
    if(!(accountId > 0)){
      show('error', t('errors.account_required', 'Account ID is required.'));
      return;
    }
    if(!(recruiterGuid > 0)){
      show('error', t('errors.recruiter_required', 'Recruiter GUID is required.'));
      return;
    }

    const json = await postSafe('/raf/api/bind', {
      account_id: accountId,
      recruiter_guid: recruiterGuid,
      force: payload.force ? 1 : 0
    });

    if(!json || !json.success){
      show('error', (json && json.message) || t('action_failure', 'Action failed.'));
      return;
    }

    closeBindModal();
    show('success', json.message || t('action_success', 'Action completed.'));
    refreshAfterCommand();
  }

  async function executeUnbind(button){
    const accountId = parseInt(button.getAttribute('data-account-id') || '0', 10);
    const accountLabel = button.getAttribute('data-account-label') || ('#' + accountId);
    const confirmMessage = t('confirm_unbind', 'Unbind account :account?').replace(':account', accountLabel);
    if(!window.confirm(confirmMessage)) return;

    const json = await postSafe('/raf/api/unbind', { account_id: accountId });
    if(!json || !json.success){
      show('error', (json && json.message) || t('action_failure', 'Action failed.'));
      return;
    }

    show('success', json.message || t('action_success', 'Action completed.'));
    refreshAfterCommand();
  }

  async function executeComment(button){
    const accountId = parseInt(button.getAttribute('data-account-id') || '0', 10);
    const current = button.getAttribute('data-comment') || '';
    const next = window.prompt(t('comment_prompt', 'Enter a new note'), current);
    if(next === null) return;

    const json = await postSafe('/raf/api/comment', {
      account_id: accountId,
      comment: next
    });
    if(!json || !json.success){
      show('error', (json && json.message) || t('action_failure', 'Action failed.'));
      return;
    }

    show('success', json.message || t('action_success', 'Action completed.'));
    loadSection('bindings');
  }

  // 列表区块会被整块替换，写操作按钮必须使用事件委托
  function bindActionButtons(){
    if(dom.bindBtn && !dom.bindBtn.__rafBound){
      dom.bindBtn.__rafBound = true;
      dom.bindBtn.addEventListener('click', openBindModal);
    }
  }

  if(page){
    page.addEventListener('click', function(event){
      const commentBtn = event.target.closest('.js-raf-comment');
      if(commentBtn){
        executeComment(commentBtn);
        return;
      }

      const unbindBtn = event.target.closest('.js-raf-unbind');
      if(unbindBtn){
        executeUnbind(unbindBtn);
      }
    });
  }

  bindSectionInteractions();
  bindStatCards();
  bindModalInteractions();
  bindActionButtons();
})();
