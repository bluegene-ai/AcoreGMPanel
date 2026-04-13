<?php
/**
 * File: resources/views/mail/index.php
 * Purpose: Provides functionality for the resources/views/mail module.
 */

?>
<?php
  $mailCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
      'list' => $__can('mail.list'),
      'view' => $__can('mail.view'),
      'mark_read' => $__can('mail.mark_read'),
      'delete' => $__can('mail.delete'),
      'stats' => $__can('mail.stats'),
      'logs' => $__can('mail.logs'),
    ];
  $__pageCapabilities = $mailCapabilities;
  $mailCanBulk = $mailCapabilities['mark_read'] || $mailCapabilities['delete'];
  $capabilityNotice = $__canAll(['mail.view', 'mail.mark_read', 'mail.delete', 'mail.stats', 'mail.logs'])
    ? null
    : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<?php

?>
<?php include __DIR__.'/../components/capability_notice.php'; ?>

<div id="mail-feedback" class="panel-flash panel-flash--inline"></div>

<form method="get" action="" class="inline mail-filter-form" id="mail-filter-form">
  <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
  <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
  <input type="text" name="filter_sender" placeholder="<?= htmlspecialchars(__('app.mail.filters.sender')) ?>" value="<?= htmlspecialchars($filters['sender']??'') ?>">
  <input type="text" name="filter_receiver" placeholder="<?= htmlspecialchars(__('app.mail.filters.receiver')) ?>" value="<?= htmlspecialchars($filters['receiver']??'') ?>">
  <input type="text" name="filter_subject" class="mail-filter-form__subject" placeholder="<?= htmlspecialchars(__('app.mail.filters.subject')) ?>" value="<?= htmlspecialchars($filters['subject']??'') ?>">
  <select name="filter_unread">
    <option value="" <?= ($filters['unread']??'')===''?'selected':'' ?>><?= htmlspecialchars(__('app.mail.filters.unread_all')) ?></option>
    <option value="1" <?= ($filters['unread']??'')==='1'?'selected':'' ?>><?= htmlspecialchars(__('app.mail.filters.unread_only')) ?></option>
  </select>
  <select name="filter_has_items">
    <option value="" <?= ($filters['has_items']??'')===''?'selected':'' ?>><?= htmlspecialchars(__('app.mail.filters.attachments_all')) ?></option>
    <option value="1" <?= ($filters['has_items']??'')==='1'?'selected':'' ?>><?= htmlspecialchars(__('app.mail.filters.attachments_only')) ?></option>
  </select>
  <input type="number" name="filter_expiring" class="mail-filter-form__expiring" placeholder="<?= htmlspecialchars(__('app.mail.filters.expiring')) ?>" value="<?= htmlspecialchars($filters['expiring']??'') ?>">
  <input type="number" name="limit" class="mail-filter-form__limit" value="<?= (int)$limit ?>">
  <div class="filter-actions">
    <button class="btn info" type="submit"><?= htmlspecialchars(__('app.mail.filters.actions.search')) ?></button>
    <button class="btn outline" type="button" id="btn-mail-reset"><?= htmlspecialchars(__('app.mail.filters.actions.reset')) ?></button>
    <button class="btn outline" type="button" id="btn-mail-refresh"><?= htmlspecialchars(__('app.mail.filters.actions.refresh')) ?></button>
    <?php if($mailCapabilities['logs']): ?>
    <button class="btn success" type="button" id="btn-mail-log"><?= htmlspecialchars(__('app.mail.filters.actions.log')) ?></button>
    <?php endif; ?>
  </div>
</form>

<div class="mail-toolbar">
  <?php if($mailCanBulk): ?>
  <div class="toolbar-group" id="mail-bulk-actions">
    <?php if($mailCapabilities['mark_read']): ?>
    <button class="btn" id="bulkMarkReadBtn" disabled><?= htmlspecialchars(__('app.mail.toolbar.bulk_read')) ?></button>
    <?php endif; ?>
    <?php if($mailCapabilities['delete']): ?>
    <button class="btn danger" id="bulkDeleteBtn" disabled><?= htmlspecialchars(__('app.mail.toolbar.bulk_delete')) ?></button>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="flex-1"></div>
  <div class="mail-toolbar__stats small muted">
    <span><?= htmlspecialchars(__('app.mail.toolbar.total')) ?> <span id="mailTotalSpan"><?= (int)$total ?></span></span>
    <?php if($mailCapabilities['stats']): ?><span id="mailStatsSpan"></span><?php endif; ?>
  </div>
</div>

