<?php
/**
 * File: resources/views/trivia/_winner_table.php
 * Purpose: 答对排行表格片段（首屏渲染 + AJAX 刷新共用）。
 */

$pager = $trivia_winners ?? null;
$items = is_array($pager->items ?? null) ? $pager->items : [];
$capabilities = is_array($triviaCapabilities ?? null)
    ? $triviaCapabilities
    : ($__pageCapabilities ?? ['manage' => false]);
$serverId = \Acme\Panel\Support\ServerContext::currentId();
?>
<?php if ($items === []): ?>
  <div class="tv-empty"><?= htmlspecialchars(__('app.trivia.winners.empty')) ?></div>
<?php else: ?>
  <div class="tv-table-wrap">
    <table class="tv-table">
      <thead>
        <tr>
          <th><?= htmlspecialchars(__('app.trivia.winners.columns.rank')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.winners.columns.name')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.winners.columns.wins')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.winners.columns.money')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.winners.columns.last_win_at')) ?></th>
          <th><?= htmlspecialchars(__('app.trivia.winners.columns.last_question')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $rank = ((int) ($pager->page ?? 1) - 1) * (int) ($pager->perPage ?? 30); ?>
        <?php foreach ($items as $winner): ?>
          <?php $rank++; ?>
        <tr>
          <td class="tv-mono"><?= $rank ?></td>
          <td>
            <?php if (function_exists('character_link')): ?>
              <?= character_link((int) ($winner['guid'] ?? 0), (string) ($winner['name'] ?? ''), $serverId) ?>
            <?php else: ?>
              <?= htmlspecialchars((string) ($winner['name'] ?? '')) ?>
            <?php endif; ?>
          </td>
          <td><?= (int) ($winner['wins'] ?? 0) ?></td>
          <td><?= htmlspecialchars((int) ($winner['total_money'] ?? 0) > 0
                ? (function_exists('format_money_gsc') ? format_money_gsc((int) $winner['total_money']) : ((int) $winner['total_money'] . 'c'))
                : '-') ?></td>
          <td class="tv-muted"><?= htmlspecialchars(format_datetime((int) ($winner['last_win_at'] ?? 0))) ?></td>
          <td class="tv-muted tv-small"><?= htmlspecialchars((string) ($winner['last_question'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pager->pages ?? 1) > 1): ?>
    <div class="tv-pager">
      <?php for ($page = 1; $page <= (int) $pager->pages; $page++): ?>
        <button type="button" class="btn ghost tv-btn-sm <?= $page === (int) $pager->page ? 'is-active' : '' ?>"
                data-tv-winner-page="<?= $page ?>"><?= $page ?></button>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
