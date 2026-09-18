<?php
/**
 * File: resources/views/account/index.php
 * Purpose: Provides functionality for the resources/views/account module.
 */

?>
<?php $filter_online = $filter_online ?? 'any'; $filter_ban = $filter_ban ?? 'any'; $load_all = !empty($load_all); $sort = $sort ?? ''; ?>
<?php $exclude_username = $exclude_username ?? ''; ?>
<?php
  $__accountCapabilities = $__pageCapabilities ?? [
    'list' => $__can('accounts.list'),
    'characters' => $__can('accounts.characters'),
    'create' => $__can('accounts.create'),
    'update' => $__can('accounts.update'),
    'password' => $__can('accounts.password'),
    'gm' => $__can('accounts.gm'),
    'ban' => $__can('accounts.ban'),
    'ip' => $__can('accounts.ip'),
    'kick' => $__can('accounts.kick'),
    'delete' => $__can('accounts.delete'),
  ];
  $__pageCapabilities = $__accountCapabilities;
  $__accountCanBulk = $__accountCapabilities['ban'] || $__accountCapabilities['delete'];
  $capabilityNotice = $__canAll(['accounts.characters', 'accounts.create', 'accounts.update', 'accounts.password', 'accounts.gm', 'accounts.ban', 'accounts.ip', 'accounts.kick', 'accounts.delete'])
    ? null
    : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<?php include __DIR__.'/../components/capability_notice.php'; ?>
<?php $hasCriteria = $load_all || ($search_value!=='') || ($filter_online!=='any') || ($filter_ban!=='any') || (trim((string)$exclude_username) !== ''); ?>
<form class="list-filter" method="get" action="">
  <div class="list-filter__grid list-filter__grid--fit">
    <label class="list-filter__field list-filter__field--fit-select">
      <span><?= htmlspecialchars(__('app.account.search.type_label')) ?></span>
      <select name="search_type">
        <option value="username" <?= $search_type==='username'?'selected':'' ?>><?= htmlspecialchars(__('app.account.search.type_username')) ?></option>
        <option value="id" <?= $search_type==='id'?'selected':'' ?>><?= htmlspecialchars(__('app.account.search.type_id')) ?></option>
      </select>
    </label>

    <label class="list-filter__field list-filter__field--fit-text">
      <span><?= htmlspecialchars(__('app.account.search.value_label')) ?></span>
      <input type="text" name="search_value" maxlength="32" value="<?= htmlspecialchars($search_value) ?>" placeholder="<?= htmlspecialchars(__('app.account.search.placeholder')) ?>">
    </label>

    <label class="list-filter__field list-filter__field--fit-select">
      <span><?= htmlspecialchars(__('app.account.filters.online')) ?></span>
      <select name="online">
        <option value="any" <?= $filter_online==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.online_any')) ?></option>
        <option value="online" <?= $filter_online==='online'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.online_only')) ?></option>
        <option value="offline" <?= $filter_online==='offline'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.online_offline')) ?></option>
      </select>
    </label>

    <label class="list-filter__field list-filter__field--fit-select">
      <span><?= htmlspecialchars(__('app.account.filters.ban')) ?></span>
      <select name="ban">
        <option value="any" <?= $filter_ban==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.ban_any')) ?></option>
        <option value="banned" <?= $filter_ban==='banned'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.ban_only')) ?></option>
        <option value="unbanned" <?= $filter_ban==='unbanned'?'selected':'' ?>><?= htmlspecialchars(__('app.account.filters.ban_unbanned')) ?></option>
      </select>
    </label>

    <label class="list-filter__field list-filter__field--fit-account">
      <span><?= htmlspecialchars(__('app.account.filters.exclude_username')) ?></span>
      <input type="text" name="exclude_username" maxlength="16" value="<?= htmlspecialchars($exclude_username) ?>" placeholder="<?= htmlspecialchars(__('app.account.filters.exclude_username_placeholder')) ?>">
    </label>
  </div>

  <div class="list-filter__footer">
    <div class="list-filter__status">
      <div id="account-feedback" class="panel-flash panel-flash--inline"></div>
      <?php if(!$hasCriteria): ?>
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.account.feedback.enter_search')) ?></div>
      <?php endif; ?>
    </div>

    <div class="list-filter__actions">
      <button class="btn" type="submit"><?= htmlspecialchars(__('app.account.search.submit')) ?></button>
      <button class="btn outline" type="submit" name="load_all" value="1"><?= htmlspecialchars(__('app.account.search.load_all')) ?></button>
      <a class="btn outline" href="<?= htmlspecialchars(url_with_server('/account')) ?>"><?= htmlspecialchars(__('app.account.search.clear')) ?></a>
      <?php if($__accountCapabilities['create']): ?>
      <button class="btn success action" type="button" data-action="create-account"><?= htmlspecialchars(__('app.account.search.create')) ?></button>
      <?php endif; ?>
    </div>
  </div>
