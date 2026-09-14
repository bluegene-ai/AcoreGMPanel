<?php

$rafStats = is_array($raf_stats ?? null) ? $raf_stats : [];
$rafDefaults = is_array($raf_defaults ?? null) ? $raf_defaults : [];
$rafCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
        'list' => $__can('raf.list'),
        'bind' => $__can('raf.bind'),
        'unbind' => $__can('raf.unbind'),
        'comment' => $__can('raf.comment'),
    ];
$__pageCapabilities = $rafCapabilities;
$capabilityNotice = $__canAll(['raf.bind', 'raf.unbind', 'raf.comment'])
    ? null
    : __('app.common.capabilities.page_limited');
$loadError = trim((string) ($raf_error ?? ''));
$schemaMissing = !empty($raf_schema_missing);
$pageSizeOptions = is_array($rafDefaults['page_size_options'] ?? null)
    ? $rafDefaults['page_size_options']
    : [20, 30, 50, 100];
$search = trim((string) ($search ?? ''));
$recruiterGuid = (int) ($recruiter_guid ?? 0);
$status = trim((string) ($status ?? 'all')) ?: 'all';
$sort = trim((string) ($sort ?? 'time_stamp')) ?: 'time_stamp';
$dir = trim((string) ($dir ?? 'DESC')) ?: 'DESC';
$limit = (int) ($limit ?? 30);
$serverName = trim((string) ($rafDefaults['server_name'] ?? ''));
$realmId = (int) ($rafDefaults['realm_id'] ?? 0);
$rows = is_object($pager ?? null) && isset($pager->items) && is_array($pager->items)
    ? $pager->items
    : [];

$rafRewardLog = is_array($raf_reward_log ?? null) ? $raf_reward_log : [];
$logPager = is_object($rafRewardLog['pager'] ?? null) ? $rafRewardLog['pager'] : null;
$logStats = is_array($rafRewardLog['stats'] ?? null) ? $rafRewardLog['stats'] : [];
$logFilters = is_array($rafRewardLog['filters'] ?? null) ? $rafRewardLog['filters'] : [];
$logError = trim((string) ($rafRewardLog['error'] ?? ''));
$logRows = ($logPager !== null && is_array($logPager->items ?? null)) ? $logPager->items : [];
$logSearch = trim((string) ($logFilters['search'] ?? ''));
$logLevel = (int) ($logFilters['level'] ?? 0);
$logSource = trim((string) ($logFilters['source'] ?? ''));
$logSort = trim((string) ($logFilters['sort'] ?? 'granted_at')) ?: 'granted_at';
$logDir = trim((string) ($logFilters['dir'] ?? 'DESC')) ?: 'DESC';
$logFromTs = (int) ($logFilters['from'] ?? 0);
$logToTs = (int) ($logFilters['to'] ?? 0);
$logLimit = (int) ($rafRewardLog['limit'] ?? 30);
$logSourceLabels = [
    'login' => __('app.raf.reward_log.sources.login'),
    'level_change' => __('app.raf.reward_log.sources.level_change'),
];

// 两套筛选参数互相保留：任一表单提交或翻页都不会清掉另一侧的条件
$mainQuery = [
    'search' => $search,
    'recruiter_guid' => $recruiterGuid > 0 ? $recruiterGuid : null,
    'status' => $status !== 'all' ? $status : null,
    'sort' => $sort !== 'time_stamp' ? $sort : null,
    'dir' => strtoupper($dir) !== 'DESC' ? strtoupper($dir) : null,
    'limit' => $limit !== 30 ? $limit : null,
];
$logQuery = [
    'log_search' => $logSearch !== '' ? $logSearch : null,
    'log_level' => $logLevel > 0 ? $logLevel : null,
    'log_source' => $logSource !== '' ? $logSource : null,
    'log_from' => $logFromTs > 0 ? date('Y-m-d', $logFromTs) : null,
    'log_to' => $logToTs > 0 ? date('Y-m-d', $logToTs) : null,
    'log_sort' => $logSort !== 'granted_at' ? $logSort : null,
    'log_dir' => strtoupper($logDir) !== 'DESC' ? strtoupper($logDir) : null,
    'log_limit' => $logLimit !== 30 ? $logLimit : null,
];
$serverQuery = ['server' => (int) $current_server];
$buildRafUrl = static function (array $query): string {
    return url('/raf?' . http_build_query(array_filter($query, static function ($value) {
        return $value !== null && $value !== '';
    })));
};

