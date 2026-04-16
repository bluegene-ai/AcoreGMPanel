(function(){
  if(document.body.dataset.module !== 'raf') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const modal = window.Modal || null;
  const capabilities = window.PANEL_CAPABILITIES || {};
  const currentServer = new URLSearchParams(window.location.search).get('server') || '';
  const dom = {
    feedback: document.getElementById('rafFeedback'),
    bindBtn: document.getElementById('rafBindBtn')
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

    const json = await post('/raf/api/bind', {
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
    window.setTimeout(function(){ window.location.reload(); }, 500);
  }

  async function executeUnbind(button){
    const accountId = parseInt(button.getAttribute('data-account-id') || '0', 10);
    const accountLabel = button.getAttribute('data-account-label') || ('#' + accountId);
    const confirmMessage = t('confirm_unbind', 'Unbind account :account?').replace(':account', accountLabel);
    if(!window.confirm(confirmMessage)) return;

    const json = await post('/raf/api/unbind', { account_id: accountId });
    if(!json || !json.success){
      show('error', (json && json.message) || t('action_failure', 'Action failed.'));
      return;
    }

    show('success', json.message || t('action_success', 'Action completed.'));
    window.setTimeout(function(){ window.location.reload(); }, 500);
  }

  async function executeComment(button){
    const accountId = parseInt(button.getAttribute('data-account-id') || '0', 10);
    const current = button.getAttribute('data-comment') || '';
    const next = window.prompt(t('comment_prompt', 'Enter a new note'), current);
    if(next === null) return;

    const json = await post('/raf/api/comment', {
      account_id: accountId,
      comment: next
    });
    if(!json || !json.success){
      show('error', (json && json.message) || t('action_failure', 'Action failed.'));
      return;
    }

    show('success', json.message || t('action_success', 'Action completed.'));
    window.setTimeout(function(){ window.location.reload(); }, 300);
  }

  if(dom.bindBtn) dom.bindBtn.addEventListener('click', openBindModal);
  document.querySelectorAll('.js-raf-unbind').forEach(function(button){
    button.addEventListener('click', function(){ executeUnbind(button); });
  });
  document.querySelectorAll('.js-raf-comment').forEach(function(button){
    button.addEventListener('click', function(){ executeComment(button); });
  });
})();