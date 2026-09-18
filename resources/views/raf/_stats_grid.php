<?php
/**
 * 招募管理顶部统计区：绑定概况 + 奖励发放概况。
 *
 * 每张卡片都可通过 data-raf-card / data-raf-card-group / data-raf-card-key
 * 交给 raf.js 打开下钻弹窗；数值节点带 data-raf-stat 由 AJAX 刷新时更新。
 */

$statsBindings = is_array($raf_stats ?? null) ? $raf_stats : [];
$statsRewardLog = is_array($raf_reward_log['stats'] ?? null) ? $raf_reward_log['stats'] : [];
$statsRewardReady = !empty($raf_reward_log['ready']);
$statsThreshold = (int) ($raf_permanent_block_threshold ?? 5);
$statsHasQuery = !empty($raf_stats_has_query);
$statsScopeHint = $statsHasQuery
    ? __('app.raf.stats.scope_filtered')
    : __('app.raf.stats.scope_all');
$statsGroupHint = static function (string $text) use ($statsHasQuery): string {
    return $statsHasQuery ? $text : __('app.raf.stats.scope_unfiltered_hint');
};

$bindingCards = [
    ['key' => 'total', 'label' => __('app.raf.stats.total'), 'value' => (int) ($statsBindings['total'] ?? 0), 'hint' => $statsGroupHint(__('app.raf.stats.hints.total'))],
    ['key' => 'active', 'label' => __('app.raf.stats.active'), 'value' => (int) ($statsBindings['active'] ?? 0), 'hint' => $statsGroupHint(__('app.raf.stats.hints.active'))],
    ['key' => 'completed', 'label' => __('app.raf.stats.completed'), 'value' => (int) ($statsBindings['completed'] ?? 0), 'hint' => $statsGroupHint(__('app.raf.stats.hints.completed'))],
    ['key' => 'inactive', 'label' => __('app.raf.stats.inactive'), 'value' => (int) ($statsBindings['inactive'] ?? 0), 'hint' => $statsGroupHint(__('app.raf.stats.hints.inactive', ['threshold' => (string) $statsThreshold]))],
    ['key' => 'permanent_blocked', 'label' => __('app.raf.stats.permanent_blocked'), 'value' => (int) ($statsBindings['permanent_blocked'] ?? 0), 'hint' => $statsGroupHint(__('app.raf.stats.hints.permanent_blocked', ['threshold' => (string) $statsThreshold]))],
    ['key' => 'rewarded_accounts', 'label' => __('app.raf.stats.rewarded_accounts'), 'value' => (int) ($statsBindings['rewarded_accounts'] ?? 0), 'hint' => $statsGroupHint(__('app.raf.stats.hints.rewarded_accounts'))],
];

