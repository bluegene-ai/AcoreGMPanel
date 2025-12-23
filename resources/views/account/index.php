<?php
/**
 * File: resources/views/account/index.php
 * Purpose: Provides functionality for the resources/views/account module.
 */

 $module='account'; include __DIR__.'/../layouts/base_top.php'; ?>
<?php $filter_online = $filter_online ?? 'any'; $filter_ban = $filter_ban ?? 'any'; $load_all = !empty($load_all); $sort = $sort ?? ''; ?>
<?php $exclude_username = $exclude_username ?? ''; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.account.page_title')) ?></h1>
<form class="account-search" method="get" action="">
  <div class="account-search__row">
    <select name="search_type">
      <option value="username" <?= $search_type==='username'?'selected':'' ?>><?= htmlspecialchars(__('app.account.search.type_username')) ?></option>
      <option value="id" <?= $search_type==='id'?'selected':'' ?>><?= htmlspecialchars(__('app.account.search.type_id')) ?></option>
    </select>
    <input type="text" name="search_value" value="<?= htmlspecialchars($search_value) ?>" placeholder="<?= htmlspecialchars(__('app.account.search.placeholder')) ?>">
  <button class="btn" type="submit"><?= htmlspecialchars(__('app.account.search.submit')) ?></button>
  <button class="btn outline" type="submit" name="load_all" value="1"><?= htmlspecialchars(__('app.account.search.load_all')) ?></button>
  <button class="btn success action" type="button" data-action="create-account"><?= htmlspecialchars(__('app.account.search.create')) ?></button>
  </div>
  <div class="account-search__row account-search__filters">
    <label style="display:flex;align-items:center;gap:6px;">
      <span><?= htmlspecialchars(__('app.account.filters.online')) ?>:</span>
      <select name="online">
        <option value="any" <?= $filter_online==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.online_any')) ?></option>
        <option value="online" <?= $filter_online==='online'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.online_only')) ?></option>
        <option value="offline" <?= $filter_online==='offline'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.online_offline')) ?></option>
      </select>
    </label>
    <label style="display:flex;align-items:center;gap:6px;">
      <span><?= htmlspecialchars(__('app.account.filters.ban')) ?>:</span>
      <select name="ban">
        <option value="any" <?= $filter_ban==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.ban_any')) ?></option>
        <option value="banned" <?= $filter_ban==='banned'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.ban_only')) ?></option>
        <option value="unbanned" <?= $filter_ban==='unbanned'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.ban_unbanned')) ?></option>
      </select>
    </label>
    <label style="display:flex;align-items:center;gap:6px;white-space:nowrap;">
      <span style="white-space:nowrap;"><?= htmlspecialchars(__('app.account.filters.exclude_username')) ?>:</span>
      <input type="text" name="exclude_username" value="<?= htmlspecialchars($exclude_username) ?>" placeholder="<?= htmlspecialchars(__('app.account.filters.exclude_username_placeholder')) ?>" style="min-width:180px;">
    </label>
  </div>
  <div id="account-feedback" class="panel-flash panel-flash--inline"></div>