<table class="table mail-table" id="mailTable" data-sort="<?= htmlspecialchars($sort) ?>" data-dir="<?= htmlspecialchars($dir) ?>">
  <thead>
    <tr>
      <?php if($mailCanBulk): ?>
      <th class="mail-table__select-col"><input type="checkbox" id="mailSelectAll"></th>
      <?php endif; ?>
  <th data-sort="id" class="sortable"><?= htmlspecialchars(__('app.mail.table.headers.id')) ?><?= $sort==='id'?($dir==='ASC'?' ▲':' ▼'):'' ?></th>
  <th><?= htmlspecialchars(__('app.mail.table.headers.sender')) ?></th>
  <th><?= htmlspecialchars(__('app.mail.table.headers.receiver')) ?></th>
  <th><?= htmlspecialchars(__('app.mail.table.headers.subject')) ?></th>
  <th data-sort="money" class="sortable"><?= htmlspecialchars(__('app.mail.table.headers.money')) ?><?= $sort==='money'?($dir==='ASC'?' ▲':' ▼'):'' ?></th>
  <th><?= htmlspecialchars(__('app.mail.table.headers.attachments')) ?></th>
  <th data-sort="expire_time" class="sortable"><?= htmlspecialchars(__('app.mail.table.headers.expire')) ?><?= $sort==='expire_time'?($dir==='ASC'?' ▲':' ▼'):'' ?></th>
  <th><?= htmlspecialchars(__('app.mail.table.headers.status')) ?></th>
  <th><?= htmlspecialchars(__('app.mail.table.headers.actions')) ?></th>
    </tr>
  </thead>
  <tbody>
  <?php $now = time(); foreach($rows as $r):
    $unread = (int)($r['checked'] ?? 0) === 0;
    $expired = (int)($r['is_expired'] ?? 0) === 1;
    $expireTs = (int)($r['expire_time'] ?? 0);
    if($expireTs === 0){
      $remainText = __('app.mail.table.expire.none');
    } elseif($expireTs < $now) {
      $remainText = __('app.mail.table.expire.expired');
    } else {
      $daysRemain = (int)floor(($expireTs - $now) / 86400);
      $remainText = __('app.mail.table.expire.in_days', ['days' => $daysRemain]);
    }
  ?>
    <tr data-mail-id="<?= (int)$r['id'] ?>" class="<?= $unread?'is-unread':'' ?> <?= $expired?'is-expired':'' ?>">
      <?php if($mailCanBulk): ?>
      <td><input type="checkbox" class="mail-select" value="<?= (int)$r['id'] ?>"></td>
      <?php endif; ?>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['sender_name'] ?? ('#'.($r['sender']??''))) ?></td>
      <td><?= htmlspecialchars($r['receiver_name'] ?? ('#'.($r['receiver']??''))) ?></td>
      <td><?= htmlspecialchars(mb_strimwidth($r['subject']??'',0,50,'...','UTF-8')) ?></td>
      <td><?= htmlspecialchars(format_money_gsc($r['money'] ?? 0)) ?></td>
      <td><?= (int)($r['has_items']??0)===1 ? '<span class="badge">'.htmlspecialchars(__('app.mail.table.attachments.has')).'</span>' : '' ?></td>
      <td><?= htmlspecialchars($remainText) ?></td>
      <td><?= $unread ? '<span class="badge primary">'.htmlspecialchars(__('app.mail.table.status.unread')).'</span>' : '<span class="badge">'.htmlspecialchars(__('app.mail.table.status.read')).'</span>' ?></td>
      <td class="nowrap">
  <?php if($mailCapabilities['view']): ?>
  <button class="btn-sm btn action-view" data-id="<?= (int)$r['id'] ?>"><?= htmlspecialchars(__('app.mail.table.actions.view')) ?></button>
  <?php endif; ?>
  <?php if($mailCapabilities['mark_read']): ?>
  <button class="btn-sm btn action-mark-read <?= $unread?'mark-btn-active':'mark-btn-disabled' ?>" data-id="<?= (int)$r['id'] ?>" <?= $unread?'':'disabled' ?>><?= htmlspecialchars(__('app.mail.table.actions.mark_read')) ?></button>
  <?php endif; ?>
        <?php if($mailCapabilities['delete']): ?>
        <button class="btn-sm btn danger action-delete" data-id="<?= (int)$r['id'] ?>"><?= htmlspecialchars(__('app.mail.table.actions.delete')) ?></button>
        <?php endif; ?>
        <?php if(!$__canAny(['mail.view', 'mail.mark_read', 'mail.delete'])): ?>
        <span class="muted small"><?= htmlspecialchars(__('app.common.capabilities.no_actions')) ?></span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if(!$rows): ?>
    <tr><td colspan="<?= $mailCanBulk ? 10 : 9 ?>" class="text-center muted"><?= htmlspecialchars(__('app.mail.table.empty')) ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
