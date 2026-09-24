<?php
/**
 * File: resources/views/auctionator/index.php
 * Purpose: mod-auctionator (拍卖机器人) management page: status / settings / item policy / GM actions.
 */

$snapshot = is_array($auctionator ?? null) ? $auctionator : [];
$paths = is_array($snapshot['paths'] ?? null) ? $snapshot['paths'] : [];
$conf = is_array($snapshot['conf'] ?? null) ? $snapshot['conf'] : [];
$fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
$groups = is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [];
$listings = is_array($snapshot['listings'] ?? null) ? $snapshot['listings'] : [];
$market = is_array($snapshot['market'] ?? null) ? $snapshot['market'] : [];
$policy = is_array($snapshot['policy'] ?? null) ? $snapshot['policy'] : [];
$logEntries = is_array($snapshot['log'] ?? null) ? $snapshot['log'] : [];
$warnings = is_array($snapshot['warnings'] ?? null) ? $snapshot['warnings'] : [];
$notes = is_array($snapshot['notes'] ?? null) ? $snapshot['notes'] : [];
$typed = is_array($conf['typed'] ?? null) ? $conf['typed'] : [];
$tables = is_array($policy['tables'] ?? null) ? $policy['tables'] : [];

$capabilities = $__pageCapabilities ?? [
    'view' => $__can('auctionator.view'),
    'manage' => $__can('auctionator.manage'),
    'control' => $__can('auctionator.control'),
];
$__pageCapabilities = $capabilities;
$canManage = (bool) ($capabilities['manage'] ?? false);
$canControl = (bool) ($capabilities['control'] ?? false);
$supported = (bool) ($notes['supported'] ?? true);
$capabilityNotice = $canManage ? null : __('app.common.capabilities.read_only');

