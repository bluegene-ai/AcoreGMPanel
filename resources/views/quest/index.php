<?php
/**
 * File: resources/views/quest/index.php
 * Purpose: Provides functionality for the resources/views/quest module.
 */

$module='quest';
include __DIR__.'/../layouts/base_top.php';



$questInfoMap = $questInfoOptions ?? [];
$serverParam = isset($_GET['server']) ? (int)$_GET['server'] : null;
$curSort = $filters['sort_by'] ?? 'ID';
$curDir = strtoupper($filters['sort_dir'] ?? 'ASC');
$toggleDir = $curDir === 'ASC' ? 'DESC' : 'ASC';
$buildSortUrl = function(string $col) use ($filters,$curSort,$toggleDir) {
        $params = $filters;
        $params['sort_by'] = $col;
        $params['sort_dir'] = ($curSort === $col) ? $toggleDir : 'ASC';
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        return url('/quest?'.http_build_query($params));
};
?>

<h1 class="page-title"><?= htmlspecialchars(__('app.quest.index.page_title')) ?></h1>
<div id="quest-feedback" class="panel-flash panel-flash--inline"></div>

<form method="get" action="" class="inline quest-filter-form" id="quest-filter-form">
    <?php if($serverParam !== null): ?><input type="hidden" name="server" value="<?= $serverParam ?>"><?php endif; ?>
    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($curSort) ?>">
    <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($curDir) ?>">
    <input type="text" name="filter_id" placeholder="<?= htmlspecialchars(__('app.quest.index.filters.id_placeholder')) ?>" value="<?= htmlspecialchars($filters['filter_id'] ?? '') ?>" style="width:120px">
    <input type="text" name="filter_title" placeholder="<?= htmlspecialchars(__('app.quest.index.filters.title_placeholder')) ?>" value="<?= htmlspecialchars($filters['filter_title'] ?? '') ?>" style="width:200px">
    <input type="text" name="filter_min_level_val" placeholder="<?= htmlspecialchars(__('app.quest.index.filters.min_level_placeholder')) ?>" value="<?= htmlspecialchars($filters['filter_min_level_val'] ?? '') ?>" style="width:110px">
    <input type="text" name="filter_level_val" placeholder="<?= htmlspecialchars(__('app.quest.index.filters.level_placeholder')) ?>" value="<?= htmlspecialchars($filters['filter_level_val'] ?? '') ?>" style="width:110px">
    <select name="filter_type" style="min-width:180px">
        <option value=""><?= htmlspecialchars(__('app.quest.index.filters.type_all')) ?></option>
        <?php foreach($questInfoMap as $val=>$label): $sel = ((string)$val === ($filters['filter_type'] ?? '')) ? 'selected' : ''; ?>
            <option value="<?= htmlspecialchars((string)$val) ?>" <?= $sel ?>><?= htmlspecialchars((string)$label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="number" name="limit" style="width:80px" value="<?= (int)($filters['limit'] ?? 50) ?>">
    <div class="filter-actions">
        <button class="btn info" type="submit"><?= htmlspecialchars(__('app.quest.index.filters.actions.search')) ?></button>
        <button class="btn outline" type="button" id="btn-filter-reset"><?= htmlspecialchars(__('app.quest.index.filters.actions.reset')) ?></button>
        <button class="btn success" type="button" id="btn-new-quest"><?= htmlspecialchars(__('app.quest.index.filters.actions.create')) ?></button>
        <button class="btn outline info" type="button" id="btn-quest-log"><?= htmlspecialchars(__('app.quest.index.filters.actions.log')) ?></button>
    </div>
</form>

<table class="table quest-table">
    <thead>
        <tr>
            <th style="width:80px"><a href="<?= htmlspecialchars($buildSortUrl('ID')) ?>"><?= htmlspecialchars(__('app.quest.index.table.headers.id')) ?><?= $curSort==='ID' ? ($curDir==='ASC'?' ▲':' ▼') : '' ?></a></th>
            <th><?= htmlspecialchars(__('app.quest.index.table.headers.title')) ?></th>
            <th style="width:110px"><a href="<?= htmlspecialchars($buildSortUrl('MinLevel')) ?>"><?= htmlspecialchars(__('app.quest.index.table.headers.min_level')) ?><?= $curSort==='MinLevel' ? ($curDir==='ASC'?' ▲':' ▼') : '' ?></a></th>
            <th style="width:110px"><a href="<?= htmlspecialchars($buildSortUrl('QuestLevel')) ?>"><?= htmlspecialchars(__('app.quest.index.table.headers.level')) ?><?= $curSort==='QuestLevel' ? ($curDir==='ASC' ? ' ▲' : ' ▼') : '' ?></a></th>
            <th style="width:140px"><?= htmlspecialchars(__('app.quest.index.table.headers.type')) ?></th>
            <th style="width:120px"><?= htmlspecialchars(__('app.quest.index.table.headers.reward_xp')) ?></th>
            <th style="width:140px"><?= htmlspecialchars(__('app.quest.index.table.headers.reward_money')) ?></th>
            <th style="width:260px"><?= htmlspecialchars(__('app.quest.index.table.headers.reward_items')) ?></th>
            <th style="width:140px"><?= htmlspecialchars(__('app.quest.index.table.headers.actions')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php $rows = $pager->items ?? []; ?>
        <?php if(!$rows): ?>
            <tr><td colspan="9" style="text-align:center" class="text-muted"><?= htmlspecialchars(__('app.quest.index.table.empty')) ?></td></tr>
        <?php endif; ?>
        <?php foreach($rows as $row): ?>
            <tr data-id="<?= (int)$row['ID'] ?>">
                <td><?= (int)$row['ID'] ?></td>
                <td><a href="?<?= http_build_query(['edit_id'=>$row['ID']] + $_GET) ?>" class="text-info"><?= htmlspecialchars($row['LogTitle'] ?? '') ?></a></td>
                <td><?= htmlspecialchars((string)($row['MinLevel'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($row['QuestLevel'] ?? '')) ?></td>
                <td>
                    <?php $questInfoId = (int)($row['QuestInfoID'] ?? 0); $infoLabel = $questInfoMap[$questInfoId] ?? ($row['quest_info_label'] ?? null); ?>
                    <?php if($infoLabel !== null && $infoLabel !== ''): ?>
                        <?= htmlspecialchars((string)$infoLabel) ?>
                    <?php elseif($questInfoId !== 0): ?>
                        <span class="muted"><?= htmlspecialchars(__('app.quest.index.table.quest_info_unknown', ['id'=>$questInfoId])) ?></span>
                    <?php else: ?>
                        <span class="muted"><?= htmlspecialchars((string)($questInfoMap[0] ?? __('app.quest.index.table.quest_info_default'))) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $xpVal = (int)($row['reward_xp_amount'] ?? 0); $xpDifficulty = (int)($row['RewardXPDifficulty'] ?? -1); ?>
                    <?php if($xpVal > 0): ?>
                        <?= number_format($xpVal) ?>
                    <?php elseif($xpDifficulty >= 0): ?>
                        <span class="muted"><?= htmlspecialchars(__('app.quest.index.table.reward_xp_difficulty', ['value'=>$xpDifficulty])) ?></span>
                    <?php else: ?>
                        <span class="muted"><?= htmlspecialchars(__('app.quest.common.na')) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $moneyText = $row['reward_money_text'] ?? __('app.quest.common.na'); $moneyDifficulty = isset($row['reward_money_difficulty']) ? (int)$row['reward_money_difficulty'] : -1; ?>
                    <?= htmlspecialchars($moneyText, ENT_QUOTES, 'UTF-8') ?>
                    <?php if($moneyDifficulty >= 0): ?><div class="muted small"><?= htmlspecialchars(__('app.quest.index.table.reward_money_difficulty', ['value'=>$moneyDifficulty])) ?></div><?php endif; ?>
                </td>
                <td>
                    <?php $fixedItems = $row['reward_items_fixed'] ?? []; $choiceItems = $row['reward_items_choice'] ?? []; ?>
                    <?php if(!$fixedItems && !$choiceItems): ?>
                        <span class="muted"><?= htmlspecialchars(__('app.quest.common.na')) ?></span>
                    <?php else: ?>
                        <?php if($fixedItems): ?>
                            <div class="muted small" style="margin-bottom:2px"><?= htmlspecialchars(__('app.quest.index.table.reward_items_fixed')) ?></div>
                            <?php foreach($fixedItems as $itm):
                                $qualityClass = isset($itm['quality']) && $itm['quality'] !== null ? ' item-quality-q'.(int)$itm['quality'] : '';
                                $itemName = htmlspecialchars($itm['name'] ?? ('#'.($itm['id'] ?? '?')), ENT_QUOTES, 'UTF-8');
                                $itemQty = (int)($itm['quantity'] ?? 0);
                                $itemId = (int)($itm['id'] ?? 0);
                            ?>
                                <div class="flex gap-1" style="align-items:center">
                                    <span class="item-name<?= $qualityClass ?>" title="<?= htmlspecialchars(__('app.quest.index.table.reward_item_title', ['id'=>$itemId])) ?>"><?= $itemName ?></span>
                                    <span class="muted small">x<?= $itemQty ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if($choiceItems): ?>
                            <div class="muted small" style="margin-top:4px;margin-bottom:2px"><?= htmlspecialchars(__('app.quest.index.table.reward_items_choice')) ?></div>
                            <?php foreach($choiceItems as $itm):
                                $qualityClass = isset($itm['quality']) && $itm['quality'] !== null ? ' item-quality-q'.(int)$itm['quality'] : '';
                                $itemName = htmlspecialchars($itm['name'] ?? ('#'.($itm['id'] ?? '?')), ENT_QUOTES, 'UTF-8');
                                $itemQty = (int)($itm['quantity'] ?? 0);
                                $itemId = (int)($itm['id'] ?? 0);
                            ?>
                                <div class="flex gap-1" style="align-items:center">
                                    <span class="item-name<?= $qualityClass ?>" title="<?= htmlspecialchars(__('app.quest.index.table.reward_item_title', ['id'=>$itemId])) ?>"><?= $itemName ?></span>
                                    <span class="muted small">x<?= $itemQty ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="nowrap">
                    <a class="btn-sm btn info outline" href="?<?= http_build_query(['edit_id'=>$row['ID']] + $_GET) ?>"><?= htmlspecialchars(__('app.quest.index.table.actions.edit')) ?></a>
                    <button class="btn-sm btn danger action-delete" data-id="<?= (int)$row['ID'] ?>"><?= htmlspecialchars(__('app.quest.index.table.actions.delete')) ?></button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$page = $pager->page;
$pages = $pager->pages;
$base = url('/quest');
$qs = $_GET; unset($qs['page']); if($serverParam !== null) $qs['server'] = $serverParam;
if(!empty($qs)) $base .= '?'.http_build_query($qs);
include __DIR__.'/../components/pagination.php';
?>

<!-- 新建任务 Modal -->
<div class="modal-backdrop" id="modal-new-quest" style="display:none">
    <div class="modal-panel small">
        <header><h3><?= htmlspecialchars(__('app.quest.index.modals.new.title')) ?></h3><button class="modal-close" data-close>&times;</button></header>
        <div class="modal-body">
            <label><?= htmlspecialchars(__('app.quest.index.modals.new.id_label')) ?> <input type="number" id="newQuestId" min="1"></label>
            <label style="margin-top:8px"><?= htmlspecialchars(__('app.quest.index.modals.new.copy_label')) ?> <input type="number" id="copyQuestId" min="1"></label>
            <div class="muted" style="margin-top:6px;font-size:12px"><?= htmlspecialchars(__('app.quest.index.modals.new.copy_hint')) ?></div>
        </div>
        <footer style="text-align:right;margin-top:12px">
            <button class="btn outline" data-close><?= htmlspecialchars(__('app.quest.index.modals.new.cancel')) ?></button>
            <button class="btn success" id="btn-create-quest"><?= htmlspecialchars(__('app.quest.index.modals.new.confirm')) ?></button>
        </footer>
    </div>
</div>

<!-- 日志 Modal -->
<div class="modal-backdrop" id="modal-quest-log" style="display:none">
    <div class="modal-panel large">
        <header><h3><?= htmlspecialchars(__('app.quest.log_modal.title')) ?></h3><button class="modal-close" data-close>&times;</button></header>
        <div class="modal-body">
            <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:10px;flex-wrap:wrap">
                <label style="display:flex;flex-direction:column;font-size:12px;color:#9bb0c0">
                    <span style="margin-bottom:4px;color:#c8d6e5;font-size:13px"><?= htmlspecialchars(__('app.quest.log_modal.type_label')) ?></span>
                    <select id="questLogType" style="min-width:150px">
                        <option value="sql"><?= htmlspecialchars(__('app.quest.log_modal.types.sql')) ?></option>
                        <option value="deleted"><?= htmlspecialchars(__('app.quest.log_modal.types.deleted')) ?></option>
                        <option value="actions"><?= htmlspecialchars(__('app.quest.log_modal.types.actions')) ?></option>
                    </select>
                </label>
                <button class="btn info outline" type="button" id="btn-refresh-quest-log"><?= htmlspecialchars(__('app.quest.log_modal.refresh')) ?></button>
            </div>
            <pre id="questLogBox" style="max-height:400px;overflow:auto;background:#111;color:#9f9;padding:8px"><?= htmlspecialchars(__('app.quest.log_modal.empty')) ?></pre>
        </div>
        <footer style="text-align:right;margin-top:8px">
            <button class="btn outline" data-close><?= htmlspecialchars(__('app.quest.log_modal.close')) ?></button>
        </footer>
    </div>
</div>

<script>
    window.QUEST_FILTERS = <?= json_encode($filters, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('js/modules/quest.js') ?>"></script>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>

