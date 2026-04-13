<?php
/**
 * File: resources/views/creature/index.php
 * Purpose: Provides functionality for the resources/views/creature module.
 * Functions:
 *   - creature_localize_config_value()
 *   - mapFactionLabel()
 *   - mapNpcFlagLabel()
 */

?>
<?php
$serverParam = isset($_GET['server']) ? (int)$_GET['server'] : null;

use Acme\Panel\Support\ConfigLocalization;

$creatureCfg = include __DIR__.'/../../../config/creature.php';
$creatureCfg = is_array($creatureCfg) ? $creatureCfg : [];
$creatureCfg = ConfigLocalization::localizeArray($creatureCfg);
$flagsConfig = $creatureCfg['flags'] ?? [];
$FACTION_LABELS = $creatureCfg['factions'] ?? [];
$NPCFLAG_LABELS = $flagsConfig['npcflag'] ?? [];

function mapFactionLabel($id,$map){ return $map[$id] ?? $id; }
function mapNpcFlagLabel($val,$map){
  $val = (int)$val; if($val===0) return '0';
  $bits=[]; for($i=0;$i<32;$i++){ $mask=(1<<$i); if(($val & $mask)!==0){ $bits[] = isset($map[$i]) ? $map[$i] : ('#'.$i); } }
  if(!$bits) return (string)$val; $label=implode(' / ',$bits); return $label; }
$creatureCapabilities = $__pageCapabilities ?? [
  'view' => $__can('content.view'),
  'create' => $__can('content.create'),
  'delete' => $__can('content.delete'),
  'logs' => $__can('content.logs'),
];
$__pageCapabilities = $creatureCapabilities;
$capabilityNotice = $__canAll(['content.create', 'content.delete', 'content.logs'])
  ? null
  : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<div id="creature-feedback" class="panel-flash panel-flash--inline"></div>
