<?php
/**
 * File: resources/views/account/index.php
 * Purpose: Provides functionality for the resources/views/account module.
 */

 $module='account'; include __DIR__.'/../layouts/base_top.php'; ?>
<h1 class="page-title"><?= htmlspecialchars(__('app.account.page_title')) ?></h1>
<form class="account-search" method="get" action="">
  <div class="account-search__row">
    <select name="search_type">
      <option value="username" <?= $search_type==='username'?'selected':'' ?>><?= htmlspecialchars(__('app.account.search.type_username')) ?></option>
      <option value="id" <?= $search_type==='id'?'selected':'' ?>><?= htmlspecialchars(__('app.account.search.type_id')) ?></option>
    </select>
    <input type="text" name="search_value" value="<?= htmlspecialchars($search_value) ?>" placeholder="<?= htmlspecialchars(__('app.account.search.placeholder')) ?>">
  <button class="btn" type="submit"><?= htmlspecialchars(__('app.account.search.submit')) ?></button>
  <button class="btn success action" type="button" data-action="create-account"><?= htmlspecialchars(__('app.account.search.create')) ?></button>
  </div>
  <div id="account-feedback" class="panel-flash panel-flash--inline"></div>
</form>
<?php if($search_value!==''): ?>
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
  <table class="table">
  <thead><tr><th><?= htmlspecialchars(__('app.account.table.id')) ?></th><th><?= htmlspecialchars(__('app.account.table.username')) ?></th><th><?= htmlspecialchars(__('app.account.table.gm')) ?></th><th><?= htmlspecialchars(__('app.account.table.online')) ?></th><th><?= htmlspecialchars(__('app.account.table.last_login')) ?></th><th><?= htmlspecialchars(__('app.account.table.last_ip')) ?></th><th><?= htmlspecialchars(__('app.account.table.ip_location')) ?></th><th><?= htmlspecialchars(__('app.account.table.actions')) ?></th></tr></thead>
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
          <button class="btn-sm btn neutral action" data-action="ip-accounts" <?= $isPrivateIp?'disabled title="'.htmlspecialchars(__('app.account.feedback.private_ip_disabled')).'"':''; ?>><?= htmlspecialchars(__('app.account.actions.same_ip')) ?></button>
          <button class="btn-sm btn outline danger action" data-action="kick"><?= htmlspecialchars(__('app.account.actions.kick')) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php if(!$pager->items): ?><tr><td colspan="8" style="text-align:center;"><?= htmlspecialchars(__('app.account.feedback.empty')) ?></td></tr><?php endif; ?>
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
