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
 $filter_ban = $filters['ban'] ?? 'any';
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
<?php $hasCriteria = $load_all || $name!=='' || $guid>0 || $account!=='' || $levelMin>0 || $levelMax>0 || $filter_online!=='any' || $filter_ban!=='any'; ?>
<form class="list-filter" method="get" action="">
  <?php include __DIR__.'/../partials/server_field.php'; ?>
  <div class="list-filter__grid list-filter__grid--fit">
    <label class="list-filter__field list-filter__field--fit-name">
      <span><?= htmlspecialchars(__('app.character.index.search.name_label')) ?></span>
      <input type="text" name="name" maxlength="12" value="<?= htmlspecialchars($name) ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.name_placeholder')) ?>">
    </label>

    <label class="list-filter__field list-filter__field--fit-id">
      <span><?= htmlspecialchars(__('app.character.index.search.guid_label')) ?></span>
      <input type="number" name="guid" min="1" value="<?= $guid>0?(int)$guid:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.guid_placeholder')) ?>">
    </label>

    <label class="list-filter__field list-filter__field--fit-account">
      <span><?= htmlspecialchars(__('app.character.index.search.account_label')) ?></span>
      <input type="text" name="account" maxlength="16" value="<?= htmlspecialchars($account) ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.account_placeholder')) ?>">
    </label>

    <label class="list-filter__field list-filter__field--fit-level">
      <span><?= htmlspecialchars(__('app.character.index.search.level_label')) ?></span>
      <div class="list-filter__range">
        <input type="number" name="level_min" min="1" max="255" value="<?= $levelMin>0?(int)$levelMin:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.level_min')) ?>">
        <span class="list-filter__range-sep">~</span>
        <input type="number" name="level_max" min="1" max="255" value="<?= $levelMax>0?(int)$levelMax:'' ?>" placeholder="<?= htmlspecialchars(__('app.character.index.search.level_max')) ?>">
      </div>
    </label>

    <label class="list-filter__field list-filter__field--fit-select">
      <span><?= htmlspecialchars(__('app.character.index.filters.online')) ?></span>
      <select name="online">
        <option value="any" <?= $filter_online==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.online_any')) ?></option>
        <option value="online" <?= $filter_online==='online'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.online_only')) ?></option>
        <option value="offline" <?= $filter_online==='offline'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.online_offline')) ?></option>
      </select>
    </label>

    <label class="list-filter__field list-filter__field--fit-select">
      <span><?= htmlspecialchars(__('app.character.index.filters.ban')) ?></span>
      <select name="ban">
        <option value="any" <?= $filter_ban==='any'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.ban_any')) ?></option>
        <option value="banned" <?= $filter_ban==='banned'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.ban_only')) ?></option>
        <option value="unbanned" <?= $filter_ban==='unbanned'?'selected':'' ?>><?= htmlspecialchars(__('app.character.index.filters.ban_unbanned')) ?></option>
      </select>
    </label>
  </div>

  <div class="list-filter__footer">
    <div class="list-filter__status">
      <div id="char-feedback" class="panel-flash panel-flash--inline char-feedback--hidden"></div>
      <?php if(!$hasCriteria): ?>
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.character.index.feedback.enter_search')) ?></div>
      <?php endif; ?>
    </div>

    <div class="list-filter__actions">
      <button class="btn" type="submit"><?= htmlspecialchars(__('app.character.index.search.submit')) ?></button>
      <button class="btn outline" type="submit" name="load_all" value="1"><?= htmlspecialchars(__('app.character.index.search.load_all')) ?></button>
      <a class="btn outline" href="<?= htmlspecialchars(url_with_server('/character')) ?>"><?= htmlspecialchars(__('app.character.index.search.clear')) ?></a>
    </div>
  </div>
