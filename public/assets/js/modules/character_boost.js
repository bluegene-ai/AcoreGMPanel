/**
 * File: public/assets/js/modules/character_boost.js
 * Purpose: 直升管理入口页交互：预览将要发放的内容、执行直升、刷新历史。
 * Functions:
 *   - translate()
 *   - toast()
 *   - post()
 *   - collectPayload()
 *   - renderPreview()
 *   - bindApply()
 *   - refreshHistory()
 *   - init()
 */

(function(){

  /** Base path of the panel install: window.APP_BASE is never set by the server. */
  const resolveBasePath = () => ((window.Panel && window.Panel.basePath && window.Panel.basePath()) || (document.body && document.body.dataset && document.body.dataset.appBase) || '').replace(/\/$/, '');
  if(document.body.dataset.module !== 'character_boost') return;

  const panel = window.Panel || {};
  const csrf = window.__CSRF_TOKEN;

  const qs = (sel, root) => (root || document).querySelector(sel);
  const qsa = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  function translate(path, fallback, replacements){
    let text;
    if(typeof panel.moduleLocale === 'function'){
      text = panel.moduleLocale('character_boost', path, fallback);
    } else {
      text = fallback !== undefined ? fallback : path;
    }
    if(typeof text === 'string' && replacements && typeof replacements === 'object'){
      Object.entries(replacements).forEach(([key, value]) => {
        text = text.replace(new RegExp(':' + key + '(?![A-Za-z0-9_])', 'g'), String(value == null ? '' : value));
      });
    }
    return text;
  }

  function esc(value){
    return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]
    ));
  }

  /** 统一反馈：优先面板 feedback 组件，缺失时退回页面内的提示条 */
  function toast(message, type){
    const text = String(message == null ? '' : message);
    if(!text) return;
    const kind = (type === 'error' || type === 'success' || type === 'info') ? type : 'info';
    const host = qs('#boostFeedback');

    if(host && panel.feedback && typeof panel.feedback.show === 'function'){
      panel.feedback.show(host, kind, text, { duration: kind === 'error' ? 6000 : 4000 });
      return;
    }
    if(host){
      host.hidden = false;
      host.textContent = text;
      host.classList.remove('panel-flash--success', 'panel-flash--error', 'panel-flash--info', 'is-visible');
      host.classList.add('panel-flash--' + kind, 'is-visible');
      window.clearTimeout(host.__cbTimer);
      host.__cbTimer = window.setTimeout(() => {
        host.hidden = true;
        host.classList.remove('is-visible');
      }, kind === 'error' ? 6000 : 4000);
    }
  }

  async function post(path, body){
    if(panel.api && typeof panel.api.post === 'function'){
      try{
        return await panel.api.post(path, body || {});
      }catch(error){
        return { success: false, message: (error && error.message) || translate('errors.network', 'Network error') };
      }
    }

    const fd = new FormData();
    Object.entries(body || {}).forEach(([key, value]) => fd.append(key, value == null ? '' : String(value)));
    if(csrf) fd.append('_token', csrf);

    try{
      const response = await fetch(resolveBasePath() + path, {
        method: 'POST',
        body: fd,
        headers: { 'X-CSRF-TOKEN': csrf || '' }
      });
      return await response.json();
    }catch(error){
      return { success: false, message: (error && error.message) || translate('errors.network', 'Network error') };
    }
  }

  function setBusy(button, busy, busyLabel){
    if(!button) return;
    if(busy){
      if(!button.dataset.idleLabel) button.dataset.idleLabel = button.textContent;
      if(busyLabel) button.textContent = busyLabel;
    } else if(button.dataset.idleLabel){
      button.textContent = button.dataset.idleLabel;
    }
    button.disabled = !!busy;
  }

  function collectPayload(form, dryRun){
    const name = (qs('#boostApplyName', form)?.value || '').trim();
    const guid = (qs('#boostApplyGuid', form)?.value || '').trim();
    const templateId = (qs('#boostApplyTemplate', form)?.value || '').trim();
    const level = (qs('#boostApplyLevel', form)?.value || '').trim();

    return {
      character_name: name,
      guid: guid,
      template_id: templateId,
      // 选了模板就以模板的 target_level 为准，避免两个入口给出不同结果
      target_level: templateId ? '' : level,
      dry_run: dryRun ? 1 : 0
    };
  }

  function validate(payload){
    if(!payload.character_name && !payload.guid){
      return translate('admin.errors.character_required', '请输入角色名或 GUID');
    }
    if(!payload.template_id && !payload.target_level){
      return translate('admin.errors.target_required', '请选择直升模板或填写目标等级');
    }
    return null;
  }

  /** 模板与目标等级互斥：选模板时禁用等级输入，语义更清楚 */
  function bindTemplateToggle(form){
    const templateSel = qs('#boostApplyTemplate', form);
    const levelInput = qs('#boostApplyLevel', form);
    if(!templateSel || !levelInput) return;

    const apply = () => {
      const hasTemplate = (templateSel.value || '') !== '';
      levelInput.disabled = hasTemplate;
      levelInput.classList.toggle('is-muted', hasTemplate);
      if(hasTemplate){
        const option = templateSel.selectedOptions && templateSel.selectedOptions[0];
        const targetLevel = option ? option.getAttribute('data-target-level') : '';
        levelInput.value = targetLevel || '';
        levelInput.placeholder = targetLevel
          ? translate('admin.fields.target_level_from_template', '由模板决定')
          : '';
      } else {
        levelInput.placeholder = translate('admin.fields.target_level_placeholder', '例如 80');
      }
    };

    templateSel.addEventListener('change', apply);
    apply();
  }

  function renderPreview(box, body, json){
    if(!box || !body) return;
    if(!json || !json.success){
      box.hidden = true;
      return;
    }

    const preview = json.preview || {};
    const character = preview.character || json.character || {};
    const rows = [];

    rows.push('<div class="cb-preview__row"><span>' + esc(translate('admin.preview.character', '角色')) + '</span><strong>'
      + esc(character.name || '') + ' (#' + esc(character.guid || 0) + ')</strong></div>');
    rows.push('<div class="cb-preview__row"><span>' + esc(translate('admin.preview.level', '等级')) + '</span><strong>'
      + esc(preview.previous_level == null ? character.level : preview.previous_level)
      + ' → ' + esc(preview.target_level || '') + '</strong></div>');

    if(preview.template){
      rows.push('<div class="cb-preview__row"><span>' + esc(translate('admin.preview.template', '模板')) + '</span><strong>'
        + esc(preview.template.name) + ' (#' + esc(preview.template.id) + ')</strong></div>');
    }

    const items = Array.isArray(preview.items) ? preview.items.slice() : [];
    const classItems = Array.isArray(preview.class_items) ? preview.class_items : [];
    classItems.forEach((item) => items.push(item));

    if(items.length){
      const list = items.map((item) => {
        const label = item.name ? (esc(item.name) + ' ×' + esc(item.quantity || 1))
          : ('#' + esc(item.entry) + ' ×' + esc(item.quantity || 1));
        return '<li>' + label + '</li>';
      }).join('');
      rows.push('<div class="cb-preview__row cb-preview__row--block"><span>'
        + esc(translate('admin.preview.items', '将发放')) + '</span><ul class="cb-preview__items">' + list + '</ul></div>');
    } else {
      rows.push('<div class="cb-preview__row"><span>' + esc(translate('admin.preview.items', '将发放')) + '</span><strong>'
        + esc(translate('admin.preview.items_none', '仅调整等级，无物品奖励')) + '</strong></div>');
    }

    if(Number(preview.money_gold) > 0){
      rows.push('<div class="cb-preview__row"><span>' + esc(translate('admin.preview.money', '金币')) + '</span><strong>'
        + esc(preview.money_gold) + '</strong></div>');
    }

    body.innerHTML = rows.join('');
    box.hidden = false;
  }

  function bindApply(){
    const form = qs('#boostApplyForm');
    if(!form) return;

    const endpoint = form.dataset.endpoint;
    const historyEndpoint = form.dataset.historyEndpoint;
    const previewBtn = qs('#boostApplyPreview', form);
    const submitBtn = qs('#boostApplySubmit', form);
    const previewBox = qs('#boostApplyPreviewBox');
    const previewBody = qs('#boostApplyPreviewBody');

    bindTemplateToggle(form);

    if(!endpoint) return;

    const runPreview = async () => {
      const payload = collectPayload(form, true);
      const invalid = validate(payload);
      if(invalid){ toast(invalid, 'error'); return; }

      setBusy(previewBtn, true, translate('admin.actions.working', '处理中…'));
      const json = await post(endpoint, payload);
      setBusy(previewBtn, false);

      if(!json || !json.success){
        if(previewBox) previewBox.hidden = true;
        toast((json && json.message) || translate('errors.request_failed', '请求失败'), 'error');
        return;
      }

      renderPreview(previewBox, previewBody, json);
    };

    if(previewBtn){ previewBtn.addEventListener('click', runPreview); }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = collectPayload(form, false);
      const invalid = validate(payload);
      if(invalid){ toast(invalid, 'error'); return; }

      // 先预览再执行：发送前让操作者确认"到底发了什么"
      const pendingPreview = previewBox && !previewBox.hidden;
      if(!pendingPreview){
        await runPreview();
      }

      setBusy(submitBtn, true, translate('admin.actions.working', '处理中…'));
      const json = await post(endpoint, payload);
      setBusy(submitBtn, false);

      if(!json || !json.success){
        toast((json && json.message) || translate('errors.request_failed', '请求失败'), 'error');
        return;
      }

      toast(json.message || translate('admin.applied', '直升已执行'), 'success');
      if(historyEndpoint){ refreshHistory(historyEndpoint); }
    });
  }

  function historyRow(log){
    const ok = parseInt(log.success, 10) === 1;
    const errorHtml = (!ok && log.sample_errors)
      ? '<div class="small text-danger" title="' + esc(log.sample_errors) + '">' + esc(log.sample_errors) + '</div>'
      : '';
    const moneyHtml = Number(log.amount) > 0
      ? '<div class="small muted">' + esc(log.amount) + '</div>'
      : '';

    return '<tr class="' + (ok ? 'log-ok' : 'log-fail') + '">'
      + '<td>' + esc(String(log.created_at || '').slice(0, 19)) + '</td>'
      + '<td>' + esc(log.recipients || '') + '</td>'
      + '<td><div>' + esc(log.items || '') + '</div>' + moneyHtml + errorHtml + '</td>'
      + '<td>' + (ok ? '✔' : '✖') + '</td>'
      + '</tr>';
  }

  async function refreshHistory(endpoint){
    const body = qs('#boostHistoryBody');
    if(!body || !endpoint) return;

    const json = await post(endpoint, { limit: 20 });
    if(!json || !json.success){
      if(json && json.message){ toast(json.message, 'error'); }
      return;
    }

    const logs = Array.isArray(json.logs) ? json.logs : [];
    if(!logs.length){
      body.innerHTML = '<tr class="js-empty-row"><td colspan="4" class="cb-empty-cell">'
        + esc(translate('admin.history.empty', '暂无直升记录')) + '</td></tr>';
      return;
    }
    body.innerHTML = logs.map(historyRow).join('');
  }

  function bindHistory(){
    const button = qs('#boostHistoryRefresh');
    const form = qs('#boostApplyForm');
    const endpoint = (form && form.dataset.historyEndpoint) || '';
    if(!button || !endpoint) return;

    button.addEventListener('click', async () => {
      setBusy(button, true, translate('admin.actions.working', '处理中…'));
      await refreshHistory(endpoint);
      setBusy(button, false);
    });
  }

  function init(){
    bindApply();
    bindHistory();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
