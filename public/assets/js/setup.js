(function(){
  function readSetupJson(name){
    const node = document.querySelector('script[data-setup-json="' + name + '"]');
    if(!node) return null;
    try {
      return JSON.parse(node.textContent || 'null');
    } catch(_error) {
      return null;
    }
  }

  function bindAsyncForm(form, fallbackMessage){
    if(!form || form.__setupBound) return;
    form.__setupBound = true;
    form.addEventListener('submit', function(event){
      event.preventDefault();
      const data = new FormData(form);
      const submitUrl = form.getAttribute('action') || window.location.href;
      fetch(submitUrl, { method: 'POST', body: data })
        .then((resp) => resp.json())
        .then((json) => {
          if(json && json.success){
            window.location.href = json.redirect;
            return;
          }
          alert((json && json.message) || fallbackMessage);
        })
        .catch(() => alert(fallbackMessage));
    });
  }

  function initLocaleForm(){
    const form = document.getElementById('setup-lang-form');
    if(!form) return;
    const cards = form.querySelectorAll('[data-locale-card]');
    const updateActive = function(){
      cards.forEach((card) => {
        const radio = card.querySelector('input');
        card.classList.toggle('active', !!(radio && radio.checked));
      });
    };
    cards.forEach((card) => {
      const input = card.querySelector('input[type="radio"]');
      if(!input) return;
      card.addEventListener('click', function(event){
        if(event.target.tagName !== 'INPUT'){
          input.checked = true;
          updateActive();
        }
      });
      input.addEventListener('change', updateActive);
    });
    updateActive();
    bindAsyncForm(form, form.dataset.submitFail || 'Request failed');
  }

  function initAdminForm(){
    const form = document.getElementById('admin-form');
    if(!form) return;
    bindAsyncForm(form, form.dataset.submitFail || 'Request failed');
  }

  function initModeForm(){
        const form = document.getElementById('mode-form');
        if(!form || form.__setupModeBound) return;
        form.__setupModeBound = true;

        const config = readSetupJson('SETUP_MODE_CONFIG') || {};
        const locale = config.locale || {};
        const serverLocale = locale.server || {};
        const realmLocale = locale.realm || {};
        const fieldLocale = locale.fields || {};
        const actionLocale = locale.actions || {};
        const panels = form.querySelectorAll('[data-mode-panel]');
        const cards = form.querySelectorAll('[data-mode-card]');
        const serverContainer = document.getElementById('setup-server-groups');
        const generatedRealmContainer = document.getElementById('setup-generated-realms');
        const addServerBtn = document.getElementById('add-server-group');
        const verifyBtn = document.getElementById('verify-auth-realms');
        const verifyStatus = document.getElementById('verify-auth-status');
        const verifyUrl = form.dataset.realmsUrl || config.verifyUrl || '';
        let serverGroups = Array.isArray(config.serverGroups) ? config.serverGroups.slice() : [];
        let generatedRealms = Array.isArray(config.realms) ? config.realms.slice() : [];

        function updateGeneratedRealmStatus(){
          if(!verifyStatus) return;
          const template = generatedRealms.length ? (realmLocale.verify_success || '') : (realmLocale.verify_empty || '');
          verifyStatus.textContent = template.replace(':count', String(generatedRealms.length));
        }

        function escapeHtml(value){
          return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        }

        function dbDefaults(input, database){
          const value = input || {};
          return {
            host: value.host || '127.0.0.1',
            port: Number(value.port || 3306),
            database: value.database || database || '',
            username: value.username || 'root',
            password: value.password || '',
          };
        }

        function soapDefaults(input){
          const value = input || {};
          return {
            host: value.host || '127.0.0.1',
            port: Number(value.port || 7878),
            username: value.username || 'soap_user',
            password: value.password || 'soap_pass',
            uri: value.uri || 'urn:AC',
          };
        }

        function createEmptyServer(index){
          return {
            name: '',
            realm_id: index + 1,
            port: 0,
            auth: dbDefaults(config.sharedAuth, 'auth'),
            characters: dbDefaults({}, 'characters'),
            world: dbDefaults({}, 'world'),
            soap: soapDefaults({}),
          };
        }

        function getMode(){
          return form.querySelector('input[name="mode"]:checked')?.value || config.mode || 'single';
        }

        function panelAccepts(panel, mode){
          return (panel.dataset.modePanel || '').split(',').map((value) => value.trim()).filter(Boolean).includes(mode);
        }

        function serverTitle(index){
          return (serverLocale.title_prefix || 'Server :index').replace(':index', String(index + 1));
        }

        function realmTitle(index){
          return (realmLocale.title_prefix || 'Realm :index').replace(':index', String(index + 1));
        }

        function databaseFields(prefix, db, title){
          return `
            <details class="setup-collapsible" open>
              <summary>${escapeHtml(title)}</summary>
              <div class="setup-grid setup-grid--compact">
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.host || 'Host')}</label>
                  <input name="${prefix}[host]" value="${escapeHtml(db.host)}" placeholder="127.0.0.1">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.port || 'Port')}</label>
                  <input type="number" name="${prefix}[port]" value="${escapeHtml(db.port)}" min="1" max="65535">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.database || 'Database')}</label>
                  <input name="${prefix}[database]" value="${escapeHtml(db.database)}">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.user || 'Username')}</label>
                  <input name="${prefix}[username]" value="${escapeHtml(db.username)}">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.password || 'Password')}</label>
                  <input type="password" name="${prefix}[password]" value="${escapeHtml(db.password)}">
                </div>
              </div>
            </details>
          `;
        }

        function soapFields(prefix, soap, title){
          return `
            <details class="setup-collapsible" open>
              <summary>${escapeHtml(title)}</summary>
              <div class="setup-grid setup-grid--compact">
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.host || 'Host')}</label>
                  <input name="${prefix}[host]" value="${escapeHtml(soap.host)}" placeholder="127.0.0.1">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.port || 'Port')}</label>
                  <input type="number" name="${prefix}[port]" value="${escapeHtml(soap.port)}" min="1" max="65535">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.user || 'Username')}</label>
                  <input name="${prefix}[username]" value="${escapeHtml(soap.username)}">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.password || 'Password')}</label>
                  <input type="password" name="${prefix}[password]" value="${escapeHtml(soap.password)}">
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(fieldLocale.uri || 'URI')}</label>
                  <input name="${prefix}[uri]" value="${escapeHtml(soap.uri)}" placeholder="urn:AC">
                </div>
              </div>
            </details>
          `;
        }

        function renderServerGroups(){
          if(!serverContainer) return;
          serverContainer.innerHTML = serverGroups.map((server, index) => {
            const auth = dbDefaults(server.auth, 'auth');
            const characters = dbDefaults(server.characters, 'characters');
            const world = dbDefaults(server.world, 'world');
            const soap = soapDefaults(server.soap);
            const removeDisabled = serverGroups.length === 1 ? 'disabled' : '';
            return `
              <div class="realm-card">
                <div class="realm-card__title">
                  ${escapeHtml(serverTitle(index))}
                  <button type="button" class="realm-card__remove" data-remove-server="${index}" ${removeDisabled}>${escapeHtml(serverLocale.remove || 'Remove')}</button>
                </div>
                <div class="setup-field">
                  <label>${escapeHtml(serverLocale.name_label || 'Server name')}</label>
                  <input name="realms[${index}][name]" value="${escapeHtml(server.name || '')}" placeholder="${escapeHtml(serverLocale.name_placeholder || '')}">
                </div>
                <input type="hidden" name="realms[${index}][realm_id]" value="${escapeHtml(server.realm_id || (index + 1))}">
                <input type="hidden" name="realms[${index}][port]" value="${escapeHtml(server.port || 0)}">
                ${databaseFields(`realms[${index}][auth]`, auth, serverLocale.auth_title || 'Auth DB')}
                ${databaseFields(`realms[${index}][characters]`, characters, serverLocale.characters_title || 'Characters DB')}
                ${databaseFields(`realms[${index}][world]`, world, serverLocale.world_title || 'World DB')}
                ${soapFields(`realms[${index}][soap]`, soap, serverLocale.soap_title || 'SOAP')}
              </div>
            `;
          }).join('');

          serverContainer.querySelectorAll('[data-remove-server]').forEach((button) => {
            button.addEventListener('click', function(){
              const index = Number(button.dataset.removeServer);
              if (!Number.isNaN(index) && serverGroups.length > 1) {
                serverGroups.splice(index, 1);
                renderServerGroups();
                syncPanels();
              }
            });
          });
        }

        function renderGeneratedRealms(){
          if(!generatedRealmContainer) return;
          if (!generatedRealms.length) {
            generatedRealmContainer.innerHTML = `<p class="setup-summary">${escapeHtml(realmLocale.empty || '')}</p>`;
            updateGeneratedRealmStatus();
            return;
          }
          generatedRealmContainer.innerHTML = generatedRealms.map((realm, index) => {
            const characters = dbDefaults(realm.characters, 'characters');
            const world = dbDefaults(realm.world, 'world');
            const soap = soapDefaults(realm.soap);
            const meta = [];
            if (realm.realm_id)
              meta.push((realmLocale.meta_id || 'ID :value').replace(':value', String(realm.realm_id)));
            if (realm.port)
              meta.push((realmLocale.meta_port || 'Port :value').replace(':value', String(realm.port)));
            return `
              <div class="realm-card">
                <div class="realm-card__title">
                  <span class="realm-card__title-main">${escapeHtml(realm.name || realmTitle(index))}</span>
                  <span class="realm-card__title-side">
                    ${meta.length ? `<span class="realm-card__meta">${escapeHtml(meta.join(' · '))}</span>` : ''}
                    <button type="button" class="realm-card__remove" data-remove-generated-realm="${index}">${escapeHtml(realmLocale.remove || serverLocale.remove || 'Remove')}</button>
                  </span>
                </div>
                <input type="hidden" name="realms[${index}][name]" value="${escapeHtml(realm.name || '')}">
                <input type="hidden" name="realms[${index}][realm_id]" value="${escapeHtml(realm.realm_id || (index + 1))}">
                <input type="hidden" name="realms[${index}][port]" value="${escapeHtml(realm.port || 0)}">
                ${databaseFields(`realms[${index}][characters]`, characters, realmLocale.characters_title || 'Characters DB')}
                ${databaseFields(`realms[${index}][world]`, world, realmLocale.world_title || 'World DB')}
                ${soapFields(`realms[${index}][soap]`, soap, realmLocale.soap_title || 'SOAP')}
              </div>
            `;
          }).join('');

          generatedRealmContainer.querySelectorAll('[data-remove-generated-realm]').forEach((button) => {
            button.addEventListener('click', function(){
              const index = Number(button.dataset.removeGeneratedRealm);
              if (!Number.isNaN(index)) {
                generatedRealms.splice(index, 1);
                renderGeneratedRealms();
              }
            });
          });

          updateGeneratedRealmStatus();
        }

        function syncPanels(){
          const mode = getMode();
          cards.forEach((card) => {
            card.classList.toggle('active', card.dataset.modeCard === mode);
          });
          panels.forEach((panel) => {
            const enabled = panelAccepts(panel, mode);
            panel.classList.toggle('hidden', !enabled);
            panel.querySelectorAll('input, select, textarea, button').forEach((element) => {
              element.disabled = !enabled;
            });
          });
          if ((mode === 'single' || mode === 'multi-full') && serverGroups.length === 0) {
            serverGroups.push(createEmptyServer(0));
            renderServerGroups();
          }
        }

        if (addServerBtn) {
          addServerBtn.addEventListener('click', function(){
            serverGroups.push(createEmptyServer(serverGroups.length));
            renderServerGroups();
            syncPanels();
          });
        }

        if (verifyBtn) {
          verifyBtn.addEventListener('click', function(){
            if (!verifyUrl) {
              alert(actionLocale.request_fail || 'Request failed');
              return;
            }
            verifyBtn.disabled = true;
            const text = verifyBtn.textContent;
            verifyBtn.textContent = actionLocale.verifying || text;
            if (verifyStatus)
              verifyStatus.textContent = '';

            const payload = new FormData();
            payload.append('mode', getMode());
            ['host', 'port', 'db', 'user', 'pass'].forEach((key) => {
              const element = form.querySelector(`[name="auth_${key}"]`);
              payload.append(`auth_${key}`, element ? element.value : '');
            });

            fetch(verifyUrl, { method: 'POST', body: payload })
              .then((response) => response.json())
              .then((json) => {
                if (json && json.success) {
                  generatedRealms = Array.isArray(json.realms) ? json.realms : [];
                  renderGeneratedRealms();
                  return;
                }
                const message = (json && json.message) || realmLocale.verify_fail || actionLocale.request_fail || 'Request failed';
                if (verifyStatus)
                  verifyStatus.textContent = message;
                else
                  alert(message);
              })
              .catch(() => {
                const message = actionLocale.request_fail || 'Request failed';
                if (verifyStatus)
                  verifyStatus.textContent = message;
                else
                  alert(message);
              })
              .finally(() => {
                verifyBtn.disabled = false;
                verifyBtn.textContent = text;
              });
          });
        }

        cards.forEach((card) => {
          const input = card.querySelector('input[type="radio"]');
          if (!input) return;
          card.addEventListener('click', function(event){
            if (event.target.tagName !== 'INPUT') {
              input.checked = true;
              input.dispatchEvent(new Event('change', { bubbles: true }));
            }
          });
          input.addEventListener('change', syncPanels);
        });

        renderServerGroups();
        renderGeneratedRealms();
        syncPanels();
        bindAsyncForm(form, actionLocale.save_fail || actionLocale.unknown_error || 'Error');
      }

      function boot(){
        initLocaleForm();
        initAdminForm();
        initModeForm();
      }

      if(document.readyState === 'loading')
        document.addEventListener('DOMContentLoaded', boot);
      else
        boot();
    })();