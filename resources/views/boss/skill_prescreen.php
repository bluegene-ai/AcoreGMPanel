<?php
/**
 * File: resources/views/boss/skill_prescreen.php
 * Purpose: 技能池预筛页（/boss/skill-prescreen）外壳：页头 + 反馈区 + 预筛主体（见 _skill_prescreen.php）。
 */

$prescreenWritable = is_array($__pageCapabilities ?? null)
    ? !empty($__pageCapabilities['pools_write'])
    : false;
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>

<div class="boss-page">
  <div id="bossFeedback" class="panel-flash panel-flash--inline" hidden></div>

  <?php if (!$prescreenWritable): ?>
    <section class="boss-panel">
      <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
        <?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.boss.prescreen.page_title')])) ?>
      </div>
    </section>
  <?php endif; ?>

  <?php include __DIR__ . '/_skill_prescreen.php'; ?>
</div>
