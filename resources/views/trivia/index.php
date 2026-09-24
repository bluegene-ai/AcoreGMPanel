<?php
/**
 * File: resources/views/trivia/index.php
 * Purpose: 聊天答题（TriviaReward.lua）管理页：Tab 分页（运行状态 / 题库 / 奖励预设 / 答对排行 / 设置）。
 *
 * 单页原来的 5 块内容竖着堆在一起（屏幕要滚很久），这里改成 Tab；
 * 运行控制里的「暂停/恢复」「开启/关闭」各自合并成一个按钮，按实时状态切换文案。
 */

$capabilities = $__pageCapabilities ?? [
    'view' => $__can('trivia.view'),
    'control' => $__can('trivia.control'),
    'manage' => $__can('trivia.manage'),
];
$__pageCapabilities = $capabilities;

$canControl = (bool) ($capabilities['control'] ?? false);
$canManage = (bool) ($capabilities['manage'] ?? false);

$schema = is_array($trivia_schema ?? null) ? $trivia_schema : ['ready' => false, 'partial' => false, 'missing' => []];
$settings = is_array($trivia_settings ?? null) ? $trivia_settings : [];
$status = is_array($trivia_status ?? null) ? $trivia_status : ['available' => false, 'error' => '', 'data' => []];
$options = is_array($trivia_options ?? null) ? $trivia_options : [];
$questions = $trivia_questions ?? null;
$presets = is_array($trivia_presets ?? null) ? $trivia_presets : [];
$presetNames = is_array($trivia_preset_names ?? null) ? $trivia_preset_names : [];
$itemNames = is_array($trivia_preset_items ?? null) ? $trivia_preset_items : [];
$winners = $trivia_winners ?? null;
$winnerStats = is_array($trivia_winner_stats ?? null) ? $trivia_winner_stats : [];
$qStats = is_array($trivia_question_stats ?? null) ? $trivia_question_stats : [];
$questionRowCapabilities = ['view' => true, 'control' => $canControl, 'manage' => $canManage];

// 片段视图（也由控制器单独渲染）统一使用这几个变量名
$trivia_item_names = $itemNames;
$triviaCapabilities = $questionRowCapabilities;

$live = is_array($status['data'] ?? null) ? $status['data'] : [];
$liveAvailable = (bool) ($status['available'] ?? false);
$stateKey = $liveAvailable ? (string) ($live['state'] ?? 'idle') : 'offline';
$stateLabels = [
    'running' => __('app.trivia.status.states.running'),
    'idle' => __('app.trivia.status.states.idle'),
    'paused' => __('app.trivia.status.states.paused'),
    'disabled' => __('app.trivia.status.states.disabled'),
    'offline' => __('app.trivia.status.states.offline'),
];

// 运行控制的初始文案：线上状态说了算，拿不到状态就按"未知/关闭"显示
$liveEnabled = $liveAvailable ? ((bool) ($live['enabled'] ?? true)) : false;
$livePaused = $liveAvailable ? ((bool) ($live['paused'] ?? false)) : false;
$powerAction = $liveEnabled ? 'disable' : 'enable';
$powerLabel = $liveEnabled ? __('app.trivia.actions.disable') : __('app.trivia.actions.enable');
$powerConfirm = $liveEnabled ? __('app.trivia.confirm.disable') : __('app.trivia.confirm.enable');
$pauseAction = $livePaused ? 'resume' : 'pause';
$pauseLabel = $livePaused ? __('app.trivia.actions.resume') : __('app.trivia.actions.pause');

// 定时计划（来自 Lua 的实时状态；面板设置里也能改）
$scheduleLive = [
    'enabled' => (bool) ($live['schedule_enabled'] ?? false),
    'valid' => (bool) ($live['schedule_valid'] ?? false),
    'active' => (bool) ($live['schedule_active'] ?? false),
    'windows' => (string) ($live['schedule_windows'] ?? ''),
    'next_text' => (string) ($live['schedule_next_change_text'] ?? ''),
];

if (!$liveAvailable) {
    $initialNext = '—';
} elseif ((bool) ($live['round_active'] ?? false)) {
    $initialNext = __('app.trivia.status.remaining', ['seconds' => (string) (int) ($live['remaining'] ?? 0)]);
} elseif (!$liveEnabled) {
    $initialNext = __('app.trivia.status.next_disabled');
} elseif ($livePaused) {
    $initialNext = __('app.trivia.status.next_paused');
} else {
    $initialNext = __('app.trivia.status.next_in', ['seconds' => (string) (int) ($live['next_in'] ?? 0)]);
}

// 为什么没出题：脚本把原因写在 wait_reason_text 里（在线人数不足 / 题库为空 / 已暂停 / 不在计划时段…）。
// 在此之前面板只有"下一题约 N 秒后"，四种情况长得一模一样，出问题只能人工逐项排查。
$initialWaitReason = trim((string) ($live['wait_reason_text'] ?? ''));
if ($initialWaitReason === '') {
    $initialWaitReason = '—';
}
// 在线人数旁边直接标出脚本要求的最低人数，省得再去设置页翻
$initialOnlineMin = $liveAvailable
    ? __('app.trivia.status.online_min', ['min' => (string) (int) ($live['min_players_online'] ?? 0)])
    : '';

$money = static function (int $copper): string {
    if ($copper <= 0) {
        return '-';
    }
    if (function_exists('format_money_gsc')) {
        return format_money_gsc($copper);
    }

    return $copper . 'c';
};

