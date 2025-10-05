<?php
/**
 * File: resources/views/partials/server_switch.php
 * Purpose: Provides functionality for the resources/views/partials module.
 */

use Acme\Panel\Support\ServerContext; use Acme\Panel\Support\ServerList;
if(!isset($current_server)) { $current_server = ServerContext::currentId(); }
if(!isset($servers) || !is_array($servers)) { $servers = ServerList::options(); }
$params = isset($preserve) && is_array($preserve) ? $preserve : $_GET;
unset($params['server'], $params['page']);
?>
<div class="server-switch" style="margin:6px 0 14px;display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
  <label for="serverSelectBox" style="font-size:13px;color:#8ea2b2;margin-right:4px"><?= htmlspecialchars(__('app.server.label')) ?>:</label>
  <select id="serverSelectBox" style="min-width:140px;padding:4px 6px;">
    <?php foreach($servers as $srv): $sid=(int)($srv['id']??0); $label=$srv['label']??__('app.server.default_option', ['id'=>$sid]); ?>
      <option value="<?= $sid ?>" <?= $sid===$current_server?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<script>(function(){
 const sel=document.getElementById('serverSelectBox'); if(!sel) return;
 function buildUrl(){
   const base=location.pathname.split('?')[0];
   const sp=new URLSearchParams(window.location.search);
   sp.delete('page');
   sp.set('server', sel.value);
   return base+'?'+sp.toString();
 }
 sel.addEventListener('change',()=>{ location.href=buildUrl(); });
})();</script>

