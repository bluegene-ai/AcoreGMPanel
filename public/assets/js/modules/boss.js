(function(){
  if(document.body.dataset.module !== 'boss') return;

  const panel = window.Panel || {};
  const api = panel.api || null;
  const feedback = panel.feedback || null;
  const currentServer = new URLSearchParams(window.location.search).get('server') || '';
  const dom = {
    feedback: document.getElementById('bossFeedback'),
    spawnBtn: document.getElementById('bossSpawnBtn'),
    rebaseBtn: document.getElementById('bossRebaseBtn'),
    presetBtn: document.getElementById('bossPresetBtn'),
    presetSelect: document.getElementById('bossPresetSelect'),
    difficultyBtn: document.getElementById('bossDifficultyBtn'),
    difficultySelect: document.getElementById('bossDifficultySelect')
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
    [dom.spawnBtn, dom.rebaseBtn, dom.presetBtn, dom.difficultyBtn].forEach(function(node){
      if(node) node.disabled = !!disabled;
    });
    if(dom.presetSelect) dom.presetSelect.disabled = !!disabled;
    if(dom.difficultySelect) dom.difficultySelect.disabled = !!disabled;
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

  if(dom.spawnBtn){
    dom.spawnBtn.addEventListener('click', function(){ runAction('spawn'); });
  }

  if(dom.rebaseBtn){
    dom.rebaseBtn.addEventListener('click', function(){ runAction('rebase'); });
  }

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
})();