<?php
/**
 * File: resources/views/character_boost/codes.php
 * Purpose: Admin tool UI to generate redeem codes for boost templates.
 *
 * 布局：上半部分"生成"（左表单 / 右结果），下半部分"管理"（统计卡 + 筛选条 + 明细表）。
 * 结果区默认收起，生成后自动展开，避免空文本框长期占位。
 */

use Acme\Panel\Support\Csrf;

$endpoint = url('/character-boost/api/redeem-codes/generate');
$endpointStats = url('/character-boost/api/redeem-codes/stats');
$endpointList = url('/character-boost/api/redeem-codes/list');
$endpointDeleteUnused = url('/character-boost/api/redeem-codes/delete-unused');
$endpointPurgeUnused = url('/character-boost/api/redeem-codes/purge-unused');
$realmId = (int) ($realm_id ?? 1);
$templates = is_array($templates ?? null) ? $templates : [];

include dirname(__DIR__) . '/components/page_header.php';
?>
<?php include dirname(__DIR__) . '/components/capability_notice.php'; ?>

<div class="cb-codes" id="boostCodesApp" data-realm="<?= $realmId ?>">
  <div id="boostCodesFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

  <div class="cb-codes__top">
    <!-- ===== 生成 ===== -->
    <section class="cb-card">
      <header class="cb-card__head">
        <h3 class="cb-card__title"><?= htmlspecialchars(__('app.character_boost.codes.generate.title')) ?></h3>
        <span class="cb-chip">
          <?= htmlspecialchars(__('app.character_boost.codes.fields.realm')) ?>
          <strong><?= $realmId ?></strong>
        </span>
      </header>

      <p class="cb-card__note"><?= htmlspecialchars(__('app.character_boost.codes.hint.realm_from_server')) ?></p>

      <form id="boostCodesForm" data-endpoint="<?= htmlspecialchars($endpoint) ?>">
        <?= Csrf::field() ?>

        <div class="cb-codes__grid">
          <label class="cb-field cb-field--span-2">
            <span><?= htmlspecialchars(__('app.character_boost.codes.fields.template')) ?></span>
            <select id="boostCodesTemplate" name="template_id" required>
              <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.fields.template_all')) ?></option>
              <?php foreach ($templates as $tpl): ?>
                <option value="<?= (int) ($tpl['id'] ?? 0) ?>">
                  <?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?> (Lv.<?= (int) ($tpl['target_level'] ?? 0) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="cb-field">
            <span><?= htmlspecialchars(__('app.character_boost.codes.fields.count')) ?></span>
            <input id="boostCodesCount" name="count" type="number" min="1" max="10000" value="10" required>
          </label>

          <div class="cb-field cb-field--quick">
            <span><?= htmlspecialchars(__('app.character_boost.codes.generate.quick')) ?></span>
            <div class="cb-quick-set" id="boostCodesQuickSet">
              <?php foreach ([10, 50, 100, 500] as $quick): ?>
                <button type="button" class="cb-quick" data-count="<?= $quick ?>"><?= $quick ?></button>
              <?php endforeach; ?>
            </div>
          </div>

          <label class="cb-check cb-field--span-2">
            <input type="checkbox" name="download" id="boostCodesDownload" value="1">
            <span><?= htmlspecialchars(__('app.character_boost.codes.fields.download')) ?></span>
          </label>
        </div>

        <p class="cb-help"><?= htmlspecialchars(__('app.character_boost.codes.hint.count_limit')) ?></p>

        <div class="cb-codes__actions">
          <button class="btn primary" type="submit" id="boostCodesSubmit">
            <?= htmlspecialchars(__('app.character_boost.codes.actions.generate')) ?>
          </button>
          <span class="cb-muted small"><?= htmlspecialchars(__('app.character_boost.codes.generate.safety')) ?></span>
        </div>
      </form>
    </section>

    <!-- ===== 生成结果（默认收起） ===== -->
    <section class="cb-card cb-codes__result" id="boostCodesResultPanel" hidden>
      <header class="cb-card__head">
        <h3 class="cb-card__title"><?= htmlspecialchars(__('app.character_boost.codes.generated.title')) ?></h3>
        <div class="cb-card__tools">
          <span class="cb-chip" id="boostCodesResultCount"></span>
          <button type="button" class="btn btn-sm outline" id="boostCodesCopy">
            <?= htmlspecialchars(__('app.character_boost.codes.generated.copy')) ?>
          </button>
          <button type="button" class="btn btn-sm outline" id="boostCodesResultCollapse">
            <?= htmlspecialchars(__('app.character_boost.codes.generated.collapse')) ?>
          </button>
        </div>
      </header>
      <p class="cb-card__note"><?= htmlspecialchars(__('app.character_boost.codes.generated.hint')) ?></p>
      <textarea id="boostCodesOutput" class="cb-output cb-output--result" readonly></textarea>
    </section>
  </div>

  <!-- ===== 管理 ===== -->
  <section class="cb-card">
    <header class="cb-card__head">
      <h3 class="cb-card__title"><?= htmlspecialchars(__('app.character_boost.codes.manage.title')) ?></h3>
      <div class="cb-card__tools">
        <button class="btn btn-sm" id="boostCodesManageRefresh" type="button">
          <?= htmlspecialchars(__('app.character_boost.codes.manage.actions.refresh')) ?>
        </button>
        <button class="btn btn-sm danger" id="boostCodesManagePurgeUnused" type="button">
          <?= htmlspecialchars(__('app.character_boost.codes.manage.actions.purge_unused')) ?>
        </button>
      </div>
    </header>

    <div id="boostCodesManageFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

    <form
      id="boostCodesManageForm"
      class="cb-codes__filters"
      data-endpoint-stats="<?= htmlspecialchars($endpointStats) ?>"
      data-endpoint-list="<?= htmlspecialchars($endpointList) ?>"
      data-endpoint-delete-unused="<?= htmlspecialchars($endpointDeleteUnused) ?>"
      data-endpoint-purge-unused="<?= htmlspecialchars($endpointPurgeUnused) ?>"
    >
      <?= Csrf::field() ?>

      <div class="cb-codes__filter-grid">
        <label class="cb-field">
          <span><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.template')) ?></span>
          <select id="boostCodesManageTemplate" name="template_id">
            <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.fields.template_all')) ?></option>
            <?php foreach ($templates as $tpl): ?>
              <option value="<?= (int) ($tpl['id'] ?? 0) ?>">
                <?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?> (Lv.<?= (int) ($tpl['target_level'] ?? 0) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="cb-field">
          <span><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.status')) ?></span>
          <select id="boostCodesManageStatus" name="status">
            <option value="all"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.status_all')) ?></option>
            <option value="unused"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.status_unused')) ?></option>
            <option value="used"><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.status_used')) ?></option>
          </select>
        </label>

        <label class="cb-field cb-field--grow">
          <span><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.search')) ?></span>
          <input type="search" id="boostCodesManageSearch" autocomplete="off" placeholder="<?= htmlspecialchars(__('app.character_boost.codes.manage.fields.search_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label class="cb-field">
          <span><?= htmlspecialchars(__('app.character_boost.codes.manage.fields.per_page')) ?></span>
          <select id="boostCodesManagePerPage">
            <?php foreach ([20, 50, 100, 200] as $size): ?>
              <option value="<?= $size ?>" <?= $size === 50 ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <!-- 状态筛选以 status 三态为准；unused_only 复选框仅作旧接口兼容保留 -->
      <input type="checkbox" id="boostCodesManageUnusedOnly" name="unused_only" value="1" hidden>
    </form>

    <div class="cb-stat-row">
      <div class="cb-stat-card">
        <span class="cb-stat-card__label"><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.total')) ?></span>
        <strong class="cb-stat-card__value" id="boostCodesStatTotal">-</strong>
      </div>
      <div class="cb-stat-card cb-stat-card--ok">
        <span class="cb-stat-card__label"><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.unused')) ?></span>
        <strong class="cb-stat-card__value" id="boostCodesStatUnused">-</strong>
        <span class="cb-stat-card__meta" id="boostCodesStatUnusedPct"></span>
      </div>
      <div class="cb-stat-card cb-stat-card--muted">
        <span class="cb-stat-card__label"><?= htmlspecialchars(__('app.character_boost.codes.manage.stats.used')) ?></span>
        <strong class="cb-stat-card__value" id="boostCodesStatUsed">-</strong>
        <span class="cb-stat-card__meta" id="boostCodesStatUsedPct"></span>
      </div>
    </div>

    <div class="cb-table-wrap">
      <table class="table table--compact cb-table-min cb-codes-table">
        <thead>
          <tr>
            <th class="cb-col-id">
              <a href="#" id="boostCodesSortId" class="cb-sort-link">
                <span><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.id')) ?></span>
                <span id="boostCodesSortIdIcon" class="cb-sort-icon"></span>
              </a>
            </th>
            <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.template')) ?></th>
            <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.code')) ?></th>
            <th class="cb-col-status"><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.status')) ?></th>
            <th><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.used_by')) ?></th>
            <th class="cb-col-time"><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.created_at')) ?></th>
            <th class="cb-col-act"><?= htmlspecialchars(__('app.character_boost.codes.manage.columns.actions')) ?></th>
          </tr>
        </thead>
        <tbody id="boostCodesManageTbody">
          <tr><td colspan="7" class="cb-empty-cell"><?= htmlspecialchars(__('app.common.loading')) ?></td></tr>
        </tbody>
      </table>
    </div>

    <div class="cb-codes__footer">
      <p class="cb-help cb-help--flush"><?= htmlspecialchars(__('app.character_boost.codes.manage.hint')) ?></p>
      <div class="cb-pager">
        <button class="btn btn-sm" id="boostCodesPagePrev" type="button">&larr;</button>
        <span id="boostCodesPageInfo" class="cb-toolbar-note"></span>
        <button class="btn btn-sm" id="boostCodesPageNext" type="button">&rarr;</button>
      </div>
    </div>
  </section>
</div>
