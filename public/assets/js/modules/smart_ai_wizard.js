/**
 * File: public/assets/js/modules/smart_ai_wizard.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - init()
 *   - renderBaseFields()
 *   - ensureSegmentExists()
 *   - createSegment()
 *   - defaultSegmentBase()
 *   - syncActiveSegmentUI()
 *   - attachSegmentControls()
 *   - handleSegmentTabClick()
 *   - renderSegmentTabs()
 *   - buildSegmentFallbackLabel()
 *   - selectSegment()
 *   - removeSegment()
 *   - moveSegment()
 *   - getActiveSegment()
 *   - renderSegmentBaseForm()
 *   - setSegmentBaseValue()
 *   - applyStoredSegmentErrors()
 *   - getSelectorContainer()
 *   - clearSegmentError()
 *   - recordSegmentError()
 *   - removeSegmentErrorEntry()
 *   - segmentBucketHasErrors()
 *   - renderSelectors()
 *   - buildSelector()
 *   - render()
 *   - renderParamFields()
 *   - attachStepControls()
 *   - moveStep()
 *   - canProceed()
 *   - updateStepVisibility()
 *   - attachGenerate()
 *   - buildPayload()
 *   - sendPreview()
 *   - attachCopy()
 *   - createInput()
 *   - setInputValue()
 *   - getInputValue()
 *   - clearError()
 *   - clearAllErrors()
 *   - applyErrors()
 *   - highlightSegmentError()
 *   - highlightError()
 *   - findById()
 *   - escapeHtml()
 *   - numberOr()
 */

