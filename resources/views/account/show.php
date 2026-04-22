<?php
/**
 * File: resources/views/account/show.php
 * Purpose: Account detail view.
 */

$summary = is_array($summary ?? null) ? $summary : null;
$characters = is_array($characters ?? null) ? $characters : [];
$accountShowCapabilities = $__pageCapabilities ?? [
  'characters' => $__can('accounts.characters'),
];

include dirname(__DIR__) . '/components/page_header.php';
?>
<?php if(!empty($error)): ?>
  <div class="panel-flash panel-flash--danger panel-flash--inline is-visible"><?= htmlspecialchars($error) ?></div>
<?php elseif($summary === null): ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.account.show.not_found')) ?></div>
<?php else: ?>
  <div class="char-card account-show-card">
    <h3 class="char-section-header"><?= htmlspecialchars(__('app.account.show.summary.title')) ?></h3>
    <table class="table table--compact">
      <tbody>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.id')) ?></th><td><?= (int)($summary['id'] ?? 0) ?></td></tr>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.username')) ?></th><td><?= account_link((int)($summary['id'] ?? 0), (string)($summary['username'] ?? '')) ?></td></tr>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.gmlevel')) ?></th><td><?= isset($summary['gmlevel']) ? (int)$summary['gmlevel'] : '-' ?></td></tr>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.status')) ?></th><td><?= !empty($summary['ban']) ? htmlspecialchars(__('app.account.show.status.banned')) : ((int)($summary['online'] ?? 0) === 1 ? htmlspecialchars(__('app.account.status.online')) : htmlspecialchars(__('app.account.status.offline'))) ?></td></tr>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.last_login')) ?></th><td><?= htmlspecialchars(format_datetime($summary['last_login'] ?? null)) ?></td></tr>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.last_ip')) ?></th><td><?= htmlspecialchars((string)($summary['last_ip'] ?? '-')) ?></td></tr>
        <?php if(array_key_exists('email', $summary)): ?>
          <tr><th><?= htmlspecialchars(__('app.account.show.summary.email')) ?></th><td><?= htmlspecialchars((string)($summary['email'] ?? '-')) ?></td></tr>
        <?php endif; ?>
        <?php if(array_key_exists('reg_mail', $summary)): ?>
          <tr><th><?= htmlspecialchars(__('app.account.show.summary.reg_mail')) ?></th><td><?= htmlspecialchars((string)($summary['reg_mail'] ?? '-')) ?></td></tr>
        <?php endif; ?>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.character_count')) ?></th><td><?= (int)($summary['character_count'] ?? 0) ?></td></tr>
        <tr><th><?= htmlspecialchars(__('app.account.show.summary.highest_level')) ?></th><td><?= $summary['highest_level'] !== null ? (int)$summary['highest_level'] : '-' ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="char-card account-show-card">
    <h3 class="char-section-header"><?= htmlspecialchars(__('app.account.show.characters.title')) ?></h3>
    <?php if(!$accountShowCapabilities['characters']): ?>
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></div>
    <?php elseif($characters === []): ?>
      <div class="muted"><?= htmlspecialchars(__('app.account.show.characters.empty')) ?></div>
    <?php else: ?>
      <table class="table table--compact">
        <thead>
          <tr>
            <th><?= htmlspecialchars(__('app.account.show.characters.table.guid')) ?></th>
            <th><?= htmlspecialchars(__('app.account.show.characters.table.name')) ?></th>
            <th><?= htmlspecialchars(__('app.account.show.characters.table.level')) ?></th>
            <th><?= htmlspecialchars(__('app.account.show.characters.table.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($characters as $character): ?>
            <tr>
              <td><?= (int)($character['guid'] ?? 0) ?></td>
              <td><?= character_link((int)($character['guid'] ?? 0), (string)($character['name'] ?? '')) ?></td>
              <td><?= (int)($character['level'] ?? 0) ?></td>
              <td><?= !empty($character['online']) ? htmlspecialchars(__('app.character.index.status.online')) : htmlspecialchars(__('app.character.index.status.offline')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>