</form>
<?php if($hasCriteria): ?>
  <?php
    include __DIR__.'/../partials/list_url.php';

    // 排序与分页共用同一个拼装入口，且都带当前区。
    $sortUrl = static function(?string $value) use ($list_url): string {
      return $list_url('/character', ['sort' => $value]);
    };

    $nextSort = static function(string $column) use ($sort): string {
      $cur = (string)$sort;
      $asc = $column . '_asc';
      $desc = $column . '_desc';

      if($cur === $asc) return $desc;
      if($cur === $desc) return '';
      return $asc;
    };

    /** 排序链接的类：颜色之外还要有方向（箭头字形由外观批次美化）。 */
    $sortAttrs = static function(string $column) use ($sort, $sort_direction): string {
      $cur = (string)$sort;
      if($cur === '' || !str_starts_with($cur, $column . '_')) return 'table-sort';
      return 'table-sort is-active ' . $sort_direction($cur);
    };
    $sortArrow = static function(string $column) use ($sort): string {
      $cur = (string)$sort;
      if($cur === '' || !str_starts_with($cur, $column . '_')) return '';
      $arrow = str_ends_with($cur, '_desc') ? '▼' : '▲';
      return ' <span class="table-sort__dir" aria-hidden="true">' . $arrow . '</span>';
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
  <div class="table-wrap">
  <table class="table character-table">
    <thead>
      <tr>
        <?php if($characterCanBulk): ?>
        <th scope="col" class="char-select-col"><input type="checkbox" class="js-char-select-all" aria-label="<?= htmlspecialchars(__('app.account.bulk.select_all')) ?>"></th>
        <?php endif; ?>
        <th scope="col"><a class="<?= $sortAttrs('guid') ?>" href="<?= htmlspecialchars($sortUrl($nextSort('guid'))) ?>"><?= htmlspecialchars(__('app.character.index.table.guid')) ?><?= $sortArrow('guid') ?></a></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.name')) ?></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.account')) ?></th>
        <th scope="col"><a class="<?= $sortAttrs('level') ?>" href="<?= htmlspecialchars($sortUrl($nextSort('level'))) ?>"><?= htmlspecialchars(__('app.character.index.table.level')) ?><?= $sortArrow('level') ?></a></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.class')) ?></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.race')) ?></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.map')) ?></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.zone')) ?></th>
        <th scope="col"><a class="<?= $sortAttrs('online') ?>" href="<?= htmlspecialchars($sortUrl($nextSort('online'))) ?>"><?= htmlspecialchars(__('app.character.index.table.online')) ?><?= $sortArrow('online') ?></a></th>
        <th scope="col"><a class="<?= $sortAttrs('logout') ?>" href="<?= htmlspecialchars($sortUrl($nextSort('logout'))) ?>"><?= htmlspecialchars(__('app.character.index.table.last_logout')) ?><?= $sortArrow('logout') ?></a></th>
        <th scope="col"><?= htmlspecialchars(__('app.character.index.table.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($pager->items as $row): ?>
      <tr>
        <?php if($characterCanBulk): ?>
        <td><input type="checkbox" class="js-char-select" value="<?= (int)$row['guid'] ?>" aria-label="<?= htmlspecialchars(__('app.character.index.table.select_row', ['name' => (string)$row['name']])) ?>"></td>
        <?php endif; ?>
        <td><?= (int)$row['guid'] ?></td>
        <td><?= character_link((int)$row['guid'], (string)$row['name']) ?></td>
        <?php $accName = (string)($row['account_username'] ?? ''); ?>
        <?php $accId = (int)($row['account'] ?? 0); ?>
        <td>
          <?= account_link($accId, $accName) ?>
          <?php if($accName !== ''): ?>
            <a
              class="link char-inline-link"
              href="<?= htmlspecialchars(url_with_server('/character?account=' . rawurlencode($accName) . '&load_all=1')) ?>"
              title="<?= htmlspecialchars(__('app.account.table.view_characters')) ?>"
            ><?= htmlspecialchars(__('app.character.index.table.same_account')) ?></a>
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
        <?php $viewUrl = character_view_url((int)$row['guid']); ?>
        <td class="char-action-cell">
          <div class="row-action-bar">
            <?php if($characterCapabilities['details']): ?>
            <a class="btn-sm btn info" href="<?= htmlspecialchars($viewUrl) ?>"><?= htmlspecialchars(__('app.character.index.table.view')) ?></a>
            <?php endif; ?>
            <?php if($characterCapabilities['delete']): ?>
            <button class="btn-sm btn danger js-char-delete" type="button" data-guid="<?= (int)$row['guid'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars(__('app.character.actions.delete')) ?></button>
            <?php endif; ?>
            <?php if(!$__canAny(['characters.details', 'characters.delete'])): ?>
            <span class="muted small"><?= htmlspecialchars(__('app.common.capabilities.no_actions')) ?></span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$pager->items): ?><?php $colspan = $characterCanBulk ? 12 : 11; $label = __('app.character.index.feedback.empty'); $cell_class = 'char-empty-cell'; include __DIR__.'/../components/empty_state.php'; ?><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php
    $page=$pager->page; $pages=$pager->pages;
    $base = $list_url('/character');
    include __DIR__.'/../components/pagination.php';
  ?>
<?php endif; ?>
