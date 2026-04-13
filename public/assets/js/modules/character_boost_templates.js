(function(){
  const panel = window.Panel || {};
  const moduleLocaleFn = typeof panel.moduleLocale === 'function'
    ? panel.moduleLocale.bind(panel)
    : null;
  const moduleTranslator = typeof panel.createModuleTranslator === 'function'
    ? panel.createModuleTranslator('character_boost')
    : null;
  const table = document.getElementById('boostTplTable');
  const flashBox = document.getElementById('boostTplFlash');

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

  if(!table) return;
  const deleteEndpoint = table.dataset.deleteEndpoint;
  if(!deleteEndpoint) return;

  table.querySelectorAll('.js-boost-tpl-delete').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      if(!id) return;
      if(!confirm(translate('templates.confirm.delete', 'Delete template #:id?', { id }))) return;

      const data = new FormData();
      data.set('id', String(id));
      if(window.__CSRF_TOKEN){
        data.set('_csrf', window.__CSRF_TOKEN);
      }

      try {
        const res = await fetch(deleteEndpoint, {
          method: 'POST',
          body: data,
          headers: { 'X-CSRF-TOKEN': data.get('_csrf') || '' }
        });
        const json = await res.json().catch(() => ({
          success: false,
          message: translate('common.invalid_response', 'Invalid response')
        }));

        if(json && json.success){
          const row = table.querySelector('tr[data-id="' + id + '"]');
          if(row) row.remove();
        }

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
  });
})();
