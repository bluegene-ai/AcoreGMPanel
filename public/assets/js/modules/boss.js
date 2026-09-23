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
    configSaveBtn: document.getElementById('bossConfigSaveBtn')
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
    [dom.spawnBtn, dom.killBtn, dom.clearBtn, dom.rebaseBtn, dom.configReloadBtn, dom.presetBtn, dom.difficultyBtn, dom.configSaveBtn].forEach(function(node){
      if(node) node.disabled = !!disabled;
    });
    if(dom.presetSelect) dom.presetSelect.disabled = !!disabled;
    if(dom.difficultySelect) dom.difficultySelect.disabled = !!disabled;
    if(dom.tierSelect) dom.tierSelect.disabled = !!disabled;
    if(dom.healthMultiplierInput) dom.healthMultiplierInput.disabled = !!disabled;
    if(dom.configForm){
      dom.configForm.querySelectorAll('input, textarea, select').forEach(function(node){
        node.disabled = !!disabled;
      });
    }
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
    payload.guaranteed_reward_enabled = dom.configForm.querySelector('[name="guaranteed_reward_enabled"]')?.checked ? 1 : 0;
    payload.guaranteed_reward_notify = dom.configForm.querySelector('[name="guaranteed_reward_notify"]')?.checked ? 1 : 0;

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
})();