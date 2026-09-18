<?php
/**
 * 奖励发放记录区块（可被 AJAX 整块替换）。
 *
 * 变量由 RafController::buildRewardLogViewData() 提供：$raf_reward_log。
 */

$rewardSection = is_array($raf_reward_log ?? null) ? $raf_reward_log : [];
$rewardSectionPager = is_object($rewardSection['pager'] ?? null) ? $rewardSection['pager'] : null;
$rewardSectionFilters = is_array($rewardSection['filters'] ?? null) ? $rewardSection['filters'] : [];
$rewardSectionError = trim((string) ($rewardSection['error'] ?? ''));
$rewardSectionReady = !empty($rewardSection['ready']);

$rewardSectionSearch = trim((string) ($rewardSectionFilters['search'] ?? ''));
$rewardSectionLevel = (int) ($rewardSectionFilters['level'] ?? 0);
$rewardSectionDefaultOnly = !empty($rewardSectionFilters['default_only']);
$rewardSectionSource = trim((string) ($rewardSectionFilters['source'] ?? ''));
$rewardSectionSort = trim((string) ($rewardSectionFilters['sort'] ?? 'granted_at')) ?: 'granted_at';
$rewardSectionDir = trim((string) ($rewardSectionFilters['dir'] ?? 'DESC')) ?: 'DESC';
$rewardSectionFrom = (int) ($rewardSectionFilters['from'] ?? 0);
$rewardSectionTo = (int) ($rewardSectionFilters['to'] ?? 0);
$rewardSectionLimit = (int) ($rewardSection['limit'] ?? 30);

$rewardSectionOptions = is_array($rafDefaults['page_size_options'] ?? null)
    ? $rafDefaults['page_size_options']
    : [20, 30, 50, 100];
$rewardSectionPageSizes = [];
foreach ($rewardSectionOptions as $rewardSectionOption) {
    $rewardSectionOption = (int) $rewardSectionOption;
    if ($rewardSectionOption > 0) {
        $rewardSectionPageSizes[] = $rewardSectionOption;
    }
}
if ($rewardSectionPageSizes === []) {
    $rewardSectionPageSizes = [20, 30, 50, 100];
}
if (!in_array($rewardSectionLimit, $rewardSectionPageSizes, true)) {
    $rewardSectionPageSizes[] = $rewardSectionLimit;
    sort($rewardSectionPageSizes);
}

$rewardSectionSourceLabels = [
    'login' => __('app.raf.reward_log.sources.login'),
    'level_change' => __('app.raf.reward_log.sources.level_change'),
];

$rewardSectionSummary = $rewardSectionReady
    ? __('app.raf.reward_log.summary', [
        'total' => (string) ($rewardSectionPager->total ?? 0),
        'page' => (string) ($rewardSectionPager->page ?? 1),
        'pages' => (string) ($rewardSectionPager->pages ?? 1),
    ])
    : __('app.raf.reward_log.stats.table_missing');