$pageUrl = static function (int $page) use ($buildRafUrl, $serverQuery, $mainQuery, $logQuery): string {
    return $buildRafUrl(array_merge($serverQuery, ['page' => $page], $mainQuery, $logQuery));
};

$logPageUrl = static function (int $page) use ($buildRafUrl, $serverQuery, $mainQuery, $logQuery): string {
    return $buildRafUrl(array_merge($serverQuery, ['log_page' => $page], $mainQuery, $logQuery));
};
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="raf-page">
  <?php if ($loadError !== ''): ?>
    <div class="panel-flash <?= $schemaMissing ? 'panel-flash--error' : 'panel-flash--error' ?> panel-flash--inline is-visible">
      <?= htmlspecialchars($loadError) ?>
    </div>
  <?php endif; ?>

  <div id="rafFeedback" class="panel-flash panel-flash--inline" hidden></div>

  <section class="raf-summary-grid">
    <article class="raf-stat-card">
      <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.stats.total')) ?></span>
      <strong class="raf-stat-card__value"><?= (int) ($rafStats['total'] ?? 0) ?></strong>
    </article>
    <article class="raf-stat-card">
      <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.stats.active')) ?></span>
      <strong class="raf-stat-card__value"><?= (int) ($rafStats['active'] ?? 0) ?></strong>
    </article>
    <article class="raf-stat-card">
      <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.stats.completed')) ?></span>
      <strong class="raf-stat-card__value"><?= (int) ($rafStats['completed'] ?? 0) ?></strong>
    </article>
    <article class="raf-stat-card">
      <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.stats.inactive')) ?></span>
      <strong class="raf-stat-card__value"><?= (int) ($rafStats['inactive'] ?? 0) ?></strong>
    </article>
    <article class="raf-stat-card">
      <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.stats.permanent_blocked')) ?></span>
      <strong class="raf-stat-card__value"><?= (int) ($rafStats['permanent_blocked'] ?? 0) ?></strong>
    </article>
    <article class="raf-stat-card">
      <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.stats.rewarded_accounts')) ?></span>
      <strong class="raf-stat-card__value"><?= (int) ($rafStats['rewarded_accounts'] ?? 0) ?></strong>
    </article>
  </section>

  <section class="raf-panel">
    <div class="raf-panel__head">
      <div>
        <h2><?= htmlspecialchars(__('app.raf.filters.title')) ?></h2>
        <p class="muted raf-panel__meta">
          <?= htmlspecialchars(__('app.raf.scope_note', ['server' => $serverName, 'realm' => (string) $realmId])) ?>
        </p>
      </div>
      <?php if ($rafCapabilities['bind']): ?>
        <button type="button" class="btn" id="rafBindBtn">
          <?= htmlspecialchars(__('app.raf.actions.bind')) ?>
        </button>
      <?php endif; ?>
    </div>

    <form class="raf-filter-grid" method="get" action="">
      <input type="hidden" name="server" value="<?= (int) $current_server ?>">
      <?php foreach ($logQuery as $logHiddenName => $logHiddenValue): ?>
        <?php if ($logHiddenValue !== null && $logHiddenValue !== ''): ?>
          <input type="hidden" name="<?= htmlspecialchars((string) $logHiddenName) ?>" value="<?= htmlspecialchars((string) $logHiddenValue) ?>">
        <?php endif; ?>
      <?php endforeach; ?>

      <label class="raf-field raf-field--span-2">
        <span><?= htmlspecialchars(__('app.raf.filters.search')) ?></span>
        <input
          type="text"
          name="search"
          value="<?= htmlspecialchars($search) ?>"
          placeholder="<?= htmlspecialchars(__('app.raf.filters.search_placeholder')) ?>"
        >
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.recruiter_guid')) ?></span>
        <input type="number" min="1" name="recruiter_guid" value="<?= $recruiterGuid > 0 ? $recruiterGuid : '' ?>">
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.status')) ?></span>
        <select name="status">
          <?php foreach (['all', 'active', 'completed', 'inactive', 'permanent'] as $statusValue): ?>
            <option value="<?= htmlspecialchars($statusValue) ?>" <?= $statusValue === $status ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.raf.filters.status_values.' . $statusValue)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.sort')) ?></span>
        <select name="sort">
          <?php foreach (['time_stamp', 'account_id', 'recruiter_guid', 'ip_abuse_counter', 'kick_counter', 'reward_level'] as $sortValue): ?>
            <option value="<?= htmlspecialchars($sortValue) ?>" <?= $sortValue === $sort ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.raf.sorts.' . $sortValue)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.direction')) ?></span>
        <select name="dir">
          <?php foreach (['DESC', 'ASC'] as $directionValue): ?>
            <option value="<?= $directionValue ?>" <?= strtoupper($dir) === $directionValue ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.raf.directions.' . $directionValue)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.limit')) ?></span>
        <select name="limit">
          <?php foreach ($pageSizeOptions as $pageSize): ?>
            <?php $pageSize = (int) $pageSize; ?>
            <option value="<?= $pageSize ?>" <?= $pageSize === $limit ? 'selected' : '' ?>>
              <?= $pageSize ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="raf-filter-actions">
        <button class="btn" type="submit"><?= htmlspecialchars(__('app.raf.filters.search_btn')) ?></button>
        <a class="btn outline" href="<?= htmlspecialchars(url_with_server('/raf')) ?>">
          <?= htmlspecialchars(__('app.raf.filters.clear_btn')) ?>
        </a>
      </div>
    </form>
  </section>

  <section class="raf-panel">
    <div class="raf-panel__head">
      <div>
        <h2><?= htmlspecialchars(__('app.raf.table.title')) ?></h2>
        <p class="muted raf-panel__meta">
          <?= htmlspecialchars(__('app.raf.summary', [
              'total' => (string) ($pager->total ?? 0),
              'page' => (string) ($pager->page ?? 1),
              'pages' => (string) ($pager->pages ?? 1),
          ])) ?>
        </p>
      </div>
    </div>

    <div class="raf-table-wrap">
      <table class="table raf-table">
        <thead>
          <tr>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.account')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.recruiter')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.status')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.bound_at')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.abuse')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.kicks')) ?></th>
            <th scope="col" title="<?= htmlspecialchars(__('app.raf.table.reward_level_hint')) ?>"><?= htmlspecialchars(__('app.raf.table.columns.reward_level')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.comment')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []): ?>
            <tr>
              <td colspan="9" class="text-center muted"><?= htmlspecialchars(__('app.raf.empty')) ?></td>
            </tr>
          <?php endif; ?>

          <?php foreach ($rows as $row): ?>
            <?php
            $accountLabel = trim((string) ($row['account_username'] ?? ''));
            if ($accountLabel === '') {
                $accountLabel = '#' . (int) ($row['account_id'] ?? 0);
            }
            $recruiterLabel = trim((string) ($row['recruiter_name'] ?? ''));
            if ($recruiterLabel === '') {
                $recruiterLabel = '#' . (int) ($row['recruiter_guid'] ?? 0);
            }
            $comment = trim((string) ($row['comment'] ?? ''));
            // time_stamp 语义：>1 为绑定时间，1 已完成，-1 永久有效，<=0 已失效
            $timeStamp = (int) ($row['time_stamp'] ?? 0);
            if ($timeStamp > 1) {
                $boundAtText = format_datetime($timeStamp);
            } elseif ($timeStamp === -1) {
                $boundAtText = __('app.raf.time.permanent');
            } else {
                $boundAtText = '-';
            }
            ?>
            <tr>
              <td>
                <div class="raf-cell-title"><?= account_link((int) ($row['account_id'] ?? 0), $accountLabel) ?></div>
                <div class="small muted">ID #<?= (int) ($row['account_id'] ?? 0) ?></div>
              </td>
              <td>
                <div class="raf-cell-title"><?= character_link((int) ($row['recruiter_guid'] ?? 0), $recruiterLabel) ?></div>
                <div class="small muted">
                  GUID #<?= (int) ($row['recruiter_guid'] ?? 0) ?>
                  <?php if (!empty($row['recruiter_account_id'])): ?>
                    · <?= account_link((int) $row['recruiter_account_id']) ?>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <span class="raf-status raf-status--<?= htmlspecialchars((string) ($row['status_key'] ?? 'active')) ?>">
                  <?= htmlspecialchars(__('app.raf.status.' . ($row['status_key'] ?? 'active'))) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($boundAtText) ?></td>
              <td><?= (int) ($row['ip_abuse_counter'] ?? 0) ?></td>
              <td><?= (int) ($row['kick_counter'] ?? 0) ?></td>
              <td><?= (int) ($row['reward_level'] ?? 0) ?></td>
              <td>
                <?php if ($comment !== ''): ?>
                  <div class="raf-comment-text"><?= htmlspecialchars($comment) ?></div>
                <?php else: ?>
                  <span class="small muted"><?= htmlspecialchars(__('app.raf.comment.empty')) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="raf-action-stack">
                  <?php if ($rafCapabilities['comment']): ?>
                    <button
                      type="button"
                      class="btn outline btn-sm js-raf-comment"
                      data-account-id="<?= (int) ($row['account_id'] ?? 0) ?>"
                      data-account-label="<?= htmlspecialchars($accountLabel) ?>"
                      data-comment="<?= htmlspecialchars($comment) ?>"
                    ><?= htmlspecialchars(__('app.raf.actions.comment')) ?></button>
                  <?php endif; ?>

                  <?php if ($rafCapabilities['unbind']): ?>
                    <button
                      type="button"
                      class="btn warn btn-sm js-raf-unbind"
                      data-account-id="<?= (int) ($row['account_id'] ?? 0) ?>"
                      data-account-label="<?= htmlspecialchars($accountLabel) ?>"
                    ><?= htmlspecialchars(__('app.raf.actions.unbind')) ?></button>
                  <?php endif; ?>

                  <?php if (!$rafCapabilities['comment'] && !$rafCapabilities['unbind']): ?>
                    <span class="small muted"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (($pager->pages ?? 1) > 1): ?>
      <div class="raf-pagination">
        <?php if (($pager->page ?? 1) > 1): ?>
          <a class="btn outline btn-sm" href="<?= htmlspecialchars($pageUrl((int) $pager->page - 1)) ?>">
            <?= htmlspecialchars(__('app.pagination.previous')) ?>
          </a>
        <?php endif; ?>
        <span class="raf-pagination__label">
          <?= htmlspecialchars(__('app.raf.summary', [
              'total' => (string) ($pager->total ?? 0),
              'page' => (string) ($pager->page ?? 1),
              'pages' => (string) ($pager->pages ?? 1),
          ])) ?>
        </span>
        <?php if (($pager->page ?? 1) < ($pager->pages ?? 1)): ?>
          <a class="btn outline btn-sm" href="<?= htmlspecialchars($pageUrl((int) $pager->page + 1)) ?>">
            <?= htmlspecialchars(__('app.pagination.next')) ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="raf-panel">
    <div class="raf-panel__head">
      <div>
        <h2><?= htmlspecialchars(__('app.raf.reward_log.title')) ?></h2>
        <p class="muted raf-panel__meta"><?= htmlspecialchars(__('app.raf.reward_log.subtitle')) ?></p>
      </div>
    </div>

    <?php if ($logError !== ''): ?>
      <div class="panel-flash panel-flash--error panel-flash--inline is-visible"><?= htmlspecialchars($logError) ?></div>
    <?php else: ?>
      <section class="raf-summary-grid raf-summary-grid--log">
        <article class="raf-stat-card">
          <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.reward_log.stats.total')) ?></span>
          <strong class="raf-stat-card__value"><?= (int) ($logStats['total'] ?? 0) ?></strong>
        </article>
        <article class="raf-stat-card">
          <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.reward_log.stats.recruiters')) ?></span>
          <strong class="raf-stat-card__value"><?= (int) ($logStats['recruiters'] ?? 0) ?></strong>
        </article>
        <article class="raf-stat-card">
          <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.reward_log.stats.recruits')) ?></span>
          <strong class="raf-stat-card__value"><?= (int) ($logStats['recruits'] ?? 0) ?></strong>
        </article>
        <article class="raf-stat-card">
          <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.reward_log.stats.default_rewards')) ?></span>
          <strong class="raf-stat-card__value"><?= (int) ($logStats['default_rewards'] ?? 0) ?></strong>
        </article>
        <article class="raf-stat-card">
          <span class="raf-stat-card__label"><?= htmlspecialchars(__('app.raf.reward_log.stats.latest')) ?></span>
          <strong class="raf-stat-card__value raf-stat-card__value--time"><?= htmlspecialchars(format_datetime((int) ($logStats['latest_granted_at'] ?? 0))) ?></strong>
        </article>
      </section>

      <form class="raf-filter-grid" method="get" action="">
        <input type="hidden" name="server" value="<?= (int) $current_server ?>">
        <?php foreach ($mainQuery as $mainHiddenName => $mainHiddenValue): ?>
          <?php if ($mainHiddenValue !== null && $mainHiddenValue !== ''): ?>
            <input type="hidden" name="<?= htmlspecialchars((string) $mainHiddenName) ?>" value="<?= htmlspecialchars((string) $mainHiddenValue) ?>">
          <?php endif; ?>
        <?php endforeach; ?>

        <label class="raf-field raf-field--span-2">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.search')) ?></span>
          <input
            type="text"
            name="log_search"
            value="<?= htmlspecialchars($logSearch) ?>"
            placeholder="<?= htmlspecialchars(__('app.raf.reward_log.filters.search_placeholder')) ?>"
          >
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.level')) ?></span>
          <input type="number" min="0" name="log_level" value="<?= $logLevel > 0 ? $logLevel : '' ?>">
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.source')) ?></span>
          <select name="log_source">
            <?php foreach (['', 'login', 'level_change'] as $sourceValue): ?>
              <option value="<?= htmlspecialchars($sourceValue) ?>" <?= $sourceValue === $logSource ? 'selected' : '' ?>>
                <?= htmlspecialchars($sourceValue === '' ? __('app.raf.reward_log.sources.all') : ($logSourceLabels[$sourceValue] ?? $sourceValue)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.from')) ?></span>
          <input type="date" name="log_from" value="<?= $logFromTs > 0 ? htmlspecialchars(date('Y-m-d', $logFromTs)) : '' ?>">
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.to')) ?></span>
          <input type="date" name="log_to" value="<?= $logToTs > 0 ? htmlspecialchars(date('Y-m-d', $logToTs)) : '' ?>">
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.sort')) ?></span>
          <select name="log_sort">
            <?php foreach (['granted_at', 'reward_level', 'recruiter_guid', 'recruit_account_id'] as $logSortValue): ?>
              <option value="<?= htmlspecialchars($logSortValue) ?>" <?= $logSortValue === $logSort ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.raf.reward_log.sorts.' . $logSortValue)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.direction')) ?></span>
          <select name="log_dir">
            <?php foreach (['DESC', 'ASC'] as $logDirection): ?>
              <option value="<?= $logDirection ?>" <?= strtoupper($logDir) === $logDirection ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.raf.directions.' . $logDirection)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.limit')) ?></span>
          <select name="log_limit">
            <?php foreach ($pageSizeOptions as $logPageSize): ?>
              <?php $logPageSize = (int) $logPageSize; ?>
              <option value="<?= $logPageSize ?>" <?= $logPageSize === $logLimit ? 'selected' : '' ?>><?= $logPageSize ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <div class="raf-filter-actions">
          <button class="btn" type="submit"><?= htmlspecialchars(__('app.raf.reward_log.filters.search_btn')) ?></button>
          <a class="btn outline" href="<?= htmlspecialchars($buildRafUrl(array_merge($serverQuery, $mainQuery))) ?>">
            <?= htmlspecialchars(__('app.raf.reward_log.filters.clear_btn')) ?>
          </a>
        </div>
      </form>

      <div class="raf-panel__head raf-panel__head--sub">
        <div>
          <h3 class="raf-panel__subtitle"><?= htmlspecialchars(__('app.raf.reward_log.table_title')) ?></h3>
          <p class="muted raf-panel__meta">
            <?= htmlspecialchars(__('app.raf.reward_log.summary', [
                'total' => (string) ($logPager->total ?? 0),
                'page' => (string) ($logPager->page ?? 1),
                'pages' => (string) ($logPager->pages ?? 1),
            ])) ?>
          </p>
        </div>
      </div>

      <div class="raf-table-wrap">
        <table class="table raf-table">
          <thead>
            <tr>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.granted_at')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.recruiter')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.recruit')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.reward_level')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.target_level')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.items')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.source')) ?></th>
              <th scope="col"><?= htmlspecialchars(__('app.raf.reward_log.columns.mail')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($logRows === []): ?>
              <tr>
                <td colspan="8" class="text-center muted"><?= htmlspecialchars(__('app.raf.reward_log.empty')) ?></td>
              </tr>
            <?php endif; ?>

            <?php foreach ($logRows as $logRow): ?>
              <?php
              $logRecruiterGuid = (int) ($logRow['recruiter_guid'] ?? 0);
              $logRecruiterName = trim((string) ($logRow['recruiter_name'] ?? ''));
              if ($logRecruiterName === '') {
                  $logRecruiterName = '#' . $logRecruiterGuid;
              }
              $logRecruiterAccount = (int) ($logRow['recruiter_account'] ?? 0);
              $logRecruiterAccountName = trim((string) ($logRow['recruiter_account_username'] ?? ''));
              $logRecruitAccount = (int) ($logRow['recruit_account_id'] ?? 0);
              $logRecruitName = trim((string) ($logRow['recruit_account_username'] ?? ''));
              $logRowSource = trim((string) ($logRow['reward_source'] ?? ''));
              $logItems = is_array($logRow['reward_item_list'] ?? null) ? $logRow['reward_item_list'] : [];
              $logMoney = (int) ($logRow['reward_money'] ?? 0);
              ?>
              <tr>
                <td>
                  <div class="raf-cell-title"><?= htmlspecialchars(format_datetime((int) ($logRow['granted_at'] ?? 0))) ?></div>
                  <div class="small muted">#<?= (int) ($logRow['id'] ?? 0) ?></div>
                </td>
                <td>
                  <div class="raf-cell-title"><?= character_link($logRecruiterGuid, $logRecruiterName) ?></div>
                  <div class="small muted">
                    GUID #<?= $logRecruiterGuid ?>
                    <?php if ($logRecruiterAccount > 0): ?>
                      · <?= account_link($logRecruiterAccount, $logRecruiterAccountName) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="raf-cell-title"><?= account_link($logRecruitAccount, $logRecruitName) ?></div>
                  <div class="small muted">ID #<?= $logRecruitAccount ?></div>
                </td>
                <td><?= (int) ($logRow['reward_level'] ?? 0) ?></td>
                <td><?= (int) ($logRow['target_level'] ?? 0) ?></td>
                <td>
                  <?php if ($logItems === [] && $logMoney <= 0): ?>
                    <span class="small muted"><?= htmlspecialchars(__('app.raf.reward_log.items_empty')) ?></span>
                  <?php endif; ?>

                  <?php if ($logItems !== []): ?>
                    <ul class="raf-reward-items">
                      <?php foreach ($logItems as $logItem): ?>
                        <?php
                        $itemEntry = (int) ($logItem['entry'] ?? 0);
                        $itemName = trim((string) ($logItem['name'] ?? ''));
                        $itemQuality = $logItem['quality'] ?? null;
                        $itemQualityClass = $itemQuality === null ? '' : ' item-quality-q' . (int) $itemQuality;
                        ?>
                        <li class="raf-reward-item">
                          <span class="raf-reward-item__name<?= $itemQualityClass ?>">
                            <?= htmlspecialchars($itemName !== '' ? $itemName : ('#' . $itemEntry)) ?>
                          </span>
                          <span class="raf-reward-item__count">×<?= (int) ($logItem['count'] ?? 0) ?></span>
                          <span class="small muted">#<?= $itemEntry ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>

                  <?php if ($logMoney > 0): ?>
                    <div class="small muted"><?= htmlspecialchars(__('app.raf.reward_log.money')) ?>: <?= htmlspecialchars(format_money_gsc($logMoney)) ?></div>
                  <?php endif; ?>

                  <?php if ((int) ($logRow['used_default'] ?? 0) === 1): ?>
                    <div class="raf-badge"><?= htmlspecialchars(__('app.raf.reward_log.default_reward')) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="raf-badge raf-badge--<?= htmlspecialchars($logRowSource !== '' ? $logRowSource : 'unknown') ?>">
                    <?= htmlspecialchars($logSourceLabels[$logRowSource] ?? ($logRowSource !== '' ? $logRowSource : '—')) ?>
                  </span>
                </td>
                <td><span class="small"><?= htmlspecialchars((string) ($logRow['mail_subject'] ?? '')) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (($logPager->pages ?? 1) > 1): ?>
        <div class="raf-pagination">
          <?php if (($logPager->page ?? 1) > 1): ?>
            <a class="btn outline btn-sm" href="<?= htmlspecialchars($logPageUrl((int) $logPager->page - 1)) ?>">
              <?= htmlspecialchars(__('app.pagination.previous')) ?>
            </a>
          <?php endif; ?>
          <span class="raf-pagination__label">
            <?= htmlspecialchars(__('app.raf.reward_log.summary', [
                'total' => (string) ($logPager->total ?? 0),
                'page' => (string) ($logPager->page ?? 1),
                'pages' => (string) ($logPager->pages ?? 1),
            ])) ?>
          </span>
          <?php if (($logPager->page ?? 1) < ($logPager->pages ?? 1)): ?>
            <a class="btn outline btn-sm" href="<?= htmlspecialchars($logPageUrl((int) $logPager->page + 1)) ?>">
              <?= htmlspecialchars(__('app.pagination.next')) ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>