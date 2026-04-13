<?php
/**
 * File: resources/views/character_boost/templates.php
 * Purpose: Admin list for boost templates.
 */

$templates = is_array($templates ?? null) ? $templates : [];
$realmId = (int) ($realm_id ?? 1);
$deleteEndpoint = url('/character-boost/api/templates/delete');

include dirname(__DIR__) . '/components/page_header.php';
?>

<div class="panel">
  <div id="boostTplFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

  <table class="table" id="boostTplTable" data-delete-endpoint="<?= htmlspecialchars($deleteEndpoint) ?>">
    <thead>
      <tr>
        <th>ID</th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.name')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.target_level')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.money_gold')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.items')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.class_rewards')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.require_match')) ?></th>
        <th><?= htmlspecialchars(__('app.character_boost.templates.columns.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$templates): ?>
        <tr class="js-empty-row"><td colspan="8" class="cb-empty-cell"><?= htmlspecialchars(__('app.character_boost.templates.empty')) ?></td></tr>
      <?php else: ?>
        <?php foreach($templates as $tpl): ?>
          <?php
            $items = is_array($tpl['items'] ?? null) ? $tpl['items'] : [];
            $tiers = is_array($tpl['class_rewards'] ?? null) ? $tpl['class_rewards'] : [];
          ?>
          <tr data-id="<?= (int)($tpl['id'] ?? 0) ?>">
            <td><?= (int)($tpl['id'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($tpl['name'] ?? '')) ?></td>
            <td><?= (int)($tpl['target_level'] ?? 0) ?></td>
            <td><?= (int)($tpl['money_gold'] ?? 0) ?></td>
            <td class="cb-detail-cell">
              <?php if(!$items): ?>
                <span class="cb-muted">-</span>
              <?php else: ?>
                <?php foreach($items as $it): ?>
                  <?php
                    $entry = (int)($it['item_entry'] ?? 0);
                    $qty = (int)($it['quantity'] ?? 0);
                    $nm = (string)($it['item_name'] ?? '');
                    if($entry<=0 || $qty<=0) continue;
                  ?>
                  <div><?= htmlspecialchars($nm !== '' ? $nm : ('#'.$entry)) ?> ×<?= $qty ?> <span class="cb-code-meta">(#<?= $entry ?>)</span></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td class="cb-detail-cell">
              <?php if(!$tiers): ?>
                <span class="cb-muted">-</span>
              <?php else: ?>
                <?= htmlspecialchars(implode(', ', array_values(array_unique(array_map('strval', $tiers))))) ?>
              <?php endif; ?>
            </td>
            <td><?= ((int)($tpl['require_account_level_match'] ?? 0) === 1) ? 'Y' : 'N' ?></td>
            <td class="flex cb-action-group">
              <a class="btn" href="<?= url('/character-boost/templates/edit?id='.(int)($tpl['id'] ?? 0)) ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.edit')) ?></a>
              <button class="btn btn-danger js-boost-tpl-delete" type="button" data-id="<?= (int)($tpl['id'] ?? 0) ?>"><?= htmlspecialchars(__('app.character_boost.templates.actions.delete')) ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