$itemLabel = static function (int $entry, array $names): string {
    $name = trim((string) ($names[$entry] ?? ''));

    return $name !== '' ? $name . ' (#' . $entry . ')' : '#' . $entry;
};

$presetItemsText = static function (string $raw, array $names) use ($itemLabel): string {
    $parts = [];
    foreach (preg_split('/[,;]+/', trim($raw)) ?: [] as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk === '') {
            continue;
        }
        if (preg_match('/^(\d+)\s*[:xX*]\s*(\d+)$/', $chunk, $m)) {
            $parts[] = $itemLabel((int) $m[1], $names) . ' ×' . (int) $m[2];
        } elseif (preg_match('/^(\d+)$/', $chunk, $m)) {
            $parts[] = $itemLabel((int) $m[1], $names);
        }
    }

    return $parts === [] ? '' : implode('、', $parts);
};

// Tab 顺序；「设置」只有管理权限才出现
$tabs = [
    'status' => __('app.trivia.sections.tabs.status'),
    'questions' => __('app.trivia.sections.tabs.questions'),
    'presets' => __('app.trivia.sections.tabs.presets'),
    'winners' => __('app.trivia.sections.tabs.winners'),
];
if ($canManage) {
    $tabs['settings'] = __('app.trivia.sections.tabs.settings');
}
?>
<?php include __DIR__ . '/../components/page_header.php'; ?>
<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<?php if (!$schema['ready']): ?>
  <div class="tv-notice tv-notice--error">
    <?= htmlspecialchars(__('app.trivia.errors.schema_missing', ['tables' => implode(', ', (array) ($schema['missing'] ?? []))])) ?>
  </div>
<?php endif; ?>

<?php if (($trivia_error ?? '') !== ''): ?>
  <div class="tv-notice tv-notice--warn"><?= htmlspecialchars((string) $trivia_error) ?></div>
<?php endif; ?>

<div class="tv-tabs-wrap" id="tvTabs">
  <nav class="tv-tabs" role="tablist" aria-label="<?= htmlspecialchars(__('app.trivia.sections.tabs.label')) ?>">
    <?php $first = true; foreach ($tabs as $tabKey => $tabLabel): ?>
      <button type="button" class="tv-tab<?= $first ? ' is-active' : '' ?>" role="tab"
              aria-selected="<?= $first ? 'true' : 'false' ?>" data-tv-tab="<?= htmlspecialchars($tabKey, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($tabLabel) ?>
        <?php if ($tabKey === 'status'): ?>
          <span class="tv-tab__badge tv-badge tv-badge--<?= htmlspecialchars($stateKey, ENT_QUOTES, 'UTF-8') ?>" data-tv-tabstate>
            <?= htmlspecialchars($stateLabels[$stateKey] ?? $stateKey) ?>
          </span>
        <?php endif; ?>
      </button>
    <?php $first = false; endforeach; ?>
  </nav>
</div>

<?php // ------------------------------------------------------------ Tab 1：运行状态 ?>

