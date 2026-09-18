<?php
/**
 * 绑定列表表格（列表区块与统计卡下钻弹窗共用）。
 *
 * 列表区块：$pager / $rafCapabilities / $current_server / $search / $status /
 * $sort / $dir / $limit / $recruiter_guid 均已由 RafController 注入。
 * 下钻弹窗：仅提供 $pager / $rafCapabilities / $raf_detail_mode = true。
 */

$bindingTableDetailMode = !empty($raf_detail_mode);
$bindingTableRows = (is_object($pager ?? null) && is_array($pager->items ?? null))
    ? $pager->items
    : [];

$bindingTablePagination = static function () use ($pager, $bindingTableDetailMode): ?string {
    if ($bindingTableDetailMode || !is_object($pager ?? null)) {
        return null;
    }
    $pages = (int) ($pager->pages ?? 1);
    if ($pages <= 1) {
        return null;
    }

    return 'raf-pagination';
};
?>
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
        <?php if (!$bindingTableDetailMode): ?>
          <th scope="col"><?= htmlspecialchars(__('app.raf.table.columns.actions')) ?></th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if ($bindingTableRows === []): ?>
        <tr>
          <td colspan="<?= $bindingTableDetailMode ? 8 : 9 ?>" class="text-center muted"><?= htmlspecialchars(__('app.raf.empty')) ?></td>
        </tr>
      <?php endif; ?>

      <?php foreach ($bindingTableRows as $row): ?>
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
          <?php if (!$bindingTableDetailMode): ?>
            <td>
              <div class="raf-action-stack">
                <?php if ($rafCapabilities['comment'] ?? false): ?>
                  <button
                    type="button"
                    class="btn outline btn-sm js-raf-comment"
                    data-account-id="<?= (int) ($row['account_id'] ?? 0) ?>"
                    data-account-label="<?= htmlspecialchars($accountLabel) ?>"
                    data-comment="<?= htmlspecialchars($comment) ?>"
                  ><?= htmlspecialchars(__('app.raf.actions.comment')) ?></button>
                <?php endif; ?>

                <?php if ($rafCapabilities['unbind'] ?? false): ?>
                  <button
                    type="button"
                    class="btn warn btn-sm js-raf-unbind"
                    data-account-id="<?= (int) ($row['account_id'] ?? 0) ?>"
                    data-account-label="<?= htmlspecialchars($accountLabel) ?>"
                  ><?= htmlspecialchars(__('app.raf.actions.unbind')) ?></button>
                <?php endif; ?>

                <?php if (!($rafCapabilities['comment'] ?? false) && !($rafCapabilities['unbind'] ?? false)): ?>
                  <span class="small muted"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></span>
                <?php endif; ?>
              </div>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($bindingTablePagination() !== null): ?>
  <div class="raf-pagination">
    <?php if ((int) ($pager->page ?? 1) > 1): ?>
      <button
        type="button"
        class="btn outline btn-sm"
        data-raf-page="bindings"
        data-raf-page-target="<?= (int) $pager->page - 1 ?>"
        data-raf-page-key="page"
      ><?= htmlspecialchars(__('app.pagination.previous')) ?></button>
    <?php endif; ?>
    <span class="raf-pagination__label">
      <?= htmlspecialchars(__('app.raf.summary', [
          'total' => (string) ($pager->total ?? 0),
          'page' => (string) ($pager->page ?? 1),
          'pages' => (string) ($pager->pages ?? 1),
      ])) ?>
    </span>
    <?php if ((int) ($pager->page ?? 1) < (int) ($pager->pages ?? 1)): ?>
      <button
        type="button"
        class="btn outline btn-sm"
        data-raf-page="bindings"
        data-raf-page-target="<?= (int) $pager->page + 1 ?>"
        data-raf-page-key="page"
      ><?= htmlspecialchars(__('app.pagination.next')) ?></button>
    <?php endif; ?>
  </div>
<?php endif; ?>
