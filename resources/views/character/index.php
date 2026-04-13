<?php
/**
 * File: resources/views/character/index.php
 * Purpose: Character list and search UI.
 */

 $filters = $filters ?? [];
 $name = $filters['name'] ?? '';
 $guid = (int)($filters['guid'] ?? 0);
 $account = $filters['account'] ?? '';
 $levelMin = (int)($filters['level_min'] ?? 0);
 $levelMax = (int)($filters['level_max'] ?? 0);
 $filter_online = $filters['online'] ?? 'any';
 $sort = $sort ?? '';
 $load_all = !empty($load_all);
 $characterCapabilities = $__pageCapabilities ?? [
   'list' => $__can('characters.list'),
   'details' => $__can('characters.details'),
   'ban' => $__can('characters.ban'),
   'delete' => $__can('characters.delete'),
 ];
 $__pageCapabilities = $characterCapabilities;
 $characterCanBulk = $characterCapabilities['ban'] || $characterCapabilities['delete'];
 $capabilityNotice = $__canAll(['characters.details', 'characters.ban', 'characters.delete'])
   ? null
   : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<?php include __DIR__.'/../components/capability_notice.php'; ?>
<form class="character-search" method="get" action="">
  <div class="character-search__row">
    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.name_placeholder')) ?>">
    <input type="number" name="guid" value="<?= $guid>0?(int)$guid:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.guid_placeholder')) ?>" class="character-search__guid">
    <input type="text" name="account" value="<?= htmlspecialchars($account) ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.account_placeholder')) ?>">
    <input type="number" name="level_min" value="<?= $levelMin>0?(int)$levelMin:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.level_min')) ?>" class="character-search__level">
    <input type="number" name="level_max" value="<?= $levelMax>0?(int)$levelMax:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.level_max')) ?>" class="character-search__level">
    <select name="online">
      <option value="any" <?= $filter_online==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.online_any')) ?></option>
      <option value="online" <?= $filter_online==='online'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.online_only')) ?></option>
      <option value="offline" <?= $filter_online==='offline'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.online_offline')) ?></option>
    </select>
    <span class="character-search__actions">
      <button class="btn" type="submit"><?= htmlspecialchars(__('app.character.index.search.submit')) ?></button>
      <button class="btn outline" type="submit" name="load_all" value="1"><?= htmlspecialchars(__('app.character.index.search.load_all')) ?></button>
    </span>
  </div>