<section class="tv-tabpanel is-active" role="tabpanel" data-tv-tabpanel="status">
  <section class="tv-panel tv-panel--status" id="tvStatusPanel"
           data-tv-state="<?= htmlspecialchars($stateKey, ENT_QUOTES, 'UTF-8') ?>"
           data-tv-available="<?= $liveAvailable ? '1' : '0' ?>">
    <header class="tv-panel__head">
      <h3><?= htmlspecialchars(__('app.trivia.status.title')) ?></h3>
      <div class="tv-panel__head-actions">
        <span class="tv-badge tv-badge--<?= htmlspecialchars($stateKey, ENT_QUOTES, 'UTF-8') ?>" data-tv-field="state_label">
          <?= htmlspecialchars($stateLabels[$stateKey] ?? $stateKey) ?>
        </span>
        <label class="tv-autorefresh">
          <input type="checkbox" id="tvAutoRefresh" checked>
          <?= htmlspecialchars(__('app.trivia.status.auto_refresh')) ?>
        </label>
        <button type="button" class="btn ghost" id="tvRefreshStatus"><?= htmlspecialchars(__('app.trivia.actions.refresh')) ?></button>
      </div>
    </header>

    <?php if (!$liveAvailable): ?>
      <div class="tv-notice tv-notice--warn">
        <?= htmlspecialchars((string) ($status['error'] ?? __('app.trivia.warnings.soap_unreachable'))) ?>
        <?php if (trim((string) ($status['detail'] ?? '')) !== ''): ?>
          <div class="tv-muted tv-small"><?= htmlspecialchars(mb_substr((string) $status['detail'], 0, 300)) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <dl class="tv-facts">
      <div class="tv-facts__wide"><dt><?= htmlspecialchars(__('app.trivia.status.question')) ?></dt>
        <dd data-tv-field="question">
          <?php if ($liveAvailable && (string) ($live['question'] ?? '') !== ''): ?>
            <?= htmlspecialchars((string) $live['question']) ?>
            <span class="tv-muted" data-tv-field="answer">
              （<?= htmlspecialchars(__('app.trivia.status.answer')) ?>：<?= htmlspecialchars((string) ($live['answer_text'] ?? '')) ?>）
            </span>
            <span class="tv-muted" data-tv-field="remaining">
              · <?= htmlspecialchars(__('app.trivia.status.remaining', ['seconds' => (string) (int) ($live['remaining'] ?? 0)])) ?>
            </span>
          <?php else: ?>
            <span class="tv-muted" data-tv-field="question_empty"><?= htmlspecialchars(__('app.trivia.status.no_question')) ?></span>
          <?php endif; ?>
        </dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.next')) ?></dt>
        <dd data-tv-field="next"><?= htmlspecialchars($initialNext) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.wait_reason')) ?></dt>
        <dd data-tv-field="wait_reason"><?= htmlspecialchars($initialWaitReason) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.auto')) ?></dt>
        <dd data-tv-field="auto">
          <?php
          // 单独一列说明"自动出题"本身是开是关：出题中也能一眼看出暂停/关闭有没有生效
          if (!$liveAvailable) {
              $initialAuto = '-';
          } elseif (!$liveEnabled) {
              $initialAuto = __('app.trivia.status.auto_disabled');
          } elseif ($livePaused) {
              $initialAuto = __('app.trivia.status.auto_paused');
          } else {
              $initialAuto = __('app.trivia.status.auto_on');
          }
          ?>
          <?= htmlspecialchars($initialAuto) ?>
        </dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.bank')) ?></dt>
        <dd><span data-tv-field="bank"><?= (int) ($live['bank'] ?? 0) ?></span>
          <span class="tv-muted tv-small" data-tv-field="bank_detail">
            <?= htmlspecialchars(__('app.trivia.status.bank_detail', [
                'builtin' => (string) (int) ($live['builtin'] ?? 0),
                'custom' => (string) (int) ($live['custom'] ?? 0),
                'db' => (string) (int) ($live['from_db'] ?? 0),
            ])) ?>
          </span></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.online')) ?></dt>
        <dd><span data-tv-field="online"><?= (int) ($live['online'] ?? 0) ?></span>
          <span class="tv-muted tv-small" data-tv-field="online_min"><?= htmlspecialchars($initialOnlineMin) ?></span></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.rounds')) ?></dt>
        <dd data-tv-field="rounds"><?= (int) ($live['rounds'] ?? 0) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.sources')) ?></dt>
        <dd data-tv-field="sources"><?= htmlspecialchars((string) ($live['sources'] ?? '-')) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.labels')) ?></dt>
        <dd data-tv-field="labels"><?= htmlspecialchars((string) ($live['labels'] ?? '-')) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.interval')) ?></dt>
        <dd><span data-tv-field="interval"><?= (int) ($live['interval'] ?? 0) ?></span>s
          <span class="tv-muted tv-small">/ <?= htmlspecialchars(__('app.trivia.status.answer_seconds')) ?>
            <span data-tv-field="answer_seconds"><?= (int) ($live['answer_seconds'] ?? 0) ?></span>s</span></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.presets')) ?></dt>
        <dd data-tv-field="presets"><?= (int) ($live['presets'] ?? count($presets)) ?></dd></div>
      <div><dt><?= htmlspecialchars(__('app.trivia.status.database')) ?></dt>
        <dd><?= htmlspecialchars(($live['db'] ?? false)
              ? __('app.trivia.status.database_ok')
              : __('app.trivia.status.database_off')) ?>
          <span class="tv-muted tv-small"><?= htmlspecialchars((string) ($trivia_db_name ?? '')) ?></span></dd></div>
      <div class="tv-facts__wide"><dt><?= htmlspecialchars(__('app.trivia.status.schedule')) ?></dt>
        <dd>
          <span class="tv-badge tv-badge--<?= $scheduleLive['enabled'] ? ($scheduleLive['active'] ? 'running' : 'paused') : 'muted' ?>" data-tv-field="schedule_state">
            <?= htmlspecialchars($scheduleLive['enabled']
                ? ($scheduleLive['active'] ? __('app.trivia.schedule.state_active') : __('app.trivia.schedule.state_waiting'))
                : __('app.trivia.schedule.state_off')) ?>
          </span>
          <span class="tv-muted tv-small" data-tv-field="schedule_detail">
            <?php if ($scheduleLive['enabled'] && $scheduleLive['windows'] !== ''): ?>
              <?= htmlspecialchars($scheduleLive['windows']) ?>
              <?php if ($scheduleLive['next_text'] !== ''): ?>
                · <?= htmlspecialchars(__('app.trivia.schedule.next_change', ['time' => $scheduleLive['next_text']])) ?>
              <?php endif; ?>
            <?php elseif ($scheduleLive['enabled']): ?>
              <?= htmlspecialchars(__('app.trivia.schedule.no_windows')) ?>
            <?php else: ?>
              <?= htmlspecialchars(__('app.trivia.schedule.hint_off')) ?>
            <?php endif; ?>
          </span>
        </dd></div>
      <div class="tv-facts__wide"><dt><?= htmlspecialchars(__('app.trivia.actions.refresh')) ?></dt>
        <dd class="tv-muted tv-small" id="tvRefreshedAt"><?= htmlspecialchars(__('app.trivia.status.refreshed', ['time' => date('H:i:s')])) ?></dd></div>
    </dl>

    <footer class="tv-panel__actions">
      <?php if ($canControl): ?>
        <button type="button" class="btn" data-tv-action="start"><?= htmlspecialchars(__('app.trivia.actions.start')) ?></button>
        <span class="tv-inline">
          <input type="number" id="tvStartIndex" min="1" step="1" placeholder="<?= htmlspecialchars(__('app.trivia.actions.start_index'), ENT_QUOTES, 'UTF-8') ?>">
          <button type="button" class="btn outline" data-tv-action="start_index"><?= htmlspecialchars(__('app.trivia.actions.start_index')) ?></button>
        </span>
        <button type="button" class="btn outline" data-tv-action="stop"
                data-tv-confirm="<?= htmlspecialchars(__('app.trivia.confirm.stop'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('app.trivia.actions.stop')) ?></button>
        <?php // 「暂停 / 恢复」是一个按钮：按线上的 paused 状态决定文案与动作 ?>
        <button type="button" class="btn outline" id="tvPauseToggle"
                data-tv-action="<?= htmlspecialchars($pauseAction, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pauseLabel) ?></button>
        <button type="button" class="btn outline" data-tv-action="reload"><?= htmlspecialchars(__('app.trivia.actions.reload')) ?></button>
        <?php // 「开启 / 关闭」同理，按线上的 enabled 状态决定文案与动作 ?>
        <button type="button" class="btn outline<?= $liveEnabled ? ' danger' : '' ?>" id="tvPowerToggle"
                data-tv-action="<?= htmlspecialchars($powerAction, ENT_QUOTES, 'UTF-8') ?>"
                data-tv-confirm="<?= htmlspecialchars($powerConfirm, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($powerLabel) ?></button>
        <p class="tv-hint tv-small" id="tvScheduleWarn"<?= $scheduleLive['enabled'] ? '' : ' hidden' ?>>
          <?= htmlspecialchars(__('app.trivia.warnings.schedule_manual_override')) ?>
        </p>
      <?php endif; ?>
    </footer>
  </section>