</form>
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
  <p class="list-summary account-summary">
    <?= htmlspecialchars(__('app.account.feedback.found', ['total' => $pager->total, 'page' => $pager->page, 'pages' => $pager->pages])) ?>
  </p>
  <?php if(!$__accountCanBulk && !$__canAny(['accounts.characters', 'accounts.gm', 'accounts.ban', 'accounts.password', 'accounts.update', 'accounts.ip', 'accounts.kick'])): ?>
  <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></div>
  <?php endif; ?>
  <?php if($__accountCanBulk): ?>
  <div class="flex between center account-bulk-toolbar">
    <div class="flex center account-bulk-toolbar__actions">
      <label class="small account-bulk-toolbar__select-all">
        <input type="checkbox" class="js-account-select-all">
        <span><?= htmlspecialchars(__('app.account.bulk.select_all')) ?></span>
      </label>
      <?php if($__accountCapabilities['delete']): ?>
      <button class="btn-sm btn danger js-account-bulk" data-bulk="delete" type="button"><?= htmlspecialchars(__('app.account.bulk.delete')) ?></button>
      <?php endif; ?>
      <?php if($__accountCapabilities['ban']): ?>
      <button class="btn-sm btn danger js-account-bulk" data-bulk="ban" type="button"><?= htmlspecialchars(__('app.account.bulk.ban')) ?></button>
      <button class="btn-sm btn success js-account-bulk" data-bulk="unban" type="button"><?= htmlspecialchars(__('app.account.bulk.unban')) ?></button>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <table class="table">
  <thead><tr>
    <?php if($__accountCanBulk): ?>
    <th class="account-table__select-col"><input type="checkbox" class="js-account-select-all" aria-label="select all"></th>
    <?php endif; ?>
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
        <?php if($__accountCanBulk): ?>
        <td><input type="checkbox" class="js-account-select" value="<?= (int)$row['id'] ?>" aria-label="select"></td>
        <?php endif; ?>
        <td><?= (int)$row['id'] ?></td>
        <td><?= account_link((int)$row['id'], (string)$row['username']) ?></td>
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
            <span class="badge account-badge account-badge--banned" title="<?= htmlspecialchars($tooltip) ?>">
              <?= htmlspecialchars(__('app.account.ban.badge', ['duration' => $friendlyTime($b['remaining_seconds'])])) ?>
            </span>
          <?php else: ?>
            <?= (int)$row['online']
              ? '<span class="badge account-badge account-badge--online">'.htmlspecialchars(__('app.account.status.online')).'</span>'
              : '<span class="badge">'.htmlspecialchars(__('app.account.status.offline')).'</span>'
            ?>
          <?php endif; ?>
        </td>
        <td><?= !empty($row['last_login'])?htmlspecialchars($row['last_login']):'-' ?></td>
        <td><?= htmlspecialchars($lastIp) ?></td>
        <td class="ip-location" data-ip="<?= htmlspecialchars($lastIp) ?>">-</td>
        <td class="account-table__actions-cell">
          <?php
            // 归组规则与 account.js 的 rowActionBarHtml 完全一致：
            // 行内只平铺"角色"，其余低频/危险操作收进"更多"菜单，避免每行挤 9 个按钮
            $accountMenuActions = [];
            if($__accountCapabilities['gm'])        { $accountMenuActions[] = ['action' => 'gm',     'class' => 'btn-sm btn warn',            'label' => __('app.account.actions.gm')]; }
            if($__accountCapabilities['ban'])       { $accountMenuActions[] = ['action' => 'ban',    'class' => 'btn-sm btn danger',          'label' => __('app.account.actions.ban')]; }
            if($__accountCapabilities['ban'])       { $accountMenuActions[] = ['action' => 'unban',  'class' => 'btn-sm btn success',         'label' => __('app.account.actions.unban')]; }
            if($__accountCapabilities['password'])  { $accountMenuActions[] = ['action' => 'pass',   'class' => 'btn-sm btn info outline',    'label' => __('app.account.actions.password')]; }
            if($__accountCapabilities['update'])    { $accountMenuActions[] = ['action' => 'email',  'class' => 'btn-sm btn neutral',         'label' => __('app.account.actions.email')]; }
            if($__accountCapabilities['update'])    { $accountMenuActions[] = ['action' => 'rename', 'class' => 'btn-sm btn neutral outline', 'label' => __('app.account.actions.rename')]; }
            if($__accountCapabilities['ip'])        { $accountMenuActions[] = ['action' => 'ip-accounts', 'class' => 'btn-sm btn neutral',    'label' => __('app.account.actions.same_ip'), 'disabled' => $isPrivateIp, 'title' => $isPrivateIp ? __('app.account.feedback.private_ip_disabled') : '']; }
            if($__accountCapabilities['kick'])      { $accountMenuActions[] = ['action' => 'kick',   'class' => 'btn-sm btn outline danger',  'label' => __('app.account.actions.kick')]; }
            if($__accountCapabilities['delete'])    { $accountMenuActions[] = ['action' => 'delete', 'class' => 'btn-sm btn danger',          'label' => __('app.account.actions.delete')]; }
            $accountHasAnyAction = $__accountCapabilities['characters'] || $accountMenuActions !== [];
          ?>
          <?php if(!$accountHasAnyAction): ?>
            <span class="muted small"><?= htmlspecialchars(__('app.common.capabilities.no_actions')) ?></span>
          <?php else: ?>
            <div class="row-action-bar">
              <?php if($__accountCapabilities['characters']): ?>
              <button class="btn-sm btn info action" data-action="chars"><?= htmlspecialchars(__('app.account.actions.chars')) ?></button>
              <?php endif; ?>

              <?php if($accountMenuActions !== []): ?>
              <details class="row-menu">
                <summary class="btn-sm btn neutral outline" title="<?= htmlspecialchars(__('app.account.actions.more')) ?>">
                  <?= htmlspecialchars(__('app.account.actions.more')) ?>
                </summary>
                <div class="row-menu__list">
                  <?php foreach($accountMenuActions as $menuAction): ?>
                    <button
                      class="<?= htmlspecialchars($menuAction['class']) ?> action"
                      data-action="<?= htmlspecialchars($menuAction['action']) ?>"
                      <?= !empty($menuAction['disabled']) ? 'disabled' : '' ?>
                      <?= !empty($menuAction['title']) ? 'title="' . htmlspecialchars($menuAction['title']) . '"' : '' ?>
                    ><?= htmlspecialchars($menuAction['label']) ?></button>
                  <?php endforeach; ?>
                </div>
              </details>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php if(!$pager->items): ?><tr><td colspan="<?= $__accountCanBulk ? 9 : 8 ?>" class="account-table__empty-cell"><?= htmlspecialchars(__('app.account.feedback.empty')) ?></td></tr><?php endif; ?>
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
<?php endif; ?>
