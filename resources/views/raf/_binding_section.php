<?php
/**
 * 绑定列表区块（可被 AJAX 整块替换）。
 *
 * 变量由 RafController::buildListViewData() 提供（含 $pager、筛选/排序参数、$rafCapabilities 等）。
 */

$bindingSectionSearch = trim((string) ($search ?? ''));
$bindingSectionRecruiter = (int) ($recruiter_guid ?? 0);
$bindingSectionStatus = trim((string) ($status ?? 'all')) ?: 'all';
$bindingSectionSort = trim((string) ($sort ?? 'time_stamp')) ?: 'time_stamp';
$bindingSectionDir = trim((string) ($dir ?? 'DESC')) ?: 'DESC';
$bindingSectionLimit = (int) ($limit ?? 30);
$bindingSectionOptions = is_array($rafDefaults['page_size_options'] ?? null)
    ? $rafDefaults['page_size_options']
    : [20, 30, 50, 100];
$bindingSectionPageSizes = [];
foreach ($bindingSectionOptions as $bindingSectionOption) {
    $bindingSectionOption = (int) $bindingSectionOption;
    if ($bindingSectionOption > 0) {
        $bindingSectionPageSizes[] = $bindingSectionOption;
    }
}
if ($bindingSectionPageSizes === []) {
    $bindingSectionPageSizes = [20, 30, 50, 100];
}
if (!in_array($bindingSectionLimit, $bindingSectionPageSizes, true)) {
    $bindingSectionPageSizes[] = $bindingSectionLimit;
    sort($bindingSectionPageSizes);
}

$bindingSectionSummary = __('app.raf.summary', [
    'total' => (string) ($pager->total ?? 0),
    'page' => (string) ($pager->page ?? 1),
    'pages' => (string) ($pager->pages ?? 1),
]);
// 错误提示放在区块内部：AJAX 刷新时提示才会跟着一起更新
$bindingSectionError = trim((string) ($raf_error ?? ''));
$bindingSectionActiveFilters = [];
if ($bindingSectionSearch !== '') {
    $bindingSectionActiveFilters[] = __('app.raf.filters.search') . '：' . $bindingSectionSearch;
}
if ($bindingSectionRecruiter > 0) {
    $bindingSectionActiveFilters[] = __('app.raf.filters.recruiter_guid') . '：' . $bindingSectionRecruiter;
}
if ($bindingSectionStatus !== 'all') {
    $bindingSectionActiveFilters[] = __('app.raf.filters.status') . '：'
        . __('app.raf.filters.status_values.' . $bindingSectionStatus);
}
?>
<section class="raf-section" id="rafBindingSection" data-raf-section="bindings">
  <header class="raf-section__head">
    <div class="raf-section__heading">
      <h2 class="raf-section__title"><?= htmlspecialchars(__('app.raf.table.title')) ?></h2>
      <p class="muted raf-panel__meta" data-raf-summary><?= htmlspecialchars($bindingSectionSummary) ?></p>
    </div>
    <div class="raf-section__actions">
      <?php if ($rafCapabilities['bind'] ?? false): ?>
        <button type="button" class="btn" id="rafBindBtn">
          <?= htmlspecialchars(__('app.raf.actions.bind')) ?>
        </button>
      <?php endif; ?>
      <button
        type="button"
        class="btn outline js-raf-toggle-filters"
        data-raf-target="#rafBindingFilters"
        aria-expanded="true"
        aria-controls="rafBindingFilters"
      ><?= htmlspecialchars(__('app.raf.filters.toggle_btn')) ?></button>
    </div>
  </header>

  <div class="raf-section__body" id="rafBindingFilters" data-raf-collapsible>
    <form class="raf-filter-grid" method="get" action="<?= htmlspecialchars(url('/raf')) ?>" data-raf-form="bindings">
      <input type="hidden" name="server" value="<?= (int) $current_server ?>">
      <input type="hidden" name="tab" value="bindings">

      <label class="raf-field raf-field--span-2">
        <span><?= htmlspecialchars(__('app.raf.filters.search')) ?></span>
        <input
          type="text"
          name="search"
          value="<?= htmlspecialchars($bindingSectionSearch) ?>"
          placeholder="<?= htmlspecialchars(__('app.raf.filters.search_placeholder')) ?>"
        >
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.recruiter_guid')) ?></span>
        <input type="number" min="1" name="recruiter_guid" value="<?= $bindingSectionRecruiter > 0 ? $bindingSectionRecruiter : '' ?>">
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.status')) ?></span>
        <select name="status">
          <?php foreach (['all', 'active', 'completed', 'inactive', 'permanent'] as $statusValue): ?>
            <option value="<?= htmlspecialchars($statusValue) ?>" <?= $statusValue === $bindingSectionStatus ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.raf.filters.status_values.' . $statusValue)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.sort')) ?></span>
        <select name="sort">
          <?php foreach (['time_stamp', 'account_id', 'recruiter_guid', 'ip_abuse_counter', 'kick_counter', 'reward_level'] as $sortValue): ?>
            <option value="<?= htmlspecialchars($sortValue) ?>" <?= $sortValue === $bindingSectionSort ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.raf.sorts.' . $sortValue)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.direction')) ?></span>
        <select name="dir">
          <?php foreach (['DESC', 'ASC'] as $directionValue): ?>
            <option value="<?= $directionValue ?>" <?= strtoupper($bindingSectionDir) === $directionValue ? 'selected' : '' ?>>
              <?= htmlspecialchars(__('app.raf.directions.' . $directionValue)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="raf-field">
        <span><?= htmlspecialchars(__('app.raf.filters.limit')) ?></span>
        <select name="limit">
          <?php foreach ($bindingSectionPageSizes as $bindingPageSize): ?>
            <option value="<?= (int) $bindingPageSize ?>" <?= (int) $bindingPageSize === $bindingSectionLimit ? 'selected' : '' ?>>
              <?= (int) $bindingPageSize ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="raf-filter-actions">
        <button class="btn" type="submit" data-raf-submit><?= htmlspecialchars(__('app.raf.filters.search_btn')) ?></button>
        <button class="btn outline" type="button" data-raf-reset><?= htmlspecialchars(__('app.raf.filters.clear_btn')) ?></button>
      </div>
    </form>

    <?php if ($bindingSectionActiveFilters !== []): ?>
      <p class="raf-filter-state">
        <span class="raf-filter-state__label"><?= htmlspecialchars(__('app.raf.filters.active')) ?></span>
        <?php foreach ($bindingSectionActiveFilters as $bindingActiveFilter): ?>
          <span class="raf-chip"><?= htmlspecialchars($bindingActiveFilter) ?></span>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="raf-section__body raf-section__body--flush" data-raf-results="bindings">
    <?php if ($bindingSectionError !== ''): ?>
      <div class="panel-flash panel-flash--error panel-flash--inline is-visible"><?= htmlspecialchars($bindingSectionError) ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/_binding_table.php'; ?>
  </div>
</section>
