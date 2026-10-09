<?php
/**
 * File: resources/views/boss/_reward_pools.php
 * Purpose: 奖池页主体（ac_eluna.boss_reward_pools）：奖池表 + 新建/编辑/删除/重排
 * + 模拟一次击杀 + 跨区复制。
 *
 * 数据来自控制器传的 $boss_pools：available（表是否已建）/ rows（含软删除行）/
 * max_pool_id / next_pool_id / item_names（奖品 ID → 名称）/ tabs（跨区复制的分组清单）。
 *
 * 位号（pool_id）与序号（sort_order）是两件事，表格里并排展示：位号决定贡献快照
 * reward_pools_mask 的位，创建后不复用；序号决定发放与展示顺序。
 */

$poolData = is_array($boss_pools ?? null) ? $boss_pools : [];
$poolAvailable = ($poolData['available'] ?? false) === true;
$poolRows = is_array($poolData['rows'] ?? null) ? $poolData['rows'] : [];
$poolMaxId = (int) ($poolData['max_pool_id'] ?? 31);
$poolNextId = (int) ($poolData['next_pool_id'] ?? 0);
$poolItemNames = is_array($poolData['item_names'] ?? null) ? $poolData['item_names'] : [];
$poolTabs = is_array($poolData['tabs'] ?? null) ? $poolData['tabs'] : [];
$poolCanWrite = $__can('boss.pools.write');

