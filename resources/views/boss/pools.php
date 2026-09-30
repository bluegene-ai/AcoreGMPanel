<?php
/**
 * File: resources/views/boss/pools.php
 * Purpose: 奖池页（/boss/pools）外壳：页头 + 反馈区 + 奖池主体（见 _reward_pools.php）。
 */

$poolWritable = is_array($__pageCapabilities ?? null)
    ? !empty($__pageCapabilities['pools_write'])
    : false;
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>

<div class="boss-page">
  <div id="bossFeedback" class="panel-flash panel-flash--inline" hidden></div>

  <?php if (!$poolWritable): ?>
    <section class="boss-panel">
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
        <?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.boss.pools.page_title')])) ?>
      </div>
    </section>
  <?php endif; ?>

  <?php include __DIR__ . '/_reward_pools.php'; ?>
</div>
