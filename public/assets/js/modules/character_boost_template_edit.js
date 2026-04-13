(function(){
  const panel = window.Panel || {};
  const moduleLocaleFn = typeof panel.moduleLocale === 'function'
    ? panel.moduleLocale.bind(panel)
    : null;
  const moduleTranslator = typeof panel.createModuleTranslator === 'function'
    ? panel.createModuleTranslator('character_boost')
    : null;
  const form = document.getElementById('boostTplEditForm');
  const flashBox = document.getElementById('boostTplEditFlash');

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

  if(!form) return;
  const endpoint = form.dataset.endpoint;
  if(!endpoint) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = new FormData(form);
    if(!data.get('_csrf') && window.__CSRF_TOKEN){
      data.set('_csrf', window.__CSRF_TOKEN);
    }

    try {
      const res = await fetch(endpoint, {
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

      if(json && json.success && json.payload && json.payload.id){
        // If created, keep user on the page and update URL.
        const id = String(json.payload.id);
        const url = new URL(window.location.href);
        url.searchParams.set('id', id);
        window.history.replaceState({}, '', url.toString());
      }
    } catch(err){
      showFlash(
        (err && err.message) ? err.message : translate('common.network_error', 'Network error'),
        false
      );
    }
  });
})();
