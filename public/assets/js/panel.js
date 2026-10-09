// Global Panel helper (dynamic base path + fetch wrappers)
(function(){
  if(window.Panel) return;

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
    if(/^https?:\/\//i.test(path)) return path;
    if(path[0] !== '/') path = '/' + path;
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

  /**
   * 原始响应体只进控制台，不进界面：后端抛出的 HTML 错误页/堆栈对操作者没有意义，
   * 展示出来还会泄露路径与 SQL。用户看到的是可读文案，排障靠 console。
   */
  function logRawPayload(label, status, raw){
    console.error('[panel] ' + label + ' (HTTP ' + status + ')', raw);
  }

  async function parseApiResponse(resp){
    const fallbackMsg = getLocale(['common','errors','invalid_json'], 'Invalid JSON');
    const text = await resp.text();
    const parsed = parseApiText(text);
    if(parsed !== null) return parsed;
    logRawPayload('non-JSON response', resp.status, text);
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
    let resp;
    try {
      resp = await fetch(url, init);
    } catch (cause) {
      // 网络层失败（断线、请求被拦、超时）也包成同一个形状：调用方只需要看 success/message，
      // 原始异常留给控制台。
      logRawPayload('request failed: ' + url, 0, cause);
      return {
        success:false,
        message:getLocale(['common','errors','network'], 'Network error, please retry'),
        network:true,
        raw:cause && cause.message ? String(cause.message) : String(cause)
      };
    }
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

    /** 通知条的屏幕阅读器语义：错误用 alert（打断播报），其余用 status（择机播报）。 */
    function applyLiveSemantics(el, severity){
      if(!el) return;
      if(severity === 'error'){
        el.setAttribute('role', 'alert');
        el.setAttribute('aria-live', 'assertive');
      } else {
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
      }
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
      applyLiveSemantics(el, key === 'error' ? 'error' : 'info');
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

  /** HTML 转义。面板里十几个模块各写了一份自己的 esc，这里是共用的一份。 */
  function escapeHtmlText(value){
    return String(value ?? '').replace(/[&<>"']/g, (ch)=>({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
  }

  /**
   * 物品选择器（combobox）：把"让 GM 背 item entry"换成"按名字搜"。
   *
   * 服务端渲染的骨架约定：
   *   <div data-au-picker [data-au-picker-multiple="1"]>
   *     <input type="hidden" name="item" data-au-picker-value>          <- 表单真正提交的字段
   *     <input type="text" data-au-picker-search role="combobox" ...>   <- 可见的搜索框
   *     <ul data-au-picker-list role="listbox" hidden></ul>
   *   </div>
   * 隐藏字段沿用原来的 name，所以表单处理器与后端契约都不用改。
   *
   * 键盘：上下键移动、Enter 选中、Esc 关闭；多选模式下搜索框为空时 Backspace 删掉最后一个。
   * 请求带序号，过期响应直接丢弃——否则快速输入时后到的旧结果会覆盖新结果。
   *
   * @param {Element|string} target  [data-au-picker] 容器
   * @param {object} [options]
   * @param {string} [options.endpoint='/auctionator/api/items']
   * @param {object} [options.params]    每次请求都带上的额外查询参数（例如 server）
   * @param {number} [options.limit=20]
   * @param {number} [options.debounceMs=180]
   * @param {number} [options.minChars=1]
   * @param {boolean} [options.multiple] 默认读容器的 data-au-picker-multiple
   * @param {Function} [options.onSelect] (item, instance) => void
   * @returns {{value: Function, values: Function, set: Function, clear: Function, focus: Function}|null}
   */
  function createItemPicker(target, options){
    const opts = options || {};
    const host = typeof target === 'string' ? document.querySelector(target) : target;
    if(!host) return null;
    if(host.__itemPicker) return host.__itemPicker;

    const searchInput = host.querySelector('[data-au-picker-search]');
    const valueInput = host.querySelector('[data-au-picker-value]');
    const list = host.querySelector('[data-au-picker-list]');
    if(!searchInput || !valueInput || !list) return null;

    const multiple = opts.multiple !== undefined ? !!opts.multiple : host.getAttribute('data-au-picker-multiple') === '1';
    const endpoint = opts.endpoint || '/auctionator/api/items';
    const limit = opts.limit || 20;
    const debounceMs = opts.debounceMs === undefined ? 180 : opts.debounceMs;
    const minChars = opts.minChars === undefined ? 1 : opts.minChars;
    const extraParams = opts.params || {};
    const onSelect = typeof opts.onSelect === 'function' ? opts.onSelect : null;
    const label = (path, fallback)=> getLocale(path, fallback);

    let selected = [];
    let matches = [];
    let activeIndex = -1;
    let timer = null;
    let seq = 0;

    if(!list.id) list.id = 'panel-picker-' + Math.random().toString(36).slice(2, 8);
    searchInput.setAttribute('aria-controls', list.id);
    searchInput.setAttribute('aria-expanded', 'false');

    function close(){
      list.hidden = true;
      list.innerHTML = '';
      matches = [];
      activeIndex = -1;
      searchInput.setAttribute('aria-expanded', 'false');
      searchInput.removeAttribute('aria-activedescendant');
    }

    function syncValue(){
      valueInput.value = selected.map((item)=> item.entry).join(',');
      host.classList.toggle('has-value', selected.length > 0);
    }

    function renderChips(){
      const box = host.querySelector('[data-au-picker-chips]');
      if(!box) return;
      box.innerHTML = selected.map((item, index)=> [
        '<span class="au-picker__chip">',
        '<span class="au-picker__chip-name ' + (item.quality === null || item.quality === undefined ? '' : 'item-quality-q' + item.quality) + '">',
        escapeHtmlText(item.name || ('#' + item.entry)),
        '</span>',
        ' <span class="au-picker__chip-id">#' + item.entry + '</span>',
        '<button type="button" class="au-picker__chip-x" data-au-picker-remove="' + index + '" aria-label="'
          + escapeHtmlText(label('common.actions.remove', 'Remove')) + '">&times;</button>',
        '</span>'
      ].join('')).join('');
    }

    function highlight(index){
      if(!matches.length){ return; }
      activeIndex = (index + matches.length) % matches.length;
      Array.prototype.forEach.call(list.children, (node, i)=>{
        const on = i === activeIndex;
        node.classList.toggle('is-active', on);
        node.setAttribute('aria-selected', on ? 'true' : 'false');
        if(on){
          const id = list.id + '-opt-' + i;
          node.id = id;
          searchInput.setAttribute('aria-activedescendant', id);
          if(typeof node.scrollIntoView === 'function') node.scrollIntoView({ block: 'nearest' });
        }
      });
    }

    function renderMatches(){
      if(!matches.length){
        const hasQuery = searchInput.value.trim().length >= minChars;
        list.innerHTML = '<li class="au-picker__empty" role="presentation">'
          + escapeHtmlText(hasQuery ? label('common.picker.no_match', 'No matching item') : label('common.picker.type_to_search', 'Type a name or item id'))
          + '</li>';
        list.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
        activeIndex = -1;
        return;
      }

      list.innerHTML = matches.map((item, i)=> {
        const q = item.quality === null || item.quality === undefined ? '' : ' item-quality-q' + item.quality;
        return '<li class="au-picker__option" role="option" aria-selected="false" data-index="' + i + '">'
          + '<span class="au-picker__option-name' + q + '">' + escapeHtmlText(item.name || ('#' + item.entry)) + '</span>'
          + '<span class="au-picker__option-id">#' + item.entry + '</span>'
          + '</li>';
      }).join('');
      list.hidden = false;
      searchInput.setAttribute('aria-expanded', 'true');
      highlight(0);
    }

    async function search(){
      const keyword = searchInput.value.trim();
      if(keyword.length < minChars){ close(); return; }

      list.innerHTML = '<li class="au-picker__empty" role="presentation">' + escapeHtmlText(label('common.loading', 'Loading…')) + '</li>';
      list.hidden = false;
      searchInput.setAttribute('aria-expanded', 'true');

      const mine = ++seq;
      let payload = null;
      try{
        payload = await api.get(endpoint, Object.assign({ keyword, limit }, extraParams));
      }catch(error){
        payload = null;
      }
      if(mine !== seq) return;          // 已经有更新的请求了，丢掉这份过期结果

      if(!payload || payload.success === false){
        list.innerHTML = '<li class="au-picker__empty" role="presentation">'
          + escapeHtmlText((payload && payload.message) || label('common.api.errors.request_failed_retry', 'Request failed, try again later'))
          + '</li>';
        matches = [];
        return;
      }
      matches = payload.items || payload.data || [];
      renderMatches();
    }

    function commit(item){
      if(!item) return;
      if(multiple){
        if(!selected.some((row)=> row.entry === item.entry)) selected.push(item);
        renderChips();
      }else{
        selected = [item];
        searchInput.value = item.name || ('#' + item.entry);
      }
      syncValue();
      if(multiple){ searchInput.value = ''; }
      close();
      if(onSelect) onSelect(item, instance);
      if(multiple) searchInput.focus();
    }

    function removeAt(index){
      selected.splice(index, 1);
      renderChips();
      syncValue();
      if(onSelect) onSelect(null, instance);
    }

    searchInput.addEventListener('input', ()=>{
      if(timer) window.clearTimeout(timer);
      timer = window.setTimeout(search, debounceMs);
    });

    searchInput.addEventListener('keydown', (event)=>{
      if(event.key === 'ArrowDown'){ event.preventDefault(); if(list.hidden){ search(); } else { highlight(activeIndex + 1); } }
      else if(event.key === 'ArrowUp'){ event.preventDefault(); highlight(activeIndex - 1); }
      else if(event.key === 'Enter'){
        if(!list.hidden && matches.length){ event.preventDefault(); commit(matches[activeIndex < 0 ? 0 : activeIndex]); }
      }
      else if(event.key === 'Escape'){ close(); }
      else if(event.key === 'Backspace' && multiple && searchInput.value === '' && selected.length){ removeAt(selected.length - 1); }
    });

    searchInput.addEventListener('focus', ()=>{
      if(searchInput.value.trim().length >= minChars && list.hidden && !matches.length) search();
    });
    searchInput.addEventListener('blur', ()=>{ window.setTimeout(()=>{ if(!host.contains(document.activeElement)) close(); }, 120); });

    list.addEventListener('mousedown', (event)=>{
      // mousedown 而不是 click：blur 先跑会把列表关掉
      const option = event.target.closest ? event.target.closest('[data-index]') : null;
      if(!option) return;
      event.preventDefault();
      commit(matches[Number(option.getAttribute('data-index'))]);
    });

    host.addEventListener('click', (event)=>{
      const remove = event.target.closest ? event.target.closest('[data-au-picker-remove]') : null;
      if(remove){ event.preventDefault(); removeAt(Number(remove.getAttribute('data-au-picker-remove'))); }
    });

    const instance = {
      value: ()=> valueInput.value,
      values: ()=> selected.slice(),
      set(items){
        selected = (Array.isArray(items) ? items : []).filter((item)=> item && item.entry);
        if(!multiple && selected.length){ searchInput.value = selected[0].name || ('#' + selected[0].entry); }
        renderChips();
        syncValue();
      },
      clear(){
        selected = [];
        searchInput.value = '';
        renderChips();
        syncValue();
        close();
      },
      focus: ()=> searchInput.focus()
    };

    syncValue();
    host.__itemPicker = instance;
    return instance;
  }

  /**
   * 表格客户端过滤：给已经渲染好的表加一个搜索框，纯前端过滤，不发请求。
   *
   * 约定（服务端只要在 <table> 上写 data-au-searchable 即可，ids 都不用给）：
   *   <table class="au-table" data-au-searchable data-filter-empty="没有匹配的行">
   * JS 在 .au-table-wrap 之前插入搜索框，并管理"全部被过滤掉"时的空行。
   * 可重复调用：已经绑过的表会被跳过，所以局部刷新之后直接再调一次就行。
   *
   * @param {Element|string} [root] 扫描范围，默认 document
   * @param {object} [defaults] 没有在表上写 data-filter-* 时用的默认文案
   * @param {string} [defaults.emptyLabel]
   * @param {string} [defaults.placeholder]
   * @returns {Array<{table: Element, apply: Function}>}
   */
  function bindTableFilters(root, defaults){
    const fallback = defaults || {};
    const scope = typeof root === 'string' ? document.querySelector(root) : (root || document);
    if(!scope) return [];

    const bound = [];
    Array.prototype.forEach.call(scope.querySelectorAll('table[data-au-searchable]'), (table)=>{
      if(table.__panelTableFilter) { bound.push(table.__panelTableFilter); return; }
      const tbody = table.tBodies[0];
      if(!tbody) return;

      const emptyLabel = table.getAttribute('data-filter-empty') || fallback.emptyLabel || getLocale(['common','no_data'], 'No data');
      const placeholder = table.getAttribute('data-filter-placeholder') || fallback.placeholder || getLocale(['common','search_placeholder'], 'Search…');

      const tools = document.createElement('div');
      tools.className = 'table-filter-tools';
      tools.innerHTML = '<input type="search" class="au-input table-filter-input" autocomplete="off"'
        + ' aria-label="' + escapeHtmlText(placeholder) + '" placeholder="' + escapeHtmlText(placeholder) + '">';

      const wrap = table.closest('.au-table-wrap') || table.parentNode;
      if(wrap && wrap.parentNode) wrap.parentNode.insertBefore(tools, wrap);
      const input = tools.querySelector('input');

      // 「没有匹配的行」这一行由 JS 维护，不影响原来的空状态行
      let noneRow = tbody.querySelector('.js-filter-none');
      if(!noneRow){
        noneRow = document.createElement('tr');
        noneRow.className = 'js-filter-none';
        noneRow.hidden = true;
        const cell = document.createElement('td');
        cell.colSpan = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells.length : 1;
        cell.className = 'muted small';
        cell.textContent = emptyLabel;
        noneRow.appendChild(cell);
        tbody.appendChild(noneRow);
      }

      function apply(){
        const query = (input.value || '').trim().toLowerCase();
        let visible = 0;

        Array.prototype.forEach.call(tbody.rows, (row)=>{
          if(row === noneRow) return;
          const text = (row.textContent || '').toLowerCase();
          const match = query === '' || text.indexOf(query) >= 0;
          // 原来的"表是空的"提示行只在没有查询时才由数据决定显示
          if(row.classList.contains('js-empty-row')){
            row.hidden = query !== '';
            return;
          }
          row.hidden = !match;
          if(match) visible++;
        });

        noneRow.hidden = !(query !== '' && visible === 0);
        tools.classList.toggle('is-filtering', query !== '');
      }

      input.addEventListener('input', apply);
      apply();

      table.__panelTableFilter = { table, input, apply };
      bound.push(table.__panelTableFilter);
    });

    return bound;
  }

  /**
   * 带后果说明的确认框，取代 window.confirm。
   *
   * 返回 Promise<boolean>：确定 true；取消 / Esc / 点背景都 false。
   * opts.requireText 非空时要求照打一段文字（用于"会波及全服玩家"这类不可逆操作）。
   *
   * 用 window.Modal 渲染（同一套 .modal-backdrop 样式），并补上 Modal 缺的东西：
   * role="dialog" / aria-modal、打开时移入焦点、关闭后把焦点还给触发元素、
   * 以及让 Esc 与背景点击都能把 Promise 落地（否则 await 会永远挂住）。
   *
   * @param {object} options
   * @param {string} options.message      后果说明（必填，纯文本，会被转义）
   * @param {string} [options.title]
   * @param {string} [options.confirmLabel]
   * @param {string} [options.cancelLabel]
   * @param {boolean} [options.danger]    确定按钮用危险色
   * @param {string} [options.requireText] 需要照打的文字；非空时确定按钮初始禁用
   * @param {string} [options.requireLabel] 提示"请照打 :text"
   * @returns {Promise<boolean>}
   */
  function createConfirm(){
    const MODAL_ID = 'panel-confirm';
    let resolver = null;
    let keyHandler = null;
    let trigger = null;

    function finish(value){
      const resolve = resolver;
      resolver = null;
      if(keyHandler){ window.removeEventListener('keydown', keyHandler, true); keyHandler = null; }
      if(window.Modal) window.Modal.hide(MODAL_ID);
      const back = trigger;
      trigger = null;
      if(back && typeof back.focus === 'function' && document.contains(back)) back.focus();
      if(resolve) resolve(value);
    }

    return function confirm(options){
      const opts = options || {};
      if(!window.Modal){
        // 没有 Modal 就退回原生确认，至少不会静默丢掉这次操作
        return Promise.resolve(window.confirm(String(opts.message || '')));
      }

      // 上一次还没被回答就再来一次：把旧的当成取消，避免 Promise 悬挂
      if(resolver) finish(false);

      const required = String(opts.requireText || '');
      const okLabel = opts.confirmLabel || getLocale(['common','actions','confirm'], 'Confirm');
      const cancelLabel = opts.cancelLabel || getLocale(['common','actions','cancel'], 'Cancel');

      const body = '<p class="panel-confirm__message">' + escapeHtmlText(opts.message || '') + '</p>'
        + (required === ''
          ? ''
          : '<label class="panel-confirm__gate"><span>'
            + escapeHtmlText(opts.requireLabel || '') + '</span>'
            + '<input class="au-input" type="text" data-confirm-gate autocomplete="off" spellcheck="false"></label>');

      const footer = '<button type="button" class="btn" data-confirm-cancel>' + escapeHtmlText(cancelLabel) + '</button>'
        + '<button type="button" class="btn ' + (opts.danger ? 'danger' : 'primary') + '" data-confirm-ok>'
        + escapeHtmlText(okLabel) + '</button>';

      trigger = document.activeElement;
      const ref = window.Modal.show({
        id: MODAL_ID,
        title: opts.title || '',
        content: body,
        footer: footer,
        width: ''
      });

      ref.el.setAttribute('role', 'dialog');
      ref.el.setAttribute('aria-modal', 'true');
      if(opts.title) ref.el.setAttribute('aria-label', String(opts.title));

      const okBtn = ref.footerEl.querySelector('[data-confirm-ok]');
      const cancelBtn = ref.footerEl.querySelector('[data-confirm-cancel]');
      const gate = ref.bodyEl.querySelector('[data-confirm-gate]');

      if(gate){
        const sync = ()=>{ okBtn.disabled = gate.value.trim().toUpperCase() !== required.toUpperCase(); };
        gate.addEventListener('input', sync);
        sync();
      }

      cancelBtn.addEventListener('click', ()=> finish(false));
      okBtn.addEventListener('click', ()=>{ if(!okBtn.disabled) finish(true); });
      // 确认框不因误点背景而消失（Promise 会一直挂着），Esc 与「取消」都是明确的拒绝
      ref.el.__panelCloseOnBackdrop = false;

      keyHandler = (event)=>{
        if(event.key !== 'Escape') return;
        event.stopPropagation();
        event.preventDefault();
        finish(false);
      };
      window.addEventListener('keydown', keyHandler, true);

      const focusTarget = gate || okBtn;
      if(focusTarget && typeof focusTarget.focus === 'function') focusTarget.focus();

      return new Promise((resolve)=>{ resolver = resolve; });
    };
  }

  /**
   * 局部刷新：取同一个路由的 HTML，只替换指定区域。
   *
   * 整页 location.reload() 会把滚动位置、筛选条件、分页与展开的 Tab 一起丢掉，而列表页
   * 一次删除/封禁之后真正变的只有那张表。区域替换后重新挂上全站增强，并广播
   * `panel:region` 事件让模块自己重绑（模块注册的监听都是 document 级委托，通常无需处理）。
   *
   * @param {string|Element} target 要替换的区域
   * @param {string} [url]          取 HTML 的地址（面板相对路径；默认当前地址）
   * @param {object} [options]
   * @param {string} [options.source] 从响应里取哪个选择器（默认与 target 相同）
   * @param {'inner'|'outer'} [options.mode='inner']
   * @param {Function} [options.onAfterSwap] (scope, parsedDocument) => void
   * @returns {Promise<boolean>} 是否成功替换
   */
  async function reloadRegion(target, url, options){
    const opts = options || {};
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    const selector = typeof target === 'string' ? target : opts.selector;
    if(!el || !selector) return false;

    const requestUrl = url || (window.location.pathname + window.location.search);
    let html;
    try {
      const resp = await fetch(buildUrl(requestUrl), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      });
      if(!resp.ok){
        console.error('[panel] reloadRegion failed', resp.status, requestUrl);
        return false;
      }
      html = await resp.text();
    } catch (error) {
      console.error('[panel] reloadRegion request failed', requestUrl, error);
      return false;
    }

    const parsed = new DOMParser().parseFromString(html, 'text/html');
    const next = parsed.querySelector(opts.source || selector);
    if(!next){
      console.error('[panel] reloadRegion: region missing in response', opts.source || selector);
      return false;
    }

    if(opts.mode === 'outer'){
      if(!el.parentNode) return false;
      el.replaceWith(next);
    } else {
      el.innerHTML = next.innerHTML;
    }

    const scope = opts.mode === 'outer' ? next : el;
    if(window.GameMetaColorize) window.GameMetaColorize();
    bindTableFilters(scope);
    document.dispatchEvent(new CustomEvent('panel:region', { detail: { scope } }));
    if(typeof opts.onAfterSwap === 'function') opts.onAfterSwap(scope, parsed);
    return true;
  }

  /**
   * 分页条（服务端 components/pagination.php 的同一套标记）。
   *
   * 传了 onNavigate 就渲染成按钮（AJAX 列表自己取数），否则用 urlFor 生成真实链接
   * （无 JS 也能翻页）。`aria-current="page"` 与总条数/跳页都在这里统一。
   *
   * @param {string|Element} target 承载分页条的容器
   * @param {object} options page / pages / total / perPage / onNavigate / urlFor / window
   * @returns {{el: Element, update: Function}|null}
   */
  function paginateBar(target, options){
    const opts = options || {};
    const host = typeof target === 'string' ? document.querySelector(target) : target;
    if(!host) return null;

    const label = (path, fallback)=> getLocale(path, fallback);
    const previousLabel = label(['common','pagination','previous'], 'Previous');
    const nextLabel = label(['common','pagination','next'], 'Next');
    const windowSize = Math.max(1, parseInt(opts.window, 10) || 3);

    function render(){
      const page = Math.max(1, parseInt(opts.page, 10) || 1);
      const pages = Math.max(0, parseInt(opts.pages, 10) || 0);
      const total = opts.total === undefined || opts.total === null ? null : Number(opts.total);
      const perPage = parseInt(opts.perPage, 10) || 0;

      host.textContent = '';
      if(pages <= 1){
        host.hidden = true;
        return;
      }
      host.hidden = false;

      const nav = document.createElement('nav');
      nav.className = 'pagination-bar';
      nav.setAttribute('aria-label', label(['common','pagination','label'], 'Pagination'));
      const list = document.createElement('ul');
      list.className = 'pagination-list';

      const makeItem = (targetPage, text, extraClass, ariaLabel, disabled, current)=>{
        const li = document.createElement('li');
        const tag = typeof opts.onNavigate === 'function' ? 'button' : 'a';
        const node = document.createElement(tag);
        node.className = 'pg' + (extraClass ? ' ' + extraClass : '') + (current ? ' active' : '');
        node.textContent = text;
        if(ariaLabel) node.setAttribute('aria-label', ariaLabel);
        if(ariaLabel) node.setAttribute('title', ariaLabel);
        if(tag === 'button') node.type = 'button';
        if(current) node.setAttribute('aria-current', 'page');
        if(disabled){
          node.classList.add('disabled');
          node.setAttribute('aria-disabled', 'true');
          if(tag === 'button') node.disabled = true;
        } else if(tag === 'a'){
          const href = typeof opts.urlFor === 'function' ? opts.urlFor(targetPage) : ('?page=' + targetPage);
          node.setAttribute('href', href);
        }
        if(!disabled && !current){
          node.addEventListener('click', (event)=>{
            event.preventDefault();
            if(typeof opts.onNavigate === 'function') opts.onNavigate(targetPage);
            else window.location.href = node.getAttribute('href');
          });
        }
        li.appendChild(node);
        return li;
      };

      const start = Math.max(1, page - windowSize);
      const end = Math.min(pages, page + windowSize);
      list.appendChild(makeItem(page - 1, '«', 'prev', previousLabel, page <= 1, false));
      if(start > 1){
        list.appendChild(makeItem(1, '1', '', null, false, page === 1));
        if(start > 2){
          const gap = document.createElement('li');
          gap.className = 'pagination-gap';
          gap.setAttribute('aria-hidden', 'true');
          gap.textContent = '…';
          list.appendChild(gap);
        }
      }
      for(let i = start; i <= end; i += 1){
        list.appendChild(makeItem(i, String(i), '', label(['common','pagination','page'], 'Page') + ' ' + i, false, i === page));
      }
      if(end < pages){
        if(end < pages - 1){
          const gap = document.createElement('li');
          gap.className = 'pagination-gap';
          gap.setAttribute('aria-hidden', 'true');
          gap.textContent = '…';
          list.appendChild(gap);
        }
        list.appendChild(makeItem(pages, String(pages), '', null, false, page === pages));
      }
      list.appendChild(makeItem(page + 1, '»', 'next', nextLabel, page >= pages, false));
      nav.appendChild(list);

      if(total !== null && total >= 0){
        const meta = document.createElement('div');
        meta.className = 'pagination-meta muted small';
        const from = perPage > 0 ? (page - 1) * perPage + 1 : 0;
        const to = perPage > 0 ? Math.min(page * perPage, total) : total;
        meta.textContent = perPage > 0
          ? label(['common','pagination','range'], ':from-:to of :total')
              .replace(':from', String(from)).replace(':to', String(to)).replace(':total', String(total))
          : label(['common','pagination','total'], ':total total').replace(':total', String(total));
        nav.appendChild(meta);
      }
      host.appendChild(nav);
    }

    render();
    return {
      el: host,
      update(next){
        if(next) Object.assign(opts, next);
        render();
      }
    };
  }

  /**
   * 金额单位解析：显式传入 > modules.<module>.money.units.* > modules.<module>.money.*
   * > modules.<module>.gold.units.* > common.money.units.* > 英文兜底。
   */
  function resolveMoneyUnit(name, moduleName, explicitUnits, fallback){
    if(explicitUnits && typeof explicitUnits[name] === 'string' && explicitUnits[name] !== '') return explicitUnits[name];
    const candidates = [];
    if(moduleName){
      candidates.push(['modules', moduleName, 'money', 'units', name]);
      candidates.push(['modules', moduleName, 'money', name]);
      candidates.push(['modules', moduleName, 'gold', 'units', name]);
    }
    candidates.push(['common','money','units', name]);
    for(const path of candidates){
      const value = getLocale(path, null);
      if(typeof value === 'string' && value !== '' && !looksLikeI18nKey(value)) return value;
    }
    return fallback;
  }

  /**
   * 铜币 → 「1 金 20 银 3 铜」。单位走语言包：模块自己的 modules.<module>.money.units.*
   * 或既有的 modules.<module>.gold.units.*，都没有时退回英文单位 —— 不再各写一套硬编码。
   */
  function formatMoney(copper, options){
    const opts = options || {};
    let amount = Number(copper);
    if(!Number.isFinite(amount) || amount < 0) amount = 0;
    amount = Math.floor(amount);

    const moduleName = opts.module ? String(opts.module) : '';
    const unit = (name, fallback)=> resolveMoneyUnit(name, moduleName, opts.units, fallback);

    const gold = Math.floor(amount / 10000);
    const silver = Math.floor((amount % 10000) / 100);
    const copperPart = amount % 100;
    const separator = opts.separator || ' ';
    const parts = [];
    if(gold > 0 || opts.always) parts.push(gold + separator + unit('gold', 'Gold'));
    if(silver > 0 || opts.always) parts.push(silver + separator + unit('silver', 'Silver'));
    if(copperPart > 0 || parts.length === 0) parts.push(copperPart + separator + unit('copper', 'Copper'));
    return parts.join(' ');
  }

  /**
   * 单个金额单位（给"12.34 金"这类自定格式用，避免为了取一个单位再造一份映射）。
   * @param {'gold'|'silver'|'copper'} name
   */
  function moneyUnit(name, options){
    const opts = options || {};
    return resolveMoneyUnit(String(name), opts.module ? String(opts.module) : '', opts.units, opts.fallback || String(name));
  }

  /** 剪贴板：HTTPS/localhost 走异步 API，其余（含 http 内网部署）退回 execCommand。 */
  async function copyText(text, options){
    const opts = options || {};
    const value = String(text ?? '');
    let ok = false;

    if(navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext){
      try {
        await navigator.clipboard.writeText(value);
        ok = true;
      } catch (error) {
        console.warn('[panel] clipboard API unavailable, falling back', error);
      }
    }
    if(!ok){
      try {
        const area = document.createElement('textarea');
        area.value = value;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        document.body.appendChild(area);
        area.select();
        ok = document.execCommand('copy');
        area.remove();
      } catch (error) {
        console.warn('[panel] copy fallback failed', error);
        ok = false;
      }
    }
    if(typeof opts.feedback === 'function') opts.feedback(ok);
    return ok;
  }

  const MODIFIER_KEYS = { ctrl: 'ctrlKey', control: 'ctrlKey', alt: 'altKey', shift: 'shiftKey', meta: 'metaKey', cmd: 'metaKey' };
  const EDITABLE_TAGS = { INPUT: true, TEXTAREA: true, SELECT: true };
  /**
   * 在输入框里也必须生效的键：它们不产生字符，被吞掉只会让用户以为快捷键坏了。
   * 可打印字符（如 `/`）相反 —— 在输入框里就是正常输入，不能被快捷键吃掉。
   */
  const EDITABLE_SAFE_KEYS = {
    escape: true, enter: true, tab: true, backspace: true, delete: true,
    arrowup: true, arrowdown: true, arrowleft: true, arrowright: true,
    home: true, end: true, pageup: true, pagedown: true
  };

  function comboOf(event){
    const parts = [];
    if(event.ctrlKey) parts.push('ctrl');
    if(event.altKey) parts.push('alt');
    if(event.shiftKey) parts.push('shift');
    if(event.metaKey) parts.push('meta');
    let key = String(event.key || '').toLowerCase();
    if(key === ' ' || key === 'spacebar') key = 'space';
    parts.push(key);
    return parts.join('+');
  }

  /**
   * 声明式页面快捷键。
   *
   * 可打印字符的单键（`/`、`k` 之类）在输入框里不触发 —— 否则会吃掉正常打字；
   * 不可打印键（`Escape`、`Enter`、方向键…）在输入框里照常触发，它们不产生字符，
   * 被吞掉只会让人以为快捷键坏了。带修饰键的组合（`ctrl+enter`）在任何地方都生效。
   * 返回句柄可 destroy。
   *
   * @param {Object<string, Function>} map 例如 {'/': fn, 'escape': fn, 'ctrl+enter': fn}
   * @param {object} [options]
   * @param {string|Element} [options.root] 生效范围，默认 document
   * @param {boolean} [options.preventDefault=true]
   * @returns {{destroy: Function}}
   */
  function bindHotkeys(map, options){
    const opts = options || {};
    const root = typeof opts.root === 'string' ? document.querySelector(opts.root) : (opts.root || document);
    const handlers = {};
    Object.entries(map || {}).forEach(([combo, handler])=>{
      if(typeof handler !== 'function') return;
      handlers[String(combo).toLowerCase().split('+').map((part)=> part.trim()).join('+')] = handler;
    });

    function onKeydown(event){
      const combo = comboOf(event);
      const handler = handlers[combo];
      if(!handler) return;
      const hasModifier = /(^|\+)(ctrl|alt|meta)\+/.test(combo) || combo.indexOf('shift+') === 0;
      const target = event.target;
      const editable = target && (EDITABLE_TAGS[target.tagName] || target.isContentEditable);
      const bareKey = combo.indexOf('+') === -1 ? combo : combo.slice(combo.lastIndexOf('+') + 1);
      if(editable && !hasModifier && !EDITABLE_SAFE_KEYS[bareKey]) return;
      if(opts.preventDefault !== false) event.preventDefault();
      handler(event);
    }

    root.addEventListener('keydown', onKeydown);
    return { destroy(){ root.removeEventListener('keydown', onKeydown); } };
  }

  function normalizeErrorList(errors){
    if(errors === null || errors === undefined) return [];
    const list = Array.isArray(errors) ? errors : [errors];
    return list.map((item)=>{
      if(item && typeof item === 'object'){
        return { field: item.field ? String(item.field) : '', message: String(item.message ?? '') };
      }
      return { field: '', message: String(item ?? '') };
    }).filter((item)=> item.message !== '');
  }

  /**
   * 逐字段渲染校验错误：.is-invalid + aria-invalid + 字段下错误文本 + 焦点移到第一个错处。
   * 只报第一条错误的做法会让用户来回提交好几轮。
   *
   * @param {Element|string} form
   * @param {Array<{field:string,message:string}>|string} errors
   * @param {object} [options] summarySelector 额外把汇总写到某个容器
   */
  function applyFormErrors(form, errors, options){
    const opts = options || {};
    const el = typeof form === 'string' ? document.querySelector(form) : form;
    if(!el) return [];
    const list = normalizeErrorList(errors);

    Array.prototype.forEach.call(el.querySelectorAll('.is-invalid'), (node)=>{
      node.classList.remove('is-invalid');
      node.removeAttribute('aria-invalid');
      node.removeAttribute('aria-describedby');
    });
    Array.prototype.forEach.call(el.querySelectorAll('[data-field-error]'), (node)=> node.remove());

    const focused = [];
    list.forEach((entry, index)=>{
      if(!entry.field) return;
      const input = el.querySelector('[name="' + entry.field.replace(/"/g, '\\"') + '"]');
      if(!input) return;
      const errorId = (input.id || ('panel-field-' + index)) + '-error';
      input.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', errorId);

      const holder = input.closest('.form-field, .massmail-field, .list-filter__field') || input.parentNode;
      const note = document.createElement('div');
      note.className = 'field-error small text-danger';
      note.id = errorId;
      note.setAttribute('data-field-error', '1');
      note.textContent = entry.message;
      if(holder && holder.parentNode) holder.appendChild(note);
      else if(input.parentNode) input.parentNode.insertBefore(note, input.nextSibling);
      focused.push(input);
    });

    if(opts.summarySelector){
      const summary = document.querySelector(opts.summarySelector);
      if(summary){
        const generic = list.filter((item)=> !item.field);
        summary.textContent = generic.length ? generic.map((item)=> item.message).join(' · ') : '';
        summary.hidden = summary.textContent === '';
      }
    }

    if(focused.length && typeof focused[0].focus === 'function') focused[0].focus();
    return focused;
  }

  /**
   * 整页刷新但把滚动位置带回来。
   *
   * 用于确实需要重取整页的场景（保存配置后多处区块都会变、局部替换拿不到可信页面时的兜底）。
   * 筛选条件、分页、Tab 都在 URL 里；刷新后仍在，只需保住滚动位置。
   */
  function reloadKeepingPosition(){
    try {
      window.sessionStorage.setItem('panel:scroll:' + window.location.pathname, String(window.scrollY || 0));
    } catch (error) { /* 隐私模式下 sessionStorage 不可用 */ }
    window.location.reload();
  }

  function restoreScrollPosition(){
    let saved = null;
    try { saved = window.sessionStorage.getItem('panel:scroll:' + window.location.pathname); } catch (error) { saved = null; }
    if(saved === null) return;
    try { window.sessionStorage.removeItem('panel:scroll:' + window.location.pathname); } catch (error) { /* ignore */ }
    const top = parseInt(saved, 10);
    if(!Number.isFinite(top) || top <= 0) return;
    const paint = ()=> window.scrollTo(0, top);
    paint();
    // 图片/字体撑高页面后位置会漂，下一帧再补一次
    window.requestAnimationFrame(paint);
  }

  /**
   * 表格空态行（JS 生成时用，文案统一走 app.common.empty.*）。
   */
  function emptyRowHtml(colspan, label, options){
    const opts = options || {};
    const text = label === null || label === undefined
      ? getLocale(['common','empty','no_data'], 'No data')
      : String(label);
    const cls = opts.cellClassName || 'text-center muted';
    return '<tr class="' + (opts.rowClassName || 'js-empty-row') + '"><td colspan="' + Math.max(1, parseInt(colspan, 10) || 1)
      + '" class="' + cls + '">' + escapeHtmlText(text) + '</td></tr>';
  }

  /**
   * 轮询：失败退避、隐藏标签页暂停、回到前台立刻补一次，并提供 stop()。
   * supervisor 那种"无条件 15 s 拉一次、页面切走也不停"的写法是全局性能负担，统一走这里。
   *
   * @param {Function} fn 每次轮询执行（可返回 Promise）
   * @param {number} interval 基础间隔 ms
   * @param {object} [options] pauseWhenHidden / backoffOnError / maxInterval / onError / immediate
   * @returns {{start: Function, stop: Function, isRunning: Function, trigger: Function}}
   */
  function createPoll(fn, interval, options){
    const opts = options || {};
    const base = Math.max(1000, Number(interval) || 5000);
    const maxInterval = Math.max(base, Number(opts.maxInterval) || base * 8);
    const backoff = opts.backoffOnError !== false;
    let timer = null;
    let stopped = true;
    let failures = 0;
    let busy = false;

    const delay = ()=> (backoff && failures > 0 ? Math.min(base * Math.pow(2, failures), maxInterval) : base);

    function schedule(){
      if(stopped) return;
      if(timer) window.clearTimeout(timer);
      timer = window.setTimeout(tick, delay());
    }

    async function tick(){
      if(stopped || busy) return;
      if(opts.pauseWhenHidden !== false && document.hidden){ schedule(); return; }
      busy = true;
      try {
        await fn();
        failures = 0;
      } catch (error) {
        failures += 1;
        console.warn('[panel] poll iteration failed (' + failures + ')', error);
        if(typeof opts.onError === 'function') opts.onError(error, failures);
      } finally {
        busy = false;
        schedule();
      }
    }

    function onVisibilityChange(){
      if(stopped || document.hidden) return;
      if(timer) window.clearTimeout(timer);
      tick();
    }

    function start(){
      if(!stopped) return;
      stopped = false;
      failures = 0;
      if(opts.pauseWhenHidden !== false) document.addEventListener('visibilitychange', onVisibilityChange);
      if(opts.immediate === false) schedule(); else tick();
    }

    function stop(){
      stopped = true;
      if(timer){ window.clearTimeout(timer); timer = null; }
      document.removeEventListener('visibilitychange', onVisibilityChange);
    }

    return {
      start,
      stop,
      isRunning: ()=> !stopped,
      trigger(){
        if(timer) window.clearTimeout(timer);
        return tick();
      }
    };
  }

  /**
   * 脏数据守卫：表单改过之后，关页/刷新/点站内链接都会被拦下。
   *
   * - `draftKey` 非空时把未提交的值写进 localStorage 并在下次进入时恢复（键要含 server 与页面）。
   * - `blockUnload:false` 用于筛选表单这类"改了就只是查询条件"的场景：只留草稿，不拦导航。
   *
   * @param {Element|string} target 表单（或包含表单字段的容器）
   * @param {object} [options] message / draftKey / blockUnload / onRestore / leaveLabel
   * @returns {{isDirty:Function, markDirty:Function, clear:Function, saveDraft:Function, clearDraft:Function, requestLeave:Function, destroy:Function}}
   */
  function createUnsavedGuard(target, options){
    const opts = options || {};
    const scope = typeof target === 'string' ? document.querySelector(target) : target;
    if(!scope) return null;
    if(scope.__panelUnsavedGuard) return scope.__panelUnsavedGuard;

    const message = opts.message || getLocale(['common','unsaved','message'], 'There are unsaved changes. Leave this page?');
    const leaveLabel = opts.leaveLabel || getLocale(['common','unsaved','leave'], 'Discard changes');
    const draftKey = opts.draftKey ? String(opts.draftKey) : '';
    const blockUnload = opts.blockUnload !== false;

    let dirty = false;
    let bypass = false;
    let draftTimer = null;

    function fields(){
      return Array.prototype.slice.call(scope.querySelectorAll('input[name], textarea[name], select[name]'))
        .filter((node)=> node.type !== 'hidden' && node.type !== 'submit' && node.type !== 'button');
    }

    function draftPayload(){
      const data = {};
      fields().forEach((node)=>{
        const name = node.name;
        if(!name) return;
        if(node.type === 'checkbox' || node.type === 'radio'){
          if(node.checked && node.defaultChecked !== true) data[name] = node.value;
          return;
        }
        const value = String(node.value ?? '');
        if(value !== '' && value !== String(node.defaultValue ?? '')) data[name] = value;
      });
      return data;
    }

    function saveDraft(){
      if(!draftKey) return;
      try {
        const data = draftPayload();
        if(Object.keys(data).length === 0){ window.localStorage.removeItem(draftKey); return; }
        window.localStorage.setItem(draftKey, JSON.stringify(data));
      } catch (error) { /* 隐私模式下 localStorage 不可用 */ }
    }

    function scheduleDraftSave(){
      if(!draftKey) return;
      if(draftTimer) window.clearTimeout(draftTimer);
      draftTimer = window.setTimeout(saveDraft, 400);
    }

    function clearDraft(){
      if(!draftKey) return;
      try { window.localStorage.removeItem(draftKey); } catch (error) { /* ignore */ }
    }

    function restoreDraft(){
      if(!draftKey || opts.restore === false) return;
      let saved = null;
      try {
        const raw = window.localStorage.getItem(draftKey);
        if(raw) saved = JSON.parse(raw);
      } catch (error) { saved = null; }
      if(!saved || typeof saved !== 'object') return;
      let applied = 0;
      Object.entries(saved).forEach(([name, value])=>{
        const node = scope.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
        if(!node) return;
        if(node.type === 'checkbox' || node.type === 'radio') node.checked = true;
        else if(String(node.value ?? '') === '') node.value = String(value);
        else return;
        applied += 1;
      });
      if(applied > 0){
        markDirty();
        if(typeof opts.onRestore === 'function') opts.onRestore(applied);
      }
    }

    function markDirty(){
      if(bypass) return;
      dirty = true;
      scope.__panelDirty = true;
      scheduleDraftSave();
    }

    function clear(){
      dirty = false;
      scope.__panelDirty = false;
      clearDraft();
    }

    function onBeforeUnload(event){
      if(!dirty || bypass || !blockUnload) return undefined;
      event.preventDefault();
      event.returnValue = message;
      return message;
    }

    function onInput(){ markDirty(); }

    // 提交属于"用户自己的动作"：让导航照走，否则筛选表单一点搜索就弹确认框
    function onSubmit(){
      bypass = true;
      window.setTimeout(()=>{ bypass = false; }, 1000);
      if(draftKey) saveDraft();
    }

    function onDocumentClick(event){
      if(!dirty || bypass) return;
      if(event.defaultPrevented || event.button !== 0) return;
      if(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
      if(!link) return;
      if(link.target && link.target !== '_self') return;
      if(link.hasAttribute('download')) return;
      const href = link.getAttribute('href') || '';
      if(href === '' || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) return;
      let url;
      try { url = new URL(link.href, window.location.href); } catch (error) { return; }
      if(url.origin !== window.location.origin) return;
      if(url.pathname === window.location.pathname && url.search === window.location.search) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      requestLeave(()=>{ window.location.href = url.href; });
    }

    /** 先确认再执行一段会丢弃当前编辑的动作（跳转、切 Tab、关闭）。 */
    async function requestLeave(action){
      if(!dirty){ if(typeof action === 'function') action(); return true; }
      saveDraft();
      const ok = (window.Panel && typeof window.Panel.confirm === 'function')
        ? await window.Panel.confirm({ message, danger: true, confirmLabel: leaveLabel })
        : window.confirm(message);
      if(!ok) return false;
      bypass = true;
      dirty = false;
      if(typeof action === 'function') action();
      return true;
    }

    scope.addEventListener('input', onInput);
    scope.addEventListener('change', onInput);
    scope.addEventListener('submit', onSubmit);
    if(blockUnload) window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('click', onDocumentClick, true);

    const guard = {
      element: scope,
      isDirty: ()=> dirty,
      markDirty,
      clear,
      saveDraft,
      clearDraft,
      requestLeave,
      destroy(){
        scope.removeEventListener('input', onInput);
        scope.removeEventListener('change', onInput);
        scope.removeEventListener('submit', onSubmit);
        window.removeEventListener('beforeunload', onBeforeUnload);
        document.removeEventListener('click', onDocumentClick, true);
        delete scope.__panelUnsavedGuard;
      }
    };

    scope.__panelUnsavedGuard = guard;
    restoreDraft();
    return guard;
  }

  /**
   * 表单弹窗骨架：取消/提交按钮、提交锁、字段级错误区、点遮罩不误关。
   * 一个模块用一个 id，重复打开复用同一个槽位，不会叠出多层遮罩。
   *
   * @param {object} options id / title / body / submitLabel / cancelLabel / danger / width / onSubmit
   * @returns {{modal:Element, bodyEl:Element, form:Element, submit:Function, close:Function,
   *            showError:Function, showFieldErrors:Function, setBusy:Function}|null}
   */
  function formModal(options){
    const opts = options || {};
    if(!window.Modal) return null;
    const id = opts.id || 'panel-form';
    const cancelLabel = opts.cancelLabel || getLocale(['common','actions','cancel'], 'Cancel');
    const submitLabel = opts.submitLabel || getLocale(['common','actions','save'], 'Save');
    const footer = '<button type="button" class="btn outline" data-form-cancel>' + escapeHtmlText(cancelLabel) + '</button>'
      + '<button type="button" class="btn ' + (opts.danger ? 'danger' : 'primary') + '" data-form-submit>'
      + escapeHtmlText(submitLabel) + '</button>';

    const ref = window.Modal.show({
      id,
      title: opts.title || '',
      content: opts.body || '',
      footer,
      width: opts.width || '',
      closeOnBackdrop: false
    });

    const form = ref.bodyEl.querySelector('form');
    const errorBox = ref.bodyEl.querySelector('.form-error');
    const submitBtn = ref.footerEl.querySelector('[data-form-submit]');
    const cancelBtn = ref.footerEl.querySelector('[data-form-cancel]');

    const ui = {
      modal: ref.el,
      bodyEl: ref.bodyEl,
      footerEl: ref.footerEl,
      form,
      submitBtn,
      cancelBtn,
      close(){ window.Modal.hide(id); },
      showError(message){
        if(!errorBox) return;
        errorBox.textContent = message || '';
        errorBox.hidden = !message;
      },
      showFieldErrors(errors){
        if(form) applyFormErrors(form, errors, { summarySelector: '.form-error' });
        else ui.showError(normalizeErrorList(errors).map((item)=> item.message).join(' · '));
      },
      setBusy(busy, pendingLabel){
        if(!submitBtn) return;
        if(busy){
          if(!submitBtn.dataset.originalLabel) submitBtn.dataset.originalLabel = submitBtn.textContent;
          if(pendingLabel) submitBtn.textContent = pendingLabel;
        } else if(submitBtn.dataset.originalLabel){
          submitBtn.textContent = submitBtn.dataset.originalLabel;
        }
        submitBtn.disabled = busy;
        if(cancelBtn) cancelBtn.disabled = busy;
      },
      async submit(){
        if(typeof opts.onSubmit === 'function') await opts.onSubmit(ui);
      }
    };

    // 重新打开时旧的监听随 innerHTML 一起消失，所以每次 show() 之后都要重挂
    if(cancelBtn) cancelBtn.addEventListener('click', ()=> ui.close());
    if(submitBtn) submitBtn.addEventListener('click', ()=> ui.submit());
    if(form) form.addEventListener('submit', (event)=>{ event.preventDefault(); ui.submit(); });
    return ui;
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
    },
    /**
     * 面板基路径（"/agmp"，部署在根时为 ""）。服务端只通过 <body data-app-base> 发布：
     * window.APP_BASE 永远不存在，直接读它会得到 "" 并生成子路径下 404 的根相对 URL。
     */
    basePath(){
      return resolveBasePath();
    },
    /** Absolute URL for a panel-relative path, base path included. */
    absoluteUrl(path){
      const base = resolveBasePath();
      const suffix = String(path ?? '');
      return base + (suffix.startsWith('/') ? suffix : '/' + suffix);
    },
    /** HTML 转义（模块里那些手写的 esc 可以改用这个）。 */
    escapeHtml(value){
      return escapeHtmlText(value);
    },
    /** 按名字搜索的物品选择器；见 createItemPicker() 的用法说明。 */
    itemPicker(target, options){
      return createItemPicker(target, options);
    },
    /** 给所有 table[data-au-searchable] 挂上客户端搜索框；可重复调用。 */
    tableFilter(root, defaults){
      return bindTableFilters(root, defaults);
    },
    /** 带后果说明的确认框（Promise<boolean>），取代 window.confirm。 */
    confirm: createConfirm(),
    /** 局部刷新一个区域，取代整页 location.reload()；见 reloadRegion()。 */
    reloadRegion,
    /** 整页刷新但保留滚动位置；仅在区域替换不可用时使用。 */
    reload: reloadKeepingPosition,
    /** 分页条（服务端 pagination.php 的同一套标记），AJAX 列表复用；见 paginateBar()。 */
    paginate: paginateBar,
    /** 铜币 → 本地化金额文案；见 formatMoney()。 */
    money: formatMoney,
    /** 单个金额单位（gold/silver/copper），走同一套语言包解析。 */
    moneyUnit,
    /** 复制到剪贴板，非安全上下文自动降级；见 copyText()。 */
    copy: copyText,
    /** 声明式快捷键；见 bindHotkeys()。 */
    hotkeys: bindHotkeys,
    /** 表格空态行 HTML；见 emptyRowHtml()。 */
    emptyRow: emptyRowHtml,
    /** 统一轮询（隐藏暂停 + 失败退避 + stop）；见 createPoll()。 */
    poll: createPoll,
    /** 脏数据守卫（含可选 localStorage 草稿）；见 createUnsavedGuard()。 */
    unsavedGuard: createUnsavedGuard,
    /** 逐字段渲染校验错误；见 applyFormErrors()。 */
    formErrors: applyFormErrors,
    /** 表单弹窗骨架（一个模块一个槽位）；见 formModal()。 */
    formModal,
    /** 把任意错误整理成可读的一句话（原始异常只进控制台）。 */
    errorMessage(error, fallback){
      const raw = error && error.message ? String(error.message) : '';
      if(raw !== '') console.error('[panel] ' + (fallback || 'error'), error);
      return fallback || getLocale(['common','errors','network'], 'Network error, please retry');
    },
    /** 读回某个选择器当前的 entry（逗号分隔），表单校验用；未初始化时返回 null。 */
    itemPickerValue(target){
      const host = typeof target === 'string' ? document.querySelector(target) : target;
      return host && host.__itemPicker ? host.__itemPicker.value() : null;
    },
    /**
     * 标准标签页行为：点击、左右方向键、Home / End 切换；roving tabindex 与 aria-selected 同步。
     *
     * 面板由每个 tab 的 aria-controls 解析（ID 列表），切的是 hidden 属性——所以一个 tab 可以由
     * 页面上不相邻的多段区块组成，而标记里仍然只有一份声明。项目里此前有 7 份手写的标签页实现，
     * 全都没有键盘支持，这个函数是它们共同该用的那份。
     *
     * @param {object} [options]
     * @param {Element|string} [options.root]        含 tablist 的容器（选择器或元素），默认 document
     * @param {string} [options.tabSelector]         默认 '[role="tab"]'
     * @param {string} [options.tabNameAttr]         tab 上承载名字的属性，默认 'data-tab'
     * @param {string} [options.activeClass]         tab 的选中类，默认 'is-active'
     * @param {string} [options.defaultTab]          没有 hash 或 hash 无效时的 tab 名
     * @param {boolean} [options.hash]               切换时是否写回 location.hash（replaceState）
     * @param {Function} [options.onChange]          (name) => void
     * @returns {{activate: Function, active: Function}|null} 找不到 tablist 时返回 null
     */
    tabs(options){
      const opts = options || {};
      const root = typeof opts.root === 'string' ? document.querySelector(opts.root) : (opts.root || document);
      if(!root) return null;

      const tabs = Array.prototype.slice.call(root.querySelectorAll(opts.tabSelector || '[role="tab"]'));
      if(!tabs.length) return null;

      const nameAttr = opts.tabNameAttr || 'data-tab';
      const activeClass = opts.activeClass || 'is-active';
      const nameOf = (tab) => tab.getAttribute(nameAttr) || '';
      const panelsFor = (tab) => String(tab.getAttribute('aria-controls') || '')
        .split(/\s+/).filter(Boolean)
        .map((id) => document.getElementById(id))
        .filter(Boolean);

      let current = '';

      function activate(name, mode){
        const opts2 = mode || {};
        if(!name || !tabs.some((tab) => nameOf(tab) === name)) return;
        current = name;

        tabs.forEach((tab)=>{
          const on = nameOf(tab) === name;
          tab.classList.toggle(activeClass, on);
          tab.setAttribute('aria-selected', on ? 'true' : 'false');
          tab.tabIndex = on ? 0 : -1;
          panelsFor(tab).forEach((panel)=>{ panel.hidden = !on; });
          if(on && opts2.focus && typeof tab.focus === 'function') tab.focus();
        });

        if(opts.hash && opts2.pushHash && window.history && typeof window.history.replaceState === 'function'){
          window.history.replaceState(null, '', '#' + name);
        }
        if(typeof opts.onChange === 'function') opts.onChange(name);
      }

      // 只接管左右方向键：上下键留给页面滚动，横向 tablist 不该把它们吃掉。
      function step(from, delta){
        const index = tabs.indexOf(from);
        if(index < 0) return;
        activate(nameOf(tabs[(index + delta + tabs.length) % tabs.length]), { focus: true, pushHash: true });
      }

      tabs.forEach((tab)=>{
        tab.addEventListener('click', ()=> activate(nameOf(tab), { pushHash: true }));
        tab.addEventListener('keydown', (event)=>{
          if(event.key === 'ArrowRight'){ event.preventDefault(); step(tab, 1); }
          else if(event.key === 'ArrowLeft'){ event.preventDefault(); step(tab, -1); }
          else if(event.key === 'Home'){ event.preventDefault(); activate(nameOf(tabs[0]), { focus: true, pushHash: true }); }
          else if(event.key === 'End'){ event.preventDefault(); activate(nameOf(tabs[tabs.length - 1]), { focus: true, pushHash: true }); }
        });
      });

      const fromHash = (window.location.hash || '').replace('#', '');
      const initial = (fromHash && tabs.some((tab) => nameOf(tab) === fromHash))
        ? fromHash
        : ((opts.defaultTab && tabs.some((tab) => nameOf(tab) === opts.defaultTab)) ? opts.defaultTab : nameOf(tabs[0]));
      activate(initial, {});

      return { activate, active: () => current };
    },
    /**
     * DOM 就绪后执行 fn。模块脚本由 body 内联脚本注入，常在文档仍解析时（interactive）
     * 执行：此时 DOMContentLoaded 未触发、readyState 也不是 "loading"，经典判断式会静默不执行。
     */
    whenDomReady(fn){
      if(typeof fn !== 'function' || typeof document === 'undefined') return;
      if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', fn, { once: true });
        return;
      }
      // 'interactive' and 'complete' both guarantee the parsed DOM exists.
      fn();
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
    restoreScrollPosition();
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


  /**
   * 全站物品属性卡：任何带 data-item-entry 的元素（静态渲染或 JS 事后插入都一样，
   * 因为用的是事件委托）在鼠标悬停/键盘聚焦时弹出物品属性，数据由 /item/api/tooltip 现取。
   *
   * 为什么放在 panel.js 而不是某个页面的模块：物品名出现在几乎所有页面（物品列表、任务奖励、
   * 邮件附件、发放记录、Boss 奖池、背包、拍卖行…），而每个页面模块只在对应页面加载。
   * 物品编辑页刻意不挂 data-item-entry——那一页表单里已经列出全部列。
   *
   * 服务端渲染挂属性用 PHP 的 item_tooltip_attrs()；JS 里拼 HTML 用 ItemTooltip.attrs()。
   */
  (function(){
    if(window.ItemTooltip) return;

    const SHOW_DELAY = 140;      // 鼠标扫过时不要闪出一堆卡片
    const HIDE_DELAY = 60;
    const ANCHOR_GAP = 6;
    const VIEWPORT_MARGIN = 8;
    const endpoint = '/item/api/tooltip';
    const cache = new Map();     // "entry[:link]" -> html 字符串（'' 表示已知查不到）

    let box = null;
    let contentEl = null;
    let anchorEl = null;
    let showTimer = null;
    let hideTimer = null;
    let seq = 0;

    function ensureBox(){
      if(box && box.isConnected) return box;
      box = document.createElement('div');
      box.className = 'item-tooltip';
      box.setAttribute('role', 'tooltip');
      box.hidden = true;
      contentEl = document.createElement('div');
      contentEl.className = 'item-tooltip__content';
      box.appendChild(contentEl);
      document.body.appendChild(box);
      return box;
    }

    function keyOf(el){
      const entry = String(el.getAttribute('data-item-entry') || '').trim();
      if(!entry) return '';
      return entry + (el.hasAttribute('data-item-tooltip-link') ? ':link' : '');
    }

    function position(){
      if(!box || !anchorEl || box.hidden) return;
      const rect = anchorEl.getBoundingClientRect();
      const size = box.getBoundingClientRect();
      const maxLeft = window.innerWidth - VIEWPORT_MARGIN - size.width;
      const maxTop = window.innerHeight - VIEWPORT_MARGIN - size.height;

      let left = Math.min(rect.left, Math.max(VIEWPORT_MARGIN, maxLeft));
      let top = rect.bottom + ANCHOR_GAP;
      if(top > maxTop){
        const above = rect.top - ANCHOR_GAP - size.height;
        top = above >= VIEWPORT_MARGIN ? above : Math.max(VIEWPORT_MARGIN, maxTop);
      }

      box.style.left = Math.round(left) + 'px';
      box.style.top = Math.round(top) + 'px';
    }

    function hide(){
      clearTimeout(showTimer);
      clearTimeout(hideTimer);
      showTimer = null;
      hideTimer = null;
      anchorEl = null;
      seq += 1;                  // 让在途响应失效
      if(box) box.hidden = true;
    }

    async function fill(el, key, ticket){
      const entry = key.split(':')[0];
      const payload = await api.get(endpoint, {
        entry: entry,
        link: key.endsWith(':link') ? 1 : undefined
      });

      if(ticket !== seq || el !== anchorEl) return;           // 已经换目标或收起来了

      const html = payload && payload.success === true && typeof payload.html === 'string'
        ? payload.html
        : '';
      cache.set(key, html);

      if(!html){ hide(); return; }
      if(!contentEl) return;
      contentEl.innerHTML = html;
      position();
    }

    function show(el){
      // 元素可能在 SHOW_DELAY 里被 AJAX 刷新掉了：锚点已脱开文档时直接放弃，
      // 否则 getBoundingClientRect() 全是 0，卡片会飘到左上角。
      if(!el || el.isConnected === false) return;
      ensureBox();
      const key = keyOf(el);
      if(!key) return;

      anchorEl = el;
      seq += 1;
      const ticket = seq;

      const cached = cache.get(key);
      if(cached !== undefined){
        if(!cached){ hide(); return; }
        contentEl.innerHTML = cached;
        box.hidden = false;
        position();
        return;
      }

      contentEl.innerHTML = '<div class="item-tooltip__loading">…</div>';
      box.hidden = false;
      position();
      fill(el, key, ticket).catch(()=>{ if(ticket === seq) hide(); });
    }

    function schedule(el){
      if(!el || el === anchorEl) return;
      clearTimeout(hideTimer);
      hideTimer = null;
      clearTimeout(showTimer);
      showTimer = setTimeout(()=>{ showTimer = null; show(el); }, SHOW_DELAY);
    }

    function scheduleHide(){
      clearTimeout(showTimer);
      showTimer = null;
      clearTimeout(hideTimer);
      hideTimer = setTimeout(hide, HIDE_DELAY);
    }

    function elementFrom(node){
      const el = node && node.nodeType === 1 ? node : (node && node.parentElement);
      if(!el || typeof el.closest !== 'function') return null;
      const target = el.closest('[data-item-entry]');
      if(!target) return null;
      if(target.closest('[data-item-tooltip="off"]')) return null;
      if(box && box.contains(target)) return null;
      return target;
    }

    document.addEventListener('mouseover', (event)=>{
      const el = elementFrom(event.target);
      if(!el) return;
      if(event.relatedTarget && el.contains(event.relatedTarget)) return;  // 元素内部移动
      schedule(el);
    }, true);

    document.addEventListener('mouseout', (event)=>{
      // 注意：这里不能因为 anchorEl 为空就 return —— 鼠标在 SHOW_DELAY 之内划走时
      // 卡片还没显示（anchorEl 仍为 null），但 showTimer 已经排上了；必须让 scheduleHide()
      // 去清掉它，否则 140ms 后卡片会自己冒出来且再也没有 mouseout 来收。
      const el = elementFrom(event.target);
      if(!el) return;
      if(event.relatedTarget && el.contains(event.relatedTarget)) return;
      scheduleHide();
    }, true);

    document.addEventListener('focusin', (event)=>{
      const el = elementFrom(event.target);
      if(el) show(el);
    });

    document.addEventListener('focusout', (event)=>{
      if(anchorEl && elementFrom(event.target) === anchorEl) hide();
    });

    // 页面滚动/改尺寸后锚点位置就失效了，直接收起，避免卡片飘在错误的位置
    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
    document.addEventListener('keydown', (event)=>{ if(event.key === 'Escape') hide(); });

    window.ItemTooltip = {
      show,
      hide,
      /**
       * 给 JS 里拼的物品名加属性（等价于 PHP 的 item_tooltip_attrs()）。
       * @param {number|string} entry
       * @param {number|null} [quality] 有值时顺带交给 GameMetaColorize 上色
       * @param {boolean} [linkable] 元素本身可点击时传 true
       */
      attrs(entry, quality, linkable){
        const id = parseInt(entry, 10);
        if(!Number.isFinite(id) || id <= 0) return '';
        let out = ' data-item-entry="' + id + '"';
        const q = parseInt(quality, 10);
        if(Number.isFinite(q) && q >= 0) out += ' data-item-quality="' + q + '"';
        if(linkable) out += ' data-item-tooltip-link="1"';
        return out;
      }
    };
  })();


  /**
   * 移动端导航折叠：窄屏下侧栏变成顶部一条 + 汉堡按钮（样式见 app-core.css 的 ≤960px 块）。
   * 纯类切换，不写内联样式，宽窄屏各自的表现都在 CSS 里；桌面端按钮本身 display:none，点了也没副作用。
   * 用事件委托，页面局部刷新后按钮依然有效。
   */
  (function(){
    if(window.PanelNav) return;

    function sidebarOf(button){
      return button && typeof button.closest === 'function' ? button.closest('.sidebar--shell') : null;
    }

    function setOpen(sidebar, open){
      if(!sidebar) return;
      sidebar.classList.toggle('is-open', open);
      const button = sidebar.querySelector('[data-sidebar-toggle]');
      if(button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    document.addEventListener('click', (event)=>{
      const button = event.target && event.target.closest ? event.target.closest('[data-sidebar-toggle]') : null;
      if(!button) return;
      event.preventDefault();
      const sidebar = sidebarOf(button);
      setOpen(sidebar, !(sidebar && sidebar.classList.contains('is-open')));
    });

    // 点了导航项就跳走了，但页面内锚点/被拦下的跳转不会重载：顺手收起，避免菜单一直占着屏幕
    document.addEventListener('click', (event)=>{
      const link = event.target && event.target.closest ? event.target.closest('#panelNavigation a') : null;
      if(!link) return;
      setOpen(sidebarOf(link), false);
    });

    window.PanelNav = {
      open(sidebar){ setOpen(sidebar || document.getElementById('panelSidebar'), true); },
      close(sidebar){ setOpen(sidebar || document.getElementById('panelSidebar'), false); },
      toggle(sidebar){ const el = sidebar || document.getElementById('panelSidebar'); setOpen(el, !(el && el.classList.contains('is-open'))); }
    };
  })();


  (function(){
    if(window.Modal) return;
    const registry = new Map();
    const stack = [];                 // 打开顺序；栈顶是最后打开的那个
    const widthClassMap = {
      '760px': 'modal-panel--760',
      '820px': 'modal-panel--820'
    };
    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

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

    function focusableIn(root){
      if(!root) return [];
      return Array.prototype.filter.call(root.querySelectorAll(FOCUSABLE), (node)=>
        node.offsetParent !== null || node === document.activeElement
      );
    }

    function topId(){ return stack.length ? stack[stack.length - 1] : null; }

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
        // 点遮罩关闭由每个弹窗自己声明（__panelCloseOnBackdrop）；表单类弹窗默认不关，
        // 否则输入到一半点到背景就白填了。
        el.addEventListener('click', e=>{
          if(e.target === el && el.__panelCloseOnBackdrop !== false) hide(id);
        });
        el.querySelector('[data-close]').addEventListener('click', ()=> hide(id));
        el.__bound = true;
      }
      const ref = {
        id,
        el,
        trigger: null,
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
      const wasOpen = ref.el.classList.contains('active');
      if(title !== undefined) ref.titleEl.textContent = title;
      if(content !== undefined) ref.bodyEl.innerHTML = content;
      if(footer !== undefined) ref.footerEl.innerHTML = footer;
      else if(!ref.footerEl.innerHTML){
        const closeLabel = String(getLocale(['common','actions','close'], 'Close'));
        ref.footerEl.innerHTML = '<button class="btn" data-close>'+closeLabel+'</button>';
        ref.footerEl.querySelector('[data-close]').addEventListener('click', ()=> hide(id));
      }
      applyWidthClass(panel, width);
      ref.el.__panelCloseOnBackdrop = opts.closeOnBackdrop !== false;

      if(!wasOpen){
        // 记录触发元素：关闭后把焦点还回去，键盘用户的落点不会跑回文档开头
        ref.trigger = document.activeElement && document.activeElement.nodeType === 1 ? document.activeElement : null;
        if(stack.indexOf(id) === -1) stack.push(id);
      }

      ref.el.setAttribute('role', 'dialog');
      ref.el.setAttribute('aria-modal', 'true');
      ref.el.classList.add('active');
      syncBodyModalState();

      if(!wasOpen){
        const first = focusableIn(ref.bodyEl)[0] || panel;
        if(first){
          if(first === panel && !panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1');
          if(typeof first.focus === 'function') first.focus();
        }
      }
      return ref;
    }
    function hide(id){
      const ref = registry.get(id);
      if(!ref) return;
      const index = stack.indexOf(id);
      if(index >= 0) stack.splice(index, 1);
      ref.el.classList.remove('active');
      ref.el.__panelCloseOnBackdrop = true;
      syncBodyModalState();
      const back = ref.trigger;
      ref.trigger = null;
      if(back && typeof back.focus === 'function' && document.contains(back)) back.focus();
    }
    /** 只关栈顶：Esc 不该一次抹掉用户叠起来的所有弹窗。 */
    function hideAll(){ const id = topId(); if(id) hide(id); }
    function updateContent(id, html){ const ref = ensure(id); ref.bodyEl.innerHTML = html; }
    function append(id, html){ const ref = ensure(id); ref.bodyEl.insertAdjacentHTML('beforeend', html); }

    /** Tab 在弹窗内循环，不允许焦点跑到背后的页面上。 */
    function trapTab(event){
      const id = topId();
      if(!id) return;
      const ref = registry.get(id);
      if(!ref || !ref.el.classList.contains('active')) return;
      const items = focusableIn(ref.el);
      if(!items.length){ event.preventDefault(); return; }
      const first = items[0];
      const last = items[items.length - 1];
      const active = document.activeElement;
      const inside = ref.el.contains(active);
      if(event.shiftKey && (!inside || active === first)){ event.preventDefault(); last.focus(); }
      else if(!event.shiftKey && (!inside || active === last)){ event.preventDefault(); first.focus(); }
    }

    window.addEventListener('keydown', e=>{
      if(e.key === 'Tab'){ trapTab(e); return; }
      if(e.key === 'Escape'){ hideAll(); }
    });
    window.Modal = { show, hide, hideAll, updateContent, append, top: topId };
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
        } else if(init.body && typeof init.body === 'object'){
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