<?php include __DIR__.'/../components/capability_notice.php'; ?>
<form method="get" action="" class="inline creature-filter-form">
  <?php if($serverParam!==null): ?><input type="hidden" name="server" value="<?= $serverParam ?>"><?php endif; ?>
  <input type="hidden" name="filter_npcflag_bits" id="filter_npcflag_bits" value="<?= htmlspecialchars($filter_npcflag_bits ?? '') ?>">
  <select name="search_type">
    <option value="name" <?= $search_type==='name'?'selected':'' ?>><?= __('app.creature.index.filters.search_type.name') ?></option>
    <option value="id" <?= $search_type==='id'?'selected':'' ?>><?= __('app.creature.index.filters.search_type.id') ?></option>
  </select>
  <input type="text" name="search_value" placeholder="<?= htmlspecialchars(__('app.creature.index.filters.placeholders.search_value'),ENT_QUOTES,'UTF-8') ?>" value="<?= htmlspecialchars($search_value) ?>">
  <input type="text" name="filter_minlevel" placeholder="<?= htmlspecialchars(__('app.creature.index.filters.placeholders.min_level'),ENT_QUOTES,'UTF-8') ?>" value="<?= htmlspecialchars((string)$filter_minlevel) ?>">
  <input type="text" name="filter_maxlevel" placeholder="<?= htmlspecialchars(__('app.creature.index.filters.placeholders.max_level'),ENT_QUOTES,'UTF-8') ?>" value="<?= htmlspecialchars((string)$filter_maxlevel) ?>">
  <input type="number" name="limit" class="creature-filter-form__limit" value="<?= (int)$limit ?>">
  <button class="btn info" type="submit"><?= __('app.creature.index.filters.buttons.search') ?></button>
  <button class="btn outline" type="button" id="btn-filter-reset"><?= __('app.creature.index.filters.buttons.reset') ?></button>
  <?php if($creatureCapabilities['create']): ?>
  <button class="btn success" type="button" id="btn-new-creature"><?= __('app.creature.index.filters.buttons.create') ?></button>
  <?php endif; ?>
  <?php if($creatureCapabilities['logs']): ?>
  <button class="btn outline info" type="button" id="btn-creature-sql-log"><?= __('app.creature.index.filters.buttons.log') ?></button>
  <?php endif; ?>
  <details class="npcflag-filter creature-npcflag-filter" <?= !empty($filter_npcflag_bits)?'open':'' ?>>
    <summary class="creature-npcflag-filter__summary"><?= __('app.creature.index.npcflag.summary') ?></summary>
    <?php
  $npcBitsMap = $flagsConfig['npcflag'] ?? [];
      $selectedBits=[]; if(!empty($filter_npcflag_bits)){ foreach(explode(',', $filter_npcflag_bits) as $sb){ $sb=trim($sb); if($sb!=='' && ctype_digit($sb)) $selectedBits[(int)$sb]=true; } }
    ?>
    <div class="creature-npcflag-filter__grid">
      <?php foreach($npcBitsMap as $bit=>$label): ?>
        <label class="creature-npcflag-filter__option">
          <input type="checkbox" class="npcflag-bit" value="<?= (int)$bit ?>" <?= isset($selectedBits[$bit])?'checked':'' ?>> <span><?= htmlspecialchars($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="creature-npcflag-filter__actions">
      <button type="button" class="btn btn-sm outline" id="npcflagApplyBtn"><?= __('app.creature.index.npcflag.apply') ?></button>
      <button type="button" class="btn btn-sm outline" id="npcflagClearBtn"><?= __('app.creature.index.npcflag.clear') ?></button>
      <span class="muted creature-npcflag-filter__hint"><?= __('app.creature.index.npcflag.mode_hint') ?></span>
    </div>
  </details>
</form>
<table class="table creature-table">
  <thead><tr>
    <th><?= __('app.creature.index.table.headers.id') ?></th>
    <th><?= __('app.creature.index.table.headers.name') ?></th>
    <th><?= __('app.creature.index.table.headers.subname') ?></th>
    <th><?= __('app.creature.index.table.headers.min_level') ?></th>
    <th><?= __('app.creature.index.table.headers.max_level') ?></th>
    <th><?= __('app.creature.index.table.headers.faction') ?></th>
    <th><?= __('app.creature.index.table.headers.npcflag') ?></th>
    <th><?= __('app.creature.index.table.headers.actions') ?></th>
    <th><?= __('app.creature.index.table.headers.verify') ?></th>
  </tr></thead>
  <tbody>
  <?php foreach($pager->items as $row): ?>
    <tr data-entry="<?= (int)$row['entry'] ?>">
      <td><?= (int)$row['entry'] ?></td>
      <td><a href="?<?= http_build_query((['edit_id'=>$row['entry']] + $_GET)) ?>" class="text-info"><?= htmlspecialchars($row['name']??'') ?></a></td>
      <td><?= htmlspecialchars($row['subname']??'') ?></td>
      <td><?= (int)$row['minlevel'] ?></td>
      <td><?= (int)$row['maxlevel'] ?></td>
  <td title="<?= (int)$row['faction'] ?>"><?= htmlspecialchars(mapFactionLabel((int)$row['faction'],$FACTION_LABELS)) ?></td>
  <td title="<?= (int)$row['npcflag'] ?>" class="creature-table__npcflag"><?= htmlspecialchars(mapNpcFlagLabel((int)$row['npcflag'],$NPCFLAG_LABELS)) ?></td>
      <td class="nowrap">
        <a class="btn-sm btn info outline" href="?<?= http_build_query((['edit_id'=>$row['entry']] + $_GET)) ?>"><?= __('app.creature.index.table.actions.edit') ?></a>
        <?php if($creatureCapabilities['delete']): ?>
        <button class="btn-sm btn danger action-delete" data-id="<?= (int)$row['entry'] ?>"><?= __('app.creature.index.table.actions.delete') ?></button>
        <?php endif; ?>
        <?php if(!$creatureCapabilities['delete']): ?>
        <span class="muted small"><?= htmlspecialchars(__('app.common.capabilities.read_only')) ?></span>
        <?php endif; ?>
      </td>
      <td><button class="btn-sm btn outline action-verify" data-entry="<?= (int)$row['entry'] ?>"><?= __('app.creature.index.table.verify_button') ?></button></td>
    </tr>
  <?php endforeach; if(!$pager->items): ?>
    <tr><td colspan="9" class="text-muted creature-table__empty"><?= __('app.creature.index.table.empty') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
<?php
  $pages=$pager->pages; $page=$pager->page;
  $base=url('/creature');
  $qs=$_GET; unset($qs['page']); if($serverParam!==null) $qs['server']=$serverParam; if(!empty($qs)){ $base.='?'.http_build_query($qs); }
  include __DIR__.'/../components/pagination.php';
?>

<!-- Create creature modal -->
<div class="modal-backdrop creature-modal-hidden" id="modal-new-creature">
  <div class="modal-panel small">
    <header><h3><?= __('app.creature.index.modals.new.title') ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <label><?= __('app.creature.index.modals.new.id_label') ?> <input type="number" id="newCreatureId"></label>
      <label class="creature-modal-field-spaced"><?= __('app.creature.index.modals.new.copy_label') ?> <input type="number" id="copyCreatureId"></label>
      <div class="muted creature-modal-hint"><?= __('app.creature.index.modals.new.copy_hint') ?></div>
    </div>
    <footer class="creature-modal-footer">
      <button class="btn outline" data-close><?= __('app.creature.index.modals.new.cancel') ?></button>
      <button class="btn success" id="btn-create-creature"><?= __('app.creature.index.modals.new.confirm') ?></button>
    </footer>
  </div>
</div>

<!-- Log modal -->
<div class="modal-backdrop creature-modal-hidden" id="modal-creature-log">
  <div class="modal-panel large">
    <header><h3><?= __('app.creature.index.modals.log.title') ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <div class="creature-log-toolbar">
        <label class="creature-log-toolbar__field">
          <span class="creature-log-toolbar__label"><?= __('app.creature.index.modals.log.type_label') ?></span>
          <select id="creatureLogType" class="creature-log-toolbar__select">
            <option value="sql"><?= __('app.creature.index.modals.log.types.sql') ?></option>
            <option value="deleted"><?= __('app.creature.index.modals.log.types.deleted') ?></option>
            <option value="actions"><?= __('app.creature.index.modals.log.types.actions') ?></option>
          </select>
        </label>
        <button class="btn info outline" type="button" id="btn-refresh-creature-log"><?= __('app.creature.index.modals.log.refresh') ?></button>
      </div>
      <pre id="creatureLogBox" class="creature-log-box"><?= __('app.creature.index.modals.log.empty') ?></pre>
    </div>
    <footer class="creature-modal-footer creature-modal-footer--tight">
      <button class="btn outline" data-close><?= __('app.creature.index.modals.log.close') ?></button>
    </footer>
  </div>
</div>

<!-- Verification modal -->
<div class="modal-backdrop creature-modal-hidden" id="modal-verify">
  <div class="modal-panel large">
    <header><h3><?= __('app.creature.index.modals.verify.title') ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <div id="verifyDiag" class="muted creature-verify-diag"></div>
      <table class="table" id="verifyDiffTable"><thead><tr>
        <th><?= __('app.creature.index.modals.verify.headers.field') ?></th>
        <th><?= __('app.creature.index.modals.verify.headers.rendered') ?></th>
        <th><?= __('app.creature.index.modals.verify.headers.database') ?></th>
        <th><?= __('app.creature.index.modals.verify.headers.status') ?></th>
      </tr></thead><tbody></tbody></table>
      <div id="verifySuggestion" class="creature-verify-suggestion"></div>
    </div>
    <footer class="creature-modal-footer">
      <button class="btn" data-close><?= __('app.creature.index.modals.verify.close') ?></button>
      <button class="btn outline creature-hidden" id="verifyCopySQL"><?= __('app.creature.index.modals.verify.copy_sql') ?></button>
    </footer>
  </div>
</div>
