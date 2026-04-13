<?php
/**
 * File: resources/views/partials/server_switch.php
 * Purpose: Provides functionality for the resources/views/partials module.
 */

$current_server = isset($current_server) ? (int) $current_server : 0;
$servers = isset($servers) && is_array($servers) ? $servers : [];
?>
<div class="server-switch">
  <label for="serverSelectBox" class="server-switch__label"><?= htmlspecialchars(__('app.server.label')) ?>:</label>
  <select id="serverSelectBox" class="server-switch__select" data-panel-server-switch="1">
    <?php foreach($servers as $srv): $sid=(int)($srv['id']??0); $label=$srv['label']??__('app.server.default_option', ['id'=>$sid]); ?>
      <option value="<?= $sid ?>" <?= $sid===$current_server?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
  </select>
</div>