$rewardSectionActiveFilters = [];
if ($rewardSectionSearch !== '') {
    $rewardSectionActiveFilters[] = __('app.raf.reward_log.filters.search') . '：' . $rewardSectionSearch;
}
if ($rewardSectionLevel > 0) {
    $rewardSectionActiveFilters[] = __('app.raf.reward_log.filters.level') . '：' . $rewardSectionLevel;
}
if ($rewardSectionSource !== '') {
    $rewardSectionActiveFilters[] = __('app.raf.reward_log.filters.source') . '：'
        . ($rewardSectionSourceLabels[$rewardSectionSource] ?? $rewardSectionSource);
}
if ($rewardSectionFrom > 0) {
    $rewardSectionActiveFilters[] = __('app.raf.reward_log.filters.from') . '：' . date('Y-m-d', $rewardSectionFrom);
}
if ($rewardSectionTo > 0) {
    $rewardSectionActiveFilters[] = __('app.raf.reward_log.filters.to') . '：' . date('Y-m-d', $rewardSectionTo);
}
if ($rewardSectionDefaultOnly) {
    $rewardSectionActiveFilters[] = __('app.raf.reward_log.default_reward');
}
?>
<section class="raf-section" id="rafRewardLogSection" data-raf-section="reward-log">
  <header class="raf-section__head">
    <div class="raf-section__heading">
      <h2 class="raf-section__title"><?= htmlspecialchars(__('app.raf.reward_log.title')) ?></h2>
      <p class="muted raf-panel__meta" data-raf-summary><?= htmlspecialchars($rewardSectionSummary) ?></p>
      <p class="muted raf-section__note"><?= htmlspecialchars(__('app.raf.reward_log.subtitle')) ?></p>
    </div>
    <div class="raf-section__actions">
      <button
        type="button"
        class="btn outline js-raf-toggle-filters"
        data-raf-target="#rafRewardLogFilters"
        aria-expanded="<?= $rewardSectionActiveFilters === [] ? 'false' : 'true' ?>"
        aria-controls="rafRewardLogFilters"
      ><?= htmlspecialchars(__('app.raf.reward_log.filters.toggle_btn')) ?></button>
    </div>
  </header>

  <div
    class="raf-section__body"
    id="rafRewardLogFilters"
    data-raf-collapsible
    <?= $rewardSectionActiveFilters === [] ? 'hidden' : '' ?>
  >
    <?php if ($rewardSectionError !== ''): ?>
      <div class="panel-flash panel-flash--error panel-flash--inline is-visible"><?= htmlspecialchars($rewardSectionError) ?></div>
    <?php else: ?>
      <form class="raf-filter-grid" method="get" action="<?= htmlspecialchars(url('/raf')) ?>" data-raf-form="reward-log">
        <input type="hidden" name="server" value="<?= (int) $current_server ?>">
        <input type="hidden" name="tab" value="reward-log">

        <label class="raf-field raf-field--span-2">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.search')) ?></span>
          <input
            type="text"
            name="log_search"
            value="<?= htmlspecialchars($rewardSectionSearch) ?>"
            placeholder="<?= htmlspecialchars(__('app.raf.reward_log.filters.search_placeholder')) ?>"
          >
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.level')) ?></span>
          <input type="number" min="0" name="log_level" value="<?= $rewardSectionLevel > 0 ? $rewardSectionLevel : '' ?>">
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.source')) ?></span>
          <select name="log_source">
            <?php foreach (['', 'login', 'level_change'] as $sourceValue): ?>
              <option value="<?= htmlspecialchars($sourceValue) ?>" <?= $sourceValue === $rewardSectionSource ? 'selected' : '' ?>>
                <?= htmlspecialchars($sourceValue === '' ? __('app.raf.reward_log.sources.all') : ($rewardSectionSourceLabels[$sourceValue] ?? $sourceValue)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.default_only')) ?></span>
          <select name="log_default">
            <option value="0" <?= $rewardSectionDefaultOnly ? '' : 'selected' ?>><?= htmlspecialchars(__('app.raf.reward_log.filters.default_only_all')) ?></option>
            <option value="1" <?= $rewardSectionDefaultOnly ? 'selected' : '' ?>><?= htmlspecialchars(__('app.raf.reward_log.filters.default_only_yes')) ?></option>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.from')) ?></span>
          <input type="date" name="log_from" value="<?= $rewardSectionFrom > 0 ? htmlspecialchars(date('Y-m-d', $rewardSectionFrom)) : '' ?>">
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.to')) ?></span>
          <input type="date" name="log_to" value="<?= $rewardSectionTo > 0 ? htmlspecialchars(date('Y-m-d', $rewardSectionTo)) : '' ?>">
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.sort')) ?></span>
          <select name="log_sort">
            <?php foreach (['granted_at', 'reward_level', 'recruiter_guid', 'recruit_account_id'] as $logSortValue): ?>
              <option value="<?= htmlspecialchars($logSortValue) ?>" <?= $logSortValue === $rewardSectionSort ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.raf.reward_log.sorts.' . $logSortValue)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.direction')) ?></span>
          <select name="log_dir">
            <?php foreach (['DESC', 'ASC'] as $logDirection): ?>
              <option value="<?= $logDirection ?>" <?= strtoupper($rewardSectionDir) === $logDirection ? 'selected' : '' ?>>
                <?= htmlspecialchars(__('app.raf.directions.' . $logDirection)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="raf-field">
          <span><?= htmlspecialchars(__('app.raf.reward_log.filters.limit')) ?></span>
          <select name="log_limit">
            <?php foreach ($rewardSectionPageSizes as $rewardPageSize): ?>
              <option value="<?= (int) $rewardPageSize ?>" <?= (int) $rewardPageSize === $rewardSectionLimit ? 'selected' : '' ?>>
                <?= (int) $rewardPageSize ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <div class="raf-filter-actions">
          <button class="btn" type="submit" data-raf-submit><?= htmlspecialchars(__('app.raf.reward_log.filters.search_btn')) ?></button>
          <button class="btn outline" type="button" data-raf-reset><?= htmlspecialchars(__('app.raf.reward_log.filters.clear_btn')) ?></button>
        </div>
      </form>

      <?php if ($rewardSectionActiveFilters !== []): ?>
        <p class="raf-filter-state">
          <span class="raf-filter-state__label"><?= htmlspecialchars(__('app.raf.reward_log.filters.active')) ?></span>
          <?php foreach ($rewardSectionActiveFilters as $rewardActiveFilter): ?>
            <span class="raf-chip"><?= htmlspecialchars($rewardActiveFilter) ?></span>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="raf-section__body raf-section__body--flush" data-raf-results="reward-log">
    <?php
    // 表格片段与统计卡下钻共用，统一用 $pager / $raf_log_source_labels 驱动
    $pager = $rewardSectionPager;
    $raf_log_source_labels = $rewardSectionSourceLabels;
    include __DIR__ . '/_reward_log_table.php';
    ?>
  </div>
</section>
