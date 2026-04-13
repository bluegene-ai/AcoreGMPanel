// Global Panel helper (dynamic base path + fetch wrappers)
(function(){
  if(window.Panel) return; // idempotent

  function parsePanelJsonScripts(){
    document.querySelectorAll('script[data-panel-json]').forEach((node)=>{
      if(node.__panelJsonApplied) return;
      node.__panelJsonApplied = true;
      const target = node.dataset.global;
      if(!target) return;
      const raw = (node.textContent || '').trim();
      if(!raw) return;
      try {
        const value = JSON.parse(raw);
        window[target] = node.dataset.freeze === 'true' && value && typeof value === 'object'
          ? Object.freeze(value)
          : value;
      } catch (error) {
        console.warn('Failed to parse panel JSON payload for', target, error);
      }
    });
  }

  function resolveBasePath(){
    const bodyBase = document.body?.dataset?.appBase;
    const htmlBase = document.documentElement?.dataset?.appBase;
    const globalBase = typeof window.APP_BASE === 'string' ? window.APP_BASE : '';
    return (globalBase || bodyBase || htmlBase || '').replace(/\/$/, '');
  }

  function resolveCsrfToken(){
    if(typeof window.__CSRF_TOKEN === 'string' && window.__CSRF_TOKEN.trim() !== ''){
      return window.__CSRF_TOKEN.trim();
    }
    const jsonNode = document.querySelector('script[data-panel-json][data-global="__CSRF_TOKEN"]');
    if(jsonNode){
      const raw = (jsonNode.textContent || '').trim();
      if(raw){
        try {
          const value = JSON.parse(raw);
          if(typeof value === 'string' && value.trim() !== ''){
            window.__CSRF_TOKEN = value.trim();
            return window.__CSRF_TOKEN;
          }
        } catch (error) {
          console.warn('Failed to resolve CSRF token payload', error);
        }
      }
    }
    const field = document.querySelector('input[name="_csrf"], input[name="_token"]');
    const value = field && typeof field.value === 'string' ? field.value.trim() : '';
    if(value !== ''){
      window.__CSRF_TOKEN = value;
      return value;
    }
    return '';
  }

  parsePanelJsonScripts();

  const BASE = resolveBasePath();
  const localeStore = { common: {}, modules: {} };

  function looksLikeI18nKey(value){
    if(typeof value !== 'string') return false;
    const text = value.trim();
    if(!text) return false;
    return /^app\.[A-Za-z0-9_.-]+$/.test(text) || /^modules\.[A-Za-z0-9_.-]+$/.test(text);
  }

  function humanizeI18nKey(value){
    if(typeof value !== 'string') return value;
    let text = value.trim();
    if(!text) return value;

    text = text
      .replace(/^app\.js\.modules\./, '')
      .replace(/^app\.js\./, '')
      .replace(/^app\./, '')
      .replace(/^modules\./, '');

    const parts = text.split('.').map((p)=>p.trim()).filter(Boolean);
    const tail = parts.length ? parts.slice(-2).join(' ') : text;
    text = tail
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    text = text.replace(/\b(label|hint|description|tooltip|placeholder|help|message)\b/ig, '').replace(/\s+/g, ' ').trim();
    if(!text) text = parts[parts.length - 1] || value;
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function isPlainObject(value){
    return value !== null && typeof value === 'object' && !Array.isArray(value);
  }

  function mergeLocale(target, source){
    if(!isPlainObject(source)) return target;
    Object.keys(source).forEach((key)=>{
      const value = source[key];
      if(isPlainObject(value)){
        if(!isPlainObject(target[key])) target[key] = {};
        mergeLocale(target[key], value);
      } else {
        target[key] = value;
      }
    });
    return target;
  }

  function setLocale(pathOrData, value){
    if(typeof pathOrData === 'string' || Array.isArray(pathOrData)){
      const segments = (Array.isArray(pathOrData)? pathOrData : String(pathOrData).split('.'))
        .map((seg)=>String(seg).trim()).filter(Boolean);
      if(segments.length === 0){
        if(isPlainObject(value)) mergeLocale(localeStore, value);
        return localeStore;
      }
      let node = localeStore;
      for(let i=0;i<segments.length-1;i+=1){
        const segment = segments[i];
        if(!isPlainObject(node[segment])) node[segment] = {};
        node = node[segment];
      }
      const last = segments[segments.length-1];
      if(isPlainObject(value)){
        if(!isPlainObject(node[last])) node[last] = {};
        mergeLocale(node[last], value);
      } else {
        node[last] = value;
      }
      return node[last];
    }
    if(isPlainObject(pathOrData)){
      mergeLocale(localeStore, pathOrData);
    }
    return localeStore;
  }

  function getLocale(path, fallback){
    if(path == null) return localeStore;
    const segments = Array.isArray(path) ? path : String(path).split('.');
    const resolvedPath = Array.isArray(path) ? segments.join('.') : String(path);
    let node = localeStore;
    for(let i=0;i<segments.length;i+=1){
      const segment = String(segments[i] ?? '').trim();
      if(!segment) continue;
      if(node && typeof node === 'object' && segment in node){
        node = node[segment];
      } else {
        if(fallback !== undefined){
          return looksLikeI18nKey(fallback) ? humanizeI18nKey(fallback) : fallback;
        }
        return looksLikeI18nKey(resolvedPath) ? humanizeI18nKey(resolvedPath) : resolvedPath;
      }
    }
    if(node === undefined){
      if(fallback !== undefined){
        return looksLikeI18nKey(fallback) ? humanizeI18nKey(fallback) : fallback;
      }
      return looksLikeI18nKey(resolvedPath) ? humanizeI18nKey(resolvedPath) : resolvedPath;
    }
    if(looksLikeI18nKey(node)){
      return fallback !== undefined ? fallback : humanizeI18nKey(node);
    }
    return node;
  }

  function moduleLocaleValue(moduleName, path, fallback){
    if(!moduleName) return getLocale(['modules'], fallback);
    const pathSegments = Array.isArray(path)
      ? path.map((seg)=>String(seg).trim()).filter(Boolean)
      : (path ? String(path).split('.').map((seg)=>seg.trim()).filter(Boolean) : []);
    const moduleSegments = ['modules', moduleName, ...pathSegments];

    let value = getLocale(moduleSegments, null);
    if(value !== null && value !== undefined){
      if(looksLikeI18nKey(value)) return fallback !== undefined ? fallback : humanizeI18nKey(value);
      return value;
    }

    if(pathSegments.length){
      value = getLocale(['common','modules', moduleName, ...pathSegments], null);
      if(value !== null && value !== undefined){
        if(looksLikeI18nKey(value)) return fallback !== undefined ? fallback : humanizeI18nKey(value);
        return value;
      }

      value = getLocale(['common','api', ...pathSegments], null);
      if(value !== null && value !== undefined){
        if(looksLikeI18nKey(value)) return fallback !== undefined ? fallback : humanizeI18nKey(value);
        return value;
      }

      value = getLocale(['common', ...pathSegments], null);
      if(value !== null && value !== undefined){
        if(looksLikeI18nKey(value)) return fallback !== undefined ? fallback : humanizeI18nKey(value);
        return value;
      }
    }

    if(fallback !== undefined) return fallback;
    if(pathSegments.length) return ['modules', moduleName, ...pathSegments].join('.');
    return fallback;
  }

  function buildUrl(path){
    if(!path) path = '/';
    if(/^https?:\/\//i.test(path)) return path; // absolute
    if(path[0] !== '/') path = '/' + path; // ensure leading slash
    return BASE + path; // BASE may be ''
  }

  function parseApiText(text){
    if(typeof text !== 'string') return null;
    const attempts = [];
    const trimmed = text.trim();
    if(text !== '') attempts.push(text);
    if(trimmed !== '' && trimmed !== text) attempts.push(trimmed);

    const objectStart = trimmed.indexOf('{');
    const objectEnd = trimmed.lastIndexOf('}');
    if(objectStart !== -1 && objectEnd > objectStart){
      attempts.push(trimmed.slice(objectStart, objectEnd + 1));
    }

    const arrayStart = trimmed.indexOf('[');
    const arrayEnd = trimmed.lastIndexOf(']');
    if(arrayStart !== -1 && arrayEnd > arrayStart){
      attempts.push(trimmed.slice(arrayStart, arrayEnd + 1));
    }

    for(const attempt of attempts){
      try {
        return JSON.parse(attempt);
      } catch (error) {
      }
    }

    return null;
  }

  async function parseApiResponse(resp){
    const fallbackMsg = getLocale(['common','errors','invalid_json'], 'Invalid JSON');
    const text = await resp.text();
    const parsed = parseApiText(text);
    if(parsed !== null) return parsed;
    return { success:false, message:fallbackMsg, raw:text, status:resp.status };
  }

  async function api(path, options){
    const url = buildUrl(path);
    options = options || {};
    const init = { method: options.method || 'GET', headers: options.headers ? {...options.headers} : {} };
    let body = options.body;
    if(body && !(body instanceof FormData) && !(body instanceof URLSearchParams) && !(body instanceof Blob) && typeof body !== 'string'){
      const fd = new FormData();
      const appendValue = (key, value) => {
        if(value === undefined || value === null){
          return;
        }
        if(value instanceof Blob){
          fd.append(key, value);
          return;
        }
        if(Array.isArray(value)){
          value.forEach((item)=> appendValue(key + '[]', item));
          return;
        }
        if(value instanceof Date){
          fd.append(key, value.toISOString());
          return;
        }
        if(isPlainObject(value)){
          Object.entries(value).forEach(([childKey, childValue])=>{
            appendValue(`${key}[${childKey}]`, childValue);
          });
          return;
        }
        fd.append(key, value);
      };
      if(Array.isArray(body)){
        body.forEach((value, index)=> appendValue(String(index), value));
      } else {
        Object.entries(body).forEach(([k,v])=> appendValue(k, v));
      }
      body = fd;
    }
    if(body) init.body = body;
  
    const csrfToken = resolveCsrfToken();
    if(csrfToken && body instanceof FormData){
      if(!body.has('_token')) body.append('_token', csrfToken);
      if(!body.has('_csrf')) body.append('_csrf', csrfToken);
      init.headers['X-CSRF-TOKEN'] = csrfToken;
    }
    const resp = await fetch(url, init);
    return await parseApiResponse(resp);
  }

  api.get = function(path, params){
    if(params && typeof params === 'object'){
      const usp = new URLSearchParams();
      Object.entries(params).forEach(([k,v])=>{ if(v!==undefined && v!==null) usp.append(k,v); });
      const q = usp.toString();
      if(q) path += (path.includes('?')?'&':'?') + q;
    }
    return api(path, { method:'GET' });
  };
  api.post = function(path, body){ return api(path, { method:'POST', body: body || {} }); };

  function createFeedback(){
    const TYPE_CLASS = { success:'panel-flash--success', error:'panel-flash--error', info:'panel-flash--info' };

    function resolve(target){
      if(!target) return null;
      if(typeof target === 'string') return document.querySelector(target);
      if(target && typeof target === 'object' && target.nodeType === 1) return target;
      return null;
    }

    function clearTimer(el){ if(el && el.__panelFlashTimer){ clearTimeout(el.__panelFlashTimer); el.__panelFlashTimer = null; } }

    function hide(el){
      if(!el) return;
      clearTimer(el);
      el.classList.remove('panel-flash--success','panel-flash--error','panel-flash--info','is-visible');
      el.hidden = true;
      el.textContent = '';
    }

    function show(target,type,message,opts){
      const el = resolve(target);
      if(!el) return;
      const options = opts || {};
      clearTimer(el);
      el.classList.add('panel-flash');
      el.classList.remove('panel-flash--success','panel-flash--error','panel-flash--info');
      const key = (type||'').toLowerCase();
      const cls = TYPE_CLASS[key];
      if(cls) el.classList.add(cls);
      const allowHtml = !!options.allowHtml;
      const text = message==null? '' : message;
      if(allowHtml){ el.innerHTML = text; }
      else { el.textContent = String(text); }
      el.hidden = false;
      el.classList.add('is-visible');
      const duration = typeof options.duration === 'number' ? options.duration : 5000;
      if(duration > 0){
        el.__panelFlashTimer = setTimeout(()=> hide(el), duration);
      } else {
        clearTimer(el);
      }
    }

    function success(target,message,opts){ show(target,'success',message,opts); }
    function error(target,message,opts){ show(target,'error',message,opts); }
    function info(target,message,opts){ show(target,'info',message,opts); }

    function clear(target){ const el = resolve(target); if(el) hide(el); }

    return { show, success, error, info, clear };
  }

  const PanelContext = {
    base: BASE,
    url: buildUrl,
    api,
    feedback: createFeedback(),
    i18n: (path, fallback) => getLocale(path, fallback),
    t: (path, fallback) => getLocale(path, fallback),
    setLocale,
    extendLocale: setLocale,
    moduleLocale: (moduleName, path, fallback) => moduleLocaleValue(moduleName, path, fallback),
    registerModuleLocale(moduleName, data){
      if(!moduleName) return;
      setLocale(['modules', moduleName], data);
    },
    localeTree: localeStore,
    createModuleTranslator(moduleName){
      return (path, fallback) => moduleLocaleValue(moduleName, path, fallback);
    }
  };
  window.Panel = PanelContext;
  if(window.PANEL_LOCALE && typeof window.PANEL_LOCALE === 'object'){
    setLocale(window.PANEL_LOCALE);
  }

  (function initSharedUi(){
    function applyMetrics(){
      const metrics = window.PANEL_METRICS;
      if(!metrics || typeof metrics !== 'object') return;
      const el = document.getElementById('sidebar-metrics');
      if(!el) return;
      const span = el.querySelector('span');
      const text = metrics.text || '';
      const title = metrics.title || '';
      if(span) span.textContent = text;
      else el.textContent = text;
      if(title) el.setAttribute('title', title);
    }

    function bindLanguageSwitch(){
      const select = document.getElementById('panelLanguageSelect');
      if(!select || select.__panelBound) return;
      select.__panelBound = true;
      select.addEventListener('change', function(){
        const url = new URL(window.location.href);
        url.searchParams.set('lang', this.value);
        window.location.href = url.toString();
      });
    }

    function bindServerSwitch(){
      document.querySelectorAll('[data-panel-server-switch]').forEach((select)=>{
        if(select.__panelBound) return;
        select.__panelBound = true;
        select.addEventListener('change', ()=>{
          const url = new URL(window.location.href);
          url.searchParams.delete('page');
          url.searchParams.set('server', select.value);
          window.location.href = url.toString();
        });
      });
    }

    function loadPageModule(){
      const moduleName = typeof window.PANEL_PAGE_SCRIPT_MODULE === 'string'
        ? window.PANEL_PAGE_SCRIPT_MODULE
        : '';
      const scriptSrc = typeof window.PANEL_PAGE_SCRIPT_SRC === 'string'
        ? window.PANEL_PAGE_SCRIPT_SRC
        : '';
      if(!moduleName && !scriptSrc) return;
      window.__PANEL_MODULES_LOADED = window.__PANEL_MODULES_LOADED || {};
      if(window.__PANEL_MODULES_LOADED[moduleName]) return;
      window.__PANEL_MODULES_LOADED[moduleName] = true;

      const script = document.createElement('script');
      script.src = scriptSrc || PanelContext.url('/assets/js/modules/' + moduleName + '.js');
      document.body.appendChild(script);
    }

    applyMetrics();
    bindLanguageSwitch();
    bindServerSwitch();
    loadPageModule();
  })();

  (function(){
    if(window.GameMetaColorize) return;
    function replacePrefixedClass(el, prefix, value){
      if(!el) return;
      Array.from(el.classList).forEach((className)=>{
        if(className.indexOf(prefix) === 0){
          el.classList.remove(className);
        }
      });
      if(value !== '' && value !== null && value !== undefined){
        el.classList.add(prefix + value);
      }
    }

    function apply(){
      document.querySelectorAll('[data-class-id]').forEach(el=>{
        const id = parseInt(el.getAttribute('data-class-id'),10);
        replacePrefixedClass(el, 'game-class-color-', Number.isNaN(id) ? '' : String(id));
      });
      document.querySelectorAll('[data-item-quality]').forEach(el=>{
        const q = parseInt(el.getAttribute('data-item-quality'),10);
        replacePrefixedClass(el, 'item-quality-q', Number.isNaN(q) ? '' : String(q));
      });
    }
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply); else apply();
    window.GameMetaColorize = apply;
  })();


  (function(){
    if(window.Modal) return;
    const registry = new Map();
    const widthClassMap = {
      '760px': 'modal-panel--760',
      '820px': 'modal-panel--820'
    };

    function applyWidthClass(panel, width){
      if(!panel) return;
      Object.values(widthClassMap).forEach((className)=> panel.classList.remove(className));
      const widthClass = widthClassMap[String(width || '').trim()] || '';
      if(widthClass) panel.classList.add(widthClass);
    }

    function syncBodyModalState(){
      const hasActiveModal = !!document.querySelector('.modal-backdrop.active');
      document.body.classList.toggle('modal-open', hasActiveModal);
    }

    function ensure(id){
      if(registry.has(id)) return registry.get(id);
      let el = document.getElementById('modal-' + id);
      if(!el){
        el = document.createElement('div');
        el.className = 'modal-backdrop';
        el.id = 'modal-' + id;
        el.innerHTML = [
          '<div class="modal-panel" data-role="panel">',
          '  <header><h3 data-role="title"></h3><button class="modal-close" data-close>&times;</button></header>',
          '  <div class="modal-body modal-scroll" data-role="body"></div>',
          '  <footer class="modal-footer-right" data-role="footer"></footer>',
          '</div>'
        ].join('');
        document.body.appendChild(el);
      }
      if(!el.__bound){
        el.addEventListener('click', e=>{ if(e.target === el) hide(id); });
        el.querySelector('[data-close]').addEventListener('click', ()=> hide(id));
        el.__bound = true;
      }
      const ref = {
        id,
        el,
        titleEl: el.querySelector('[data-role="title"]'),
        bodyEl: el.querySelector('[data-role="body"]'),
        footerEl: el.querySelector('[data-role="footer"]')
      };
      registry.set(id, ref);
      return ref;
    }
    function show(opts){
      const { id, title, content, footer, width } = opts;
      const ref = ensure(id);
      const panel = ref.el.querySelector('[data-role="panel"]');
      if(title !== undefined) ref.titleEl.textContent = title;
      if(content !== undefined) ref.bodyEl.innerHTML = content;
      if(footer !== undefined) ref.footerEl.innerHTML = footer;
      else if(!ref.footerEl.innerHTML){
        const closeLabel = String(getLocale(['common','actions','close'], 'Close'));
        ref.footerEl.innerHTML = '<button class="btn" data-close>'+closeLabel+'</button>';
        ref.footerEl.querySelector('[data-close]').addEventListener('click', ()=> hide(id));
      }
      applyWidthClass(panel, width);
      ref.el.classList.add('active');
      syncBodyModalState();
      return ref;
    }
    function hide(id){
      const ref = registry.get(id);
      if(!ref) return;
      ref.el.classList.remove('active');
      syncBodyModalState();
    }
    function hideAll(){ registry.forEach((_, key)=> hide(key)); }
    function updateContent(id, html){ const ref = ensure(id); ref.bodyEl.innerHTML = html; }
    function append(id, html){ const ref = ensure(id); ref.bodyEl.insertAdjacentHTML('beforeend', html); }
    window.addEventListener('keydown', e=>{ if(e.key === 'Escape'){ hideAll(); }});
    window.Modal = { show, hide, hideAll, updateContent, append };
  })();


  if(!window.__FETCH_CSRF_PATCHED){
    window.__FETCH_CSRF_PATCHED = true;
    const _origFetch = window.fetch;
    window.fetch = function(input, init){
      init = init || {};
      if(!('credentials' in init)) init.credentials = 'same-origin';
      const method = (init.method || 'GET').toUpperCase();
      const csrfToken = resolveCsrfToken();
      if(method !== 'GET' && method !== 'HEAD' && csrfToken){
        if(init.body instanceof FormData){
          if(!init.body.has('_csrf')) init.body.append('_csrf', csrfToken);
          if(!init.body.has('_token')) init.body.append('_token', csrfToken);
        } else if(init.body instanceof URLSearchParams){
          if(!init.body.has('_csrf')) init.body.append('_csrf', csrfToken);
          if(!init.body.has('_token')) init.body.append('_token', csrfToken);
        } else if(typeof init.body === 'string' && (init.headers||{})['Content-Type'] === 'application/json'){
          try {
            const obj = JSON.parse(init.body);
            if(!obj._csrf && !obj._token){ obj._csrf = csrfToken; obj._token = csrfToken; }
            init.body = JSON.stringify(obj);
          }catch(e){ /* ignore parse error */ }
        } else if(init.body && typeof init.body === 'object'){ // plain object => convert
          const fd = new FormData();
            Object.entries(init.body).forEach(([k,v])=>fd.append(k,v));
            if(!fd.has('_csrf')) fd.append('_csrf', csrfToken);
            if(!fd.has('_token')) fd.append('_token', csrfToken);
            init.body = fd;
        }
        init.headers = init.headers || {};
        if(!('X-CSRF-TOKEN' in init.headers)) init.headers['X-CSRF-TOKEN'] = csrfToken;
      }
      return _origFetch.call(this,input,init);
    };
  }
})();
