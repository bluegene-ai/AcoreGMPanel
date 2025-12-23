<?php
/**
 * File: resources/views/character/index.php
 * Purpose: Character list and search UI.
 */

 $module='character'; include __DIR__.'/../layouts/base_top.php';
 $filters = $filters ?? [];
 $name = $filters['name'] ?? '';
 $guid = (int)($filters['guid'] ?? 0);
 $account = $filters['account'] ?? '';
 $levelMin = (int)($filters['level_min'] ?? 0);
 $levelMax = (int)($filters['level_max'] ?? 0);
 $filter_online = $filters['online'] ?? 'any';
 $sort = $sort ?? '';
 $load_all = !empty($load_all);
?>
<h1 class="page-title"><?= htmlspecialchars(__('app.character.index.title')) ?></h1>
<form class="character-search" method="get" action="">
  <div class="character-search__row">
    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.name_placeholder')) ?>">
    <input type="number" name="guid" value="<?= $guid>0?(int)$guid:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.guid_placeholder')) ?>" style="width:140px;">
    <input type="text" name="account" value="<?= htmlspecialchars($account) ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.account_placeholder')) ?>">
    <input type="number" name="level_min" value="<?= $levelMin>0?(int)$levelMin:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.level_min')) ?>" style="width:120px;">
    <input type="number" name="level_max" value="<?= $levelMax>0?(int)$levelMax:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.level_max')) ?>" style="width:120px;">
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
<div id="char-feedback" class="panel-flash panel-flash--inline" style="display:none"></div>
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
  <p style="margin-top:10px;font-size:13px;color:#8aa4b8;">
    <?= htmlspecialchars(__('app.character.index.feedback.found', ['total' => $pager->total, 'page' => $pager->page, 'pages' => $pager->pages])) ?>
  </p>
  <div class="flex between center" style="gap:10px;flex-wrap:wrap;margin:8px 0;">
    <div class="flex center" style="gap:10px;flex-wrap:wrap;">
      <label class="small" style="display:inline-flex;align-items:center;gap:6px;">
        <input type="checkbox" class="js-char-select-all">
        <span><?= htmlspecialchars(__('app.account.bulk.select_all')) ?></span>
      </label>
      <button class="btn-sm btn danger js-char-bulk" data-bulk="delete" type="button"><?= htmlspecialchars(__('app.account.bulk.delete')) ?></button>
      <button class="btn-sm btn danger js-char-bulk" data-bulk="ban" type="button"><?= htmlspecialchars(__('app.account.bulk.ban')) ?></button>
      <button class="btn-sm btn success js-char-bulk" data-bulk="unban" type="button"><?= htmlspecialchars(__('app.account.bulk.unban')) ?></button>
    </div>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th style="width:34px;"><input type="checkbox" class="js-char-select-all" aria-label="select all"></th>
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
        <td><input type="checkbox" class="js-char-select" value="<?= (int)$row['guid'] ?>" aria-label="select"></td>
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
            <span class="badge" style="background:#7a1b1b" title="<?= htmlspecialchars($tooltip) ?>">
              <?= htmlspecialchars(__('app.account.ban.badge', ['duration' => $duration])) ?>
            </span>
          <?php else: ?>
            <?= (int)$row['online'] ? '<span class="badge" style="background:#16a34a">'.htmlspecialchars(__('app.character.index.status.online')).'</span>' : '<span class="badge">'.htmlspecialchars(__('app.character.index.status.offline')).'</span>' ?>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars(format_datetime($row['logout_time'] ?? null)) ?></td>
        <?php $viewUrl = \Acme\Panel\Core\Url::to('/character/view') . '?guid=' . (int)$row['guid']; ?>
        <td style="white-space:nowrap">
          <a class="btn-sm btn info" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars(__('app.character.index.table.view')) ?></a>
          <button class="btn-sm btn danger js-char-delete" type="button" data-guid="<?= (int)$row['guid'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars(__('app.character.actions.delete')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$pager->items): ?><tr><td colspan="12" style="text-align:center;">&<?= 'nbsp;' ?><?= htmlspecialchars(__('app.character.index.feedback.empty')) ?></td></tr><?php endif; ?>
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
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