<?php


  $base=url('/mail'); $pages=$pages; $page=$page;
  $qs=$_GET; unset($qs['page']); if(!empty($qs)){ $base.='?'.http_build_query($qs); }
  include __DIR__.'/../components/pagination.php';
?>

<?php if($mailCapabilities['logs']): ?>
<!-- 日志 Modal -->
<div class="modal-backdrop mail-modal-hidden" id="modal-mail-log">
  <div class="modal-panel large">
    <header>
      <h3><?= htmlspecialchars(__('app.mail.log_modal.title')) ?></h3>
      <button class="modal-close" type="button" data-close>&times;</button>
    </header>
    <div class="modal-body">
  <div class="mail-log-controls">
        <label class="mail-log-select">
          <span class="muted small"><?= htmlspecialchars(__('app.mail.log_modal.type_label')) ?></span>
          <select id="mailLogType">
            <option value="sql"><?= htmlspecialchars(__('app.mail.log_modal.types.sql')) ?></option>
            <option value="deleted"><?= htmlspecialchars(__('app.mail.log_modal.types.deleted')) ?></option>
          </select>
        </label>
        <label class="mail-log-select">
          <span class="muted small"><?= htmlspecialchars(__('app.mail.log_modal.limit_label')) ?></span>
          <select id="mailLogLimit">
            <option value="50"><?= htmlspecialchars(__('app.mail.log_modal.limits.recent', ['count'=>50])) ?></option>
            <option value="100"><?= htmlspecialchars(__('app.mail.log_modal.limits.recent', ['count'=>100])) ?></option>
            <option value="200"><?= htmlspecialchars(__('app.mail.log_modal.limits.recent', ['count'=>200])) ?></option>
          </select>
        </label>
        <button class="btn outline" type="button" id="btn-refresh-mail-log"><?= htmlspecialchars(__('app.mail.log_modal.refresh')) ?></button>
        <div class="flex-1"></div>
        <div class="muted small" id="mailLogMeta"></div>
      </div>
      <pre id="mailLogBox" class="mail-log-box"><?= htmlspecialchars(__('app.mail.log_modal.empty')) ?></pre>
    </div>
    <footer><button class="btn" type="button" data-close><?= htmlspecialchars(__('app.mail.log_modal.close')) ?></button></footer>
  </div>
</div>
<?php endif; ?>

<?php if($mailCapabilities['view']): ?>
<!-- 详情 Modal -->
<div class="modal-backdrop mail-modal-hidden" id="modal-mail-detail">
  <div class="modal-panel">
    <header>
      <h3><?= htmlspecialchars(__('app.mail.detail.title')) ?> <span id="mdMailId" class="status-badge" hidden></span></h3>
      <button class="modal-close" type="button" data-close>&times;</button>
    </header>
    <div class="modal-body" id="mailDetailBody">
      <div class="mail-detail-loading"><?= htmlspecialchars(__('app.mail.detail.loading')) ?></div>
      <div class="mail-detail-content mail-detail-content--hidden">
        <div class="mail-meta">
          <div class="kv"><label><?= htmlspecialchars(__('app.mail.detail.labels.sender')) ?></label><span id="mdSender"></span></div>
          <div class="kv"><label><?= htmlspecialchars(__('app.mail.detail.labels.receiver')) ?></label><span id="mdReceiver"></span></div>
          <div class="kv"><label><?= htmlspecialchars(__('app.mail.detail.labels.money')) ?></label><span id="mdMoney"></span></div>
          <div class="kv"><label><?= htmlspecialchars(__('app.mail.detail.labels.expire')) ?></label><span id="mdExpire"></span></div>
          <div class="kv"><label><?= htmlspecialchars(__('app.mail.detail.labels.status')) ?></label><span id="mdStatus"></span></div>
          <div class="kv"><label><?= htmlspecialchars(__('app.mail.detail.labels.attachment_count')) ?></label><span id="mdItemCount"></span></div>
        </div>
  <div class="subject-box"><strong><?= htmlspecialchars(__('app.mail.detail.labels.subject')) ?></strong> <span id="mdSubject"></span></div>
        <div class="body-box" id="mdBody"></div>
        <div class="attachments-wrap">
          <strong><?= htmlspecialchars(__('app.mail.detail.labels.attachments')) ?></strong>
          <div id="mdItems" class="attachments-list"></div>
        </div>
      </div>
    </div>
    <footer><button class="btn" type="button" data-close><?= htmlspecialchars(__('app.mail.detail.close')) ?></button></footer>
  </div>
</div>
<?php endif; ?>

<script type="application/json" data-panel-json data-global="MAIL_FILTERS"><?= json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" data-panel-json data-global="MAIL_STATE"><?= json_encode([
  'sort' => $sort,
  'dir' => $dir,
  'limit' => (int)$limit,
  'page' => (int)$page,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
