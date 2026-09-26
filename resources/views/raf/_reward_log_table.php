<?php
/**
 * 奖励发放明细表格（记录区块与统计卡下钻弹窗共用）。
 * 下钻弹窗：仅 $pager / $raf_log_source_labels / $raf_detail_mode = true。
 */

$rewardTableDetailMode = !empty($raf_detail_mode);
$rewardTableRows = (is_object($pager ?? null) && is_array($pager->items ?? null))
    ? $pager->items
    : [];
$rewardTableSourceLabels = is_array($raf_log_source_labels ?? null)
    ? $raf_log_source_labels
    : [
        'login' => __('app.raf.reward_log.sources.login'),
        'level_change' => __('app.raf.reward_log.sources.level_change'),
    ];
?>
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
      <?php if ($rewardTableRows === []): ?>
        <tr>
          <td colspan="8" class="text-center muted"><?= htmlspecialchars(__('app.raf.reward_log.empty')) ?></td>
        </tr>
      <?php endif; ?>

      <?php foreach ($rewardTableRows as $logRow): ?>
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
              <?= htmlspecialchars($rewardTableSourceLabels[$logRowSource] ?? ($logRowSource !== '' ? $logRowSource : '—')) ?>
            </span>
          </td>
          <td><span class="small"><?= htmlspecialchars((string) ($logRow['mail_subject'] ?? '')) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (!$rewardTableDetailMode && (int) ($pager->pages ?? 1) > 1): ?>
  <div class="raf-pagination">
    <?php if ((int) ($pager->page ?? 1) > 1): ?>
      <button
        type="button"
        class="btn outline btn-sm"
        data-raf-page="reward-log"
        data-raf-page-target="<?= (int) $pager->page - 1 ?>"
        data-raf-page-key="log_page"
      ><?= htmlspecialchars(__('app.pagination.previous')) ?></button>
    <?php endif; ?>
    <span class="raf-pagination__label">
      <?= htmlspecialchars(__('app.raf.reward_log.summary', [
          'total' => (string) ($pager->total ?? 0),
          'page' => (string) ($pager->page ?? 1),
          'pages' => (string) ($pager->pages ?? 1),
      ])) ?>
    </span>
    <?php if ((int) ($pager->page ?? 1) < (int) ($pager->pages ?? 1)): ?>
      <button
        type="button"
        class="btn outline btn-sm"
        data-raf-page="reward-log"
        data-raf-page-target="<?= (int) $pager->page + 1 ?>"
        data-raf-page-key="log_page"
      ><?= htmlspecialchars(__('app.pagination.next')) ?></button>
    <?php endif; ?>
  </div>
<?php endif; ?>