</section>

<?php // ------------------------------------------------------------ Tab 2：题库 ?>

<section class="tv-tabpanel" role="tabpanel" data-tv-tabpanel="questions">
  <section class="tv-panel" id="tvQuestionsPanel">
    <header class="tv-panel__head">
      <h3><?= htmlspecialchars(__('app.trivia.sections.questions')) ?></h3>
      <div class="tv-panel__head-actions">
        <span class="tv-muted tv-small" data-tv-qstats>
          <?= htmlspecialchars(__('app.trivia.questions.stats', [
              'enabled' => (string) (int) ($qStats['enabled'] ?? 0),
              'disabled' => (string) (int) ($qStats['disabled'] ?? 0),
              'reward' => (string) (int) ($qStats['with_reward'] ?? 0),
          ])) ?>
        </span>
        <?php if ($canManage): ?>
          <button type="button" class="btn" id="tvNewQuestion"><?= htmlspecialchars(__('app.trivia.actions.new_question')) ?></button>
        <?php endif; ?>
      </div>
    </header>

    <?php // 编辑表单紧跟面板标题：点「编辑」时把面板标题滚到视口顶部，标题与表单头部（取消）都在视野内 ?>
    <?php if ($canManage): ?>
      <form id="tvQuestionForm" class="tv-form tv-form--editor" hidden>
        <header class="tv-editor__head">
          <h4 id="tvQuestionFormTitle"><?= htmlspecialchars(__('app.trivia.actions.new_question')) ?></h4>
          <button type="button" class="btn ghost" id="tvQuestionCancel"><?= htmlspecialchars(__('app.trivia.actions.cancel')) ?></button>
        </header>
        <input type="hidden" name="id" value="0">
        <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.question')) ?></span>
          <input type="text" name="question" maxlength="255" required></label>
        <div class="tv-grid tv-grid--two">
          <?php foreach ([1, 2, 3, 4] as $index): ?>
            <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.option', ['index' => (string) $index])) ?></span>
              <input type="text" name="option<?= $index ?>" maxlength="120" <?= $index <= 2 ? 'required' : '' ?>></label>
          <?php endforeach; ?>
        </div>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.questions.answer_hint')) ?></p>
        <div class="tv-grid">
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.answer_index')) ?></span>
            <select name="answer_index">
              <?php foreach ([1, 2, 3, 4] as $index): ?>
                <option value="<?= $index ?>"><?= $index ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.labels')) ?></span>
            <input type="text" name="labels" placeholder="甲,乙,丙,丁"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.sort_order')) ?></span>
            <input type="number" name="sort_order" value="0"></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" checked>
            <span><?= htmlspecialchars(__('app.trivia.fields.question_enabled')) ?></span></label>
        </div>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.questions.reward_hint')) ?></p>
        <div class="tv-grid">
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.reward_preset')) ?></span>
            <select name="reward_preset">
              <option value=""><?= htmlspecialchars(__('app.trivia.fields.inherit_default')) ?></option>
              <?php foreach ($presetNames as $presetName): ?>
                <option value="<?= htmlspecialchars($presetName) ?>"><?= htmlspecialchars($presetName) ?></option>
              <?php endforeach; ?>
            </select></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.reward_items')) ?></span>
            <input type="text" name="reward_items" placeholder="33470:5,33447:2"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.reward_money')) ?></span>
            <input type="number" name="reward_money" min="0" value="0"></label>
        </div>
        <div class="tv-form__actions">
          <button type="button" class="btn ghost" id="tvQuestionCancelBottom"><?= htmlspecialchars(__('app.trivia.actions.back_to_list')) ?></button>
          <button type="submit" class="btn" id="tvQuestionSave"><?= htmlspecialchars(__('app.trivia.actions.save')) ?></button>
        </div>
      </form>
    <?php endif; ?>

    <form class="tv-filters" id="tvQuestionFilters">
      <input type="search" name="search" placeholder="<?= htmlspecialchars(__('app.trivia.fields.search'), ENT_QUOTES, 'UTF-8') ?>"
             value="<?= htmlspecialchars((string) ($trivia_filters['search'] ?? '')) ?>">
      <select name="status">
        <?php $statusFilter = (string) ($trivia_filters['status'] ?? 'all'); ?>
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>><?= htmlspecialchars(__('app.trivia.fields.status_all')) ?></option>
        <option value="enabled" <?= $statusFilter === 'enabled' ? 'selected' : '' ?>><?= htmlspecialchars(__('app.trivia.fields.status_enabled')) ?></option>
        <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>><?= htmlspecialchars(__('app.trivia.fields.status_disabled')) ?></option>
      </select>
      <button type="submit" class="btn outline"><?= htmlspecialchars(__('app.trivia.actions.filter')) ?></button>
    </form>

    <div id="tvQuestionTable">
      <?php include __DIR__ . '/_question_table.php'; ?>
    </div>

    <?php if ($canManage): ?>
      <div class="tv-import" id="tvImportPanel">
        <header class="tv-import__head">
          <h4><?= htmlspecialchars(__('app.trivia.import.title')) ?></h4>
          <div class="tv-import__actions">
            <a class="btn ghost tv-btn-sm" href="<?= htmlspecialchars(url_with_server('/trivia/api/questions/template')) ?>">
              <?= htmlspecialchars(__('app.trivia.import.download_template')) ?>
            </a>
            <a class="btn ghost tv-btn-sm" href="<?= htmlspecialchars(url_with_server('/trivia/api/questions/export')) ?>">
              <?= htmlspecialchars(__('app.trivia.import.export')) ?>
            </a>
          </div>
        </header>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.import.hint')) ?></p>
        <div class="tv-import__row">
          <input type="file" id="tvImportFile" accept=".csv,.tsv,.txt,.json">
          <button type="button" class="btn outline" id="tvImportPreview"><?= htmlspecialchars(__('app.trivia.import.preview')) ?></button>
          <button type="button" class="btn" id="tvImportCommit" disabled><?= htmlspecialchars(__('app.trivia.import.commit')) ?></button>
          <button type="button" class="btn ghost tv-btn-sm" id="tvImportReset"><?= htmlspecialchars(__('app.trivia.actions.reset')) ?></button>
        </div>
        <textarea id="tvImportText" rows="6" spellcheck="false"
                  placeholder="<?= htmlspecialchars(__('app.trivia.import.placeholder'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
        <div id="tvImportResult" class="tv-import__result"></div>
      </div>
    <?php endif; ?>
  </section>