(function(){
  if(typeof CSS === 'undefined' || typeof CSS.escape !== 'function'){
    window.CSS = window.CSS || {};
    CSS.escape = CSS.escape || function(value){
      return String(value).replace(/[^a-zA-Z0-9_\-]/g, function(c){
        return '\\' + c.charCodeAt(0).toString(16) + ' ';
      });
    };
  }
  const data = window.SMART_AI_WIZARD_DATA || {};
  const Feedback = (window.Panel && Panel.feedback) ? Panel.feedback : { success(){}, error(){}, info(){}, clear(){} };
  const panelLocale = window.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function'
    ? panelLocale.moduleLocale.bind(panelLocale)
    : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('smartai')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.smartai.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('smartai', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    if(typeof text === 'string' && text === `modules.smartai.${path}` && fallback){
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

  const STRINGS = {
    segmentMoveUpTitle: translate('segments.move_up_title', 'Move up'),
    segmentMoveDownTitle: translate('segments.move_down_title', 'Move down'),
    segmentDeleteTitle: translate('segments.delete_segment_title', 'Delete segment'),
    segmentFallbackLabel: index => translate('segments.default_label', 'Segment :number', { number: index + 1 }),
    segmentEmptyMessage: translate('segments.empty_prompt', 'Please add a segment.'),
    searchPlaceholder: translate('search.placeholder', 'Search keywords or ID'),
    listEmpty: translate('list.empty', 'No matches found'),
    selectorSelectType: translate('selector.select_type', 'Select a type.'),
    selectorNoParams: translate('selector.no_params', 'This type has no extra parameters.'),
    entryRequired: translate('validation.entry_required', 'Please enter a valid entry.'),
    entryInvalid: translate('validation.entry_invalid', 'A valid entry is required.'),
    segmentRequired: translate('validation.segment_required', 'Please add at least one segment.'),
    eventRequiredNext: translate('validation.event_required_next', 'Select an event type before continuing.'),
    eventRequired: translate('validation.event_required', 'Please select an event type.'),
  eventRequiredAll: translate('validation.event_required_all', 'Please select an event type for every segment.'),
    actionRequiredNext: translate('validation.action_required_next', 'Select an action type before continuing.'),
    actionRequired: translate('validation.action_required', 'Please select an action type.'),
  actionRequiredAll: translate('validation.action_required_all', 'Please select an action type for every segment.'),
    targetRequiredNext: translate('validation.target_required_next', 'Select a target type before continuing.'),
    targetRequired: translate('validation.target_required', 'Please select a target type.'),
  targetRequiredAll: translate('validation.target_required_all', 'Please select a target type for every segment.'),
    apiNoResponse: translate('api.no_response', 'No response from server'),
    previewPlaceholder: translate('preview.placeholder', '-- No SQL generated --'),
    summarySegments: count => translate('summary.segments', 'Segments: :count', { count }),
    summaryEvent: name => translate('summary.event', 'Event: :name', { name: String(name ?? '') }),
    summaryAction: name => translate('summary.action', 'Action: :name', { name: String(name ?? '') }),
    summaryTarget: name => translate('summary.target', 'Target: :name', { name: String(name ?? '') }),
    generateSuccess: translate('feedback.generate_success', 'SQL generated successfully'),
    previewErrorPlaceholder: translate('preview.error_placeholder', '-- Generation failed, check form errors --'),
    generateFailed: translate('feedback.generate_failed', 'Generation failed'),
  requestFailed: translate('errors.request_failed', 'Request failed'),
    copySuccess: translate('feedback.copy_success', 'Copied to clipboard'),
    copyFailed: translate('feedback.copy_failed', 'Copy failed, please copy manually'),
  };
  const baseFieldByKey = {};
  (data.base || []).forEach(field => {
    if(field && field.key){ baseFieldByKey[field.key] = field; }
  });
  const SEGMENT_BASE_KEYS = ['id','link','event_phase_mask','event_chance','event_flags','comment'];

  const dom = {
    steps: document.getElementById('smartAiSteps'),
    stepLabel: document.getElementById('smartAiStepLabel'),
    baseFields: document.getElementById('smartAiBaseFields'),
    eventSelect: document.getElementById('smartAiEventSelect'),
    eventParams: document.getElementById('smartAiEventParams'),
    actionSelect: document.getElementById('smartAiActionSelect'),
    actionParams: document.getElementById('smartAiActionParams'),
    targetSelect: document.getElementById('smartAiTargetSelect'),
    targetParams: document.getElementById('smartAiTargetParams'),
    segmentSection: document.getElementById('smartAiSegmentSection'),
    segmentTabs: document.getElementById('smartAiSegmentTabs'),
    addSegmentBtn: document.getElementById('smartAiAddSegmentBtn'),
    segmentBase: document.getElementById('smartAiSegmentBase'),
    preview: document.getElementById('smartAiPreview'),
    summary: document.getElementById('smartAiSummary'),
    generateBtn: document.getElementById('smartAiGenerateBtn'),
    copyBtn: document.getElementById('smartAiCopyBtn'),
    nextBtn: document.getElementById('smartAiNextBtn'),
    prevBtn: document.getElementById('smartAiPrevBtn'),
    flashBox: document.getElementById('smartAiFlash'),
    stepContainers: document.querySelectorAll('.smartai-step')
  };

  const state = {
    step: 1,
    base: {},
    segments: [],
    activeSegmentId: null,
    segmentErrors: {},
    lastSql: ''
  };

  if(!dom.baseFields){ return; }

  init();

  function init(){
    renderBaseFields();
    ensureSegmentExists();
    attachSegmentControls();
    attachStepControls();
    attachGenerate();
    attachCopy();
    updateStepVisibility();
  }

  function renderBaseFields(){
    const fields = data.base || [];
    const frag = document.createDocumentFragment();
    fields.forEach(field => {
      const wrap = document.createElement('div');
      wrap.className = 'smartai-field';
      wrap.dataset.baseKey = field.key;
      const label = document.createElement('label');
      label.innerHTML = `${escapeHtml(field.label || field.key)}${field.required ? '<span class="smartai-required">*</span>' : ''}`;
      wrap.appendChild(label);

      const input = createInput(field, state.base[field.key]);
      if(field.hint){
        input.setAttribute('aria-describedby', `${field.key}-hint`);
      }
      input.dataset.baseKey = field.key;
      wrap.appendChild(input);

      const error = document.createElement('div');
      error.className = 'smartai-field__error';
      error.dataset.error = '';
      wrap.appendChild(error);

      if(field.hint){
        const hint = document.createElement('p');
        hint.className = 'smartai-field__hint muted small';
        hint.id = `${field.key}-hint`;
        hint.textContent = field.hint;
        wrap.appendChild(hint);
      }

      frag.appendChild(wrap);

      const initial = field.type === 'checkbox'
        ? field.default === true || field.default === 1
        : (field.default ?? '');
      setInputValue(input, initial);
      state.base[field.key] = getInputValue(input, field.type);

      input.addEventListener('input', () => {
        state.base[field.key] = getInputValue(input, field.type);
        clearError(wrap);
      });
      input.addEventListener('change', () => {
        state.base[field.key] = getInputValue(input, field.type);
        clearError(wrap);
      });
    });
    dom.baseFields.innerHTML = '';
    dom.baseFields.appendChild(frag);
  }

  function ensureSegmentExists(){
    if(state.segments.length === 0){
      const segment = createSegment();
      state.segments.push(segment);
      state.activeSegmentId = segment.id;
    } else if(!getActiveSegment()){
      state.activeSegmentId = state.segments[0].id;
    }
    syncActiveSegmentUI();
  }

  function createSegment(initial){
    const index = state.segments.length;
    const id = `seg-${Date.now().toString(36)}-${Math.random().toString(16).slice(2,8)}-${index}`;
    const base = initial && initial.base ? {...defaultSegmentBase(index), ...initial.base} : defaultSegmentBase(index);
    return {
      id,
      base,
      eventType: initial && initial.eventType !== undefined ? initial.eventType : null,
      eventParams: initial && initial.eventParams ? {...initial.eventParams} : {},
      actionType: initial && initial.actionType !== undefined ? initial.actionType : null,
      actionParams: initial && initial.actionParams ? {...initial.actionParams} : {},
      targetType: initial && initial.targetType !== undefined ? initial.targetType : null,
      targetParams: initial && initial.targetParams ? {...initial.targetParams} : {},
      label: initial && initial.label ? initial.label : null,
    };
  }

  function defaultSegmentBase(index){
    const numberOr = (value, fallback)=>{
      if(value === '' || value === null || value === undefined) return fallback;
      const num = Number(value);
      return Number.isFinite(num) ? num : fallback;
    };
    const base = state.base || {};
    const baseId = numberOr(base.id, 0);
    return {
      id: baseId + index,
      link: numberOr(base.link, 0),
      event_phase_mask: numberOr(base.event_phase_mask, 0),
      event_chance: numberOr(base.event_chance, 100),
      event_flags: numberOr(base.event_flags, 0),
      comment: typeof base.comment === 'string' ? base.comment : (base.comment ?? ''),
    };
  }

  function syncActiveSegmentUI(){
    renderSegmentTabs();
    renderSegmentBaseForm();
    renderSelectors();
    applyStoredSegmentErrors();
  }

  function attachSegmentControls(){
    if(dom.addSegmentBtn){
      dom.addSegmentBtn.addEventListener('click', () => {
        const segment = createSegment();
        state.segments.push(segment);
        state.activeSegmentId = segment.id;
        syncActiveSegmentUI();
      });
    }
    if(dom.segmentTabs){
      dom.segmentTabs.addEventListener('click', handleSegmentTabClick);
    }
  }

  function handleSegmentTabClick(event){
    const btn = event.target.closest('[data-segment-act]');
    if(!btn){ return; }
    const id = btn.getAttribute('data-segment-id');
    if(!id){ return; }
    const act = btn.getAttribute('data-segment-act');
    if(act === 'select'){
      selectSegment(id);
    } else if(act === 'remove'){
      removeSegment(id);
    } else if(act === 'up'){
      moveSegment(id, -1);
    } else if(act === 'down'){
      moveSegment(id, 1);
    }
  }

  function renderSegmentTabs(){
    if(!dom.segmentTabs) return;
    dom.segmentTabs.innerHTML='';
    const frag=document.createDocumentFragment();
    state.segments.forEach((segment, index)=>{
      const wrapper=document.createElement('div');
      wrapper.className='smartai-segment-chip'+(segment.id===state.activeSegmentId?' active':'');
      if(state.segmentErrors[segment.id]){
        wrapper.classList.add('has-error');
      }
      const selectBtn=document.createElement('button');
      selectBtn.type='button';
      selectBtn.className='smartai-segment-chip__select';
      selectBtn.setAttribute('data-segment-act','select');
      selectBtn.setAttribute('data-segment-id',segment.id);
      const label=segment.label || buildSegmentFallbackLabel(index);
      selectBtn.textContent=label;
      wrapper.appendChild(selectBtn);

      const tools=document.createElement('div');
      tools.className='smartai-segment-chip__tools';
      if(state.segments.length>1){
        const up=document.createElement('button');
        up.type='button';
        up.className='smartai-segment-chip__tool';
        up.title=STRINGS.segmentMoveUpTitle;
        up.textContent='↑';
        up.setAttribute('data-segment-act','up');
        up.setAttribute('data-segment-id',segment.id);
        up.disabled=index===0;
        tools.appendChild(up);

        const down=document.createElement('button');
        down.type='button';
        down.className='smartai-segment-chip__tool';
        down.title=STRINGS.segmentMoveDownTitle;
        down.textContent='↓';
        down.setAttribute('data-segment-act','down');
        down.setAttribute('data-segment-id',segment.id);
        down.disabled=index===state.segments.length-1;
        tools.appendChild(down);

        const remove=document.createElement('button');
        remove.type='button';
        remove.className='smartai-segment-chip__tool smartai-segment-chip__tool--danger';
        remove.title=STRINGS.segmentDeleteTitle;
        remove.textContent='✕';
        remove.setAttribute('data-segment-act','remove');
        remove.setAttribute('data-segment-id',segment.id);
        tools.appendChild(remove);
      }
      wrapper.appendChild(tools);
      frag.appendChild(wrapper);
    });
    dom.segmentTabs.appendChild(frag);
  }

  function buildSegmentFallbackLabel(index){
    return STRINGS.segmentFallbackLabel(index);
  }

  function selectSegment(id){
    if(state.activeSegmentId === id) return;
    if(!state.segments.find(seg=>seg.id===id)) return;
    state.activeSegmentId = id;
    syncActiveSegmentUI();
  }

  function removeSegment(id){
    if(state.segments.length <= 1) return;
    const idx = state.segments.findIndex(seg=>seg.id===id);
    if(idx === -1) return;
    state.segments.splice(idx,1);
    delete state.segmentErrors[id];
    if(state.activeSegmentId === id){
      const fallback = state.segments[idx] || state.segments[idx-1] || state.segments[0];
      state.activeSegmentId = fallback ? fallback.id : null;
    }
    syncActiveSegmentUI();
  }

  function moveSegment(id, delta){
    const idx = state.segments.findIndex(seg=>seg.id===id);
    if(idx === -1) return;
    const target = idx + delta;
    if(target < 0 || target >= state.segments.length) return;
    const [seg] = state.segments.splice(idx,1);
    state.segments.splice(target,0,seg);
    syncActiveSegmentUI();
  }

  function getActiveSegment(){
    return state.segments.find(seg=>seg.id===state.activeSegmentId) || null;
  }

  function renderSegmentBaseForm(){
    if(!dom.segmentBase) return;
    const segment = getActiveSegment();
    dom.segmentBase.innerHTML = '';
    if(!segment){
      const empty=document.createElement('p');
      empty.className='muted small';
      empty.textContent=STRINGS.segmentEmptyMessage;
      dom.segmentBase.appendChild(empty);
      return;
    }
    const frag=document.createDocumentFragment();
    SEGMENT_BASE_KEYS.forEach(key=>{
      const fieldDef = baseFieldByKey[key] || { key, label: key, type: key==='comment'?'text':'number' };
      const wrap=document.createElement('div');
      wrap.className='smartai-field';
      wrap.dataset.segmentBaseKey = key;
      const label=document.createElement('label');
      label.textContent = fieldDef.label || key;
      if(fieldDef.required){
        label.innerHTML = `${escapeHtml(fieldDef.label || key)}<span class="smartai-required">*</span>`;
      }
      wrap.appendChild(label);
      const input = createInput(fieldDef, segment.base[key]);
      input.dataset.segmentBaseKey = key;
      wrap.appendChild(input);
      const err=document.createElement('div');
      err.className='smartai-field__error';
      err.dataset.error='';
      wrap.appendChild(err);
      if(fieldDef.hint){
        const hint=document.createElement('p');
        hint.className='smartai-field__hint muted small';
        hint.textContent=fieldDef.hint;
        wrap.appendChild(hint);
      }
      input.addEventListener('input', ()=>{
        setSegmentBaseValue(segment, key, getInputValue(input, fieldDef.type));
        clearSegmentError('base', key);
      });
      input.addEventListener('change', ()=>{
        setSegmentBaseValue(segment, key, getInputValue(input, fieldDef.type));
        clearSegmentError('base', key);
      });
      frag.appendChild(wrap);
    });
    dom.segmentBase.appendChild(frag);
  }

  function setSegmentBaseValue(segment, key, value){
    if(key === 'comment'){
      segment.base[key] = value===''? '' : String(value ?? '');
      return;
    }
    if(value === '' || value === null || value === undefined){
      delete segment.base[key];
      return;
    }
    if(typeof value === 'number' && Number.isFinite(value)){
      segment.base[key] = value;
    } else {
      const num = Number(value);
      if(Number.isFinite(num)){
        segment.base[key] = num;
      }
    }
  }

  function applyStoredSegmentErrors(){
    const segment = getActiveSegment();
    if(!segment) return;
    const err = state.segmentErrors[segment.id];
    if(!err) return;
    if(err.base){
      Object.entries(err.base).forEach(([key,message])=>{
        highlightSegmentError('base', key, message);
      });
    }
    if(err.event){
      Object.entries(err.event).forEach(([key,message])=>{
        highlightSegmentError('event', key, message);
      });
    }
    if(err.action){
      Object.entries(err.action).forEach(([key,message])=>{
        highlightSegmentError('action', key, message);
      });
    }
    if(err.target){
      Object.entries(err.target).forEach(([key,message])=>{
        highlightSegmentError('target', key, message);
      });
    }
  }

  function getSelectorContainer(section){
    if(section === 'event') return dom.eventSelect;
    if(section === 'action') return dom.actionSelect;
    if(section === 'target') return dom.targetSelect;
    return null;
  }

  function clearSegmentError(section, key){
    const segment = getActiveSegment();
    if(section === 'base'){
      if(!dom.segmentBase) return;
      const field = dom.segmentBase.querySelector(`.smartai-field[data-segment-base-key="${CSS.escape(key)}"]`);
      if(field){ clearError(field); }
      if(segment){ removeSegmentErrorEntry(segment.id, section, key); }
      return;
    }
    if(section === 'event' || section === 'action' || section === 'target'){
      if(key === 'type'){
        const container = getSelectorContainer(section);
        if(!container) return;
        container.classList.remove('has-error');
        const err = container.querySelector('.smartai-selector-error');
        if(err){ err.textContent = ''; }
        if(segment){ removeSegmentErrorEntry(segment.id, section, key); }
        return;
      }
      const paramsContainer = section === 'event' ? dom.eventParams : section === 'action' ? dom.actionParams : dom.targetParams;
      if(!paramsContainer) return;
      const field = paramsContainer.querySelector(`.smartai-field[data-${section}Key="${CSS.escape(key)}"]`);
      if(field){ clearError(field); }
      if(segment){ removeSegmentErrorEntry(segment.id, section, key); }
    }
  }

  function recordSegmentError(segmentId, section, key, message){
    if(!segmentId) return;
    const bucket = state.segmentErrors[segmentId] = state.segmentErrors[segmentId] || { base:{}, event:{}, action:{}, target:{} };
    if(!bucket[section]){
      bucket[section] = {};
    }
    bucket[section][key] = message;
    renderSegmentTabs();
  }

  function removeSegmentErrorEntry(segmentId, section, key){
    if(!segmentId) return;
    const bucket = state.segmentErrors[segmentId];
    if(!bucket) return;
    if(bucket[section] && Object.prototype.hasOwnProperty.call(bucket[section], key)){
      delete bucket[section][key];
    }
    if(!segmentBucketHasErrors(bucket)){
      delete state.segmentErrors[segmentId];
      renderSegmentTabs();
    }
  }

  function segmentBucketHasErrors(bucket){
    return ['base','event','action','target'].some(section => bucket[section] && Object.keys(bucket[section]).length > 0);
  }

  function renderSelectors(){
    const segment = getActiveSegment();
    if(!segment){
      if(dom.eventSelect) dom.eventSelect.innerHTML = '';
      if(dom.actionSelect) dom.actionSelect.innerHTML = '';
      if(dom.targetSelect) dom.targetSelect.innerHTML = '';
      if(dom.eventParams) dom.eventParams.innerHTML = '';
      if(dom.actionParams) dom.actionParams.innerHTML = '';
      if(dom.targetParams) dom.targetParams.innerHTML = '';
      return;
    }

    buildSelector(dom.eventSelect, data.events || [], 'event', segment.eventType, (id)=>{
      if(segment.eventType === id) return;
      segment.eventType = id;
      segment.eventParams = {};
      renderParamFields(dom.eventParams, findById(data.events, id), 'event', segment);
      clearSegmentError('event', 'type');
    });

    buildSelector(dom.actionSelect, data.actions || [], 'action', segment.actionType, (id)=>{
      if(segment.actionType === id) return;
      segment.actionType = id;
      segment.actionParams = {};
      renderParamFields(dom.actionParams, findById(data.actions, id), 'action', segment);
      clearSegmentError('action', 'type');
    });

    buildSelector(dom.targetSelect, data.targets || [], 'target', segment.targetType, (id)=>{
      if(segment.targetType === id) return;
      segment.targetType = id;
      segment.targetParams = {};
      renderParamFields(dom.targetParams, findById(data.targets, id), 'target', segment);
      clearSegmentError('target', 'type');
    });

    renderParamFields(dom.eventParams, findById(data.events, segment.eventType), 'event', segment);
    renderParamFields(dom.actionParams, findById(data.actions, segment.actionType), 'action', segment);
    renderParamFields(dom.targetParams, findById(data.targets, segment.targetType), 'target', segment);
  }

  function buildSelector(container, list, section, selectedId, onSelect){
    if(!container){ return; }
    container.innerHTML = '';
    const searchWrap = document.createElement('div');
    searchWrap.className = 'smartai-search';
    const searchInput = document.createElement('input');
    searchInput.type = 'search';
  searchInput.placeholder = STRINGS.searchPlaceholder;
    searchWrap.appendChild(searchInput);
    container.appendChild(searchWrap);

    const listWrap = document.createElement('div');
    listWrap.className = 'smartai-selector-list';
    container.appendChild(listWrap);

  const errorBox = document.createElement('div');
  errorBox.className = 'smartai-field__error smartai-selector-error';
  errorBox.dataset.error = '';
  container.appendChild(errorBox);

    let currentFilter = '';

    function render(){
      listWrap.innerHTML = '';
      const filtered = list.filter(item => {
        if(!currentFilter) return true;
        const key = `${item.name || ''} ${item.code || ''} ${item.id ?? ''}`.toLowerCase();
        return key.includes(currentFilter);
      });
      filtered.forEach(item => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'smartai-selector-item';
        btn.dataset.id = item.id;
        btn.innerHTML = `<div class="smartai-selector-title">${escapeHtml(item.name || item.code || '')}</div>
          <div class="smartai-selector-sub">${escapeHtml(item.code || '')} · ID ${item.id}</div>
          <p class="smartai-selector-desc small muted">${escapeHtml(item.description || '')}</p>`;
        if(selectedId === item.id){
          btn.classList.add('active');
        }
        btn.addEventListener('click', () => {
          listWrap.querySelectorAll('.active').forEach(el=>el.classList.remove('active'));
          btn.classList.add('active');
          onSelect(item.id);
        });
        listWrap.appendChild(btn);
      });
      if(filtered.length === 0){
        const empty = document.createElement('div');
        empty.className = 'smartai-empty muted small';
        empty.textContent = STRINGS.listEmpty;
        listWrap.appendChild(empty);
      }
    }

    searchInput.addEventListener('input', () => {
      currentFilter = searchInput.value.trim().toLowerCase();
      render();
    });

    render();
  }

  function renderParamFields(container, definition, section, segment){
    container.innerHTML = '';
    if(!segment){ return; }
    if(!definition){
      const empty = document.createElement('p');
      empty.className = 'muted small';
      empty.textContent = STRINGS.selectorSelectType;
      container.appendChild(empty);
      return;
    }
    const params = definition.params || [];
    if(params.length === 0){
      const empty = document.createElement('p');
      empty.className = 'muted small';
      empty.textContent = STRINGS.selectorNoParams;
      container.appendChild(empty);
      return;
    }
    const frag = document.createDocumentFragment();
  const paramsState = section === 'event' ? segment.eventParams : section === 'action' ? segment.actionParams : segment.targetParams;
    params.forEach(param => {
      const field = document.createElement('div');
      field.className = 'smartai-field';
      field.dataset[`${section}Key`] = param.column;
      const label = document.createElement('label');
      label.innerHTML = `${escapeHtml(param.label || param.column)}${param.required ? '<span class="smartai-required">*</span>' : ''}`;
      field.appendChild(label);

      const input = createInput(param, param.default ?? '');
      input.dataset[`${section}Key`] = param.column;
      field.appendChild(input);

      const err = document.createElement('div');
      err.className = 'smartai-field__error';
      err.dataset.error = '';
      field.appendChild(err);

      if(param.hint){
        const hint = document.createElement('p');
        hint.className = 'smartai-field__hint muted small';
        hint.textContent = param.hint;
        field.appendChild(hint);
      }

      frag.appendChild(field);

      const defaultValue = paramsState[param.column] ?? param.default ?? (param.type === 'checkbox' ? false : '');
      setInputValue(input, defaultValue);
      paramsState[param.column] = getInputValue(input, param.type);

      input.addEventListener('input', () => {
        paramsState[param.column] = getInputValue(input, param.type);
        clearSegmentError(section, param.column);
      });
      input.addEventListener('change', () => {
        paramsState[param.column] = getInputValue(input, param.type);
        clearSegmentError(section, param.column);
      });
    });
    container.appendChild(frag);
  }

  function attachStepControls(){
    if(dom.nextBtn){
      dom.nextBtn.addEventListener('click', () => moveStep(1));
    }
    if(dom.prevBtn){
      dom.prevBtn.addEventListener('click', () => moveStep(-1));
    }
    if(dom.steps){
      dom.steps.addEventListener('click', (e)=>{
        const li = e.target.closest('li[data-step]');
        if(!li) return;
        const target = parseInt(li.dataset.step, 10);
        if(target < state.step && target >= 1){
          state.step = target;
          updateStepVisibility();
        }
      });
    }
  }

  function moveStep(delta){
    const target = state.step + delta;
    if(delta > 0 && !canProceed(state.step)){
      return;
    }
    if(target < 1 || target > 4) return;
    state.step = target;
    updateStepVisibility();
  }

  function canProceed(current){
    const segment = getActiveSegment();
    if(current === 1){
      if((state.base.entryorguid ?? 0) <= 0){
        Feedback.error(dom.flashBox, STRINGS.entryRequired);
        highlightError('base', 'entryorguid', STRINGS.entryInvalid);
        return false;
      }
    }
    if(!segment){
      Feedback.error(dom.flashBox, STRINGS.segmentRequired);
      return false;
    }
    if(current === 2){
      if(segment.eventType === null){
        Feedback.error(dom.flashBox, STRINGS.eventRequiredNext);
        const message = STRINGS.eventRequired;
        recordSegmentError(segment.id, 'event', 'type', message);
        highlightSegmentError('event', 'type', message);
        return false;
      }
    }
    if(current === 3){
      if(segment.actionType === null){
        Feedback.error(dom.flashBox, STRINGS.actionRequiredNext);
        const message = STRINGS.actionRequired;
        recordSegmentError(segment.id, 'action', 'type', message);
        highlightSegmentError('action', 'type', message);
        return false;
      }
    }
    return true;
  }

  function updateStepVisibility(){
    dom.stepContainers.forEach(section => {
      const step = parseInt(section.dataset.step,10);
      section.hidden = step !== state.step;
    });
    if(dom.segmentSection){
      dom.segmentSection.hidden = state.step < 2;
    }
    if(dom.stepLabel){ dom.stepLabel.textContent = state.step; }
    if(dom.prevBtn){ dom.prevBtn.disabled = state.step <= 1; }
    if(dom.nextBtn){ dom.nextBtn.disabled = state.step >= 4; }
    if(dom.steps){
      dom.steps.querySelectorAll('li').forEach(li => {
        const step = parseInt(li.dataset.step,10);
        li.classList.toggle('active', step === state.step);
        li.classList.toggle('completed', step < state.step);
      });
    }
  }

  function attachGenerate(){
    if(!dom.generateBtn) return;
    dom.generateBtn.addEventListener('click', () => {
      const activeSegment = getActiveSegment();
      if(!canProceed(3)){
        return;
      }
      const incomplete = state.segments.find(segment => segment.eventType === null || segment.actionType === null || segment.targetType === null);
      if(incomplete){
        selectSegment(incomplete.id);
        if(incomplete.eventType === null){
          const message = STRINGS.eventRequired;
          Feedback.error(dom.flashBox, STRINGS.eventRequiredAll);
          recordSegmentError(incomplete.id, 'event', 'type', message);
          highlightSegmentError('event', 'type', message);
        } else if(incomplete.actionType === null){
          const message = STRINGS.actionRequired;
          Feedback.error(dom.flashBox, STRINGS.actionRequiredAll);
          recordSegmentError(incomplete.id, 'action', 'type', message);
          highlightSegmentError('action', 'type', message);
        } else if(incomplete.targetType === null){
          const message = STRINGS.targetRequired;
          Feedback.error(dom.flashBox, STRINGS.targetRequiredAll);
          recordSegmentError(incomplete.id, 'target', 'type', message);
          highlightSegmentError('target', 'type', message);
        }
        return;
      }
      if(activeSegment && activeSegment.targetType === null){
        const message = STRINGS.targetRequired;
        Feedback.error(dom.flashBox, STRINGS.targetRequired);
        recordSegmentError(activeSegment.id, 'target', 'type', message);
        highlightSegmentError('target', 'type', message);
        return;
      }
      clearAllErrors();
      state.segmentErrors = {};
      renderSegmentTabs();
      Feedback.clear(dom.flashBox);
      const payload = buildPayload();
      sendPreview(payload);
    });
  }

  function buildPayload(){
    const segments = state.segments.map((segment, index) => ({
      key: segment.id,
      order: index,
      label: segment.label || null,
      base: {...segment.base},
      event: { type: segment.eventType, params: {...segment.eventParams} },
      action: { type: segment.actionType, params: {...segment.actionParams} },
      target: { type: segment.targetType, params: {...segment.targetParams} }
    }));
    const first = segments[0] || { event: { type: null, params: {} }, action: { type: null, params: {} }, target: { type: null, params: {} } };
    return {
      base: {...state.base},
      segments,
      event: first.event,
      action: first.action,
      target: first.target
    };
  }

  function sendPreview(payload){
    const body = { payload: JSON.stringify(payload) };
    const runner = window.Panel && Panel.api ? Panel.api.post('/smart-ai/api/preview', body) : fetch(window.APP_BASE + '/smart-ai/api/preview', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(body)
    }).then(res => res.json());

    Promise.resolve(runner).then(res => {
      if(!res){
        Feedback.error(dom.flashBox, STRINGS.apiNoResponse);
        return;
      }
      if(res.success){
        state.segmentErrors = {};
        if(Array.isArray(res.segments)){
          res.segments.forEach((segRes, index) => {
            const key = segRes && segRes.key ? segRes.key : null;
            let targetSegment = null;
            if(key){
              targetSegment = state.segments.find(seg => seg.id === key) || null;
            }
            if(!targetSegment){
              targetSegment = state.segments[index] || null;
            }
            if(!targetSegment) return;
            if(segRes.base){
              targetSegment.base = {...targetSegment.base, ...segRes.base};
            }
            if(segRes.label){
              targetSegment.label = segRes.label;
            }
            if(segRes.event && typeof segRes.event.id !== 'undefined'){
              targetSegment.eventType = segRes.event.id;
            }
            if(segRes.action && typeof segRes.action.id !== 'undefined'){
              targetSegment.actionType = segRes.action.id;
            }
            if(segRes.target && typeof segRes.target.id !== 'undefined'){
              targetSegment.targetType = segRes.target.id;
            }
          });
        }
        syncActiveSegmentUI();
        state.lastSql = res.sql || '';
        dom.preview.textContent = res.sql || STRINGS.previewPlaceholder;
        dom.copyBtn.disabled = !res.sql;
        const summaryPieces = [];
        if(Array.isArray(res.segments) && res.segments.length > 1){
          summaryPieces.push(STRINGS.summarySegments(res.segments.length));
        }
        if(res.event){
          const label = res.event.name || res.event.code || '';
          if(label){ summaryPieces.push(STRINGS.summaryEvent(label)); }
        }
        if(res.action){
          const label = res.action.name || res.action.code || '';
          if(label){ summaryPieces.push(STRINGS.summaryAction(label)); }
        }
        if(res.target){
          const label = res.target.name || res.target.code || '';
          if(label){ summaryPieces.push(STRINGS.summaryTarget(label)); }
        }
        dom.summary.textContent = summaryPieces.join(' · ');
        Feedback.success(dom.flashBox, STRINGS.generateSuccess);
      } else {
        applyErrors(res.errors || {});
        dom.preview.textContent = STRINGS.previewErrorPlaceholder;
        dom.copyBtn.disabled = true;
        Feedback.error(dom.flashBox, res.message || STRINGS.generateFailed);
      }
    }).catch(err => {
      console.error(err);
      Feedback.error(dom.flashBox, err && err.message ? err.message : STRINGS.requestFailed);
    });
  }

  function attachCopy(){
    if(!dom.copyBtn) return;
    dom.copyBtn.addEventListener('click', () => {
      if(!state.lastSql){ return; }
      navigator.clipboard.writeText(state.lastSql).then(()=>{
        Feedback.success(dom.flashBox, STRINGS.copySuccess);
      }).catch(()=>{
        Feedback.error(dom.flashBox, STRINGS.copyFailed);
      });
    });
  }

  function createInput(def, value){
    const type = def.type || 'text';
    let el;
    if(type === 'textarea'){
      el = document.createElement('textarea');
      el.rows = def.rows || 3;
    } else if(type === 'select' && Array.isArray(def.options)){
      el = document.createElement('select');
      def.options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label || opt.value;
        el.appendChild(option);
      });
    } else if(type === 'checkbox'){
      el = document.createElement('input');
      el.type = 'checkbox';
      el.className = 'smartai-checkbox-toggle';
    } else {
      el = document.createElement('input');
      el.type = type === 'number' ? 'number' : 'text';
      if(type === 'number'){
        if(def.min !== undefined) el.min = def.min;
        if(def.max !== undefined) el.max = def.max;
        if(def.step !== undefined) el.step = def.step;
      }
    }
    if(el){
      if(value !== undefined && value !== null){
        setInputValue(el, value);
      }
      return el;
    }
    return document.createElement('input');
  }

  function setInputValue(el, value){
    if(el.type === 'checkbox'){
      el.checked = value === true || value === 1 || value === '1';
      return;
    }
    el.value = value !== undefined && value !== null ? value : '';
  }

  function getInputValue(el, type){
    if(type === 'checkbox' || el.type === 'checkbox'){
      return el.checked;
    }
    if(type === 'number' || el.type === 'number'){
      return el.value === '' ? '' : Number(el.value);
    }
    return el.value;
  }

  function clearError(wrapper){
    if(!wrapper) return;
    wrapper.classList.remove('has-error');
    const err = wrapper.querySelector('[data-error]');
    if(err){ err.textContent = ''; }
  }

  function clearAllErrors(){
    document.querySelectorAll('.smartai-field').forEach(clearError);
    ['event','action','target'].forEach(section => {
      const container = getSelectorContainer(section);
      if(!container) return;
      container.classList.remove('has-error');
      const err = container.querySelector('.smartai-selector-error');
      if(err){ err.textContent = ''; }
    });
    if(dom.segmentBase){
      dom.segmentBase.querySelectorAll('.smartai-field').forEach(clearError);
    }
    if(dom.segmentTabs){
      dom.segmentTabs.querySelectorAll('.smartai-segment-chip').forEach(chip => chip.classList.remove('has-error'));
    }
  }

  function applyErrors(errors){
    if(!errors) return;
    const segmentErrorMap = {};
    const segments = state.segments;

    if(errors.segments){
      const list = Array.isArray(errors.segments) ? errors.segments : Object.values(errors.segments);
      list.forEach((segErr, index) => {
        if(!segErr) return;
        const key = segErr.key || null;
        let segment = null;
        if(key){
          segment = segments.find(seg => seg.id === key) || null;
        }
        if(!segment){
          segment = segments[index] || null;
        }
        if(!segment) return;
        segmentErrorMap[segment.id] = {
          base: {...(segErr.base || {})},
          event: {...(segErr.event || {})},
          action: {...(segErr.action || {})},
          target: {...(segErr.target || {})}
        };
      });
    }

    const legacySegment = segments[0] || null;
    if(legacySegment){
      if(errors.event){
        const bucket = segmentErrorMap[legacySegment.id] = segmentErrorMap[legacySegment.id] || { base:{}, event:{}, action:{}, target:{} };
        Object.assign(bucket.event, errors.event);
      }
      if(errors.action){
        const bucket = segmentErrorMap[legacySegment.id] = segmentErrorMap[legacySegment.id] || { base:{}, event:{}, action:{}, target:{} };
        Object.assign(bucket.action, errors.action);
      }
      if(errors.target){
        const bucket = segmentErrorMap[legacySegment.id] = segmentErrorMap[legacySegment.id] || { base:{}, event:{}, action:{}, target:{} };
        Object.assign(bucket.target, errors.target);
      }
    }

    state.segmentErrors = segmentErrorMap;
    renderSegmentTabs();
    applyStoredSegmentErrors();

    if(errors.base){
      Object.entries(errors.base).forEach(([key,message]) => {
        highlightError('base', key, message);
      });
    }
  }

  function highlightSegmentError(section, key, message){
    if(section === 'base'){
      if(!dom.segmentBase) return;
      const field = dom.segmentBase.querySelector(`.smartai-field[data-segment-base-key="${CSS.escape(key)}"]`);
      if(!field) return;
      field.classList.add('has-error');
      const err = field.querySelector('[data-error]');
      if(err){ err.textContent = message; }
      return;
    }

    if(section === 'event' || section === 'action' || section === 'target'){
      if(key === 'type'){
        const container = getSelectorContainer(section);
        if(!container) return;
        container.classList.add('has-error');
        let err = container.querySelector('.smartai-selector-error');
        if(!err){
          err = document.createElement('div');
          err.className = 'smartai-field__error smartai-selector-error';
          err.dataset.error = '';
          container.appendChild(err);
        }
        err.textContent = message;
        return;
      }
      const paramsContainer = section === 'event' ? dom.eventParams : section === 'action' ? dom.actionParams : dom.targetParams;
      if(!paramsContainer) return;
      const field = paramsContainer.querySelector(`.smartai-field[data-${section}Key="${CSS.escape(key)}"]`);
      if(!field) return;
      field.classList.add('has-error');
      const err = field.querySelector('[data-error]');
      if(err){ err.textContent = message; }
    }
  }

  function highlightError(section, key, message){
    let selector;
    if(section === 'base'){
      selector = `.smartai-field[data-base-key="${CSS.escape(key)}"]`;
    } else if(section === 'event'){
      selector = `.smartai-field[data-event-key="${CSS.escape(key)}"]`;
    } else if(section === 'action'){
      selector = `.smartai-field[data-action-key="${CSS.escape(key)}"]`;
    } else if(section === 'target'){
      selector = `.smartai-field[data-target-key="${CSS.escape(key)}"]`;
    }
    if(!selector) return;
    const field = document.querySelector(selector);
    if(!field) return;
    field.classList.add('has-error');
    const err = field.querySelector('[data-error]');
    if(err){ err.textContent = message; }
  }

  function findById(list, id){
    if(!Array.isArray(list)) return null;
    return list.find(item => item.id === id) || null;
  }

  function escapeHtml(str){
    return (str || '').replace(/[&<>"]+/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]);
    });
  }
})();