$formatCopper = static function (int $copper): string {
    $gold = intdiv($copper, 10000);
    $silver = intdiv($copper % 10000, 100);
    $rest = $copper % 100;
    $parts = [];
    if ($gold > 0) {
        $parts[] = $gold . 'g';
    }
    if ($silver > 0) {
        $parts[] = $silver . 's';
    }
    if ($rest > 0 || $parts === []) {
        $parts[] = $rest . 'c';
    }

    return implode(' ', $parts);
};
$formatTime = static function (int $timestamp): string {
    return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : '--';
};
$toneClass = static function (string $tone): string {
    return 'au-badge au-badge--' . preg_replace('/[^a-z]/', '', strtolower($tone));
};
$houseLabel = static function (int $house): string {
    return match ($house) {
        2 => __('app.auctionator.houses.alliance'),
        6 => __('app.auctionator.houses.horde'),
        7 => __('app.auctionator.houses.neutral'),
        default => '#' . $house,
    };
};
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="au-page" data-au-page>
  <div class="au-tabs" role="tablist" aria-label="<?= htmlspecialchars(__('app.auctionator.tabs.label')) ?>">
    <button type="button" role="tab" class="au-tab au-tab--active" data-au-tab="status" aria-selected="true"><?= htmlspecialchars(__('app.auctionator.tabs.status')) ?></button>
    <button type="button" role="tab" class="au-tab" data-au-tab="settings" aria-selected="false"><?= htmlspecialchars(__('app.auctionator.tabs.settings')) ?></button>
    <button type="button" role="tab" class="au-tab" data-au-tab="policy" aria-selected="false"><?= htmlspecialchars(__('app.auctionator.tabs.policy')) ?></button>
    <button type="button" role="tab" class="au-tab" data-au-tab="actions" aria-selected="false"><?= htmlspecialchars(__('app.auctionator.tabs.actions')) ?></button>
  </div>

  <div class="au-feedback panel-flash" id="auFeedback" hidden></div>

  <?php if ($warnings !== []): ?>
    <div class="au-warnings">
      <?php foreach ($warnings as $warning): ?>
        <div class="alert au-warning"><?= htmlspecialchars((string) $warning) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$supported): ?>
    <div class="alert au-warning"><?= htmlspecialchars(__('app.auctionator.warnings.server_not_supported', ['server' => (string) ($current_server ?? '')])) ?></div>
  <?php endif; ?>

  <!-- ============================================================ status -->
  <section class="au-panel" data-au-panel="status">
    <div class="au-grid au-grid--cards">
      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.master.title')) ?></h3>
        <dl class="au-kv">
          <dt><?= htmlspecialchars(__('app.auctionator.fields.enabled')) ?></dt>
          <dd><span class="<?= $toneClass(((int) ($typed['Auctionator.Enabled'] ?? 0)) === 1 ? 'ok' : 'muted') ?>"><?= htmlspecialchars(((int) ($typed['Auctionator.Enabled'] ?? 0)) === 1 ? __('app.auctionator.state.on') : __('app.auctionator.state.off')) ?></span></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.bid_only')) ?></dt>
          <dd><span class="<?= $toneClass(((int) ($typed['Auctionator.Seller.BidOnly'] ?? 0)) === 1 ? 'warn' : 'muted') ?>"><?= htmlspecialchars(((int) ($typed['Auctionator.Seller.BidOnly'] ?? 0)) === 1 ? __('app.auctionator.state.on') : __('app.auctionator.state.off')) ?></span></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.character_id')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.CharacterId'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.character_guid')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.CharacterGuid'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.auctions_per_run')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.Seller.AuctionsPerRun'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.cycle_minutes')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.NeutralSeller.CycleMinutes'] ?? '--')) ?></dd>
          <dt><?= htmlspecialchars(__('app.auctionator.fields.max_auctions')) ?></dt>
          <dd><?= htmlspecialchars((string) ($typed['Auctionator.NeutralSeller.MaxAuctions'] ?? '--')) ?></dd>
        </dl>

        <?php if ($supported): ?>
          <?php // 按区一键启停：写本区 conf 的 Auctionator.Enabled + 发本区 .auctionator start|stop ?>
          <div class="au-power" data-au-power-panel>
            <strong class="au-power__title"><?= htmlspecialchars(__('app.auctionator.master.power_title')) ?></strong>
            <div class="au-power__actions">
              <button type="button" class="btn primary" data-au-power="start"<?= $canControl ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.master.power_start')) ?></button>
              <button type="button" class="btn outline danger" data-au-power="stop"<?= $canControl ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.master.power_stop')) ?></button>
            </div>
            <p class="muted small au-power__hint"><?= htmlspecialchars(__('app.auctionator.master.power_hint')) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.listings.title')) ?></h3>
        <?php if (!($listings['ok'] ?? false)): ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.listings.unavailable')) ?></p>
        <?php else: ?>
          <dl class="au-kv">
            <dt><?= htmlspecialchars(__('app.auctionator.listings.total')) ?></dt>
            <dd><?= (int) ($listings['total'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.bot')) ?></dt>
            <dd><?= (int) ($listings['bot'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.player')) ?></dt>
            <dd><?= (int) ($listings['player'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.bid_only')) ?></dt>
            <dd><?= (int) ($listings['bid_only'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.with_buyout')) ?></dt>
            <dd><?= (int) ($listings['with_buyout'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.distinct_items')) ?></dt>
            <dd><?= (int) ($listings['distinct_items'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.expiry_window')) ?></dt>
            <dd><?= htmlspecialchars($formatTime((int) ($listings['min_expire'] ?? 0)) . ' → ' . $formatTime((int) ($listings['max_expire'] ?? 0))) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.listings.bot_mail')) ?></dt>
            <dd><?= (int) ($listings['bot_mail'] ?? 0) ?></dd>
          </dl>
          <div class="au-houses">
            <?php foreach (($listings['by_house'] ?? []) as $house => $count): ?>
              <span class="au-chip"><?= htmlspecialchars($houseLabel((int) $house)) ?>: <strong><?= (int) $count ?></strong></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.market.title')) ?></h3>
        <?php if (!($market['ok'] ?? false)): ?>
          <p class="alert au-warning small">
            <?= htmlspecialchars(($market['error'] ?? '') === 'missing_table'
                ? __('app.auctionator.market.missing_table')
                : __('app.auctionator.market.unreadable')) ?>
          </p>
        <?php else: ?>
          <dl class="au-kv">
            <dt><?= htmlspecialchars(__('app.auctionator.market.rows')) ?></dt>
            <dd><?= (int) ($market['rows'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.items')) ?></dt>
            <dd><?= (int) ($market['distinct_items'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.fresh_items')) ?></dt>
            <dd><?= (int) ($market['fresh_items'] ?? 0) ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.newest')) ?></dt>
            <dd><?= htmlspecialchars((string) ($market['newest'] ?? '--') ?: '--') ?></dd>
            <dt><?= htmlspecialchars(__('app.auctionator.market.oldest')) ?></dt>
            <dd><?= htmlspecialchars((string) ($market['oldest'] ?? '--') ?: '--') ?></dd>
          </dl>
          <?php if (($market['sources'] ?? []) !== []): ?>
            <div class="au-houses">
              <?php foreach (($market['sources'] ?? []) as $source => $count): ?>
                <span class="au-chip"><?= htmlspecialchars((string) $source) ?>: <strong><?= (int) $count ?></strong></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.market.hint')) ?></p>
      </div>
    </div>

    <div class="au-card au-card--wide">
      <h3><?= htmlspecialchars(__('app.auctionator.log.title')) ?></h3>
      <p class="muted small"><?= htmlspecialchars((string) ($paths['log_file'] ?? '')) ?></p>
      <?php if ($logEntries === []): ?>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.log.empty')) ?></p>
      <?php else: ?>
        <pre class="au-log"><?php foreach ($logEntries as $entry): ?><span class="au-log__line au-log__line--<?= htmlspecialchars((string) ($entry['tone'] ?? 'muted')) ?>"><?= htmlspecialchars((string) ($entry['line'] ?? '')) ?></span>
<?php endforeach; ?></pre>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================================================ settings -->
  <section class="au-panel" data-au-panel="settings" hidden>
    <div class="au-card au-card--wide">
      <h3><?= htmlspecialchars(__('app.auctionator.settings.title')) ?></h3>
      <p class="muted small">
        <?= htmlspecialchars(__('app.auctionator.settings.path', ['path' => (string) ($paths['conf_file'] ?? '')])) ?>
        <?php if (!($conf['exists'] ?? false)): ?>
          <span class="au-badge au-badge--error"><?= htmlspecialchars(__('app.auctionator.settings.missing')) ?></span>
        <?php elseif (!($conf['writable'] ?? false)): ?>
          <span class="au-badge au-badge--error"><?= htmlspecialchars(__('app.auctionator.settings.read_only_file')) ?></span>
        <?php endif; ?>
      </p>
      <p class="alert au-note"><?= htmlspecialchars(__('app.auctionator.settings.restart_note')) ?></p>

      <form id="auConfigForm" class="au-form" autocomplete="off">
        <?php foreach ($groups as $group): ?>
          <fieldset class="au-fieldset">
            <legend><?= htmlspecialchars(__('app.auctionator.groups.' . (string) ($group['key'] ?? 'other'))) ?></legend>
            <div class="au-form__grid">
              <?php foreach (($group['fields'] ?? []) as $key): ?>
                <?php
                  $spec = is_array($fields[$key] ?? null) ? $fields[$key] : [];
                  $type = (string) ($spec['type'] ?? 'string');
                  $label = __('app.auctionator.fields.' . (string) ($spec['label'] ?? 'value'));
                  $value = $typed[$key] ?? '';
                  $disabled = $canManage && $supported ? '' : ' disabled';
                ?>
                <label class="au-form__row">
                  <span class="au-form__label" title="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $label) ?></span>
                  <?php if ($type === 'bool'): ?>
                    <select class="au-input" data-au-field="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"<?= $disabled ?>>
                      <option value="1" <?= ((int) $value) === 1 ? 'selected' : '' ?>><?= htmlspecialchars(__('app.auctionator.state.on')) ?></option>
                      <option value="0" <?= ((int) $value) === 0 ? 'selected' : '' ?>><?= htmlspecialchars(__('app.auctionator.state.off')) ?></option>
                    </select>
                  <?php elseif ($type === 'int' || $type === 'float'): ?>
                    <input class="au-input" type="number" step="<?= $type === 'float' ? '0.01' : '1' ?>"
                           min="<?= htmlspecialchars((string) ($spec['min'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           max="<?= htmlspecialchars((string) ($spec['max'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           data-au-field="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"<?= $disabled ?>>
                  <?php else: ?>
                    <input class="au-input" type="text" data-au-field="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>"
                           value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"<?= $disabled ?>>
                  <?php endif; ?>
                  <span class="au-form__key small muted"><?= htmlspecialchars((string) $key) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>

        <?php if ($canManage && $supported): ?>
          <div class="au-form__actions">
            <button type="submit" class="btn primary" id="auConfigSaveBtn"><?= htmlspecialchars(__('app.auctionator.settings.save')) ?></button>
            <span class="muted small"><?= htmlspecialchars(__('app.auctionator.settings.backup_note')) ?></span>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </section>

  <!-- ============================================================ policy -->
  <section class="au-panel" data-au-panel="policy" hidden>
    <div class="au-grid au-grid--cards">
      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.policy.disabled_title')) ?></h3>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.disabled_hint')) ?></p>
        <?php $totals = is_array($policy['totals'] ?? null) ? $policy['totals'] : []; ?>
        <?php if ($tables['disabled_items']): ?>
          <div class="au-actions">
            <span class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.showing', ['shown' => count($policy['disabled'] ?? []), 'total' => (int) ($totals['disabled'] ?? 0)])) ?></span>
            <form class="au-inline-form" method="get">
              <?php if ((int) ($current_server ?? 0) > 0): ?>
                <input type="hidden" name="server" value="<?= (int) $current_server ?>">
              <?php endif; ?>
              <input class="au-input" type="number" min="0" name="disabled_from" value="<?= (int) ($policy['disabled_from'] ?? 0) ?>" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.filter_from')) ?>">
              <button type="submit" class="btn btn-xs outline"><?= htmlspecialchars(__('app.auctionator.policy.filter_apply')) ?></button>
            </form>
          </div>
        <?php endif; ?>
        <?php if (!$tables['disabled_items']): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.table_missing', ['table' => 'mod_auctionator_disabled_items'])) ?></p>
        <?php else: ?>
          <?php if ($canManage && $supported): ?>
            <form class="au-inline-form" data-au-policy="disabled_add">
              <input class="au-input" type="number" min="1" name="item" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?>" required>
              <button type="submit" class="btn btn-sm"><?= htmlspecialchars(__('app.auctionator.policy.add')) ?></button>
            </form>
          <?php endif; ?>
          <div class="au-table-wrap">
            <table class="au-table">
              <thead><tr><th><?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?></th><th><?= htmlspecialchars(__('app.auctionator.policy.item_name')) ?></th><th></th></tr></thead>
              <tbody>
              <?php foreach (($policy['disabled'] ?? []) as $row): ?>
                <tr>
                  <td><?= (int) ($row['item'] ?? 0) ?></td>
                  <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                  <td class="au-table__actions">
                    <?php if ($canManage && $supported): ?>
                      <button type="button" class="btn btn-xs outline danger" data-au-policy="disabled_remove" data-au-item="<?= (int) ($row['item'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.remove')) ?></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (($policy['disabled'] ?? []) === []): ?>
                <tr><td colspan="3" class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.empty')) ?></td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card au-card--wide">
        <h3><?= htmlspecialchars(__('app.auctionator.policy.itemclass_title')) ?></h3>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.itemclass_hint')) ?></p>
        <?php if ($tables['itemclass_config']): ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.showing', ['shown' => count($policy['itemclass'] ?? []), 'total' => (int) ($totals['itemclass'] ?? 0)])) ?></p>
        <?php endif; ?>
        <?php if (!$tables['itemclass_config']): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.table_missing', ['table' => 'mod_auctionator_itemclass_config'])) ?></p>
        <?php else: ?>
          <?php if ($canManage && $supported): ?>
            <form class="au-inline-form" data-au-policy="itemclass_save">
              <input class="au-input" type="number" min="0" max="15" name="class" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.class')) ?>" required>
              <input class="au-input" type="number" min="0" max="255" name="subclass" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.subclass')) ?>" required>
              <input class="au-input" type="number" min="0" max="3" name="bonding" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.bonding')) ?>" value="0">
              <input class="au-input" type="number" min="0" name="max_count" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.max_count')) ?>" value="2">
              <input class="au-input" type="number" min="0" name="stack_count" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.stack_count')) ?>" value="1">
              <button type="submit" class="btn btn-sm"><?= htmlspecialchars(__('app.auctionator.policy.save')) ?></button>
            </form>
          <?php endif; ?>
          <div class="au-table-wrap">
            <table class="au-table">
              <thead>
                <tr>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.class')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.subclass')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.bonding')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.max_count')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.stack_count')) ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach (($policy['itemclass'] ?? []) as $row): ?>
                <tr data-au-itemclass="<?= (int) ($row['class'] ?? 0) ?>:<?= (int) ($row['subclass'] ?? 0) ?>">
                  <td><?= htmlspecialchars((string) ($row['class_label'] ?? '')) ?> <span class="muted small">#<?= (int) ($row['class'] ?? 0) ?></span></td>
                  <td><?= htmlspecialchars((string) ($row['subclass_label'] ?? '')) ?> <span class="muted small">#<?= (int) ($row['subclass'] ?? 0) ?></span></td>
                  <td>
                    <?php if ($canManage && $supported): ?>
                      <input class="au-input au-input--tiny" type="number" min="0" max="3" data-au-field-name="bonding" value="<?= (int) ($row['bonding'] ?? 0) ?>">
                    <?php else: ?><?= (int) ($row['bonding'] ?? 0) ?><?php endif; ?>
                  </td>
                  <td>
                    <?php if ($canManage && $supported): ?>
                      <input class="au-input au-input--tiny" type="number" min="0" data-au-field-name="max_count" value="<?= (int) ($row['max_count'] ?? 0) ?>">
                    <?php else: ?><?= (int) ($row['max_count'] ?? 0) ?><?php endif; ?>
                  </td>
                  <td>
                    <?php if ($canManage && $supported): ?>
                      <input class="au-input au-input--tiny" type="number" min="0" data-au-field-name="stack_count" value="<?= (int) ($row['stack_count'] ?? 0) ?>">
                    <?php else: ?><?= (int) ($row['stack_count'] ?? 0) ?><?php endif; ?>
                  </td>
                  <td class="au-table__actions">
                    <?php if ($canManage && $supported): ?>
                      <button type="button" class="btn btn-xs outline" data-au-policy="itemclass_save" data-au-class="<?= (int) ($row['class'] ?? 0) ?>" data-au-subclass="<?= (int) ($row['subclass'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.save')) ?></button>
                      <button type="button" class="btn btn-xs outline danger" data-au-policy="itemclass_delete" data-au-class="<?= (int) ($row['class'] ?? 0) ?>" data-au-subclass="<?= (int) ($row['subclass'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.delete')) ?></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="au-card au-card--wide">
        <h3><?= htmlspecialchars(__('app.auctionator.policy.gm_title')) ?></h3>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.gm_hint')) ?></p>
        <?php if ($tables['gm_list']): ?>
          <p class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.gm_totals', [
              'shown' => count($policy['gm_list'] ?? []),
              'total' => (int) ($totals['gm_list'] ?? 0),
              'enabled' => (int) ($totals['gm_list_enabled'] ?? 0),
          ])) ?></p>
        <?php endif; ?>
        <?php if (!$tables['gm_list']): ?>
          <p class="alert au-warning small"><?= htmlspecialchars(__('app.auctionator.policy.table_missing', ['table' => 'mod_auctionator_gm_list'])) ?></p>
        <?php else: ?>
          <?php if ($canManage && $supported): ?>
            <form class="au-inline-form" data-au-policy="gm_save">
              <input class="au-input" type="number" min="1" name="item" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?>" required>
              <input class="au-input" type="number" min="0" name="price" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.unit_price')) ?>" required>
              <input class="au-input" type="number" min="1" name="stack" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.stack')) ?>" value="1">
              <input class="au-input" type="number" min="1" max="720" name="hours" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.hours')) ?>" value="48">
              <select class="au-input" name="house">
                <option value="7"><?= htmlspecialchars(__('app.auctionator.houses.neutral')) ?></option>
                <option value="2"><?= htmlspecialchars(__('app.auctionator.houses.alliance')) ?></option>
                <option value="6"><?= htmlspecialchars(__('app.auctionator.houses.horde')) ?></option>
              </select>
              <input class="au-input" type="number" min="0" name="owner" placeholder="<?= htmlspecialchars(__('app.auctionator.policy.owner')) ?>" value="0">
              <select class="au-input" name="enabled">
                <option value="1"><?= htmlspecialchars(__('app.auctionator.state.on')) ?></option>
                <option value="0"><?= htmlspecialchars(__('app.auctionator.state.off')) ?></option>
              </select>
              <button type="submit" class="btn btn-sm"><?= htmlspecialchars(__('app.auctionator.policy.save')) ?></button>
            </form>
          <?php endif; ?>
          <div class="au-table-wrap">
            <table class="au-table">
              <thead>
                <tr>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.item_id')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.item_name')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.unit_price')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.stack')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.hours')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.house')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.owner')) ?></th>
                  <th><?= htmlspecialchars(__('app.auctionator.policy.enabled')) ?></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach (($policy['gm_list'] ?? []) as $row): ?>
                <tr>
                  <td><?= (int) ($row['item'] ?? 0) ?></td>
                  <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                  <td><?= (int) ($row['price'] ?? 0) ?> <span class="muted small"><?= htmlspecialchars($formatCopper((int) ($row['price'] ?? 0))) ?></span></td>
                  <td><?= (int) ($row['stack'] ?? 0) ?></td>
                  <td><?= (int) ($row['hours'] ?? 0) ?></td>
                  <td><?= htmlspecialchars($houseLabel((int) ($row['house'] ?? 7))) ?></td>
                  <td><?= (int) ($row['owner'] ?? 0) ?></td>
                  <td><span class="<?= $toneClass(((int) ($row['enabled'] ?? 0)) === 1 ? 'ok' : 'muted') ?>"><?= htmlspecialchars(((int) ($row['enabled'] ?? 0)) === 1 ? __('app.auctionator.state.on') : __('app.auctionator.state.off')) ?></span></td>
                  <td class="au-table__actions">
                    <?php if ($canManage && $supported): ?>
                      <button type="button" class="btn btn-xs outline" data-au-policy="gm_toggle" data-au-item="<?= (int) ($row['item'] ?? 0) ?>" data-au-enabled="<?= ((int) ($row['enabled'] ?? 0)) === 1 ? '0' : '1' ?>"><?= htmlspecialchars(((int) ($row['enabled'] ?? 0)) === 1 ? __('app.auctionator.policy.disable') : __('app.auctionator.policy.enable')) ?></button>
                      <button type="button" class="btn btn-xs outline danger" data-au-policy="gm_delete" data-au-item="<?= (int) ($row['item'] ?? 0) ?>"><?= htmlspecialchars(__('app.auctionator.policy.delete')) ?></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (($policy['gm_list'] ?? []) === []): ?>
                <tr><td colspan="9" class="muted small"><?= htmlspecialchars(__('app.auctionator.policy.empty')) ?></td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ============================================================ actions -->
  <section class="au-panel" data-au-panel="actions" hidden>
    <div class="au-grid au-grid--cards">
      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.actions.read_title')) ?></h3>
        <div class="au-actions">
          <button type="button" class="btn outline" data-au-action="status"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.status')) ?></button>
          <button type="button" class="btn outline" data-au-action="market"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.market')) ?></button>
        </div>
        <h3><?= htmlspecialchars(__('app.auctionator.actions.runtime_title')) ?></h3>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.runtime_hint')) ?></p>
        <div class="au-actions">
          <select class="au-input" data-au-action-field="target">
            <?php foreach (['neutralseller', 'allianceseller', 'hordeseller', 'neutralbidder', 'alliancebidder', 'hordebidder', 'all'] as $target): ?>
              <option value="<?= htmlspecialchars($target) ?>"><?= htmlspecialchars($target) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn outline success" data-au-action="enable"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.enable')) ?></button>
          <button type="button" class="btn outline warn" data-au-action="disable"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.disable')) ?></button>
        </div>
        <div class="au-actions">
          <input class="au-input" type="number" min="0" max="1000" value="<?= (int) ($typed['Auctionator.Seller.AuctionsPerRun'] ?? 10) ?>" data-au-action-field="value" title="<?= htmlspecialchars(__('app.auctionator.fields.auctions_per_run')) ?>">
          <button type="button" class="btn outline" data-au-action="auctionspercycle"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.auctionspercycle')) ?></button>
        </div>
      </div>

      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.actions.gm_title')) ?></h3>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.gm_hint')) ?></p>
        <div class="au-actions">
          <select class="au-input" data-au-action-field="house">
            <option value="7"><?= htmlspecialchars(__('app.auctionator.houses.neutral')) ?></option>
            <option value="2"><?= htmlspecialchars(__('app.auctionator.houses.alliance')) ?></option>
            <option value="6"><?= htmlspecialchars(__('app.auctionator.houses.horde')) ?></option>
          </select>
          <button type="button" class="btn primary" data-au-action="addlist"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.addlist')) ?></button>
          <label class="au-checkbox">
            <input type="checkbox" data-au-action-field="all">
            <span><?= htmlspecialchars(__('app.auctionator.actions.expire_all_included')) ?></span>
          </label>
          <button type="button" class="btn outline danger" data-au-action="expireall"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.expireall')) ?></button>
        </div>
        <form class="au-form au-form--add" id="auAddForm">
          <div class="au-form__grid">
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.actions.items')) ?></span>
              <input class="au-input" type="text" name="items" placeholder="19019,4359"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.unit_price')) ?></span>
              <input class="au-input" type="number" min="1" name="price"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.stack')) ?></span>
              <input class="au-input" type="number" min="1" name="stack" value="1"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.hours')) ?></span>
              <input class="au-input" type="number" min="1" max="720" name="hours" value="48"<?= $canControl && $supported ? '' : ' disabled' ?>></label>
            <label class="au-form__row"><span class="au-form__label"><?= htmlspecialchars(__('app.auctionator.policy.owner')) ?></span>
              <select class="au-input" name="owner"<?= $canControl && $supported ? '' : ' disabled' ?>>
                <option value="bot">bot</option>
                <option value="me">me</option>
              </select></label>
          </div>
          <div class="au-form__actions">
            <button type="submit" class="btn primary"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.add')) ?></button>
            <span class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.add_hint', ['hours' => (int) ($notes['listing_hours'] ?? 12)])) ?></span>
          </div>
        </form>
      </div>

      <div class="au-card">
        <h3><?= htmlspecialchars(__('app.auctionator.actions.market_title')) ?></h3>
        <div class="au-actions">
          <button type="button" class="btn outline" data-au-action="marketimport"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.marketimport')) ?></button>
          <label class="au-checkbox">
            <input type="checkbox" data-au-action-field="force">
            <span>force</span>
          </label>
        </div>
        <div class="au-actions">
          <input class="au-input" type="number" min="1" max="3650" value="30" data-au-action-field="days" title="<?= htmlspecialchars(__('app.auctionator.market.retention_days')) ?>">
          <button type="button" class="btn outline warn" data-au-action="marketprune"<?= $canControl && $supported ? '' : ' disabled' ?>><?= htmlspecialchars(__('app.auctionator.actions.marketprune')) ?></button>
        </div>
        <p class="muted small"><?= htmlspecialchars(__('app.auctionator.actions.market_hint')) ?></p>
      </div>

      <div class="au-card au-card--wide">
        <h3><?= htmlspecialchars(__('app.auctionator.actions.output_title')) ?></h3>
        <pre class="au-output" id="auOutput"><?= htmlspecialchars(__('app.auctionator.actions.output_empty')) ?></pre>
      </div>
    </div>
  </section>
</div>
