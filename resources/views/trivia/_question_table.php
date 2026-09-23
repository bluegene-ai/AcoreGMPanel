<?php
/**
 * File: resources/views/trivia/_question_table.php
 * Purpose: 题库表格片段（首屏渲染 + AJAX 刷新共用）。
 */

$pager = $trivia_questions ?? null;
$items = is_array($pager->items ?? null) ? $pager->items : [];
$itemNames = is_array($trivia_item_names ?? null) ? $trivia_item_names : [];
$capabilities = is_array($triviaCapabilities ?? null)
    ? $triviaCapabilities
    : ($__pageCapabilities ?? ['manage' => false]);
$canManage = (bool) ($capabilities['manage'] ?? false);

$itemLabel = static function (int $entry) use ($itemNames): string {
    $name = trim((string) ($itemNames[$entry] ?? ''));

    return $name !== '' ? $name . ' (#' . $entry . ')' : '#' . $entry;
};

$rewardText = static function (array $question) use ($itemLabel): string {
    $parts = [];
    if ((string) ($question['reward_preset'] ?? '') !== '') {
        $parts[] = (string) $question['reward_preset'];
    }
    foreach (($question['reward_items_parsed'] ?? []) as $item) {
        $parts[] = $itemLabel((int) $item['entry']) . ' ×' . (int) $item['count'];
    }
    if ((int) ($question['reward_money'] ?? 0) > 0) {
        $parts[] = function_exists('format_money_gsc')
            ? format_money_gsc((int) $question['reward_money'])
            : ((int) $question['reward_money'] . 'c');
    }

    return $parts === [] ? '' : implode(' + ', $parts);
};
?>
<?php if ($items === []): ?>
  <div class="tv-empty"><?= htmlspecialchars(__('app.trivia.questions.empty')) ?></div>
<?php else: ?>
  <div class="tv-table-wrap">
    <table class="tv-table">
      <thead>
        <tr>
          <th>ID</th>
          <th><?= htmlspecialchars(__('app.trivia.fields.question')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.fields.answer_index')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.fields.reward_preset')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.fields.status')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $question): ?>
          <?php
            $questionId = (int) ($question['id'] ?? 0);
            $answerIndex = (int) ($question['answer_index'] ?? 1);
            $labels = [];
            if ((string) ($question['labels'] ?? '') !== '') {
                $labels = array_values(array_filter(array_map('trim', explode(',', (string) $question['labels'])), static fn($v) => $v !== ''));
            }
            $optionLabel = static function (int $index) use ($labels): string {
                return $labels[$index - 1] ?? (string) $index;
            };
            $rowJson = json_encode([
                'id' => $questionId,
                'question' => (string) ($question['question'] ?? ''),
                'option1' => (string) ($question['option1'] ?? ''),
                'option2' => (string) ($question['option2'] ?? ''),
                'option3' => (string) ($question['option3'] ?? ''),
                'option4' => (string) ($question['option4'] ?? ''),
                'answer_index' => $answerIndex,
                'labels' => implode(',', $labels),
                'reward_preset' => (string) ($question['reward_preset'] ?? ''),
                'reward_items' => (string) ($question['reward_items'] ?? ''),
                'reward_money' => (int) ($question['reward_money'] ?? 0),
                'enabled' => (bool) ($question['enabled'] ?? true),
                'sort_order' => (int) ($question['sort_order'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $reward = $rewardText($question);
        ?>
        <tr class="<?= ($question['enabled'] ?? true) ? '' : 'tv-row--disabled' ?>" data-tv-question-row="<?= $questionId ?>">
          <td class="tv-mono"><?= $questionId ?></td>
          <td>
            <div class="tv-question-text"><?= htmlspecialchars((string) ($question['question'] ?? '')) ?></div>
            <ol class="tv-options">
              <?php foreach ([1, 2, 3, 4] as $index): ?>
                <?php $text = (string) ($question['option' . $index] ?? ''); ?>
                <?php if ($text !== ''): ?>
                  <li class="<?= $index === $answerIndex ? 'is-answer' : '' ?>">
                    <span class="tv-option-label"><?= htmlspecialchars($optionLabel($index)) ?></span>
                    <?= htmlspecialchars($text) ?>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ol>
          </td>
          <td>
            <span class="tv-answer"><?= htmlspecialchars($optionLabel($answerIndex)) ?>.
              <?= htmlspecialchars((string) ($question['answer_text'] ?? '')) ?></span>
          </td>
          <td><?= $reward === '' ? '<span class="tv-muted">' . htmlspecialchars(__('app.trivia.fields.inherit_default')) . '</span>' : htmlspecialchars($reward) ?></td>
          <td>
            <span class="tv-badge tv-badge--<?= ($question['enabled'] ?? true) ? 'ok' : 'muted' ?>">
              <?= htmlspecialchars(($question['enabled'] ?? true)
                  ? __('app.trivia.fields.status_enabled')
                  : __('app.trivia.fields.status_disabled')) ?>
            </span>
            <span class="tv-muted tv-small">#<?= (int) ($question['sort_order'] ?? 0) ?></span>
          </td>
          <td class="tv-actions">
            <?php if ($canManage): ?>
              <button type="button" class="btn ghost tv-btn-sm" data-tv-question-edit
                      data-tv-question='<?= htmlspecialchars((string) $rowJson, ENT_QUOTES, 'UTF-8') ?>'>
                <?= htmlspecialchars(__('app.trivia.actions.edit')) ?>
              </button>
              <button type="button" class="btn ghost tv-btn-sm" data-tv-question-toggle
                      data-tv-id="<?= $questionId ?>" data-tv-enabled="<?= ($question['enabled'] ?? true) ? '0' : '1' ?>">
                <?= htmlspecialchars(($question['enabled'] ?? true)
                    ? __('app.trivia.actions.disable_row')
                    : __('app.trivia.actions.enable_row')) ?>
              </button>
              <button type="button" class="btn ghost tv-btn-sm danger" data-tv-question-delete
                      data-tv-id="<?= $questionId ?>"
                      data-tv-confirm="<?= htmlspecialchars(__('app.trivia.confirm.delete_question'), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(__('app.trivia.actions.delete')) ?>
              </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pager->pages ?? 1) > 1): ?>
    <div class="tv-pager">
      <span class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.questions.total', ['total' => (string) (int) $pager->total])) ?></span>
      <?php for ($page = 1; $page <= (int) $pager->pages; $page++): ?>
        <button type="button" class="btn ghost tv-btn-sm <?= $page === (int) $pager->page ? 'is-active' : '' ?>"
                data-tv-question-page="<?= $page ?>"><?= $page ?></button>
      <?php endfor; ?>
    </div>
  <?php else: ?>
    <div class="tv-pager">
      <span class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.questions.total', ['total' => (string) (int) ($pager->total ?? 0)])) ?></span>
    </div>
  <?php endif; ?>
<?php endif; ?>
