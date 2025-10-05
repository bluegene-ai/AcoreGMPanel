/**
 * File: public/assets/js/modules/creature.js
 * Purpose: Provides functionality for the public/assets/js/modules module.
 * Functions:
 *   - translate()
 *   - creatureNotify()
 *   - resolveModal()
 *   - openModal()
 *   - hideModal()
 *   - hideAllModals()
 *   - refreshCreatureLogs()
 *   - collectChanges()
 *   - buildDiffSql()
 *   - updateDiffSqlPreview()
 *   - renderExecStructured()
 *   - performExecSql()
 *   - verify()
 *   - initNav()
 *   - initCompact()
 *   - apply()
 *   - dirtyGuard()
 *   - initBitmask()
 *   - ensureModal()
 *   - filterBits()
 *   - openForInput()
 *   - commitBits()
 *   - observeSections()
 *   - qs()
 *   - qsa()
 *   - apiGet()
 *   - apiPost()
 *   - buildVal()
 *   - setActive()
 */

(function(){
  if(!document.body || document.body.getAttribute('data-module')!=='creature') return;

  const qs=(sel,ctx=document)=>ctx.querySelector(sel);
  const qsa=(sel,ctx=document)=>Array.from(ctx.querySelectorAll(sel));
  const panelApi=window.Panel?.api;
  const apiGet=(path,params)=>{
    if(panelApi?.get) return panelApi.get(path,params);
    if(typeof panelApi==='function'){
      const query=params?('?'+new URLSearchParams(params).toString()):'';
      return panelApi(path+query,{method:'GET'});
    }
  return Promise.reject(new Error(translate('errors.panel_api_not_ready','Panel API is not ready')));
  };
  const apiPost=(path,body)=>{
    if(panelApi?.post) return panelApi.post(path,body);
    if(typeof panelApi==='function') return panelApi(path,{method:'POST',body});
  return Promise.reject(new Error(translate('errors.panel_api_not_ready','Panel API is not ready')));
  };

  const FEEDBACK_SELECTOR='#creature-feedback';
  const panelFeedback=(window.Panel && window.Panel.feedback && typeof window.Panel.feedback.show==='function') ? window.Panel.feedback : null;

  const panelLocale=window.Panel || {};
  const moduleLocaleFn=typeof panelLocale.moduleLocale==='function'
    ? panelLocale.moduleLocale.bind(panelLocale)
    : null;
  const moduleTranslator=typeof panelLocale.createModuleTranslator==='function'
    ? panelLocale.createModuleTranslator('creature')
    : null;

  function translate(path, fallback, replacements){
    const defaultValue=fallback ?? `modules.creature.${path}`;
    let text;
    if(moduleLocaleFn){
      text=moduleLocaleFn('creature', path, defaultValue);
    } else if(moduleTranslator){
      text=moduleTranslator(path, defaultValue);
    } else {
      text=defaultValue;
    }
    if(typeof text==='string' && text===`modules.creature.${path}` && fallback){
      text=fallback;
    }
    if(typeof text==='string' && replacements && typeof replacements==='object'){
      Object.entries(replacements).forEach(([key,value])=>{
        const pattern=new RegExp(`:${key}(?![A-Za-z0-9_])`, 'g');
        text=text.replace(pattern, String(value ?? ''));
      });
    }
    return text;
  }

  function creatureNotify(message,type='info',opts){
    const target=document.querySelector(FEEDBACK_SELECTOR);
    if(panelFeedback && target){
      const severity=type==='error'?'error':(type==='success'?'success':'info');
      const duration = opts && typeof opts.duration==='number' ? opts.duration : 3200;
      panelFeedback.show(target,severity,message,{duration});
      return;
    }
    let zone=document.querySelector('.flash-zone');
    if(!zone){ zone=document.createElement('div'); zone.className='flash-zone'; document.body.appendChild(zone); }
    const node=document.createElement('div'); node.className='flash flash-'+type; node.textContent=message; zone.appendChild(node);
    const duration=(opts && typeof opts.duration==='number') ? opts.duration : 3200;
    if(duration){ setTimeout(()=>{ if(node.parentNode===zone) node.remove(); },duration); }
  }

  function resolveModal(id){
    if(!id) return null;
    if(typeof id==='string'){
      if(id.startsWith('#')) return document.querySelector(id);
      return document.getElementById(id);
    }
    if(id && id.nodeType===1) return id;
    return null;
  }

  function openModal(id){
    const el=resolveModal(id); if(!el) return;
    el.style.display='block';
    el.classList.add('active');
    document.body.style.overflow='hidden';
  }

  function hideModal(id){
    const el=resolveModal(id); if(!el) return;
    el.style.display='none';
    el.classList.remove('active');
    if(!document.querySelector('.modal-backdrop.active')){ document.body.style.overflow=''; }
  }

  function hideAllModals(){
    document.querySelectorAll('.modal-backdrop.active').forEach(m=>{ m.style.display='none'; m.classList.remove('active'); });
    document.body.style.overflow='';
  }

  document.addEventListener('click',e=>{
    const closeBtn=e.target.closest('[data-close]');
    if(closeBtn){ hideModal(closeBtn.closest('.modal-backdrop')); return; }
    if(e.target.classList.contains('modal-backdrop')){ hideModal(e.target); }
  });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape'){ hideAllModals(); }});


  const btnNew=qs('#btn-new-creature');
  if(btnNew){ btnNew.addEventListener('click',()=>openModal('#modal-new-creature')); }

  const createBtn=qs('#btn-create-creature');
  if(createBtn){
    createBtn.addEventListener('click',async ()=>{
      const id=parseInt(qs('#newCreatureId')?.value||'0',10);
      const copyRaw=(qs('#copyCreatureId')?.value||'').trim();
      const copy=copyRaw===''?null:parseInt(copyRaw,10);
      if(!id){ creatureNotify(translate('create.enter_new_id','Please enter a new ID'),'error'); qs('#newCreatureId')?.focus(); return; }
      try{
        const res=await apiPost('/creature/api/create',{new_creature_id:id,copy_creature_id:copy});
        if(res && res.success){
          creatureNotify(translate('create.success_redirect','Creature created, redirecting...'),'success',{duration:1500});
          setTimeout(()=>{ location.href=window.Panel.url('/creature?edit_id='+id); },400);
        } else {
          creatureNotify(res?.message||translate('create.failure','Failed to create creature'),'error',{duration:5000});
        }
      }catch(err){
        const reason=err?.message||err;
        creatureNotify(translate('create.failure_with_reason','Creation failed: :reason',{reason}), 'error',{duration:5000});
      }
    });
  }

  async function refreshCreatureLogs(type){
    const box=qs('#creatureLogBox');
    const select=qs('#creatureLogType');
    const logType=type || (select?select.value:'sql');
    if(box) box.textContent=translate('logs.loading_placeholder','-- Loading... --');
    try{
      const res=await apiPost('/creature/api/logs',{type:logType,limit:200});
      if(res && res.success){
        const lines=Array.isArray(res.logs)?res.logs:[];
        if(box) box.textContent = lines.length? lines.join('\n') : translate('logs.empty_placeholder','-- No logs --');
      } else {
        if(box) box.textContent=translate('logs.load_failed_placeholder','-- Load failed --');
        creatureNotify(res?.message||translate('logs.load_failed','Failed to load logs'),'error',{duration:5000});
      }
    }catch(err){
      if(box) box.textContent=translate('logs.load_failed_placeholder','-- Load failed --');
      const reason=err?.message||err;
      creatureNotify(translate('logs.load_failed_with_reason','Failed to load logs: :reason',{reason}),'error',{duration:5000});
    }
  }

  const logBtn=qs('#btn-creature-sql-log');
  const logTypeSel=qs('#creatureLogType');
  if(logBtn){
    logBtn.addEventListener('click',async ()=>{
      openModal('#modal-creature-log');
      await refreshCreatureLogs(logTypeSel?.value);
    });
  }
  const logRefreshBtn=qs('#btn-refresh-creature-log');
  if(logRefreshBtn){ logRefreshBtn.addEventListener('click',()=>refreshCreatureLogs(logTypeSel?.value)); }
  if(logTypeSel){ logTypeSel.addEventListener('change',()=>refreshCreatureLogs(logTypeSel.value)); }

  document.addEventListener('click',async e=>{
    const del=e.target.closest('button.action-delete');
    if(!del) return;
    const id=parseInt(del.dataset.id||'0',10);
    if(!id) return;
    if(!confirm(translate('list.confirm_delete','Delete creature :id?',{id}))) return;
    try{
      const res=await apiPost('/creature/api/delete',{entry:id});
      if(res && res.success){
  creatureNotify(translate('list.delete_success','Creature deleted'),'success',{duration:1500});
        setTimeout(()=>location.reload(),500);
      } else {
        creatureNotify(res?.message||translate('list.delete_failed','Failed to delete creature'),'error',{duration:5000});
      }
    }catch(err){
      const reason=err?.message||err;
      creatureNotify(translate('list.delete_failed_with_reason','Failed to delete creature: :reason',{reason}),'error',{duration:5000});
    }
  },{capture:true});


  const form=qs('#form-creature-edit');
  const diffToggle=qs('#toggle-show-diff');
  const sqlExecBox=qs('#creatureSqlExecResult');
  const diffCountEl=qs('#creatureDiffCount');
  const groupSelector='details.creature-group';

  function collectChanges(){
    if(!form) return {};
    const fields=qsa('input,select,textarea',form);
    const changes={};
    fields.forEach(f=>{
      const orig=f.getAttribute('data-orig');
      if(orig===null) return;
      const cur=f.value;
      const changed=String(cur)!==String(orig);
      if(changed){
        changes[f.name]=cur;
        f.setAttribute('data-dirty','1');
        f.classList.add('field-modified');
      } else {
        f.removeAttribute('data-dirty');
        f.classList.remove('field-modified');
      }
    });
    if(diffToggle){
      fields.forEach(input=>{
        const wrap=input.closest('[data-field-wrapper]');
        if(!wrap) return;
        wrap.style.display = diffToggle.checked ? (input.classList.contains('field-modified')?'flex':'none') : 'flex';
      });
    }
    qsa(groupSelector).forEach(det=>{
      const count=det.querySelectorAll('input[data-dirty],textarea[data-dirty],select[data-dirty]').length;
      const marker=det.querySelector('[data-group-diff-count]');
      if(marker) marker.textContent=count?translate('diff.group_change_count','(:count changes)',{count}):'';
    });
    if(diffCountEl) diffCountEl.textContent=Object.keys(changes).length;
    return changes;
  }
  diffToggle?.addEventListener('change',collectChanges);

  const btnGen=qs('#btn-gen-update');
  const sqlPreview=qs('#creatureSqlPreview');
  const btnCopy=qs('#btn-copy-sql');
  const btnExecPreview=qs('#btn-exec-preview-sql');

  function buildDiffSql(){
    const changes=collectChanges();
    if(!form) return {sql:null,changes:{}};
    const id=parseInt(form.dataset.entry||form.querySelector('input[name=entry]')?.value||'0',10);
    if(!id || Object.keys(changes).length===0) return {sql:null,changes};
    const buildVal=v=>{
      if(v===''||v===null||v===undefined) return 'NULL';
      if(/^-?\d+$/.test(String(v))) return v;
      return `'${String(v).replace(/'/g,"''")}'`;
    };
    const parts=Object.entries(changes).filter(([k])=>k!=='entry').map(([k,v])=>`\`${k}\`=${buildVal(v)}`);
    if(!parts.length) return {sql:null,changes};
    return {sql:`UPDATE creature_template SET ${parts.join(', ')} WHERE entry=${id} LIMIT 1;`,changes};
  }

  function updateDiffSqlPreview(){
    const {sql}=buildDiffSql();
    if(!sql){
      if(sqlPreview){
        const placeholder=translate('diff.no_changes_placeholder','-- No changes --');
        if(sqlPreview.tagName==='TEXTAREA') sqlPreview.value=placeholder;
        else sqlPreview.textContent=placeholder;
      }
      if(btnCopy) btnCopy.disabled=true;
      if(btnExecPreview) btnExecPreview.disabled=true;
    }else{
      if(sqlPreview){
        if(sqlPreview.tagName==='TEXTAREA') sqlPreview.value=sql;
        else sqlPreview.textContent=sql;
      }
      if(btnCopy) btnCopy.disabled=false;
      if(btnExecPreview) btnExecPreview.disabled=false;
    }
  }

  let diffTimer=null;
  form?.addEventListener('input',()=>{
    if(diffTimer) clearTimeout(diffTimer);
    diffTimer=setTimeout(updateDiffSqlPreview,120);
  });

  if(btnGen){ btnGen.addEventListener('click',()=>qs('#creatureSqlSection')?.scrollIntoView({behavior:'smooth',block:'start'})); }
  btnCopy?.addEventListener('click',()=>{
    const txt=sqlPreview ? (sqlPreview.tagName==='TEXTAREA'?sqlPreview.value:sqlPreview.textContent) : '';
    if(!txt) return;
    navigator.clipboard.writeText(txt)
      .then(()=>creatureNotify(translate('diff.copy_sql_success','SQL copied'),'success'))
      .catch(()=>creatureNotify(translate('common.copy_failed','Copy failed'),'error'));
  });

  function renderExecStructured(kind,res,elapsedMs,executedSql){
    if(!sqlExecBox) return;
    sqlExecBox.style.display='block';
    const ok=res?.success;
    const statusKey=ok
      ? (kind==='save'?'exec.status.save_success':'exec.status.run_success')
      : (kind==='save'?'exec.status.save_failed':'exec.status.run_failed');
    const statusText=translate(statusKey, ok
      ? (kind==='save'?'Save succeeded':'Execution succeeded')
      : (kind==='save'?'Save failed':'Execution failed'));
    const jsonStr=JSON.stringify(res,null,2);
    const truncatedSql=executedSql && executedSql.length>240 ? executedSql.slice(0,240)+' ...' : executedSql || '';
    let html='<div class="result-head" style="display:flex;align-items:center;gap:10px;margin-bottom:6px">';
    html+=`<strong style="color:#89c2ff">${translate('exec.result_heading','Execution result')}</strong>`;
    html+=`<span class="badge" style="background:${ok?'#1f8b4d':'#c74242'}">${statusText}</span>`;
    if(elapsedMs!==undefined) html+=`<span style="font-size:11px;color:#6a7b86;">${elapsedMs}ms</span>`;
    if(res && typeof res.affected!=='undefined') html+=`<span class="muted" style="font-size:11px;">${translate('exec.rows_affected','Rows: :count',{count:res.affected})}</span>`;
    if(truncatedSql) html+=`<span class="muted" style="font-size:11px;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${executedSql.replace(/"/g,'&quot;')}">${translate('exec.sql_prefix','SQL: :sql',{sql:truncatedSql.replace(/</g,'&lt;')})}</span>`;
    html+='</div>';
    if(res?.message){
      const cls=ok?'panel-flash--success':'panel-flash--error';
      html+=`<div class="panel-flash panel-flash--inline ${cls} is-visible" style="margin-bottom:6px">${res.message}</div>`;
    }
    if(res?.after){
      html+=`<div style="font-weight:bold;color:#8fa8b7;margin:4px 0 2px;">${translate('exec.sample_row_heading','Sample row')}</div>`;
      html+=`<pre class="mono" style="max-height:160px;overflow:auto;background:#0c1318;padding:8px 10px;border-radius:4px;margin:0">${JSON.stringify(res.after,null,2)}</pre>`;
    }
    html+='<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">';
    html+=`<button type="button" class="btn btn-sm outline" data-exec-act="clear">${translate('exec.actions.clear','Clear')}</button>`;
    html+=`<button type="button" class="btn btn-sm" data-exec-act="hide">${translate('exec.actions.hide','Hide')}</button>`;
    html+=`<button type="button" class="btn btn-sm outline" data-exec-act="copy-json">${translate('exec.actions.copy_json','Copy JSON')}</button>`;
    if(executedSql) html+=`<button type="button" class="btn btn-sm outline" data-exec-act="copy-sql">${translate('exec.actions.copy_sql','Copy SQL')}</button>`;
    html+='</div>';
    sqlExecBox.innerHTML=html;
    sqlExecBox.querySelectorAll('[data-exec-act]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const act=btn.getAttribute('data-exec-act');
        if(act==='clear'){ sqlExecBox.innerHTML=''; sqlExecBox.style.display='none'; }
        else if(act==='hide'){ sqlExecBox.style.display='none'; }
        else if(act==='copy-json'){ navigator.clipboard.writeText(jsonStr).then(()=>creatureNotify(translate('exec.copy_json_success','JSON copied'),'success')).catch(()=>creatureNotify(translate('common.copy_failed','Copy failed'),'error')); }
        else if(act==='copy-sql' && executedSql){ navigator.clipboard.writeText(executedSql).then(()=>creatureNotify(translate('exec.copy_sql_success','SQL copied'),'success')).catch(()=>creatureNotify(translate('common.copy_failed','Copy failed'),'error')); }
      });
    });
  }

  async function performExecSql(rawSql,{successMessage,errorPrefix,applyAfter=true}={}){
    const sql=(rawSql||'').trim();
    if(!sql){
      creatureNotify(translate('exec.sql_empty_notify','SQL is empty, cannot execute'),'error');
      return {success:false,message:translate('exec.sql_empty_response','SQL is empty')};
    }
    const started=performance.now();
    try{
      const res=await apiPost('/creature/api/exec-sql',{sql});
      const elapsed=(performance.now()-started).toFixed(1);
      const ok=res?.success;
      const successText=successMessage ?? translate('exec.default_success','Execution succeeded');
      const errorText=errorPrefix ?? translate('exec.default_error','Execution failed');
      if(ok){
        if(successText) creatureNotify(successText,'success');
        if(applyAfter && res.after && form){
          Object.entries(res.after).forEach(([k,v])=>{
            const input=form.querySelector(`[name="${k}"]`);
            if(input){ input.value=v; input.setAttribute('data-orig',String(v)); }
          });
          collectChanges();
          updateDiffSqlPreview();
        }
      } else {
        creatureNotify(res?.message||errorText,'error',{duration:5000});
      }
      renderExecStructured('exec',res,elapsed,sql);
      return res;
    }catch(err){
      const elapsed=(performance.now()-started).toFixed(1);
      const msg=err?.message||String(err);
      const prefix=errorPrefix ?? translate('exec.default_error','Execution failed');
      creatureNotify(translate('exec.failure_with_reason',':prefix: :reason',{prefix,reason:msg}),'error',{duration:5000});
      const failRes={success:false,message:msg};
      renderExecStructured('exec',failRes,elapsed,sql);
      return failRes;
    }
  }

  const btnExec=qs('#btn-exec-sql');
  if(btnExec){
    btnExec.addEventListener('click',async ()=>{
      const sql=prompt(translate('exec.prompt_sql','Enter a single UPDATE/INSERT SQL statement'));
      if(sql===null) return;
      if(!/^(UPDATE|INSERT)/i.test(sql.trim())){ creatureNotify(translate('exec.only_update_insert','Only UPDATE or INSERT statements are allowed'),'error'); return; }
      await performExecSql(sql,{successMessage:translate('exec.default_success','Execution succeeded')});
    });
  }

  btnExecPreview?.addEventListener('click',async ()=>{
    const {sql}=buildDiffSql();
    if(!sql){ creatureNotify(translate('exec.no_diff_sql','No diff SQL to execute'),'info'); return; }
    const confirmMsg=translate('exec.confirm_run_diff','Execute the following SQL?\n:sql',{sql});
    if(!confirm(confirmMsg)) return;
    await performExecSql(sql,{successMessage:translate('exec.diff_sql_success','Diff SQL executed successfully')});
  });

  // --- Verification ---
  async function verify(entry, showModal=true){
    const snapshot={};
    qsa('.creature-table tbody tr').forEach(tr=>{
      const eid=tr.getAttribute('data-entry'); if(!eid) return;
      const cells=tr.children;
      snapshot[eid]={
        entry:cells[0].textContent.trim(),
        name:cells[1].textContent.trim(),
        subname:cells[2].textContent.trim(),
        minlevel:cells[3].textContent.trim(),
        maxlevel:cells[4].textContent.trim(),
        faction:cells[5].textContent.trim(),
        npcflag:cells[6].textContent.trim()
      };
    });
    try{
      const res=await apiGet('/creature/api/fetch-row',{entry});
      if(!res?.success){ creatureNotify(res?.message||translate('verify.failure','Verification failed'),'error'); return; }
      const orig=snapshot[entry]||{};
      const now=res.row||{};
      const fields=['entry','name','subname','minlevel','maxlevel','faction','npcflag'];
      const tbody=qs('#verifyDiffTable tbody');
      if(showModal && tbody) tbody.innerHTML='';
      let diff=0;
      const diffBadText=translate('verify.diff_bad','Different');
      const diffOkText=translate('verify.diff_ok','Match');
      fields.forEach(f=>{
        const o=orig[f]??'';
        const n=now[f]??'';
        const changed=String(o)!==String(n);
        if(changed) diff++;
        if(showModal && tbody){
          const tr=document.createElement('tr');
          if(changed) tr.classList.add('diff-row-changed');
          tr.innerHTML=`<td>${f}</td><td>${o}</td><td>${n}</td><td>${changed?`<span class="text-diff-bad">${diffBadText}</span>`:`<span class="text-diff-ok">${diffOkText}</span>`}</td>`;
          tbody.appendChild(tr);
        }
      });
      if(showModal){
        qs('#verifyDiag').textContent=`DB=${res.diag?.database||'?'} HOST=${res.diag?.hostname||'?'} CID=${res.diag?.conn_id||'?'} `;
        const sugg=qs('#verifySuggestion');
        const copyBtn=qs('#verifyCopySQL');
        if(diff){
          const setParts=fields.filter(f=>f!=='entry' && String(orig[f]??'')!==String(now[f]??'')).map(f=>`\`${f}\`=${now[f]}`);
          const sql=`UPDATE creature_template SET ${setParts.join(', ')} WHERE entry=${entry};`;
          const diffSummary=translate('verify.diff_summary','Detected :count mismatches',{count:diff});
          sugg.innerHTML=`<div class="panel-flash panel-flash--inline panel-flash--error is-visible">${diffSummary}</div><pre id="verifySQLPre" class="mono code-block">${sql}</pre>`;
          if(copyBtn){
            copyBtn.style.display='inline-block';
            const copyLabel=translate('verify.copy_update','Copy UPDATE statement');
            const copiedLabel=translate('verify.copied','Copied');
            copyBtn.textContent=copyLabel;
            copyBtn.onclick=()=>{
              navigator.clipboard.writeText(sql).then(()=>{
                copyBtn.textContent=copiedLabel;
                setTimeout(()=>copyBtn.textContent=copyLabel,1500);
              }).catch(()=>creatureNotify(translate('common.copy_failed','Copy failed'),'error'));
            };
          }
        } else {
          sugg.innerHTML=`<div class="panel-flash panel-flash--inline panel-flash--success is-visible">${translate('verify.row_match','Row matches database')}</div>`;
          if(copyBtn){ copyBtn.style.display='none'; copyBtn.onclick=null; }
        }
        openModal('#modal-verify');
      }
    }catch(err){
      const reason=err?.message||err;
      creatureNotify(translate('verify.failure_with_reason','Verification failed: :reason',{reason}),'error',{duration:5000});
    }
  }

  document.addEventListener('click',e=>{
    const btn=e.target.closest('button.action-verify');
    if(btn){ verify(parseInt(btn.dataset.entry||'0',10)); }
  });

  // --- Model operations ---
  const tableModels=qs('#table-models');
  const modelModal='#modal-model';
  qs('#btn-add-model')?.addEventListener('click',()=>{
    const modal=resolveModal(modelModal);
    if(!modal) return;
    qs('#modelIdx',modal).value='';
    qs('#modelDisplayId',modal).value='';
    qs('#modelScale',modal).value='1';
    qs('#modelProb',modal).value='1';
    qs('#modelVb',modal).value='12340';
    openModal(modelModal);
  });

  qs('#btn-save-model')?.addEventListener('click',async ()=>{
    if(!tableModels) return;
    const cid=tableModels.getAttribute('data-creature');
    const idx=qs('#modelIdx').value;
    const body={
      creature_id:cid,
      display_id:parseInt(qs('#modelDisplayId').value||'0',10),
      scale:parseFloat(qs('#modelScale').value||'1'),
      probability:parseFloat(qs('#modelProb').value||'1'),
      verifiedbuild:qs('#modelVb').value.trim()
    };
    const act=idx===''?'add-model':'edit-model';
    if(act==='edit-model') body.idx=parseInt(idx,10);
    try{
      const res=await apiPost('/creature/api/'+(act==='add-model'?'add-model':'edit-model'),body);
      if(res?.success){ creatureNotify(translate('models.save_success','Model saved successfully'),'success'); location.reload(); }
      else { creatureNotify(res?.message||translate('models.save_failed','Failed to save model'),'error',{duration:5000}); }
    }catch(err){
      const reason=err?.message||err;
      creatureNotify(translate('models.save_failed_with_reason','Failed to save model: :reason',{reason}),'error',{duration:5000});
    }
  });

  tableModels?.addEventListener('click',e=>{
    const edit=e.target.closest('button.action-edit-model');
    if(edit){
      const tr=edit.closest('tr');
      qs('#modelIdx').value=tr.dataset.idx;
      qs('#modelDisplayId').value=tr.dataset.display;
      qs('#modelScale').value=tr.dataset.scale;
      qs('#modelProb').value=tr.dataset.prob;
      qs('#modelVb').value=tr.dataset.vb;
      openModal(modelModal);
      return;
    }
    const del=e.target.closest('button.action-del-model');
    if(del){
      if(!confirm(translate('models.confirm_delete','Delete this model?'))) return;
      const cid=tableModels.getAttribute('data-creature');
      const idx=del.closest('tr').dataset.idx;
      apiPost('/creature/api/delete-model',{creature_id:cid,idx}).then(res=>{
        if(res?.success){ creatureNotify(translate('models.delete_success','Model deleted'),'success'); location.reload(); }
        else { creatureNotify(res?.message||translate('models.delete_failed','Failed to delete model'),'error',{duration:5000}); }
      }).catch(err=>{
        const reason=err?.message||err;
        creatureNotify(translate('models.delete_failed_with_reason','Failed to delete model: :reason',{reason}),'error',{duration:5000});
      });
    }
  });

  // --- Save and delete ---
  const btnSave=qs('#btn-save-creature');
  btnSave?.addEventListener('click',async ()=>{
    if(!form) return;
    const {changes,sql}=buildDiffSql();
  if(Object.keys(changes).length===0){ creatureNotify(translate('save.no_changes','No changes to save'),'info'); return; }
    const entry=parseInt(form.dataset.entry||form.querySelector('input[name=entry]')?.value||'0',10);
    const started=performance.now();
    try{
      const res=await apiPost('/creature/api/save',{entry,changes});
      const elapsed=(performance.now()-started).toFixed(1);
      if(res?.success){
        creatureNotify(translate('save.success','Saved successfully'),'success');
        Object.entries(changes).forEach(([k,v])=>{
          const input=form.querySelector(`[name="${k}"]`);
          if(input) input.setAttribute('data-orig',String(v));
        });
        collectChanges(); updateDiffSqlPreview();
        document.dispatchEvent(new CustomEvent('creatureEditSaved'));
      } else {
        creatureNotify(res?.message||translate('save.failed','Failed to save'),'error',{duration:5000});
      }
      renderExecStructured('save',res,elapsed,sql||'');
    }catch(err){
      const reason=err?.message||err;
      creatureNotify(translate('save.failed_with_reason','Failed to save: :reason',{reason}),'error',{duration:5000});
      renderExecStructured('save',{success:false,message:err?.message||String(err)},'0',sql||'');
    }
  });

  qs('#btn-delete-creature')?.addEventListener('click',async e=>{
    const id=e.target.getAttribute('data-id');
    if(!id) return;
    if(!confirm(translate('save.confirm_delete_creature','Delete creature :id?',{id}))) return;
    try{
      const res=await apiPost('/creature/api/delete',{entry:parseInt(id,10)});
      if(res?.success){ creatureNotify(translate('save.delete_success','Deleted successfully'),'success'); setTimeout(()=>{ location.href=window.Panel.url('/creature'); },700); }
      else { creatureNotify(res?.message||translate('save.delete_failed','Failed to delete'),'error',{duration:5000}); }
    }catch(err){
      const reason=err?.message||err;
      creatureNotify(translate('save.delete_failed_with_reason','Failed to delete: :reason',{reason}),'error',{duration:5000});
    }
  });

  // --- Navigation, compact mode, dirty check ---
  (function initNav(){
    const details=qsa('details.creature-group');
    const nav=qs('#creature-section-nav');
    const KEY='creatureEdit:sections:v1';
    details.forEach((d,i)=>{
      if(!d.id) d.id='cg_auto_'+i;
      if(!d.dataset.title){
        const sum=d.querySelector('summary');
        d.dataset.title=sum?sum.firstChild.textContent.trim():translate('nav.auto_group_title','Group :index',{index:i+1});
      }
    });
    try{
      const saved=JSON.parse(localStorage.getItem(KEY)||'{}');
      details.forEach(d=>{ if(saved[d.id]===false) d.open=false; });
    }catch(_){ /* noop */ }
    details.forEach(d=>d.addEventListener('toggle',()=>{
      const current={}; details.forEach(x=>{ current[x.id]=x.open; });
      localStorage.setItem(KEY,JSON.stringify(current));
    }));
    if(nav){
      details.forEach(d=>{
        const btn=document.createElement('button');
        btn.type='button';
        btn.className='btn-sm btn outline';
        btn.textContent=d.dataset.title;
        btn.addEventListener('click',()=>{ d.open=true; d.scrollIntoView({behavior:'smooth',block:'start'}); });
        nav.appendChild(btn);
      });
    }
  })();

  (function initCompact(){
    const btn=qs('#btn-creature-compact');
    if(!btn) return;
    const KEY='creatureEdit:compact';
    const normalLabel=translate('compact.mode.normal','Normal');
    const compactLabel=translate('compact.mode.compact','Compact');
    function apply(flag){
      document.body.classList.toggle('compact',flag);
      btn.textContent=flag?normalLabel:compactLabel;
      localStorage.setItem(KEY,flag?'1':'0');
    }
    if(localStorage.getItem(KEY)==='1') apply(true);
    btn.addEventListener('click',()=>apply(!document.body.classList.contains('compact')));
  })();

  (function dirtyGuard(){
    if(!form) return;
    let dirty=false;
    form.addEventListener('input',()=> dirty=true,{once:true});
    window.addEventListener('beforeunload',e=>{ if(dirty){ e.preventDefault(); e.returnValue=''; }});
    document.addEventListener('creatureEditSaved',()=>{ dirty=false; });
  })();

  // --- Bitmask selector ---
  (function initBitmask(){
    const flagInputs=qsa('input[data-bitmask]');
    if(!flagInputs.length) return;
    const flagConfig=window.CREATURE_FLAG_CONFIG;
    if(!flagConfig) return;
    let modal=null; let currentInput=null; let bitsBox=null; let searchBox=null;

    function ensureModal(){
      if(modal) return;
      modal=document.createElement('div');
      modal.className='modal-backdrop';
      modal.style.display='none';
      modal.innerHTML=`<div class="modal-panel large" style="max-width:640px">
        <header style="display:flex;justify-content:space-between;align-items:center"><h3>${translate('bitmask.modal_title','Bitmask selection')}</h3><button class="modal-close" data-close>&times;</button></header>
        <div class="modal-body">
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <strong id="bitmaskFieldName"></strong>
            <input type="text" id="bitmaskSearch" placeholder="${translate('bitmask.search_placeholder','Search...')}" style="flex:1;padding:4px 6px">
            <button class="btn-sm btn outline" id="bitmaskSelectAll" type="button">${translate('bitmask.select_all','Select all')}</button>
            <button class="btn-sm btn outline" id="bitmaskClear" type="button">${translate('bitmask.clear','Clear')}</button>
          </div>
          <div id="bitmaskBits" class="bitmask-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:4px;max-height:340px;overflow:auto;border:1px solid var(--c-border,#28323c);padding:6px;border-radius:4px"></div>
          <div style="margin-top:10px;font-size:12px" class="muted">${translate('bitmask.tips','Tip: checking will update the value immediately. Use search to filter descriptions.')}</div>
        </div>
        <footer style="text-align:right;margin-top:10px"><button class="btn outline" data-close>${translate('bitmask.close','Close')}</button></footer>
      </div>`;
      document.body.appendChild(modal);
      bitsBox=qs('#bitmaskBits',modal);
      searchBox=qs('#bitmaskSearch',modal);
      const selectAll=qs('#bitmaskSelectAll',modal);
      const clear=qs('#bitmaskClear',modal);
      modal.addEventListener('click',e=>{ if(e.target===modal) hideModal(modal); });
      searchBox.addEventListener('input',filterBits);
      selectAll.addEventListener('click',()=>{ bitsBox.querySelectorAll('input[type=checkbox]').forEach(cb=>cb.checked=true); commitBits(); });
      clear.addEventListener('click',()=>{ bitsBox.querySelectorAll('input[type=checkbox]').forEach(cb=>cb.checked=false); commitBits(); });
    }

    function filterBits(){
      const kw=(searchBox.value||'').toLowerCase();
      bitsBox.querySelectorAll('.bitmask-bit').forEach(div=>{
        const txt=div.getAttribute('data-text');
        div.style.display = (!kw || txt.includes(kw)) ? 'flex' : 'none';
      });
    }

    function openForInput(input){
      ensureModal();
      currentInput=input;
      const field=input.getAttribute('data-bitmask');
      const map=flagConfig[field];
      if(!map) return;
      qs('#bitmaskFieldName',modal).textContent=translate('bitmask.field_title',':field (:value)',{field,value:input.value||0});
      bitsBox.innerHTML='';
      const current=parseInt(input.value||'0',10)||0;
      Object.keys(map).sort((a,b)=>parseInt(a,10)-parseInt(b,10)).forEach(bit=>{
        const idx=parseInt(bit,10);
        const checked=(current & (1<<idx))!==0;
        const label=document.createElement('label');
        label.className='bitmask-bit';
        label.style.cssText='display:flex;gap:4px;align-items:center;padding:2px 4px;border-radius:3px;background:var(--c-bg-alt,#1b252c);';
        label.setAttribute('data-text',String(map[bit]).toLowerCase());
        label.innerHTML=`<input type="checkbox" data-bit="${idx}" ${checked?'checked':''}><span style="font-size:12px"><strong>${idx}</strong> ${map[bit]}</span>`;
        bitsBox.appendChild(label);
      });
      searchBox.value='';
      filterBits();
      openModal(modal);
    }

    function commitBits(){
      if(!currentInput) return;
      let val=0;
      bitsBox.querySelectorAll('input[type=checkbox][data-bit]').forEach(cb=>{ if(cb.checked){ val|=(1<<parseInt(cb.getAttribute('data-bit'),10)); } });
      currentInput.value=String(val);
      currentInput.dispatchEvent(new Event('input',{bubbles:true}));
      qs('#bitmaskFieldName',modal).textContent=translate('bitmask.field_title',':field (:value)',{field:currentInput.getAttribute('data-bitmask'),value:val});
    }

    bitsBox?.addEventListener('change',e=>{ if(e.target.matches('input[type=checkbox][data-bit]')) commitBits(); });
    flagInputs.forEach(input=>{
      const btn=document.createElement('button');
      btn.type='button';
      btn.textContent=translate('bitmask.trigger','Bits');
      btn.className='btn-sm btn outline';
      btn.style.marginLeft='4px';
      input.insertAdjacentElement('afterend',btn);
      btn.addEventListener('click',()=>openForInput(input));
      input.addEventListener('dblclick',()=>openForInput(input));
    });
  })();

  // --- Initialization ---
  collectChanges();
  updateDiffSqlPreview();

  (function observeSections(){
    const nav=qs('#creature-section-nav');
    if(!nav) return;
    const buttons=qsa('button',nav);
    if(!buttons.length) return;
    const map=new Map();
    buttons.forEach(btn=>{
      const txt=btn.textContent.trim();
      const target=qsa('details.creature-group').find(d=>d.dataset.title===txt);
      if(target) map.set(target,btn);
    });
    let activeBtn=null;
    const setActive=btn=>{
      if(activeBtn===btn) return;
      activeBtn?.classList.remove('active');
      if(btn){ btn.classList.add('active'); activeBtn=btn; }
    };
    const obs=new IntersectionObserver(entries=>{
      let cand=null; let max=0;
      entries.forEach(en=>{
        if(en.isIntersecting && en.intersectionRatio>max){ max=en.intersectionRatio; cand=en.target; }
      });
      if(cand && map.has(cand)) setActive(map.get(cand));
    },{root:null,rootMargin:'-20% 0px -60% 0px',threshold:[0,0.25,0.5,0.75,1]});
    map.forEach((_,section)=>obs.observe(section));
  })();
})();