</form>
<div id="char-feedback" class="panel-flash panel-flash--inline char-feedback--hidden"></div>
<?php $hasCriteria = $load_all || $name!=='' || $guid>0 || $account!=='' || $levelMin>0 || $levelMax>0 || $filter_online!=='any'; ?>
<?php if($hasCriteria): ?>
  <?php
    $sortUrl = static function(?string $value): string {
      $base = \Acme\Panel\Core\Url::to('/character');
      $qs = $_GET;
      unset($qs['page'], $qs['server']);
      if($value === null || $value === ''){
        unset($qs['sort']);
      } else {
        $qs['sort'] = $value;
      }
      $query = http_build_query($qs);
      return $query ? ($base . '?' . $query) : $base;
    };

    $nextSort = static function(string $column) use ($sort): string {
      $cur = (string)$sort;
      $asc = $column . '_asc';
      $desc = $column . '_desc';

      if($cur === $asc) return $desc;
      if($cur === $desc) return '';
      return $asc;
    };

    $isActive = static function(string $column) use ($sort): bool {
      $cur = (string)$sort;
      return $cur !== '' && str_starts_with($cur, $column . '_');
    };
  ?>
  <?php $friendlyTime=function(int $seconds): string {
    if($seconds < 0) {
      return __('app.account.ban.permanent');
    }
    if($seconds <= 0) {
      return __('app.account.ban.soon');
    }
    $d = intdiv($seconds, 86400);
    $seconds %= 86400;
    $h = intdiv($seconds, 3600);
    $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $parts = [];
    $locale = \Acme\Panel\Core\Lang::locale();
    $isEnglish = stripos($locale, 'en') === 0;
    if ($d > 0) {
      $label = __('app.account.ban.duration.day', ['value' => $d]);
      if ($isEnglish && $d !== 1) {
        $label .= 's';
      }
      $parts[] = $label;
    }
    if ($h > 0) {
      $label = __('app.account.ban.duration.hour', ['value' => $h]);
      if ($isEnglish && $h !== 1) {
        $label .= 's';
      }
      $parts[] = $label;
    }
    if ($m > 0 && $d === 0) {
      $label = __('app.account.ban.duration.minute', ['value' => $m]);
      if ($isEnglish && $m !== 1) {
        $label .= 's';
      }
      $parts[] = $label;
    }
    if (!$parts) {
      return __('app.account.ban.under_minute');
    }
    return implode(__('app.account.ban.separator'), array_slice($parts, 0, 2));
  }; ?>
  <p class="char-results-meta">
    <?= htmlspecialchars(__('app.character.index.feedback.found', ['total' => $pager->total, 'page' => $pager->page, 'pages' => $pager->pages])) ?>
  </p>
  <?php if($characterCanBulk): ?>
  <div class="char-bulk-bar">
    <div class="char-bulk-actions">
      <label class="small char-select-all-toggle">
        <input type="checkbox" class="js-char-select-all">
        <span><?= htmlspecialchars(__('app.account.bulk.select_all')) ?></span>
      </label>
      <?php if($characterCapabilities['delete']): ?>
      <button class="btn-sm btn danger js-char-bulk" data-bulk="delete" type="button"><?= htmlspecialchars(__('app.account.bulk.delete')) ?></button>
      <?php endif; ?>
      <?php if($characterCapabilities['ban']): ?>
      <button class="btn-sm btn danger js-char-bulk" data-bulk="ban" type="button"><?= htmlspecialchars(__('app.account.bulk.ban')) ?></button>
      <button class="btn-sm btn success js-char-bulk" data-bulk="unban" type="button"><?= htmlspecialchars(__('app.account.bulk.unban')) ?></button>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <table class="table">
    <thead>
      <tr>
        <?php if($characterCanBulk): ?>
        <th class="char-select-col"><input type="checkbox" class="js-char-select-all" aria-label="select all"></th>
        <?php endif; ?>
        <th><a class="table-sort<?= $isActive('guid')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('guid'))) ?>"><?= htmlspecialchars(__('app.character.index.table.guid')) ?></a></th>
        <th><?= htmlspecialchars(__('app.character.index.table.name')) ?></th>
        <th><?= htmlspecialchars(__('app.character.index.table.account')) ?></th>
        <th><a class="table-sort<?= $isActive('level')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('level'))) ?>"><?= htmlspecialchars(__('app.character.index.table.level')) ?></a></th>
        <th><?= htmlspecialchars(__('app.character.index.table.class')) ?></th>
        <th><?= htmlspecialchars(__('app.character.index.table.race')) ?></th>
        <th><?= htmlspecialchars(__('app.character.index.table.map')) ?></th>
        <th><?= htmlspecialchars(__('app.character.index.table.zone')) ?></th>
        <th><a class="table-sort<?= $isActive('online')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('online'))) ?>"><?= htmlspecialchars(__('app.character.index.table.online')) ?></a></th>
        <th><a class="table-sort<?= $isActive('logout')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('logout'))) ?>"><?= htmlspecialchars(__('app.character.index.table.last_logout')) ?></a></th>
        <th><?= htmlspecialchars(__('app.character.index.table.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($pager->items as $row): ?>
      <tr>
        <?php if($characterCanBulk): ?>
        <td><input type="checkbox" class="js-char-select" value="<?= (int)$row['guid'] ?>" aria-label="select"></td>
        <?php endif; ?>
        <td><?= (int)$row['guid'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <?php $accName = (string)($row['account_username'] ?? ''); $accFallback = '#'.($row['account'] ?? ''); ?>
        <td>
          <?php if($accName !== ''): ?>
            <a href="<?= htmlspecialchars(url_with_server('/account?search_type=username&search_value='.rawurlencode($accName))) ?>"><?= htmlspecialchars($accName) ?></a>
          <?php else: ?>
            <?= htmlspecialchars($accFallback) ?>
          <?php endif; ?>
        </td>
        <td><?= (int)$row['level'] ?></td>
        <?php $rowClassId = (int)($row['class'] ?? 0); ?>
        <td><span data-class-id="<?= $rowClassId ?>"><?= htmlspecialchars(\Acme\Panel\Support\GameMaps::className($rowClassId)) ?></span></td>
        <?php $rowRaceId = (int)($row['race'] ?? 0); ?>
        <td><?= htmlspecialchars(\Acme\Panel\Support\GameMaps::raceName($rowRaceId)) ?></td>
        <?php $rowMapId = (int)($row['map'] ?? 0); ?>
        <td><?= htmlspecialchars(\Acme\Panel\Support\GameMaps::mapLabel($rowMapId)) ?></td>
        <?php $rowZoneId = (int)($row['zone'] ?? 0); ?>
        <td><?= htmlspecialchars(\Acme\Panel\Support\GameMaps::zoneLabel($rowZoneId)) ?></td>
        <td>
          <?php if(!empty($row['ban'])): $b=$row['ban']; ?>
            <?php
              $banReason = (string)($b['banreason'] ?? '-');
              $banStart = !empty($b['bandate']) ? date('Y-m-d H:i', (int)$b['bandate']) : '-';
              $banEnd = (!empty($b['permanent']) || (int)($b['unbandate'] ?? 0) <= time()) ? __('app.account.ban.no_end') : date('Y-m-d H:i', (int)$b['unbandate']);
              $tooltip = __('app.account.ban.tooltip', [
                'reason' => $banReason !== '' ? $banReason : '-',
                'start' => $banStart,
                'end' => $banEnd,
              ]);
              $duration = $friendlyTime((int)($b['remaining_seconds'] ?? -1));
            ?>
            <span class="badge status-banned" title="<?= htmlspecialchars($tooltip) ?>">
              <?= htmlspecialchars(__('app.account.ban.badge', ['duration' => $duration])) ?>
            </span>
          <?php else: ?>
            <?= (int)$row['online'] ? '<span class="badge status-online">'.htmlspecialchars(__('app.character.index.status.online')).'</span>' : '<span class="badge">'.htmlspecialchars(__('app.character.index.status.offline')).'</span>' ?>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars(format_datetime($row['logout_time'] ?? null)) ?></td>
        <?php $viewUrl = \Acme\Panel\Core\Url::to('/character/view') . '?guid=' . (int)$row['guid']; ?>
        <td class="char-action-cell">
          <?php if($characterCapabilities['details']): ?>
          <a class="btn-sm btn info" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars(__('app.character.index.table.view')) ?></a>
          <?php endif; ?>
          <?php if($characterCapabilities['delete']): ?>
          <button class="btn-sm btn danger js-char-delete" type="button" data-guid="<?= (int)$row['guid'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars(__('app.character.actions.delete')) ?></button>
          <?php endif; ?>
          <?php if(!$__canAny(['characters.details', 'characters.delete'])): ?>
          <span class="muted small"><?= htmlspecialchars(__('app.common.capabilities.no_actions')) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$pager->items): ?><tr><td colspan="<?= $characterCanBulk ? 12 : 11 ?>" class="char-empty-cell">&<?= 'nbsp;' ?><?= htmlspecialchars(__('app.character.index.feedback.empty')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php
    $page=$pager->page; $pages=$pager->pages;
    $base = \Acme\Panel\Core\Url::to('/character');
    $qs=$_GET; unset($qs['page'],$qs['server']); if(!empty($qs)){
      $join = strpos($base,'?')!==false?'&':'?';
      $base .= $join.http_build_query($qs);
    }
    include __DIR__.'/../components/pagination.php';
  ?>
<?php else: ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.character.index.feedback.enter_search')) ?></div>
<?php endif; ?>
