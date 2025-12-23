// Global Panel helper (dynamic base path + fetch wrappers)
(function(){
  if(window.Panel) return; // idempotent
  const BASE = (window.APP_BASE||'').replace(/\/$/,'');
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
  
    if(window.__CSRF_TOKEN && body instanceof FormData){
      if(!body.has('_token')) body.append('_token', window.__CSRF_TOKEN);
      if(!body.has('_csrf')) body.append('_csrf', window.__CSRF_TOKEN);
      init.headers['X-CSRF-TOKEN'] = window.__CSRF_TOKEN;
    }
    const resp = await fetch(url, init);
    const ctype = resp.headers.get('Content-Type')||'';
    if(ctype.includes('application/json')){
      return await resp.json();
    }
    const txt = await resp.text();
    try { return JSON.parse(txt); } catch(e){
      const fallbackMsg = getLocale(['common','errors','invalid_json'], 'Invalid JSON');
      return { success:false, message:fallbackMsg, raw:txt, status:resp.status };
    }
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
      el.style.display = 'none';
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
      el.style.display = '';
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

  (function(){
    if(window.GameMetaColorize) return;
    const classColors = { 1:'C69B6D',2:'F48CBA',3:'AAD372',4:'FFF468',5:'FFFFFF',6:'C41E3A',7:'0070DD',8:'3FC7EB',9:'8788EE',10:'00FF96',11:'FF7C0A',12:'A330C9' };
    const qualityColors = { 0:'9D9D9D',1:'FFFFFF',2:'1EFF00',3:'0070DD',4:'A335EE',5:'FF8000',6:'E6CC80',7:'00CCFF' };
    function apply(){
      document.querySelectorAll('[data-class-id]').forEach(el=>{
        const id = parseInt(el.getAttribute('data-class-id'),10);
        if(classColors[id]) el.style.color = '#' + classColors[id];
      });
      document.querySelectorAll('[data-item-quality]').forEach(el=>{
        const q = parseInt(el.getAttribute('data-item-quality'),10);
        if(qualityColors[q]) el.style.color = '#' + qualityColors[q];
      });
    }
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply); else apply();
    window.GameMetaColorize = apply;
  })();


  (function(){
    if(window.Modal) return;
    const registry = new Map();
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
      if(title !== undefined) ref.titleEl.textContent = title;
      if(content !== undefined) ref.bodyEl.innerHTML = content;
      if(footer !== undefined) ref.footerEl.innerHTML = footer;
      else if(!ref.footerEl.innerHTML){
        const closeLabel = String(getLocale(['common','actions','close'], 'Close'));
        ref.footerEl.innerHTML = '<button class="btn" data-close>'+closeLabel+'</button>';
        ref.footerEl.querySelector('[data-close]').addEventListener('click', ()=> hide(id));
      }
      if(width){ ref.el.querySelector('[data-role="panel"]').style.maxWidth = width; }
      ref.el.classList.add('active');
      document.body.style.overflow = 'hidden';
      return ref;
    }
    function hide(id){
      const ref = registry.get(id);
      if(!ref) return;
      ref.el.classList.remove('active');
      document.body.style.overflow = '';
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
      if(method !== 'GET' && method !== 'HEAD' && window.__CSRF_TOKEN){
        if(init.body instanceof FormData){
          if(!init.body.has('_csrf')) init.body.append('_csrf', window.__CSRF_TOKEN);
          if(!init.body.has('_token')) init.body.append('_token', window.__CSRF_TOKEN);
        } else if(init.body instanceof URLSearchParams){
          if(!init.body.has('_csrf')) init.body.append('_csrf', window.__CSRF_TOKEN);
          if(!init.body.has('_token')) init.body.append('_token', window.__CSRF_TOKEN);
        } else if(typeof init.body === 'string' && (init.headers||{})['Content-Type'] === 'application/json'){
          try {
            const obj = JSON.parse(init.body);
            if(!obj._csrf && !obj._token){ obj._csrf = window.__CSRF_TOKEN; obj._token = window.__CSRF_TOKEN; }
            init.body = JSON.stringify(obj);
          }catch(e){ /* ignore parse error */ }
        } else if(init.body && typeof init.body === 'object'){ // plain object => convert
          const fd = new FormData();
            Object.entries(init.body).forEach(([k,v])=>fd.append(k,v));
            if(!fd.has('_csrf')) fd.append('_csrf', window.__CSRF_TOKEN);
            if(!fd.has('_token')) fd.append('_token', window.__CSRF_TOKEN);
            init.body = fd;
        }
        init.headers = init.headers || {};
        if(!('X-CSRF-TOKEN' in init.headers)) init.headers['X-CSRF-TOKEN'] = window.__CSRF_TOKEN;
      }
      return _origFetch.call(this,input,init);
    };
  }
})();