</section>

<?php // ------------------------------------------------------------ Tab 3：奖励预设 ?>

<section class="tv-tabpanel" role="tabpanel" data-tv-tabpanel="presets">
  <section class="tv-panel" id="tvPresetsPanel">
    <header class="tv-panel__head">
      <h3><?= htmlspecialchars(__('app.trivia.sections.presets')) ?></h3>
      <div class="tv-panel__head-actions">
        <?php if ($canManage): ?>
          <button type="button" class="btn" id="tvNewPreset"><?= htmlspecialchars(__('app.trivia.actions.new_preset')) ?></button>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($canManage): ?>
      <form id="tvPresetForm" class="tv-form tv-form--editor" hidden>
        <header class="tv-editor__head">
          <h4 id="tvPresetFormTitle"><?= htmlspecialchars(__('app.trivia.actions.new_preset')) ?></h4>
          <button type="button" class="btn ghost" id="tvPresetCancel"><?= htmlspecialchars(__('app.trivia.actions.cancel')) ?></button>
        </header>
        <input type="hidden" name="original_name" value="">
        <div class="tv-grid">
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.preset_name')) ?></span>
            <input type="text" name="name" maxlength="32" pattern="[a-z0-9_\-]{1,32}" required>
            <span class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.presets.name_hint')) ?></span></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.preset_items')) ?></span>
            <input type="text" name="items" placeholder="33470:5,33447:2"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.preset_money')) ?></span>
            <input type="number" name="money" min="0" value="0"></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" checked>
            <span><?= htmlspecialchars(__('app.trivia.fields.preset_enabled')) ?></span></label>
        </div>
        <div class="tv-form__actions">
          <button type="button" class="btn ghost" id="tvPresetCancelBottom"><?= htmlspecialchars(__('app.trivia.actions.back_to_list')) ?></button>
          <button type="submit" class="btn" id="tvPresetSave"><?= htmlspecialchars(__('app.trivia.actions.save')) ?></button>
        </div>
      </form>
    <?php endif; ?>

    <div id="tvPresetTable">
      <?php include __DIR__ . '/_preset_table.php'; ?>
    </div>
  </section>
</section>

<?php // ------------------------------------------------------------ Tab 4：答对排行 ?>

<section class="tv-tabpanel" role="tabpanel" data-tv-tabpanel="winners">
  <section class="tv-panel" id="tvWinnersPanel">
    <header class="tv-panel__head">
      <h3><?= htmlspecialchars(__('app.trivia.sections.winners')) ?></h3>
      <div class="tv-panel__head-actions">
        <span class="tv-muted tv-small" data-tv-wstats>
          <?= htmlspecialchars(__('app.trivia.winners.total', [
              'players' => (string) (int) ($winnerStats['players'] ?? 0),
              'wins' => (string) (int) ($winnerStats['wins'] ?? 0),
          ])) ?>
          · <?= htmlspecialchars(__('app.trivia.winners.money', ['money' => $money((int) ($winnerStats['money'] ?? 0))])) ?>
        </span>
        <?php if ($canManage): ?>
          <button type="button" class="btn outline danger" id="tvClearWinners"
                  data-tv-confirm="<?= htmlspecialchars(__('app.trivia.confirm.clear_winners'), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(__('app.trivia.actions.clear_winners')) ?>
          </button>
        <?php endif; ?>
      </div>
    </header>
    <div id="tvWinnerTable">
      <?php include __DIR__ . '/_winner_table.php'; ?>
    </div>
  </section>
