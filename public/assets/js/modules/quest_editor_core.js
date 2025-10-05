/**
 * File: public/assets/js/modules/quest_editor_core.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - ensureInit()
 *   - cloneDeep()
 *   - isObject()
 *   - isNumeric()
 *   - valuesEqual()
 *   - parsePath()
 *   - getByPath()
 *   - normalizePath()
 *   - setByPath()
 *   - deleteByPath()
 *   - traverseDiff()
 *   - computeDirty()
 *   - getPublicDirty()
 *   - pushUndo()
 *   - withRecordSuspended()
 *   - on()
 *   - off()
 *   - emit()
 *   - diffShallow()
 *   - sqlEscape()
 */

(function(global){
  'use strict';

  const Core = {};
  const panelLocale = global.Panel || {};
  const moduleLocaleFn = typeof panelLocale.moduleLocale === 'function'
    ? panelLocale.moduleLocale.bind(panelLocale)
    : null;
  const moduleTranslator = typeof panelLocale.createModuleTranslator === 'function'
    ? panelLocale.createModuleTranslator('quest')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue = fallback ?? `modules.quest.${path}`;
    let text;
    if(moduleLocaleFn){
      text = moduleLocaleFn('quest', path, defaultValue);
    } else if(moduleTranslator){
      text = moduleTranslator(path, defaultValue);
    } else {
      text = defaultValue;
    }
    if(typeof text === 'string' && text === `modules.quest.${path}` && fallback){
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
  const state = {
    original: {},
    current: {},
    dirtyMap: {},
    listeners: {},
    undoStack: [],
    redoStack: [],
    maxHistory: 200,
    initialized: false,
    suspendRecord: false,
    legacyFlat: false,
  };

  function ensureInit(){
    if(!state.initialized){
      throw new Error('QuestEditorCore not initialized');
    }
  }

  function cloneDeep(value){
    if(value === undefined){ return undefined; }
    return JSON.parse(JSON.stringify(value));
  }

  function isObject(val){
    return val && typeof val === 'object' && !Array.isArray(val);
  }

  function isNumeric(val){
    if(val === null || val === undefined) return false;
    if(typeof val === 'number') return !Number.isNaN(val);
    if(typeof val === 'string'){
      if(val.trim() === '') return false;
      return !Number.isNaN(Number(val));
    }
    return false;
  }

  function valuesEqual(a, b){
    if(a === b) return true;
    if(a === null || b === null || a === undefined || b === undefined) return false;
    if(isObject(a) || Array.isArray(a) || isObject(b) || Array.isArray(b)){
      return JSON.stringify(a) === JSON.stringify(b);
    }
    if(isNumeric(a) && isNumeric(b)){
      return Number(a) === Number(b);
    }
    return String(a) === String(b);
  }

  function parsePath(path){
    if(!path) return [];
    const segments = [];
    const regex = /\.?([^\.\[\]]+)|\[(\d+)\]/g;
    let match;
    while((match = regex.exec(path))){
      if(match[1] !== undefined){
        segments.push({type:'key', key: match[1]});
      } else if(match[2] !== undefined){
        segments.push({type:'index', index: parseInt(match[2],10)});
      }
    }
    return segments;
  }

  function getByPath(target, pathOrSegments){
    const segments = Array.isArray(pathOrSegments) ? pathOrSegments : parsePath(pathOrSegments);
    let cursor = target;
    for(const seg of segments){
      if(cursor === undefined || cursor === null){ return undefined; }
      if(seg.type === 'key'){
        cursor = cursor[seg.key];
      } else {
        cursor = Array.isArray(cursor) ? cursor[seg.index] : undefined;
      }
    }
    return cursor;
  }

  function normalizePath(path){
    if(!path || typeof path !== 'string') return path;
    if(path === '$') return path;
    if(state.legacyFlat){
      if(path === 'template' || path.startsWith('template.') || path.startsWith('template[')){
        return path;
      }
      if(!/[.\[]/.test(path)){
        return 'template.'+path;
      }
    }
    return path;
  }

  function setByPath(target, segments, value){
    if(!segments.length){ return value; }
    let cursor = target;
    for(let i=0;i<segments.length-1;i++){
      const seg = segments[i];
      if(seg.type === 'key'){
        if(cursor[seg.key] === undefined){
          cursor[seg.key] = segments[i+1] && segments[i+1].type === 'index' ? [] : {};
        }
        cursor = cursor[seg.key];
      } else {
        if(!Array.isArray(cursor)){
          throw new Error('Path expects array at segment '+i);
        }
        if(cursor[seg.index] === undefined){
          cursor[seg.index] = segments[i+1] && segments[i+1].type === 'index' ? [] : {};
        }
        cursor = cursor[seg.index];
      }
    }
    const leaf = segments[segments.length-1];
    let previous;
    if(leaf.type === 'key'){
      previous = cursor[leaf.key];
      cursor[leaf.key] = value;
    } else {
      if(!Array.isArray(cursor)){
        throw new Error('Path expects array at leaf');
      }
      previous = cursor[leaf.index];
      cursor[leaf.index] = value;
    }
    return previous;
  }

  function deleteByPath(target, segments){
    if(!segments.length) return undefined;
    let cursor = target;
    for(let i=0;i<segments.length-1;i++){
      const seg = segments[i];
      if(seg.type === 'key'){
        cursor = cursor ? cursor[seg.key] : undefined;
      } else {
        cursor = Array.isArray(cursor) ? cursor[seg.index] : undefined;
      }
      if(cursor === undefined || cursor === null){ return undefined; }
    }
    const leaf = segments[segments.length-1];
    if(leaf.type === 'key'){
      if(cursor && Object.prototype.hasOwnProperty.call(cursor, leaf.key)){
        const previous = cursor[leaf.key];
        delete cursor[leaf.key];
        return previous;
      }
      return undefined;
    }
    if(!Array.isArray(cursor)) return undefined;
    if(leaf.index >= 0 && leaf.index < cursor.length){
      const previous = cursor[leaf.index];
      cursor.splice(leaf.index,1);
      return previous;
    }
    return undefined;
  }

  function traverseDiff(path, orig, curr, map){
    if(valuesEqual(orig, curr)) return;
    if(!isObject(orig) && !Array.isArray(orig) && !isObject(curr) && !Array.isArray(curr)){
      map[path || '$'] = {old: orig, new: curr};
      return;
    }
    if(Array.isArray(orig) || Array.isArray(curr)){
      const oArr = Array.isArray(orig) ? orig : [];
      const cArr = Array.isArray(curr) ? curr : [];
      const max = Math.max(oArr.length, cArr.length);
      for(let i=0;i<max;i++){
        const childPath = path ? path+'['+i+']' : '['+i+']';
        traverseDiff(childPath, oArr[i], cArr[i], map);
      }
      if(oArr.length !== cArr.length){
        map[path || '$'] = {old: orig, new: curr};
      }
      return;
    }
    const keys = new Set([...(orig ? Object.keys(orig) : []), ...(curr ? Object.keys(curr) : [])]);
    keys.forEach(key => {
      const childPath = path ? path+'.'+key : key;
      traverseDiff(childPath, orig ? orig[key] : undefined, curr ? curr[key] : undefined, map);
    });
  }

  function computeDirty(){
    const diff = {};
    traverseDiff('', state.original, state.current, diff);
    if(diff['']){ delete diff['']; }
    state.dirtyMap = diff;
    emit('diff:update', {dirty: getPublicDirty()});
  }

  function getPublicDirty(cloneResult=true){
    let result;
    if(!state.legacyFlat){
      result = state.dirtyMap;
    } else {
      const alias = {};
      Object.keys(state.dirtyMap).forEach(path => {
        if(path.startsWith('template.')){
          const key = path.slice(9);
          alias[key] = state.dirtyMap[path];
        }
      });
      result = alias;
    }
    if(cloneResult){
      return cloneDeep(result || {});
    }
    return result || {};
  }

  function pushUndo(entry){
    state.undoStack.push(entry);
    if(state.undoStack.length > state.maxHistory){ state.undoStack.shift(); }
    state.redoStack.length = 0;
  }

  function withRecordSuspended(fn){
    const prev = state.suspendRecord;
    state.suspendRecord = true;
    try { fn(); }
    finally { state.suspendRecord = prev; }
  }

  function on(evt, handler){
    (state.listeners[evt]||(state.listeners[evt]=[])).push(handler);
    return ()=>off(evt, handler);
  }

  function off(evt, handler){
    const list = state.listeners[evt];
    if(!list) return;
    const idx = list.indexOf(handler);
    if(idx>=0) list.splice(idx,1);
  }

  function emit(evt, payload){
    (state.listeners[evt]||[]).forEach(handler=>{
      try { handler(payload); }
      catch(err){ console.error(err); }
    });
  }

  Core.init = function(aggregate){
    const data = aggregate || {};
    const hasAggregateShape = data && typeof data === 'object' && (data.template || data.objectives || data.rewards || data.relations || data.locales || data.poi);
    state.legacyFlat = !hasAggregateShape;
    const wrapped = state.legacyFlat ? { template: data } : data;
    state.original = cloneDeep(wrapped);
    state.current = cloneDeep(wrapped);
    state.undoStack = [];
    state.redoStack = [];
    state.initialized = true;
    computeDirty();
    emit('init', {aggregate: Core.getAll()});
  };

  Core.get = function(path){
    ensureInit();
    if(!path) return cloneDeep(state.current);
    const normalized = normalizePath(path);
    let result = getByPath(state.current, normalized);
    if(result === undefined && state.legacyFlat && normalized !== path){
      result = getByPath(state.current, path);
    }
    return cloneDeep(result);
  };

  Core.getOriginal = function(path){
    ensureInit();
    if(!path) return cloneDeep(state.original);
    const normalized = normalizePath(path);
    let result = getByPath(state.original, normalized);
    if(result === undefined && state.legacyFlat && normalized !== path){
      result = getByPath(state.original, path);
    }
    return cloneDeep(result);
  };

  Core.getAll = function(){
    ensureInit();
    return cloneDeep(state.current);
  };

  Core.getDirtyMap = function(){
    ensureInit();
    return getPublicDirty(true);
  };

  Core.isDirty = function(){
    ensureInit();
    return Object.keys(getPublicDirty(true)).length > 0;
  };

  Core.set = function(path, value, options={}){
    ensureInit();
    const normalized = normalizePath(path);
    const segments = parsePath(normalized);
    const previous = cloneDeep(getByPath(state.current, segments));
    if(valuesEqual(previous, value)) return;
    setByPath(state.current, segments, cloneDeep(value));
    if(options.record !== false && !state.suspendRecord){
      pushUndo({type:'set', path: normalized, previous, value: cloneDeep(value)});
    }
    computeDirty();
    emit('field:change', {path: normalized, value, old: previous});
  };

  Core.setField = function(name, value, options){
    Core.set('template.'+name, value, options);
  };

  Core.bulkPatch = function(obj, options){
    Object.entries(obj || {}).forEach(([key, val])=> Core.setField(key, val, options));
  };

  Core.remove = function(path, options={}){
    ensureInit();
    const normalized = normalizePath(path);
    const segments = parsePath(normalized);
    const previous = cloneDeep(getByPath(state.current, segments));
    if(previous === undefined) return;
    deleteByPath(state.current, segments);
    if(options.record !== false && !state.suspendRecord){
      pushUndo({type:'remove', path: normalized, previous});
    }
    computeDirty();
    emit('field:change', {path: normalized, value: undefined, old: previous, removed: true});
  };

  Core.arraySplice = function(path, start, deleteCount, items=[], options={}){
    const current = Core.get(path) || [];
    const clone = Array.isArray(current) ? current.slice() : [];
    const newItems = (items||[]).map(item=>cloneDeep(item));
    clone.splice(start, deleteCount, ...newItems);
    Core.set(path, clone, options);
  };

  Core.arrayPush = function(path, item, options={}){
    const current = Core.get(path) || [];
    const clone = Array.isArray(current) ? current.slice() : [];
    clone.push(cloneDeep(item));
    Core.set(path, clone, options);
  };

  Core.arrayRemoveAt = function(path, index, options={}){
    const current = Core.get(path) || [];
    if(!Array.isArray(current) || index < 0 || index >= current.length) return;
    const clone = current.slice();
    clone.splice(index,1);
    Core.set(path, clone, options);
  };

  Core.undo = function(){
    ensureInit();
    const entry = state.undoStack.pop();
    if(!entry) return;
    state.redoStack.push(cloneDeep(entry));
    withRecordSuspended(()=>{
      if(entry.type === 'set'){
        if(entry.previous === undefined){
          Core.remove(entry.path, {record:false});
        } else {
          Core.set(entry.path, entry.previous, {record:false});
        }
      } else if(entry.type === 'remove'){
        Core.set(entry.path, entry.previous, {record:false});
      }
    });
    computeDirty();
    emit('undo', entry);
  };

  Core.redo = function(){
    ensureInit();
    const entry = state.redoStack.pop();
    if(!entry) return;
    state.undoStack.push(cloneDeep(entry));
    withRecordSuspended(()=>{
      if(entry.type === 'set'){
        Core.set(entry.path, entry.value, {record:false});
      } else if(entry.type === 'remove'){
        Core.remove(entry.path, {record:false});
      }
    });
    computeDirty();
    emit('redo', entry);
  };

  Core.rebaseline = function(nextAggregate){
    ensureInit();
    if(nextAggregate){
      const hasAggregateShape = nextAggregate && typeof nextAggregate === 'object' && (nextAggregate.template || nextAggregate.objectives || nextAggregate.rewards || nextAggregate.relations || nextAggregate.locales || nextAggregate.poi);
      state.legacyFlat = !hasAggregateShape;
      const wrapped = state.legacyFlat ? { template: nextAggregate } : nextAggregate;
      state.original = cloneDeep(wrapped);
      state.current = cloneDeep(wrapped);
    } else {
      state.original = cloneDeep(state.current);
    }
    state.undoStack = [];
    state.redoStack = [];
    computeDirty();
    emit('rebaseline', {aggregate: Core.getAll()});
  };

  Core.buildPayload = function(mode='diff'){
    ensureInit();
    if(mode === 'full'){
      return cloneDeep(state.current);
    }
  const payload = {};
    const sections = ['template','addon','narrative','objectives','rewards','relations','locales','poi'];
    sections.forEach(section => {
      const curr = state.current ? state.current[section] : undefined;
      const orig = state.original ? state.original[section] : undefined;
      if(valuesEqual(curr, orig)) return;
      if(section === 'template'){
        const diff = diffShallow(orig || {}, curr || {});
        if(Object.keys(diff).length){ payload.template = diff; }
      } else if(section === 'addon'){
        payload.addon = (curr === undefined || curr === null) ? null : cloneDeep(curr);
      } else if(section === 'narrative'){
        const diff = {};
        ['details','request','offer'].forEach(key => {
          const currVal = curr ? curr[key] : undefined;
          const origVal = orig ? orig[key] : undefined;
          if(!valuesEqual(currVal, origVal)){
            diff[key] = currVal === undefined ? null : cloneDeep(currVal);
          }
        });
        if(Object.keys(diff).length){ payload.narrative = diff; }
      } else {
        payload[section] = cloneDeep(curr);
      }
    });
    return payload;
  };

  Core.buildSqlUpdate = function(){
    ensureInit();
    const diff = Core.buildPayload('diff');
    let templateDiff = diff.template;
    if((!templateDiff || !Object.keys(templateDiff).length) && state.legacyFlat){
      const dirty = getPublicDirty(false);
      const fallback = {};
      Object.keys(dirty || {}).forEach(key => {
        if(key === 'ID') return;
        const record = dirty[key];
        fallback[key] = record && Object.prototype.hasOwnProperty.call(record, 'new') ? record.new : Core.get('template.'+key);
      });
      templateDiff = fallback;
    }
    if(!templateDiff || !Object.keys(templateDiff).length){
      return translate('core.no_changes_sql_comment', '-- No changes --');
    }
    const assignments = Object.entries(templateDiff).map(([column, value])=>{
      if(value === null || value === ''){
        return '`'+column+'`=NULL';
      }
      return '`'+column+'`='+sqlEscape(value);
    });
  const questId = Core.get('template.ID') || Core.get('template.id') || Core.get('ID');
    const idValue = questId ? Number(questId) : 0;
    return 'UPDATE `quest_template` SET '+assignments.join(', ')+' WHERE `ID`='+idValue+' LIMIT 1;';
  };

  function diffShallow(orig, curr){
    const changeSet = {};
    const keys = new Set([...(orig ? Object.keys(orig) : []), ...(curr ? Object.keys(curr) : [])]);
    keys.forEach(key => {
      const oldVal = orig && Object.prototype.hasOwnProperty.call(orig, key) ? orig[key] : undefined;
      const newVal = curr && Object.prototype.hasOwnProperty.call(curr, key) ? curr[key] : undefined;
      if(valuesEqual(oldVal, newVal)) return;
      changeSet[key] = newVal === undefined ? null : newVal;
    });
    return changeSet;
  }

  function sqlEscape(value){
    return '\''+String(value).replace(/'/g,"''")+'\'';
  }

  Core.diffJson = function(){
    return Core.buildPayload('diff');
  };

  Core.on = on;
  Core.off = off;
  Core.emit = emit;

  window.addEventListener('keydown', function(e){
    const key = (e.key || '').toLowerCase();
    if((e.ctrlKey||e.metaKey) && !e.shiftKey && key === 'z'){
      Core.undo();
      e.preventDefault();
    } else if((e.ctrlKey||e.metaKey) && (key === 'y' || (e.shiftKey && key === 'z'))){
      Core.redo();
      e.preventDefault();
    }
  });

  global.QuestEditorCore = Core;
})(window);