</form>
<?php $hasCriteria = $load_all || ($search_value!=='') || ($filter_online!=='any') || ($filter_ban!=='any') || (trim((string)$exclude_username) !== ''); ?>
<?php if($hasCriteria): ?>
<?php
  $sortUrl = static function(?string $value): string {
    $base = url_with_server('/account');
    $qs = $_GET;
    unset($qs['page'], $qs['server']);
    if($value === null || $value === ''){
      unset($qs['sort']);
    } else {
      $qs['sort'] = $value;
    }
    $query = http_build_query($qs);
    return $query ? ($base . (str_contains($base,'?') ? '&' : '?') . $query) : $base;
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
    <?= htmlspecialchars(__('app.account.feedback.found', ['total' => $pager->total, 'page' => $pager->page, 'pages' => $pager->pages])) ?>
  </p>
  <div class="flex between center" style="gap:10px;flex-wrap:wrap;margin:8px 0;">
    <div class="flex center" style="gap:10px;flex-wrap:wrap;">
      <label class="small" style="display:inline-flex;align-items:center;gap:6px;">
        <input type="checkbox" class="js-account-select-all">
        <span><?= htmlspecialchars(__('app.account.bulk.select_all')) ?></span>
      </label>
      <button class="btn-sm btn danger js-account-bulk" data-bulk="delete" type="button"><?= htmlspecialchars(__('app.account.bulk.delete')) ?></button>
      <button class="btn-sm btn danger js-account-bulk" data-bulk="ban" type="button"><?= htmlspecialchars(__('app.account.bulk.ban')) ?></button>
      <button class="btn-sm btn success js-account-bulk" data-bulk="unban" type="button"><?= htmlspecialchars(__('app.account.bulk.unban')) ?></button>
    </div>
  </div>
  <table class="table">
  <thead><tr>
    <th style="width:34px;"><input type="checkbox" class="js-account-select-all" aria-label="select all"></th>
    <th><a class="table-sort<?= $isActive('id')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('id'))) ?>"><?= htmlspecialchars(__('app.account.table.id')) ?></a></th>
    <th><?= htmlspecialchars(__('app.account.table.username')) ?></th>
    <th><?= htmlspecialchars(__('app.account.table.gm')) ?></th>
    <th><a class="table-sort<?= $isActive('online')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('online'))) ?>"><?= htmlspecialchars(__('app.account.table.online')) ?></a></th>
    <th><a class="table-sort<?= $isActive('last_login')?' is-active':'' ?>" href="<?= htmlspecialchars($sortUrl($nextSort('last_login'))) ?>"><?= htmlspecialchars(__('app.account.table.last_login')) ?></a></th>
    <th><?= htmlspecialchars(__('app.account.table.last_ip')) ?></th>
    <th><?= htmlspecialchars(__('app.account.table.ip_location')) ?></th>
    <th><?= htmlspecialchars(__('app.account.table.actions')) ?></th>
  </tr></thead>
    <tbody>
    <?php foreach($pager->items as $row): ?>
      <?php
        $lastIp = (string)($row['last_ip'] ?? '');
        $ipLower = strtolower($lastIp);
        $isPrivateIp = false;
        if($lastIp !== ''){
          if(str_starts_with($lastIp,'10.') || str_starts_with($lastIp,'192.168.') || str_starts_with($lastIp,'127.')){
            $isPrivateIp = true;
          } elseif(preg_match('/^172\.(1[6-9]|2\d|3[01])\./',$lastIp)){
            $isPrivateIp = true;
          } elseif($ipLower === '::1' || str_starts_with($ipLower,'fc') || str_starts_with($ipLower,'fd')){
            $isPrivateIp = true;
          }
        }
      ?>
      <tr data-id="<?= (int)$row['id'] ?>" data-username="<?= htmlspecialchars($row['username']) ?>" data-gm="<?= isset($row['gmlevel'])?(int)$row['gmlevel']:'0' ?>" data-last-ip="<?= htmlspecialchars($lastIp) ?>">
        <td><input type="checkbox" class="js-account-select" value="<?= (int)$row['id'] ?>" aria-label="select"></td>
        <td><?= (int)$row['id'] ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= isset($row['gmlevel'])?(int)$row['gmlevel']:'-' ?></td>
        <td>
          <?php if(!empty($row['ban'])): $b=$row['ban']; ?>
            <?php
              $banReason = (string)($b['banreason'] ?? '-');
              $banStart = date('Y-m-d H:i', $b['bandate']);
              $banEnd = $b['permanent'] ? __('app.account.ban.no_end') : date('Y-m-d H:i', $b['unbandate']);
              $tooltip = __('app.account.ban.tooltip', [
                'reason' => $banReason !== '' ? $banReason : '-',
                'start' => $banStart,
                'end' => $banEnd,
              ]);
            ?>
            <span class="badge" style="background:#7a1b1b" title="<?= htmlspecialchars($tooltip) ?>">
              <?= htmlspecialchars(__('app.account.ban.badge', ['duration' => $friendlyTime($b['remaining_seconds'])])) ?>
            </span>
          <?php else: ?>
            <?= (int)$row['online']
              ? '<span class="badge" style="background:#16a34a">'.htmlspecialchars(__('app.account.status.online')).'</span>'
              : '<span class="badge">'.htmlspecialchars(__('app.account.status.offline')).'</span>'
            ?>
          <?php endif; ?>
        </td>
        <td><?= !empty($row['last_login'])?htmlspecialchars($row['last_login']):'-' ?></td>
        <td><?= htmlspecialchars($lastIp) ?></td>
        <td class="ip-location" data-ip="<?= htmlspecialchars($lastIp) ?>">-</td>
        <td style="white-space:nowrap">
          <button class="btn-sm btn info action" data-action="chars"><?= htmlspecialchars(__('app.account.actions.chars')) ?></button>
          <button class="btn-sm btn warn action" data-action="gm"><?= htmlspecialchars(__('app.account.actions.gm')) ?></button>
          <button class="btn-sm btn danger action" data-action="ban"><?= htmlspecialchars(__('app.account.actions.ban')) ?></button>
          <button class="btn-sm btn success action" data-action="unban"><?= htmlspecialchars(__('app.account.actions.unban')) ?></button>
          <button class="btn-sm btn info outline action" data-action="pass"><?= htmlspecialchars(__('app.account.actions.password')) ?></button>
          <button class="btn-sm btn neutral action" data-action="email"><?= htmlspecialchars(__('app.account.actions.email')) ?></button>
          <button class="btn-sm btn neutral outline action" data-action="rename"><?= htmlspecialchars(__('app.account.actions.rename')) ?></button>
          <button class="btn-sm btn neutral action" data-action="ip-accounts" <?= $isPrivateIp?'disabled title="'.htmlspecialchars(__('app.account.feedback.private_ip_disabled')).'"':''; ?>><?= htmlspecialchars(__('app.account.actions.same_ip')) ?></button>
          <button class="btn-sm btn outline danger action" data-action="kick"><?= htmlspecialchars(__('app.account.actions.kick')) ?></button>
          <button class="btn-sm btn danger action" data-action="delete"><?= htmlspecialchars(__('app.account.actions.delete')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php if(!$pager->items): ?><tr><td colspan="9" style="text-align:center;"><?= htmlspecialchars(__('app.account.feedback.empty')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?php
  $page=$pager->page; $pages=$pager->pages;


  $base=url_with_server('/account');
    $qs=$_GET; unset($qs['page'],$qs['server']); if(!empty($qs)){

      $join = strpos($base,'?')!==false?'&':'?';
      $base .= $join.http_build_query($qs);
    }
    include __DIR__.'/../components/pagination.php';
  ?>
<?php else: ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.account.feedback.enter_search')) ?></div>
<?php endif; ?>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