</section>

<?php // ------------------------------------------------------------ Tab 5：设置 ?>

<?php if ($canManage): ?>
<section class="tv-tabpanel" role="tabpanel" data-tv-tabpanel="settings">
  <section class="tv-panel" id="tvSettingsPanel">
    <header class="tv-panel__head">
      <h3><?= htmlspecialchars(__('app.trivia.sections.settings')) ?></h3>
      <?php if (!($settings['exists'] ?? false)): ?>
        <span class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.warnings.not_installed')) ?></span>
      <?php endif; ?>
    </header>

    <form id="tvSettingsForm" class="tv-form" autocomplete="off">
      <fieldset class="tv-fieldset">
        <legend><?= htmlspecialchars(__('app.trivia.sections.rhythm')) ?></legend>
        <div class="tv-grid">
          <label class="tv-field tv-field--check">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" <?= ((int) ($settings['enabled'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.enabled')) ?></span>
          </label>
          <?php // 暂停状态也是持久化的（默认勾选 = 服务器重启后不自动出题） ?>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="paused" value="0">
            <input type="checkbox" name="paused" value="1" <?= ((int) ($settings['paused'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.paused')) ?></span>
          </label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.interval_seconds')) ?></span>
            <input type="number" name="interval_seconds" min="60" max="86400" value="<?= (int) ($settings['interval_seconds'] ?? 900) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.answer_seconds')) ?></span>
            <input type="number" name="answer_seconds" min="10" max="600" value="<?= (int) ($settings['answer_seconds'] ?? 60) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.remind_every_seconds')) ?></span>
            <input type="number" name="remind_every_seconds" min="0" max="600" value="<?= (int) ($settings['remind_every_seconds'] ?? 30) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.first_delay_seconds')) ?></span>
            <input type="number" name="first_delay_seconds" min="0" max="86400" value="<?= (int) ($settings['first_delay_seconds'] ?? 60) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.min_players_online')) ?></span>
            <input type="number" name="min_players_online" min="0" max="1000" value="<?= (int) ($settings['min_players_online'] ?? 1) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.idle_retry_seconds')) ?></span>
            <input type="number" name="idle_retry_seconds" min="1" max="300" value="<?= (int) ($settings['idle_retry_seconds'] ?? 5) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.resume_delay_seconds')) ?></span>
            <input type="number" name="resume_delay_seconds" min="1" max="600" value="<?= (int) ($settings['resume_delay_seconds'] ?? 5) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.min_level')) ?></span>
            <input type="number" name="min_level" min="1" max="255" value="<?= (int) ($settings['min_level'] ?? 1) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.min_gm_rank_for_command')) ?></span>
            <input type="number" name="min_gm_rank_for_command" min="0" max="4" value="<?= (int) ($settings['min_gm_rank_for_command'] ?? 2) ?>"></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="use_builtin_questions" value="0">
            <input type="checkbox" name="use_builtin_questions" value="1" <?= ((int) ($settings['use_builtin_questions'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.use_builtin_questions')) ?></span>
          </label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="debug_log" value="0">
            <input type="checkbox" name="debug_log" value="1" <?= ((int) ($settings['debug_log'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.debug_log')) ?></span>
          </label>
        </div>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.enabled_hint')) ?></p>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.min_players_hint')) ?></p>
      </fieldset>

      <fieldset class="tv-fieldset">
        <legend><?= htmlspecialchars(__('app.trivia.sections.schedule')) ?></legend>
        <div class="tv-grid">
          <label class="tv-field tv-field--check">
            <input type="hidden" name="schedule_enabled" value="0">
            <input type="checkbox" name="schedule_enabled" value="1" <?= ((int) ($settings['schedule_enabled'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.schedule_enabled')) ?></span>
          </label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.schedule_windows')) ?></span>
            <input type="text" name="schedule_windows" maxlength="255" list="tvScheduleSamples"
                   placeholder="08:00-09:00; 20:00-22:00"
                   value="<?= htmlspecialchars((string) ($settings['schedule_windows'] ?? '')) ?>"></label>
          <datalist id="tvScheduleSamples">
            <option value="08:00-09:00"><?= htmlspecialchars(__('app.trivia.schedule.sample_daily')) ?></option>
            <option value="08:00-09:00; 20:00-22:00"><?= htmlspecialchars(__('app.trivia.schedule.sample_twice')) ?></option>
            <option value="1-5@20:00-22:00"><?= htmlspecialchars(__('app.trivia.schedule.sample_weekday')) ?></option>
            <option value="6,7@10:00-12:00"><?= htmlspecialchars(__('app.trivia.schedule.sample_weekend')) ?></option>
          </datalist>
        </div>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.schedule_windows_hint')) ?></p>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.schedule.preview', [
            'windows' => implode('，', (array) ($settings['schedule_windows_list'] ?? [])) ?: __('app.trivia.fields.none'),
        ])) ?></p>
      </fieldset>

      <fieldset class="tv-fieldset">
        <legend><?= htmlspecialchars(__('app.trivia.sections.channels')) ?></legend>
        <div class="tv-grid">
          <label class="tv-field tv-field--check">
            <input type="hidden" name="answer_say" value="0">
            <input type="checkbox" name="answer_say" value="1" <?= ((int) ($settings['answer_say'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.answer_say')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="answer_yell" value="0">
            <input type="checkbox" name="answer_yell" value="1" <?= ((int) ($settings['answer_yell'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.answer_yell')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="answer_emote" value="0">
            <input type="checkbox" name="answer_emote" value="1" <?= ((int) ($settings['answer_emote'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.answer_emote')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="answer_whisper" value="0">
            <input type="checkbox" name="answer_whisper" value="1" <?= ((int) ($settings['answer_whisper'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.answer_whisper')) ?></span></label>
        </div>

        <div class="tv-field tv-field--wide">
          <span><?= htmlspecialchars(__('app.trivia.fields.answer_channel_ids')) ?></span>
          <div class="tv-checklist" data-tv-channels>
            <?php $selectedChannels = is_array($settings['channel_ids_list'] ?? null) ? $settings['channel_ids_list'] : []; ?>
            <?php foreach ((array) ($options['channels'] ?? []) as $channel): ?>
              <?php $channelId = (int) ($channel['id'] ?? 0); ?>
              <label class="tv-check">
                <input type="checkbox" name="tv_channel[]" value="<?= $channelId ?>" <?= in_array($channelId, $selectedChannels, true) ? 'checked' : '' ?>>
                <span><?= htmlspecialchars((string) ($channel['label'] ?? $channelId)) ?> <span class="tv-muted tv-small">#<?= $channelId ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
          <label class="tv-field">
            <span><?= htmlspecialchars(__('app.trivia.fields.answer_channel_custom')) ?></span>
            <input type="text" id="tvCustomChannels" placeholder="-5,-7"
                   value="<?= htmlspecialchars(implode(',', array_values(array_filter($selectedChannels, static fn(int $id): bool => $id < 0)))) ?>">
          </label>
          <input type="hidden" name="answer_channel_ids" id="tvChannelIds" value="<?= htmlspecialchars(implode(',', $selectedChannels)) ?>">
        </div>

        <div class="tv-grid">
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.answer_prefix')) ?></span>
            <input type="text" name="answer_prefix" maxlength="8" value="<?= htmlspecialchars((string) ($settings['answer_prefix'] ?? '')) ?>"></label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.answer_hint')) ?></span>
            <input type="text" name="answer_hint" maxlength="255" value="<?= htmlspecialchars((string) ($settings['answer_hint'] ?? '')) ?>"
                   placeholder="<?= htmlspecialchars(__('app.trivia.fields.answer_hint_placeholder'), ENT_QUOTES, 'UTF-8') ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.attempts_per_player')) ?></span>
            <input type="number" name="attempts_per_player" min="0" max="20" value="<?= (int) ($settings['attempts_per_player'] ?? 1) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.option_labels')) ?></span>
            <input type="text" name="option_labels" list="tvLabelPresets" value="<?= htmlspecialchars((string) ($settings['option_labels'] ?? 'A,B,C,D')) ?>"></label>
          <datalist id="tvLabelPresets">
            <?php foreach ((array) ($options['label_presets'] ?? []) as $preset): ?>
              <option value="<?= htmlspecialchars((string) ($preset['value'] ?? '')) ?>"><?= htmlspecialchars((string) ($preset['label'] ?? '')) ?></option>
            <?php endforeach; ?>
          </datalist>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.option_format')) ?></span>
            <input type="text" name="option_format" list="tvFormatPresets" value="<?= htmlspecialchars((string) ($settings['option_format'] ?? '%s) %s')) ?>"></label>
          <datalist id="tvFormatPresets">
            <?php foreach ((array) ($options['format_presets'] ?? []) as $preset): ?>
              <option value="<?= htmlspecialchars((string) ($preset['value'] ?? '')) ?>"><?= htmlspecialchars((string) ($preset['label'] ?? '')) ?></option>
            <?php endforeach; ?>
          </datalist>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="allow_number_answer" value="0">
            <input type="checkbox" name="allow_number_answer" value="1" <?= ((int) ($settings['allow_number_answer'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.allow_number_answer')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="allow_latin_letters" value="0">
            <input type="checkbox" name="allow_latin_letters" value="1" <?= ((int) ($settings['allow_latin_letters'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.allow_latin_letters')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="allow_loose_letter" value="0">
            <input type="checkbox" name="allow_loose_letter" value="1" <?= ((int) ($settings['allow_loose_letter'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.allow_loose_letter')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="allow_text_answer" value="0">
            <input type="checkbox" name="allow_text_answer" value="1" <?= ((int) ($settings['allow_text_answer'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.allow_text_answer')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="announce_on_login" value="0">
            <input type="checkbox" name="announce_on_login" value="1" <?= ((int) ($settings['announce_on_login'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.announce_on_login')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="reply_wrong_answer" value="0">
            <input type="checkbox" name="reply_wrong_answer" value="1" <?= ((int) ($settings['reply_wrong_answer'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.reply_wrong_answer')) ?></span></label>
          <label class="tv-field tv-field--check">
            <input type="hidden" name="reply_already_answered" value="0">
            <input type="checkbox" name="reply_already_answered" value="1" <?= ((int) ($settings['reply_already_answered'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.reply_already_answered')) ?></span></label>
        </div>
      </fieldset>

      <fieldset class="tv-fieldset">
        <legend><?= htmlspecialchars(__('app.trivia.sections.participation')) ?></legend>
        <div class="tv-grid">
          <label class="tv-field tv-field--check">
            <input type="hidden" name="ignore_gms" value="0">
            <input type="checkbox" name="ignore_gms" value="1" <?= ((int) ($settings['ignore_gms'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars(__('app.trivia.fields.ignore_gms')) ?></span></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.gm_rank_exempt')) ?></span>
            <input type="number" name="gm_rank_exempt" min="0" max="4" value="<?= (int) ($settings['gm_rank_exempt'] ?? 3) ?>"></label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.broadcast_prefix')) ?></span>
            <input type="text" name="broadcast_prefix" maxlength="32" value="<?= htmlspecialchars((string) ($settings['broadcast_prefix'] ?? '')) ?>"></label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.win_prefix')) ?></span>
            <input type="text" name="win_prefix" maxlength="32" value="<?= htmlspecialchars((string) ($settings['win_prefix'] ?? '')) ?>"></label>
        </div>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.prefix_hint')) ?></p>
        <p class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.gm_rank_exempt_hint')) ?></p>
      </fieldset>

      <fieldset class="tv-fieldset">
        <legend><?= htmlspecialchars(__('app.trivia.sections.rewards')) ?></legend>
        <div class="tv-grid">
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.reward_mode')) ?></span>
            <select name="reward_mode">
              <?php foreach ((array) ($options['reward_modes'] ?? []) as $mode): ?>
                <option value="<?= htmlspecialchars((string) $mode) ?>" <?= ((string) ($settings['reward_mode'] ?? 'question') === (string) $mode) ? 'selected' : '' ?>>
                  <?= htmlspecialchars(__('app.trivia.fields.reward_modes.' . $mode, [], (string) $mode)) ?>
                </option>
              <?php endforeach; ?>
            </select></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.default_reward_preset')) ?></span>
            <select name="default_reward_preset">
              <option value=""><?= htmlspecialchars(__('app.trivia.fields.inherit_default')) ?></option>
              <?php foreach ($presetNames as $presetName): ?>
                <option value="<?= htmlspecialchars($presetName) ?>" <?= ((string) ($settings['default_reward_preset'] ?? '') === $presetName) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($presetName) ?>
                </option>
              <?php endforeach; ?>
            </select></label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.pool_presets')) ?></span>
            <input type="text" name="pool_presets" value="<?= htmlspecialchars((string) ($settings['pool_presets'] ?? '')) ?>"
                   placeholder="<?= htmlspecialchars(implode(',', array_slice($presetNames, 0, 4)), ENT_QUOTES, 'UTF-8') ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.sender_guid')) ?></span>
            <input type="number" name="sender_guid" min="0" value="<?= (int) ($settings['sender_guid'] ?? 10667) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.mail_stationery')) ?></span>
            <input type="number" name="mail_stationery" min="0" max="255" value="<?= (int) ($settings['mail_stationery'] ?? 41) ?>"></label>
          <label class="tv-field"><span><?= htmlspecialchars(__('app.trivia.fields.item_link_locale')) ?></span>
            <input type="number" name="item_link_locale" min="0" max="8" value="<?= (int) ($settings['item_link_locale'] ?? 4) ?>"></label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.mail_subject')) ?></span>
            <input type="text" name="mail_subject" maxlength="128" value="<?= htmlspecialchars((string) ($settings['mail_subject'] ?? '')) ?>"></label>
          <label class="tv-field tv-field--wide"><span><?= htmlspecialchars(__('app.trivia.fields.mail_body')) ?></span>
            <textarea name="mail_body" rows="5"><?= htmlspecialchars((string) ($settings['mail_body'] ?? '')) ?></textarea>
            <span class="tv-muted tv-small"><?= htmlspecialchars(__('app.trivia.fields.mail_placeholders')) ?></span></label>
        </div>
      </fieldset>

      <div class="tv-form__actions">
        <button type="submit" class="btn" id="tvSaveSettings"><?= htmlspecialchars(__('app.trivia.actions.save_settings')) ?></button>
      </div>
    </form>
  </section>
</section>
<?php endif; ?>

<script type="application/json" data-panel-json data-global="TRIVIA_DATA"><?= json_encode([
    // root-relative：Panel.api 会自己补面板 base path
    'statusUrl' => '/trivia/api/status',
    'questionsUrl' => '/trivia/api/questions',
    'presetsUrl' => '/trivia/api/presets',
    'winnersUrl' => '/trivia/api/winners',
    'actionUrl' => '/trivia/api/action',
    'settingsUrl' => '/trivia/api/settings',
    'questionSaveUrl' => '/trivia/api/question/save',
    'questionDeleteUrl' => '/trivia/api/question/delete',
    'questionToggleUrl' => '/trivia/api/question/toggle',
    'presetSaveUrl' => '/trivia/api/preset/save',
    'presetDeleteUrl' => '/trivia/api/preset/delete',
    'winnersClearUrl' => '/trivia/api/winners/clear',
    'importUrl' => '/trivia/api/questions/import',
    'pollSeconds' => 10,
    'canControl' => $canControl,
    'canManage' => $canManage,
    'defaultTab' => 'status',
    'itemNames' => $itemNames,
    'presetNames' => $presetNames,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
