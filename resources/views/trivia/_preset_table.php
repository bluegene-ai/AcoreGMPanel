<?php
/**
 * File: resources/views/trivia/_preset_table.php
 * Purpose: 奖励预设表格片段（首屏渲染 + AJAX 刷新共用）。
 */

$presets = is_array($trivia_presets ?? null) ? $trivia_presets : [];
$itemNames = is_array($trivia_item_names ?? null) ? $trivia_item_names : [];
$capabilities = is_array($triviaCapabilities ?? null)
    ? $triviaCapabilities
    : ($__pageCapabilities ?? ['manage' => false]);
$canManage = (bool) ($capabilities['manage'] ?? false);

$itemLabel = static function (int $entry) use ($itemNames): string {
    $name = trim((string) ($itemNames[$entry] ?? ''));

    return $name !== '' ? $name . ' (#' . $entry . ')' : '#' . $entry;
};
?>
<?php if ($presets === []): ?>
  <div class="tv-empty"><?= htmlspecialchars(__('app.trivia.presets.empty')) ?></div>
<?php else: ?>
  <div class="tv-table-wrap">
    <table class="tv-table">
      <thead>
        <tr>
          <th><?= htmlspecialchars(__('app.trivia.fields.preset_name')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.fields.items_preview')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.fields.preset_money')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.fields.status')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($presets as $preset): ?>
          <?php
            $name = (string) ($preset['name'] ?? '');
            $itemText = [];
            foreach (preg_split('/[,;]+/', trim((string) ($preset['items'] ?? ''))) ?: [] as $chunk) {
                $chunk = trim((string) $chunk);
                if ($chunk === '') {
                    continue;
                }
                if (preg_match('/^(\d+)\s*[:xX*]\s*(\d+)$/', $chunk, $m)) {
                    $itemText[] = $itemLabel((int) $m[1]) . ' ×' . (int) $m[2];
                } elseif (preg_match('/^(\d+)$/', $chunk, $m)) {
                    $itemText[] = $itemLabel((int) $m[1]);
                }
            }
            $rowJson = json_encode([
                'name' => $name,
                'items' => (string) ($preset['items'] ?? ''),
                'money' => (int) ($preset['money'] ?? 0),
                'enabled' => (bool) ($preset['enabled'] ?? true),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
          ?>
        <tr class="<?= ($preset['enabled'] ?? true) ? '' : 'tv-row--disabled' ?>" data-tv-preset-row="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
          <td class="tv-mono"><?= htmlspecialchars($name) ?></td>
          <td>
            <?php if ($itemText === []): ?>
              <span class="tv-muted"><?= htmlspecialchars(__('app.trivia.fields.none')) ?></span>
            <?php else: ?>
              <?= htmlspecialchars(implode('、', $itemText)) ?>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars((int) ($preset['money'] ?? 0) > 0
                ? (function_exists('format_money_gsc') ? format_money_gsc((int) $preset['money']) : ((int) $preset['money'] . 'c'))
                : '-') ?></td>
          <td>
            <span class="tv-badge tv-badge--<?= ($preset['enabled'] ?? true) ? 'ok' : 'muted' ?>">
              <?= htmlspecialchars(($preset['enabled'] ?? true)
                  ? __('app.trivia.fields.status_enabled')
                  : __('app.trivia.fields.status_disabled')) ?>
            </span>
          </td>
          <td class="tv-actions">
            <?php if ($canManage): ?>
              <button type="button" class="btn ghost tv-btn-sm" data-tv-preset-edit
                      data-tv-preset='<?= htmlspecialchars((string) $rowJson, ENT_QUOTES, 'UTF-8') ?>'>
                <?= htmlspecialchars(__('app.trivia.actions.edit')) ?>
              </button>
              <button type="button" class="btn ghost tv-btn-sm danger" data-tv-preset-delete
                      data-tv-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                      data-tv-confirm="<?= htmlspecialchars(__('app.trivia.confirm.delete_preset', ['name' => $name]), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(__('app.trivia.actions.delete')) ?>
              </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
