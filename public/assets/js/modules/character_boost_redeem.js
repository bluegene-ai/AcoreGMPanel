(function(){
  const panel = window.Panel || {};
  const moduleLocaleFn = typeof panel.moduleLocale === 'function'
    ? panel.moduleLocale.bind(panel)
    : null;
  const moduleTranslator = typeof panel.createModuleTranslator === 'function'
    ? panel.createModuleTranslator('character_boost')
    : null;
  const form = document.getElementById('boostRedeemForm');
  const flashBox = document.getElementById('boostRedeemFlash');
  const realmSelect = document.getElementById('boostRedeemRealm');
  const templateSelect = document.getElementById('boostRedeemTemplate');

  const translate = (path, fallback, replacements) => {
    const sentinel = 'modules.character_boost.' + path;
    let text = sentinel;
    if(moduleLocaleFn){
      text = moduleLocaleFn('character_boost', path, sentinel);
    } else if(moduleTranslator){
      text = moduleTranslator(path, sentinel);
    }
    if(text === sentinel){
      text = fallback ?? sentinel;
    }
    if(typeof text === 'string' && replacements){
      Object.entries(replacements).forEach(([key, value]) => {
        text = text.replace(new RegExp(':' + key + '(?![A-Za-z0-9_])', 'g'), String(value ?? ''));
      });
    }
    return text;
  };

  const showFlash = (msg, ok) => {
    if(!flashBox) return;
    flashBox.textContent = msg || translate(
      ok ? 'common.ok' : 'common.error',
      ok ? 'OK' : 'Error'
    );
    flashBox.classList.remove('panel-flash--success','panel-flash--danger','cb-flash-hidden');
    flashBox.classList.add('panel-flash--inline','is-visible', ok ? 'panel-flash--success' : 'panel-flash--danger');
  };

  if(!form || !realmSelect || !templateSelect) return;

  const optionsEndpoint = form.dataset.optionsEndpoint;
  const redeemEndpoint = form.dataset.redeemEndpoint;
  if(!optionsEndpoint || !redeemEndpoint) return;

  let templates = [];

  const renderTemplatesForRealm = (realmId) => {
    const rid = String(realmId || '');
    const list = templates.filter(t => String(t.realm_id) === rid);

    templateSelect.innerHTML = '';
    if(!list.length){
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = translate('redeem.templates.empty', 'No templates');
      templateSelect.appendChild(opt);
      templateSelect.disabled = true;
      return;
    }

    list.forEach(t => {
      const opt = document.createElement('option');
      opt.value = String(t.id);
      opt.textContent = t.name + (t.target_level ? (' (Lv.' + t.target_level + ')') : '');
      templateSelect.appendChild(opt);
    });

    templateSelect.disabled = false;
  };

  const loadOptions = async () => {
    try {
      const res = await fetch(optionsEndpoint, { method: 'GET' });
      const json = await res.json();
      if(!json || !json.success){
        showFlash(
          (json && json.message)
            ? json.message
            : translate('redeem.errors.load_options_failed', 'Failed to load options'),
          false
        );
        return;
      }

      const realms = Array.isArray(json.realms) ? json.realms : [];
      templates = Array.isArray(json.templates) ? json.templates : [];

      realmSelect.innerHTML = '';
      realms.forEach(r => {
        const opt = document.createElement('option');
        opt.value = String(r.realm_id);
        opt.textContent = r.label || translate('redeem.realms.option', 'Realm :id', { id: r.realm_id });
        realmSelect.appendChild(opt);
      });

      const firstRealm = realmSelect.value || (realms[0] ? String(realms[0].realm_id) : '');
      if(firstRealm){
        realmSelect.value = firstRealm;
        renderTemplatesForRealm(firstRealm);
      }
    } catch(err){
      showFlash(
        (err && err.message) ? err.message : translate('common.network_error', 'Network error'),
        false
      );
    }
  };

  realmSelect.addEventListener('change', () => {
    renderTemplatesForRealm(realmSelect.value);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = new FormData(form);
    if(!data.get('_csrf') && window.__CSRF_TOKEN){
      data.set('_csrf', window.__CSRF_TOKEN);
    }

    data.delete('template_id');

    try {
      const res = await fetch(redeemEndpoint, {
        method: 'POST',
        body: data,
        headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
      });
      const json = await res.json().catch(() => ({
        success: false,
        message: translate('common.invalid_response', 'Invalid response')
      }));
      showFlash(
        json.message || translate(
          json.success ? 'common.ok' : 'common.failed',
          json.success ? 'OK' : 'Failed'
        ),
        !!json.success
      );
    } catch(err){
      showFlash(
        (err && err.message) ? err.message : translate('common.network_error', 'Network error'),
        false
      );
    }
  });

  loadOptions();
})();