/** 奖品文本 → 物品 ID 列表（去重，保持出现顺序） */
$poolItemIds = static function (string $text): array {
    preg_match_all('/\d+/', $text, $matches);
    $ids = [];
    foreach (($matches[0] ?? []) as $match) {
        $id = (int) $match;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_map('intval', array_keys($ids));
};

/** 铜 → 金币区间文案 */
$poolGoldText = static function (int $min, int $max): string {
    if ($max <= 0) {
        return __('app.boss.pools.gold_none');
    }

    return __('app.boss.pools.gold_range', [
        'min' => number_format($min / 10000, 2, '.', ''),
        'max' => number_format($max / 10000, 2, '.', ''),
    ]);
};

// 供 JS 回填编辑表单：只保留可编辑字段
$poolEditorRows = [];
foreach ($poolRows as $poolRow) {
    $poolEditorRows[(int) ($poolRow['pool_id'] ?? 0)] = [
        'pool_id' => (int) ($poolRow['pool_id'] ?? 0),
        'sort_order' => (int) ($poolRow['sort_order'] ?? 0),
        'name' => (string) ($poolRow['name'] ?? ''),
        'enabled' => (int) ($poolRow['enabled'] ?? 0),
        'chance' => (int) ($poolRow['chance'] ?? 0),
        'winner_mode' => (string) ($poolRow['winner_mode'] ?? 'count'),
        'winner_count' => (int) ($poolRow['winner_count'] ?? 1),
        'class_filter' => (int) ($poolRow['class_filter'] ?? 0),
        'items_text' => (string) ($poolRow['items_text'] ?? ''),
        'gold_min_copper' => (int) ($poolRow['gold_min_copper'] ?? 0),
        'gold_max_copper' => (int) ($poolRow['gold_max_copper'] ?? 0),
        'announce' => (int) ($poolRow['announce'] ?? 0),
        'deleted_at' => (int) ($poolRow['deleted_at'] ?? 0),
    ];
}
?>
<section class="boss-panel" data-boss-pools data-boss-pools-writable="<?= $poolCanWrite ? '1' : '0' ?>">
  <div class="boss-panel__head">
    <h2><?= htmlspecialchars(__('app.boss.pools.page_title')) ?></h2>
    <span class="boss-muted"><?= htmlspecialchars(__('app.boss.pools.limit_note', ['max' => (string) $poolMaxId])) ?></span>
  </div>
  <p class="muted boss-config-note"><?= htmlspecialchars(__('app.boss.pools.intro')) ?></p>

  <?php if (!$poolAvailable): ?>
    <div class="panel-flash panel-flash--info panel-flash--inline is-visible">
      <?= htmlspecialchars(__('app.boss.pools.unavailable')) ?>
    </div>
  <?php else: ?>
    <div class="boss-config-section__tools boss-pools-toolbar">
      <?php if ($poolCanWrite): ?>
        <button type="button" class="btn warn" data-boss-pool-create
                <?= $poolNextId === 0 ? 'disabled' : '' ?>>
          <?= htmlspecialchars(__('app.boss.pools.create')) ?>
        </button>
      <?php endif; ?>
      <button type="button" class="btn outline" data-boss-simulate>
        <?= htmlspecialchars(__('app.boss.pools.tools.simulate')) ?>
      </button>
      <button type="button" class="btn outline" data-boss-copy-open>
        <?= htmlspecialchars(__('app.boss.pools.tools.copy')) ?>
      </button>
    </div>

    <div class="boss-table-wrap">
      <table class="table boss-table boss-table--sticky">
        <thead>
          <tr>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.pool_id')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.sort_order')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.name')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.enabled')) ?></th>
            <th scope="col" class="boss-num"><?= htmlspecialchars(__('app.boss.pools.columns.chance')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.winner_mode')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.class_filter')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.items')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.gold')) ?></th>
            <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.announce')) ?></th>
            <?php if ($poolCanWrite): ?>
              <th scope="col"><?= htmlspecialchars(__('app.boss.pools.columns.actions')) ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($poolRows === []): ?>
            <tr>
              <td colspan="<?= $poolCanWrite ? 11 : 10 ?>" class="text-center muted">
                <?= htmlspecialchars(__('app.boss.pools.empty')) ?>
              </td>
            </tr>
          <?php endif; ?>
          <?php foreach ($poolRows as $poolIndex => $poolRow): ?>
            <?php
              $poolId = (int) ($poolRow['pool_id'] ?? 0);
              $poolDeleted = (int) ($poolRow['deleted_at'] ?? 0) > 0;
              $poolEnabled = (int) ($poolRow['enabled'] ?? 0) === 1;
              $poolMode = (string) ($poolRow['winner_mode'] ?? 'count') === 'all' ? 'all' : 'count';
              $poolItems = $poolItemIds((string) ($poolRow['items_text'] ?? ''));
              $poolPrevIds = array_values(array_filter(array_map(
                  static fn (array $row): int => (int) ($row['deleted_at'] ?? 0) > 0 ? 0 : (int) ($row['pool_id'] ?? 0),
                  array_slice($poolRows, 0, $poolIndex)
              )));
              $poolNextIds = array_values(array_filter(array_map(
                  static fn (array $row): int => (int) ($row['deleted_at'] ?? 0) > 0 ? 0 : (int) ($row['pool_id'] ?? 0),
                  array_slice($poolRows, $poolIndex + 1)
              )));
              $poolTitle = $poolDeleted
                  ? '#' . $poolId . __('app.boss.pools.deleted_suffix')
                  : (trim((string) ($poolRow['name'] ?? '')) !== '' ? (string) $poolRow['name'] : '#' . $poolId);
            ?>
            <tr class="<?= $poolDeleted ? 'boss-pool-row--deleted' : ($poolEnabled ? '' : 'boss-pool-row--off') ?>"
                data-boss-pool-row="<?= $poolId ?>">
              <td class="boss-num"><strong>#<?= $poolId ?></strong></td>
              <td class="boss-num"><?= (int) ($poolRow['sort_order'] ?? 0) ?></td>
              <td><span class="boss-cell-title"><?= htmlspecialchars($poolTitle) ?></span></td>
              <td><?= htmlspecialchars($poolEnabled ? __('app.boss.pools.state_on') : __('app.boss.pools.state_off')) ?></td>
              <td class="boss-num"><?= (int) ($poolRow['chance'] ?? 0) ?>%</td>
              <td>
                <?= htmlspecialchars($poolMode === 'all'
                    ? __('app.boss.ext.enum_options.all')
                    : __('app.boss.pools.winners_count', ['count' => (string) (int) ($poolRow['winner_count'] ?? 1)])) ?>
              </td>
              <td><?= htmlspecialchars((int) ($poolRow['class_filter'] ?? 0) === 1 ? __('app.boss.pools.state_on') : __('app.boss.pools.state_off')) ?></td>
              <td>
                <div class="boss-pool-items<?= count($poolItems) > 12 ? '' : ' is-open' ?>">
                  <span class="boss-field__unit" data-boss-pool-items-count>
                    <?= htmlspecialchars(__('app.boss.pools.items_count', ['count' => (string) count($poolItems)])) ?>
                  </span>
                  <?php if (count($poolItems) > 12): ?>
                    <button type="button" class="boss-chip" data-boss-pool-items-toggle>
                      <?= htmlspecialchars(__('app.boss.pools.items_toggle')) ?>
                    </button>
                  <?php endif; ?>
                  <div class="boss-pool-items__list">
                    <?php if ($poolItems === []): ?>
                      <span class="muted"><?= htmlspecialchars(__('app.boss.pools.no_items')) ?></span>
                    <?php else: ?>
                      <?php foreach ($poolItems as $poolItemId): $poolItemId = (int) $poolItemId; ?>
                        <span class="badge"<?= item_tooltip_attrs($poolItemId, false, null) ?>><?= htmlspecialchars($poolItemId . ' · ' . (string) ($poolItemNames[$poolItemId] ?? ('#' . $poolItemId))) ?></span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($poolGoldText((int) ($poolRow['gold_min_copper'] ?? 0), (int) ($poolRow['gold_max_copper'] ?? 0))) ?></td>
              <td><?= htmlspecialchars((int) ($poolRow['announce'] ?? 0) === 1 ? __('app.boss.pools.state_on') : __('app.boss.pools.state_off')) ?></td>
              <?php if ($poolCanWrite): ?>
                <td>
                  <?php if ($poolDeleted): ?>
                    <span class="muted"><?= htmlspecialchars(__('app.boss.pools.deleted_readonly')) ?></span>
                  <?php else: ?>
                    <div class="boss-pool-actions">
                      <button type="button" class="boss-chip" data-boss-pool-edit="<?= $poolId ?>">
                        <?= htmlspecialchars(__('app.boss.pools.edit')) ?>
                      </button>
                      <button type="button" class="boss-chip" data-boss-pool-up="<?= $poolId ?>"
                              <?= $poolPrevIds === [] ? 'disabled' : '' ?>>
                        <?= htmlspecialchars(__('app.boss.pools.up')) ?>
                      </button>
                      <button type="button" class="boss-chip" data-boss-pool-down="<?= $poolId ?>"
                              <?= $poolNextIds === [] ? 'disabled' : '' ?>>
                        <?= htmlspecialchars(__('app.boss.pools.down')) ?>
                      </button>
                      <button type="button" class="boss-chip" data-boss-pool-delete="<?= $poolId ?>">
                        <?= htmlspecialchars(__('app.boss.pools.delete')) ?>
                      </button>
                    </div>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($poolCanWrite): ?>
      <section class="boss-config-section boss-pool-editor" data-boss-pool-editor hidden>
        <div class="boss-config-section__head">
          <h3 data-boss-pool-editor-title><?= htmlspecialchars(__('app.boss.pools.create')) ?></h3>
          <span class="boss-config-section__count" data-boss-pool-editor-bit></span>
        </div>
        <div class="boss-config-columns">
          <label class="boss-field">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.name')) ?></span>
            <input type="text" maxlength="120" data-boss-pool-input="name">
          </label>

          <label class="boss-field">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.chance')) ?></span>
            <input type="number" min="0" max="100" step="1" data-boss-pool-input="chance">
          </label>

          <label class="boss-field">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.winner_mode')) ?></span>
            <select data-boss-pool-input="winner_mode" data-boss-pool-mode>
              <option value="all"><?= htmlspecialchars(__('app.boss.ext.enum_options.all')) ?></option>
              <option value="count"><?= htmlspecialchars(__('app.boss.ext.enum_options.count')) ?></option>
            </select>
          </label>

          <label class="boss-field" data-boss-pool-count-wrap>
            <span><?= htmlspecialchars(__('app.boss.pools.fields.winner_count')) ?></span>
            <input type="number" min="1" max="100" step="1" data-boss-pool-input="winner_count">
          </label>

          <label class="boss-field">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.gold_min_copper')) ?></span>
            <input type="number" min="0" step="1" data-boss-pool-input="gold_min_copper">
            <small class="muted"><?= htmlspecialchars(__('app.boss.pools.hints.gold_min_copper')) ?></small>
          </label>

          <label class="boss-field">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.gold_max_copper')) ?></span>
            <input type="number" min="0" step="1" data-boss-pool-input="gold_max_copper">
            <small class="muted"><?= htmlspecialchars(__('app.boss.pools.hints.gold_max_copper')) ?></small>
          </label>

          <label class="boss-check">
            <input type="checkbox" data-boss-pool-input="enabled">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.enabled')) ?></span>
            <small class="muted boss-check__hint"><?= htmlspecialchars(__('app.boss.pools.hints.enabled')) ?></small>
          </label>

          <label class="boss-check">
            <input type="checkbox" data-boss-pool-input="class_filter">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.class_filter')) ?></span>
            <small class="muted boss-check__hint"><?= htmlspecialchars(__('app.boss.pools.hints.class_filter')) ?></small>
          </label>

          <label class="boss-check">
            <input type="checkbox" data-boss-pool-input="announce">
            <span><?= htmlspecialchars(__('app.boss.pools.fields.announce')) ?></span>
            <small class="muted boss-check__hint"><?= htmlspecialchars(__('app.boss.pools.hints.announce')) ?></small>
          </label>

          <div class="boss-field boss-field--full boss-pool-items is-open" data-boss-pool-items>
            <span>
              <?= htmlspecialchars(__('app.boss.pools.fields.items_text')) ?>
              <span class="boss-field__unit" data-boss-pool-items-count></span>
            </span>
            <textarea rows="4" data-boss-pool-input="items_text"
                      placeholder="<?= htmlspecialchars(__('app.boss.ext.placeholders.intlist')) ?>"></textarea>
            <small class="muted"><?= htmlspecialchars(__('app.boss.pools.hints.items_text')) ?></small>
            <div class="boss-pool-items__list" data-boss-pool-items-preview></div>
          </div>
        </div>

        <div class="boss-field--action">
          <button type="button" class="btn warn" data-boss-pool-save>
            <?= htmlspecialchars(__('app.boss.pools.save')) ?>
          </button>
          <button type="button" class="btn outline" data-boss-pool-cancel>
            <?= htmlspecialchars(__('app.boss.pools.cancel')) ?>
          </button>
          <small class="muted"><?= htmlspecialchars(__('app.boss.pools.reload_hint')) ?></small>
        </div>
      </section>
    <?php endif; ?>

    <section class="boss-legend">
      <div class="boss-config-section__head">
        <strong><?= htmlspecialchars(__('app.boss.pools.legend.title')) ?></strong>
      </div>
      <ul>
        <li><?= htmlspecialchars(__('app.boss.pools.legend.settlement')) ?></li>
        <li><?= htmlspecialchars(__('app.boss.pools.legend.winner_mode')) ?></li>
        <li><?= htmlspecialchars(__('app.boss.pools.legend.class_filter')) ?></li>
        <li><?= htmlspecialchars(__('app.boss.pools.legend.items')) ?></li>
        <li><?= htmlspecialchars(__('app.boss.pools.legend.gold')) ?></li>
        <li><?= htmlspecialchars(__('app.boss.pools.legend.pool_id')) ?></li>
      </ul>

      <div class="boss-simulate" data-boss-simulate-result hidden></div>

      <div class="boss-simulate" data-boss-copy-panel hidden>
        <div class="boss-config-columns">
          <label class="boss-field">
            <span><?= htmlspecialchars(__('app.boss.pools.tools.copy_target')) ?></span>
            <select data-boss-copy-target></select>
          </label>
          <label class="boss-check">
            <input type="checkbox" data-boss-copy-main value="1">
            <span><?= htmlspecialchars(__('app.boss.pools.tools.copy_main')) ?></span>
            <small class="muted boss-check__hint"><?= htmlspecialchars(__('app.boss.pools.tools.copy_main_hint')) ?></small>
          </label>
        </div>
        <div class="boss-check-list" data-boss-copy-groups>
          <label class="boss-check">
            <input type="checkbox" data-boss-copy-group="all" value="all" checked>
            <span><?= htmlspecialchars(__('app.boss.pools.tools.copy_all')) ?></span>
          </label>
          <?php foreach ($poolTabs as $poolTabKey => $poolTabGroups): ?>
            <label class="boss-check">
              <input type="checkbox" data-boss-copy-group="<?= htmlspecialchars((string) $poolTabKey, ENT_QUOTES, 'UTF-8') ?>"
                     value="<?= htmlspecialchars((string) $poolTabKey, ENT_QUOTES, 'UTF-8') ?>">
              <span><?= htmlspecialchars(__('app.boss.ext.tabs.' . $poolTabKey, [], (string) $poolTabKey)) ?></span>
            </label>
          <?php endforeach; ?>
          <label class="boss-check">
            <input type="checkbox" data-boss-copy-group="reward_pools" value="reward_pools">
            <span><?= htmlspecialchars(__('app.boss.pools.page_title')) ?></span>
          </label>
        </div>
        <div class="boss-field--action">
          <button type="button" class="btn warn" data-boss-copy-run>
            <?= htmlspecialchars(__('app.boss.pools.tools.copy_run')) ?>
          </button>
          <small class="muted"><?= htmlspecialchars(__('app.boss.pools.tools.copy_hint')) ?></small>
        </div>
        <div class="boss-simulate" data-boss-copy-result hidden></div>
      </div>
    </section>
  <?php endif; ?>
</section>

<script type="application/json" data-panel-json
        data-global="BOSS_POOLS_DATA"><?= json_encode([
            'rows' => $poolEditorRows,
            'item_names' => $poolItemNames,
            'max_pool_id' => $poolMaxId,
            'next_pool_id' => $poolNextId,
            'writable' => $poolCanWrite,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
