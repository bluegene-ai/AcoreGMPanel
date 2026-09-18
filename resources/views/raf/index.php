<?php
/**
 * 招募管理首页。
 *
 * 结构（自上而下）：
 *   1. 统一统计区（绑定概况 + 奖励发放概况），卡片可点击下钻查看明细；
 *   2. 绑定列表区块（筛选 + 表格 + 分页）；
 *   3. 奖励发放记录区块（筛选 + 表格 + 分页）。
 *
 * 两个区块的筛选表单都通过 AJAX 提交，只替换各自区块，不再整页刷新；
 * 顶部统计卡随筛选结果同步刷新。
 */

$rafPageCapabilities = is_array($__pageCapabilities ?? null)
    ? $__pageCapabilities
    : [
        'list' => $__can('raf.list'),
        'bind' => $__can('raf.bind'),
        'unbind' => $__can('raf.unbind'),
        'comment' => $__can('raf.comment'),
    ];
$__pageCapabilities = $rafPageCapabilities;
$capabilityNotice = $__canAll(['raf.bind', 'raf.unbind', 'raf.comment'])
    ? null
    : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<div class="raf-page">
  <div id="rafFeedback" class="panel-flash panel-flash--inline" hidden></div>

  <?php include __DIR__ . '/_stats_grid.php'; ?>

  <section class="raf-workspace">
    <header class="raf-workspace__head">
      <div>
        <h2 class="raf-workspace__title"><?= htmlspecialchars(__('app.raf.filters.title')) ?></h2>
        <p class="muted raf-panel__meta">
          <?= htmlspecialchars(__('app.raf.scope_note', [
              'server' => (string) ($rafDefaults['server_name'] ?? ''),
              'realm' => (string) ($rafDefaults['realm_id'] ?? 0),
          ])) ?>
        </p>
      </div>
    </header>

    <?php include __DIR__ . '/_binding_section.php'; ?>
    <?php include __DIR__ . '/_reward_log_section.php'; ?>
  </section>
</div>
