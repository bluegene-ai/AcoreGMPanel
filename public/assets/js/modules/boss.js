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

  // 扩展配置（boss_activity_config_ext）：字段较多，全部在扩展配置 Tab 的二级 Tab 里；
  // 开关型字段页面里带一个 hidden=0，未勾选时提交 0（Object.fromEntries 取最后一个同名值）。
  async function saveExtConfig(){
    if(!dom.extConfigForm) return;

    const payload = Object.fromEntries(new FormData(dom.extConfigForm).entries());

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

  /* ------------------------------------------------------------------ Tab 分页
   * 每个 [data-boss-tabs] 容器管自己的按钮与面板（面板必须是容器的直接子节点），
   * 因此「顶层 Tab」与「扩展配置里的二级 Tab」可以共用同一套逻辑；
   * 当前 Tab 记进 URL hash（顶层 tab=…，其它组用组名当键），刷新/保存后回到同一页。 */
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
})();