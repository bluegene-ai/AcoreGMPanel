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

$pageUrl = static function (int $page) use ($current_server, $search, $recruiterGuid, $status, $sort, $dir, $limit): string {
    $query = [
        'server' => (int) $current_server,
        'page' => $page,
        'search' => $search,
        'recruiter_guid' => $recruiterGuid > 0 ? $recruiterGuid : null,
        'status' => $status !== 'all' ? $status : null,
        'sort' => $sort !== 'time_stamp' ? $sort : null,
        'dir' => strtoupper($dir) !== 'DESC' ? strtoupper($dir) : null,
        'limit' => $limit !== 30 ? $limit : null,
    ];

    return url('/raf?' . http_build_query(array_filter($query, static function ($value) {
        return $value !== null && $value !== '';
    })));
};
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="raf-page">
  <?php if ($loadError !== ''): ?>
    <div class="panel-flash panel-flash--error panel-flash--inline is-visible">
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
            <th><?= htmlspecialchars(__('app.raf.table.columns.account')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.recruiter')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.status')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.bound_at')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.abuse')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.kicks')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.reward_level')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.comment')) ?></th>
            <th><?= htmlspecialchars(__('app.raf.table.columns.actions')) ?></th>
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
              <td><?= htmlspecialchars(format_datetime((int) ($row['time_stamp'] ?? 0))) ?></td>
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
</div>