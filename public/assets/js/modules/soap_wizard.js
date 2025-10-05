/**
 * File: public/assets/js/modules/soap_wizard.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - init()
 *   - buildCommandIndex()
 *   - renderMeta()
 *   - renderCategories()
 *   - renderCommandList()
 *   - bindEvents()
 *   - selectCommand()
 *   - findRawCommand()
 *   - renderDetail()
 *   - renderRiskBadge()
 *   - renderNotes()
 *   - renderTargetHint()
 *   - renderFields()
 *   - handleFieldInput()
 *   - getFormValues()
 *   - canSubmit()
 *   - updatePreview()
 *   - mapArgs()
 *   - handleSubmit()
 *   - handleResponse()
 *   - renderOutput()
 *   - resetOutput()
 *   - clearFieldError()
 *   - clearAllFieldErrors()
 *   - applyFieldError()
 *   - copyCommand()
 *   - fallbackCopy()
 *   - sendExecute()
 *   - resolveUrl()
 *   - escapeHtml()
 *   - escapeAttr()
 *   - cssEscape()
 *   - riskLabel()
 *   - qs()
 *   - qsa()
 */

(function(){
  const qs=(s,r=document)=>r.querySelector(s); const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));
  const data = window.SOAP_WIZARD_DATA || { categories: [], metadata:{} };
  const Feedback = (window.Panel && Panel.feedback) ? Panel.feedback : { success(){}, error(){}, info(){}, clear(){}, show(){} };
  const hasPanelApi = !!(window.Panel && Panel.api && typeof Panel.api.post === 'function');
  const panelLocale = window.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function'
    ? panelLocale.moduleLocale.bind(panelLocale)
    : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('soap')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.soap.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('soap', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    const sentinel = `modules.soap.${path}`;
    if(typeof text === 'string' && text === sentinel && fallback){
      text = fallback;
    }
    if(typeof text === 'string' && replacements && typeof replacements === 'object'){
      Object.entries(replacements).forEach(([key, value])=>{
        const pattern = new RegExp(`:${key}(?![A-Za-z0-9_])`, 'g');
        text = text.replace(pattern, String(value ?? ''));
      });
    }
    return text;
  }

  const dom = {
    search: qs('#soapSearchBox'),
    categoryList: qs('#soapCategoryList'),
    commandList: qs('#soapCommandList'),
    detailWrapper: qs('#soapCommandDetail'),
    summary: qs('#soapCommandSummary'),
    name: qs('#soapDetailName'),
    syntax: qs('#soapDetailSyntax'),
    desc: qs('#soapDetailDesc'),
    notes: qs('#soapDetailNotes'),
    targetHint: qs('#soapTargetHint'),
    form: qs('#soapCommandForm'),
    fields: qs('#soapFormFields'),
    preview: qs('#soapCommandPreview'),
    executeBtn: qs('#soapExecuteBtn'),
    copyBtn: qs('#soapCopyBtn'),
    outputSection: qs('#soapOutputSection'),
    outputText: qs('#soapOutputText'),
    outputMeta: qs('#soapOutputMeta'),
    flashBox: '#soapActionFlash',
    riskBadge: qs('#soapDetailRisk'),
    meta: qs('#soapWizardMeta')
  };

  const state = {
    categories: Array.isArray(data.categories)? data.categories : [],
    commands: [],
    activeCategory: 'all',
    activeCommand: null,
    search: ''
  };

  function init(){
    buildCommandIndex();
    renderMeta();
    renderCategories();
    renderCommandList();
    bindEvents();
  }

  function buildCommandIndex(){
    const list=[];
    state.categories.forEach(cat=>{
      (cat.commands||[]).forEach(cmd=>{
        list.push({
          categoryId: cat.id||'',
          categoryLabel: cat.label||'',
          key: cmd.key,
          name: cmd.name,
          description: cmd.description||'',
          template: cmd.template||cmd.name,
          risk: cmd.risk||'unknown',
          arguments: Array.isArray(cmd.arguments)? cmd.arguments:[],
          notes: cmd.notes||[],
          requiresTarget: !!cmd.requires_target,
        });
      });
    });
    state.commands = list;
  }

  function renderMeta(){
    if(!dom.meta) return;
    const meta = data.metadata || {};
    const rows=[];
    if(meta.updated_at){
      rows.push(translate('meta.updated_at', 'Command list updated at :date', { date: meta.updated_at }));
    }
    if(meta.source){
      const linkLabel = translate('meta.source_link', 'GM Commands');
      const linkHtml = `<a href="${escapeAttr(meta.source)}" target="_blank" rel="noopener">${escapeHtml(linkLabel)}</a>`;
      rows.push(translate('meta.source_label', 'Reference: :link', { link: linkHtml }));
    }
    const separator = escapeHtml(translate('meta.separator', ' · '));
    dom.meta.innerHTML = rows.length? rows.join(separator) : '';
  }

  function renderCategories(){
    if(!dom.categoryList) return;
    const cats=[{
      id:'all',
      label: translate('categories.all.label', 'All commands'),
      summary: translate('categories.all.summary', 'Show every catalogued command'),
      commandsCount: state.commands.length
    }];
    state.categories.forEach(cat=>{
      cats.push({
        id: cat.id || '',
        label: cat.label || '',
        summary: cat.summary || '',
        commandsCount: (cat.commands||[]).length
      });
    });
    dom.categoryList.innerHTML = cats.map(cat=>{
      const active = cat.id===state.activeCategory ? ' soap-category--active' : '';
      return `<button class="soap-category${active}" data-id="${cat.id}">
        <div class="soap-category__name">${escapeHtml(cat.label)}</div>
        <div class="soap-category__meta small muted">${escapeHtml(cat.summary||'')}</div>
        <span class="soap-category__badge">${cat.commandsCount}</span>
      </button>`;
    }).join('');
  }

  function renderCommandList(){
    if(!dom.commandList) return;
    const search = state.search.trim().toLowerCase();
    const filtered = state.commands.filter(cmd=>{
      if(state.activeCategory !== 'all' && cmd.categoryId !== state.activeCategory) return false;
      if(!search) return true;
      return cmd.name.toLowerCase().includes(search) || (cmd.description||'').toLowerCase().includes(search);
    });
    if(!filtered.length){
      dom.commandList.innerHTML = `<div class="soap-command__empty">${escapeHtml(translate('list.empty', 'No matching commands'))}</div>`;
      return;
    }
    dom.commandList.innerHTML = filtered.map(cmd=>{
      const active = state.activeCommand && state.activeCommand.key===cmd.key ? ' soap-command--active' : '';
      return `<button class="soap-command${active}" data-key="${cmd.key}">
        <div class="soap-command__title"><span class="soap-command__name">${escapeHtml(cmd.name)}</span><span class="soap-command__risk soap-risk soap-risk--${cmd.risk}">${riskLabel(cmd.risk)}</span></div>
        <div class="soap-command__desc small muted">${escapeHtml(cmd.description||'')}</div>
        <div class="soap-command__cat small">${escapeHtml(cmd.categoryLabel||'')}</div>
      </button>`;
    }).join('');
  }

  function bindEvents(){
    if(dom.search){
      dom.search.addEventListener('input',()=>{
        state.search = dom.search.value||'';
        renderCommandList();
      });
    }
    if(dom.categoryList){
      dom.categoryList.addEventListener('click',e=>{
        const btn=e.target.closest('button[data-id]');
        if(!btn) return;
        const id=btn.getAttribute('data-id');
        if(!id) return;
        state.activeCategory=id;
        renderCategories();
        renderCommandList();
        Feedback.clear(dom.flashBox);
      });
    }
    if(dom.commandList){
      dom.commandList.addEventListener('click',e=>{
        const btn=e.target.closest('button[data-key]');
        if(!btn) return;
        const key=btn.getAttribute('data-key');
        selectCommand(key);
      });
    }
    if(dom.form){
      dom.form.addEventListener('submit',handleSubmit);
    }
    if(dom.copyBtn){
      dom.copyBtn.addEventListener('click',copyCommand);
    }
  }

  function selectCommand(key){
    const cmd = state.commands.find(c=>c.key===key);
    if(!cmd) return;
    state.activeCommand = Object.assign({}, cmd, { raw: findRawCommand(key) });
    renderCommandList();
    renderDetail();
    Feedback.clear(dom.flashBox);
  }

  function findRawCommand(key){
    for(const cat of state.categories){
      for(const cmd of (cat.commands||[])){
        if(cmd.key===key) return cmd;
      }
    }
    return null;
  }

  function renderDetail(){
    if(!dom.detailWrapper || !state.activeCommand){
      if(dom.detailWrapper) dom.detailWrapper.hidden=true;
      if(dom.summary) dom.summary.hidden=false;
      return;
    }
    dom.summary.hidden = true;
    dom.detailWrapper.hidden = false;
    const cmd = state.activeCommand;
    const raw = cmd.raw || {};
    dom.name.textContent = cmd.name;
    dom.syntax.textContent = raw.template || cmd.template;
    dom.desc.textContent = raw.description || cmd.description || '';
    renderRiskBadge(cmd.risk);
    renderNotes(raw.notes||[]);
    renderTargetHint(!!raw.requires_target);
    renderFields(raw.arguments||[]);
    updatePreview();
    resetOutput();
    dom.executeBtn.disabled = !canSubmit();
  }

  function renderRiskBadge(risk){
    if(!dom.riskBadge) return;
    const map={ low:'Low risk', medium:'Medium risk', high:'High risk', unknown:'Unknown risk'};
    const label = translate(`risk.badge.${risk || 'unknown'}`, map[risk] || map.unknown);
    dom.riskBadge.textContent = label;
    dom.riskBadge.className = 'soap-risk soap-risk--'+(risk||'unknown');
  }

  function renderNotes(notes){
    if(!dom.notes) return;
    if(!Array.isArray(notes) || !notes.length){
      dom.notes.innerHTML='';
      dom.notes.hidden = true;
      return;
    }
    dom.notes.hidden = false;
    dom.notes.innerHTML = notes.map(n=>'<li>'+escapeHtml(n)+'</li>').join('');
  }

  function renderTargetHint(show){
    if(!dom.targetHint) return;
    dom.targetHint.hidden = !show;
  }

  function renderFields(args){
    if(!dom.fields) return;
    if(!Array.isArray(args)) args=[];
    if(!args.length){
      dom.fields.innerHTML = `<p class="muted small">${escapeHtml(translate('fields.empty', 'No additional parameters required.'))}</p>`;
      return;
    }
    dom.fields.innerHTML = args.map(arg=>{
      const required = arg.required ? 'required' : '';
      const placeholder = arg.placeholder ? ` placeholder="${escapeAttr(arg.placeholder)}"` : '';
      const min = (arg.min !== undefined) ? ` min="${arg.min}"` : '';
      const max = (arg.max !== undefined) ? ` max="${arg.max}"` : '';
      const defaultVal = arg.default ?? '';
      const fieldId = `soap-field-${arg.key}`;
      let control='';
      if(arg.type === 'textarea'){
        control = `<textarea id="${fieldId}" data-key="${arg.key}" ${required}${placeholder}>${escapeHtml(defaultVal)}</textarea>`;
      } else if(arg.type === 'select'){
        const options = (arg.options||[]).map(opt=>{
          const sel = (String(defaultVal)!=='' && String(defaultVal)===String(opt.value))? ' selected' : '';
          return `<option value="${escapeAttr(opt.value)}"${sel}>${escapeHtml(opt.label||opt.value)}</option>`;
        }).join('');
        control = `<select id="${fieldId}" data-key="${arg.key}" ${required}>${options}</select>`;
      } else {
        const type = arg.type === 'password' ? 'password' : (arg.type === 'number' ? 'number' : 'text');
        const value = defaultVal !== '' ? ` value="${escapeAttr(defaultVal)}"` : '';
        control = `<input type="${type}" id="${fieldId}" data-key="${arg.key}" ${required}${placeholder}${value}${min}${max}>`;
      }
      const hint = arg.hint ? `<div class="small muted">${escapeHtml(arg.hint)}</div>` : '';
      return `<div class="soap-field" data-key="${arg.key}">
        <label for="${fieldId}">${escapeHtml(arg.label || arg.key)}${arg.required ? '<span class="soap-field__required">*</span>' : ''}</label>
        ${control}
        <div class="soap-field__error" data-error></div>
        ${hint}
      </div>`;
    }).join('');
    qsa('input,textarea,select', dom.fields).forEach(el=>{
      el.addEventListener('input',handleFieldInput);
      el.addEventListener('change',handleFieldInput);
    });
  }

  function handleFieldInput(){
    clearFieldError(this);
    updatePreview();
    dom.executeBtn.disabled = !canSubmit();
  }

  function getFormValues(){
    const values={};
    qsa('[data-key]', dom.fields).forEach(el=>{
      const key = el.getAttribute('data-key');
      if(!key) return;
      if(el.tagName==='SELECT'){
        values[key]=el.value;
      } else if(el.type==='number'){
        values[key]=el.value;
      } else {
        values[key]=el.value;
      }
    });
    return values;
  }

  function canSubmit(){
    if(!state.activeCommand) return false;
    const raw = state.activeCommand.raw || {};
    const args = raw.arguments||[];
    if(!args.length) return true;
    const values = getFormValues();
    return args.every(arg=>{
      if(!arg.required) return true;
      const v = values[arg.key];
      return v!==undefined && v!==null && String(v).trim()!=='';
    });
  }

  function updatePreview(){
    if(!dom.preview){ return; }
    if(!state.activeCommand){ dom.preview.textContent=''; return; }
    const raw = state.activeCommand.raw || {};
    const template = raw.template || state.activeCommand.template || state.activeCommand.name;
    const values = getFormValues();
    const argsMap = mapArgs(raw.arguments||[]);
    const command = template.replace(/\{([a-z0-9_]+)(\?)?\}/gi, function(full, key, optional){
      const def = argsMap[key] || {};
      let val = values[key];
      if(val===undefined || val===null || String(val).trim()===''){
        return optional ? '' : '<'+key+'?>';
      }
      val = String(val);
      if(def.wrap === 'quotes'){
        if(!/^".*"$/.test(val)){
          val = '"' + val.replace(/"/g,'\\"') + '"';
        }
      }
      return val;
    }).replace(/\s+/g,' ').trim();
    dom.preview.textContent = command;
    return command;
  }

  function mapArgs(list){
    const map={};
    (list||[]).forEach(arg=>{ if(arg && arg.key){ map[arg.key]=arg; } });
    return map;
  }

  function handleSubmit(e){
    e.preventDefault();
    if(!state.activeCommand) return;
    if(dom.executeBtn) dom.executeBtn.disabled = true;
    Feedback.clear(dom.flashBox);
    clearAllFieldErrors();

    const values=getFormValues();
    const payload={
      command_key: state.activeCommand.key,
      arguments: JSON.stringify(values),
      server_id: window.SOAP_WIZARD_DEFAULT_SERVER || 0
    };

    const preview = updatePreview();
    if(preview.includes('<') && preview.includes('?>')){
      Feedback.error(dom.flashBox, translate('errors.missing_required', 'Please fill all required fields.'));
      if(dom.executeBtn) dom.executeBtn.disabled = false;
      return;
    }

    sendExecute(payload)
      .then(res=>{
        handleResponse(res);
      })
      .catch(err=>{
        Feedback.error(dom.flashBox, err && err.message ? err.message : translate('errors.request_failed', 'Request failed'));
      })
      .finally(()=>{
        if(dom.executeBtn) dom.executeBtn.disabled = false;
      });
  }

  function handleResponse(res){
    if(!res || typeof res !== 'object'){
      Feedback.error(dom.flashBox, translate('errors.unknown_response', 'Unknown response'));
      return;
    }
    if(res.errors){
      Object.entries(res.errors).forEach(([key,message])=>{
        const joiner = translate('form.error_joiner', ', ');
        applyFieldError(key, Array.isArray(message)? message.join(joiner): message);
      });
    }
    if(res.success){
      Feedback.success(dom.flashBox, res.message || translate('feedback.execute_success', 'Command executed successfully'));
      renderOutput(res);
    } else {
      const msg = res.message || translate('feedback.execute_failed', 'Command failed');
      Feedback.error(dom.flashBox, msg);
      renderOutput(res);
    }
  }

  function renderOutput(res){
    if(!dom.outputSection || !dom.outputText || !dom.outputMeta) return;
    dom.outputSection.hidden = false;
    const time = res.time_ms !== undefined && res.time_ms !== null ? `${res.time_ms} ms` : translate('output.unknown_time', 'Unknown time');
    const code = res.code || (res.success ? 'ok' : 'error');
    dom.outputMeta.textContent = translate('output.meta', 'Status: :code · Time: :time', { code, time });
    let text = '';
    if(res.output){ text += res.output; }
    if(!res.success && res.execution){
      const extra = res.execution.message || res.execution.code;
      if(extra && (!text || !text.includes(extra))){
        text += (text? '\n' : '') + extra;
      }
    }
    dom.outputText.textContent = text || translate('output.empty', '(No output)');
  }

  function resetOutput(){
    if(dom.outputSection){ dom.outputSection.hidden = true; }
    if(dom.outputText){ dom.outputText.textContent=''; }
    if(dom.outputMeta){ dom.outputMeta.textContent=''; }
  }

  function clearFieldError(el){
    const wrap = el.closest('.soap-field');
    if(!wrap) return;
    wrap.classList.remove('soap-field--error');
    const hint = qs('[data-error]', wrap);
    if(hint) hint.textContent='';
  }

  function clearAllFieldErrors(){
    qsa('.soap-field', dom.fields).forEach(wrap=>{
      wrap.classList.remove('soap-field--error');
      const hint = qs('[data-error]', wrap);
      if(hint) hint.textContent='';
    });
  }

  function applyFieldError(key,message){
    const wrap = qs(`.soap-field[data-key="${cssEscape(key)}"]`, dom.fields);
    if(!wrap) return;
    wrap.classList.add('soap-field--error');
    const hint = qs('[data-error]', wrap);
    if(hint) hint.textContent = message;
  }

  function copyCommand(){
    const command = updatePreview();
    if(!command){
      Feedback.info(dom.flashBox, translate('copy.empty', 'Nothing to copy'));
      return;
    }
    if(navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(command).then(()=>{
        Feedback.success(dom.flashBox, translate('copy.success', 'Copied to clipboard'));
      }).catch(()=>{
        fallbackCopy(command);
      });
    } else {
      fallbackCopy(command);
    }
  }

  function fallbackCopy(text){
    const ta=document.createElement('textarea');
    ta.value=text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); Feedback.success(dom.flashBox, translate('copy.success', 'Copied to clipboard')); }
    catch(e){ Feedback.error(dom.flashBox, translate('copy.failure', 'Copy failed')); }
    finally { document.body.removeChild(ta); }
  }

  function sendExecute(payload){
    if(hasPanelApi){
      return Panel.api.post('/soap/api/execute', payload);
    }
    const fd=new FormData();
    Object.entries(payload||{}).forEach(([k,v])=>{ fd.append(k, v); });
    if(window.__CSRF_TOKEN){
      if(!fd.has('_csrf')) fd.append('_csrf', window.__CSRF_TOKEN);
      if(!fd.has('_token')) fd.append('_token', window.__CSRF_TOKEN);
    }
    const url = resolveUrl('/soap/api/execute');
    return fetch(url,{ method:'POST', body:fd, credentials:'same-origin' })
      .then(resp=> resp.json())
      .catch(err=>{ throw err; });
  }

  function resolveUrl(path){
    if(/^https?:/i.test(path)) return path;
    const base = window.APP_BASE || '';
    if(!path.startsWith('/')) path = '/' + path;
    if(!base) return path;
    return base + path;
  }

  function escapeHtml(str){
    return String(str||'').replace(/[&<>"']/g,c=>({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'
    })[c]);
  }

  function escapeAttr(str){
    return escapeHtml(str).replace(/"/g,'&quot;');
  }

  function cssEscape(value){
    if(window.CSS && typeof window.CSS.escape === 'function'){
      return window.CSS.escape(value);
    }
    return String(value).replace(/[^a-zA-Z0-9_\-]/g, ch => '\\' + ch);
  }

  function riskLabel(risk){
    switch(risk){
      case 'low': return translate('risk.short.low', 'L');
      case 'medium': return translate('risk.short.medium', 'M');
      case 'high': return translate('risk.short.high', 'H');
      default: return translate('risk.short.unknown', '?');
    }
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

