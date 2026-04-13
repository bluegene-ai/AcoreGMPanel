<?php if (!empty($capabilityNotice)): ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
    <?= htmlspecialchars((string)$capabilityNotice) ?>
  </div>
<?php endif; ?>