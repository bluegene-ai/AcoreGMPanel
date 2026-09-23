<?php
/**
 * File: resources/views/trivia/_import_preview.php
 * Purpose: 模板导入的解析结果片段（预览与错误诊断）。
 */

$import = is_array($trivia_import ?? null) ? $trivia_import : [];
$rows = is_array($import['preview'] ?? null) ? $import['preview'] : [];
$errors = is_array($import['errors'] ?? null) ? $import['errors'] : [];
$valid = (int) ($import['valid'] ?? 0);
$invalid = (int) ($import['invalid'] ?? 0);
$parsedTotal = (int) ($import['parsed'] ?? 0);
$format = (string) ($import['format'] ?? '');
?>
<div class="tv-import-result" data-tv-import-result>
  <div class="tv-import-summary">
    <span class="tv-badge tv-badge--<?= $valid > 0 ? 'ok' : 'muted' ?>">
      <?= htmlspecialchars(__('app.trivia.import.valid', ['count' => (string) $valid])) ?>
    </span>
    <?php if ($invalid > 0): ?>
      <span class="tv-badge tv-badge--error"><?= htmlspecialchars(__('app.trivia.import.invalid', ['count' => (string) $invalid])) ?></span>
    <?php endif; ?>
    <span class="tv-muted tv-small">
      <?= htmlspecialchars(__('app.trivia.import.summary', [
          'parsed' => (string) $parsedTotal,
          'format' => strtoupper($format),
      ])) ?>
    </span>
  </div>

  <?php if ($errors !== []): ?>
    <details class="tv-import-errors" open>
      <summary><?= htmlspecialchars(__('app.trivia.import.errors_title', ['count' => (string) count($errors)])) ?></summary>
      <ul>
        <?php foreach (array_slice($errors, 0, 50) as $error): ?>
          <li>
            <span class="tv-mono">#<?= (int) ($error['line'] ?? 0) ?></span>
            <?= htmlspecialchars((string) ($error['message'] ?? '')) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>

  <?php if ($rows !== []): ?>
    <div class="tv-table-wrap">
      <table class="tv-table tv-table--compact">
        <thead>
          <tr>
            <th>#</th>
            <th><?= htmlspecialchars(__('app.trivia.fields.question')) ?></th>
            <th><?= htmlspecialchars(__('app.trivia.fields.answer_index')) ?></th>
            <th><?= htmlspecialchars(__('app.trivia.fields.reward_preset')) ?></th>
            <th><?= htmlspecialchars(__('app.trivia.fields.status')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr class="<?= !empty($row['enabled']) ? '' : 'tv-row--disabled' ?>">
              <td class="tv-mono"><?= (int) ($row['line'] ?? 0) ?></td>
              <td>
                <div class="tv-question-text"><?= htmlspecialchars((string) ($row['question'] ?? '')) ?></div>
                <div class="tv-muted tv-small">
                  <?php
                    $parts = [];
                    foreach (($row['options'] ?? []) as $index => $option) {
                        $parts[] = ($index + 1) . '.' . (string) $option;
                    }
                    echo htmlspecialchars(implode('　', $parts));
                  ?>
                </div>
              </td>
              <td>
                <span class="tv-answer">
                  <?= (int) ($row['answer_index'] ?? 0) ?>. <?= htmlspecialchars((string) ($row['answer'] ?? '')) ?>
                </span>
                <?php if ((string) ($row['labels'] ?? '') !== ''): ?>
                  <div class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.labels')) ?>：<?= htmlspecialchars((string) $row['labels']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $reward = [];
                  if ((string) ($row['reward_preset'] ?? '') !== '') {
                      $reward[] = (string) $row['reward_preset'];
                  }
                  if ((string) ($row['reward_items'] ?? '') !== '') {
                      $reward[] = (string) $row['reward_items'];
                  }
                  if ((int) ($row['reward_money'] ?? 0) > 0) {
                      $reward[] = (string) (int) $row['reward_money'] . 'c';
                  }
                  echo $reward === []
                      ? '<span class="tv-muted">' . htmlspecialchars(__('app.trivia.fields.inherit_default')) . '</span>'
                      : htmlspecialchars(implode(' + ', $reward));
                ?>
              </td>
              <td>
                <span class="tv-badge tv-badge--<?= !empty($row['enabled']) ? 'ok' : 'muted' ?>">
                  <?= htmlspecialchars(!empty($row['enabled'])
                      ? __('app.trivia.fields.status_enabled')
                      : __('app.trivia.fields.status_disabled')) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($import['preview_truncated'])): ?>
      <div class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.import.preview_truncated')) ?></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