$rewardLogCards = [
    ['key' => 'total', 'label' => __('app.raf.reward_log.stats.total'), 'value' => (int) ($statsRewardLog['total'] ?? 0), 'text' => null, 'hint' => $statsGroupHint(__('app.raf.reward_log.stats.hints.total'))],
    ['key' => 'recruiters', 'label' => __('app.raf.reward_log.stats.recruiters'), 'value' => (int) ($statsRewardLog['recruiters'] ?? 0), 'text' => null, 'hint' => $statsGroupHint(__('app.raf.reward_log.stats.hints.recruiters'))],
    ['key' => 'recruits', 'label' => __('app.raf.reward_log.stats.recruits'), 'value' => (int) ($statsRewardLog['recruits'] ?? 0), 'text' => null, 'hint' => $statsGroupHint(__('app.raf.reward_log.stats.hints.recruits'))],
    ['key' => 'default_rewards', 'label' => __('app.raf.reward_log.stats.default_rewards'), 'value' => (int) ($statsRewardLog['default_rewards'] ?? 0), 'text' => null, 'hint' => $statsGroupHint(__('app.raf.reward_log.stats.hints.default_rewards'))],
    [
        'key' => 'latest',
        'label' => __('app.raf.reward_log.stats.latest'),
        'value' => (int) ($statsRewardLog['latest_granted_at'] ?? 0),
        'text' => format_datetime((int) ($statsRewardLog['latest_granted_at'] ?? 0)),
        'hint' => $statsGroupHint(__('app.raf.reward_log.stats.hints.latest')),
    ],
];
?>
<section class="raf-stats" aria-label="<?= htmlspecialchars(__('app.raf.stats.section_title')) ?>">
  <header class="raf-stats__head">
    <h2 class="raf-stats__title"><?= htmlspecialchars(__('app.raf.stats.section_title')) ?></h2>
    <p class="raf-stats__hint"><?= htmlspecialchars($statsScopeHint) ?></p>
  </header>

  <div class="raf-stats__group" data-raf-stat-group="bindings">
    <div class="raf-stats__group-head">
      <h3 class="raf-stats__group-title"><?= htmlspecialchars(__('app.raf.stats.bindings_title')) ?></h3>
    </div>
    <div class="raf-stats__grid raf-stats__grid--bindings">
      <?php foreach ($bindingCards as $bindingCard): ?>
        <button
          type="button"
          class="raf-stat-card is-clickable"
          data-raf-card="open"
          data-raf-card-group="bindings"
          data-raf-card-key="<?= htmlspecialchars($bindingCard['key']) ?>"
          title="<?= htmlspecialchars(__('app.raf.stats.card_open_hint')) ?>"
        >
          <span class="raf-stat-card__label"><?= htmlspecialchars($bindingCard['label']) ?></span>
          <strong class="raf-stat-card__value" data-raf-stat="bindings.<?= htmlspecialchars($bindingCard['key']) ?>">
            <?= (int) $bindingCard['value'] ?>
          </strong>
          <span class="raf-stat-card__hint"><?= htmlspecialchars($bindingCard['hint']) ?></span>
          <span class="raf-stat-card__action"><?= htmlspecialchars(__('app.raf.stats.card_open')) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="raf-stats__group" data-raf-stat-group="reward-log">
    <div class="raf-stats__group-head">
      <h3 class="raf-stats__group-title"><?= htmlspecialchars(__('app.raf.reward_log.stats.title')) ?></h3>
      <?php if (!$statsRewardReady): ?>
        <span class="raf-stats__badge"><?= htmlspecialchars(__('app.raf.reward_log.stats.table_missing')) ?></span>
      <?php endif; ?>
    </div>
    <div class="raf-stats__grid raf-stats__grid--reward-log">
      <?php foreach ($rewardLogCards as $rewardLogCard): ?>
        <button
          type="button"
          class="raf-stat-card is-clickable <?= $rewardLogCard['text'] !== null ? 'raf-stat-card--time' : '' ?>"
          data-raf-card="open"
          data-raf-card-group="reward-log"
          data-raf-card-key="<?= htmlspecialchars($rewardLogCard['key']) ?>"
          <?= $statsRewardReady ? '' : 'disabled' ?>
          title="<?= htmlspecialchars(__('app.raf.stats.card_open_hint')) ?>"
        >
          <span class="raf-stat-card__label"><?= htmlspecialchars($rewardLogCard['label']) ?></span>
          <?php if ($rewardLogCard['text'] !== null): ?>
            <strong
              class="raf-stat-card__value raf-stat-card__value--time"
              data-raf-stat="reward-log.<?= htmlspecialchars($rewardLogCard['key']) ?>"
              data-raf-stat-format="time"
            >
              <?= htmlspecialchars((string) $rewardLogCard['text']) ?>
            </strong>
          <?php else: ?>
            <strong class="raf-stat-card__value" data-raf-stat="reward-log.<?= htmlspecialchars($rewardLogCard['key']) ?>">
              <?= (int) $rewardLogCard['value'] ?>
            </strong>
          <?php endif; ?>
          <span class="raf-stat-card__hint"><?= htmlspecialchars($rewardLogCard['hint']) ?></span>
          <span class="raf-stat-card__action"><?= htmlspecialchars(__('app.raf.stats.card_open')) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>
