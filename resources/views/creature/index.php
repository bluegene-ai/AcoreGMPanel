<?php
/**
 * File: resources/views/creature/index.php
 * Purpose: Provides functionality for the resources/views/creature module.
 * Functions:
 *   - creature_localize_config_value()
 *   - mapFactionLabel()
 *   - mapNpcFlagLabel()
 */

 $module='creature'; include __DIR__.'/../layouts/base_top.php'; ?>
<?php
$serverParam = isset($_GET['server']) ? (int)$_GET['server'] : null;

$creatureCfg = include __DIR__.'/../../../config/creature.php';
$creatureCfg = is_array($creatureCfg) ? $creatureCfg : [];
if (!function_exists('creature_localize_config_value')) {
  function creature_localize_config_value($value) {
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        $value[$key] = creature_localize_config_value($item);
      }
      return $value;
    }
    if (is_string($value) && strncmp($value, 'lang:', 5) === 0) {
      $langKey = substr($value, 5);
      return __($langKey);
    }
    return $value;
  }
}
$creatureCfg = creature_localize_config_value($creatureCfg);
$flagsConfig = $creatureCfg['flags'] ?? [];
$FACTION_LABELS = $creatureCfg['factions'] ?? [];
$NPCFLAG_LABELS = $flagsConfig['npcflag'] ?? [];

function mapFactionLabel($id,$map){ return $map[$id] ?? $id; }
function mapNpcFlagLabel($val,$map){
  $val = (int)$val; if($val===0) return '0';
  $bits=[]; for($i=0;$i<32;$i++){ $mask=(1<<$i); if(($val & $mask)!==0){ $bits[] = isset($map[$i]) ? $map[$i] : ('#'.$i); } }
  if(!$bits) return (string)$val; $label=implode(' / ',$bits); return $label; }
?>
<h1 class="page-title"><?= __('app.creature.index.page_title') ?></h1>
<div id="creature-feedback" class="panel-flash panel-flash--inline"></div>
<?php ?>
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
  <input type="number" name="limit" style="width:80px" value="<?= (int)$limit ?>">
  <button class="btn info" type="submit"><?= __('app.creature.index.filters.buttons.search') ?></button>
  <button class="btn outline" type="button" id="btn-filter-reset"><?= __('app.creature.index.filters.buttons.reset') ?></button>
  <button class="btn success" type="button" id="btn-new-creature"><?= __('app.creature.index.filters.buttons.create') ?></button>
  <button class="btn outline info" type="button" id="btn-creature-sql-log"><?= __('app.creature.index.filters.buttons.log') ?></button>
  <details style="display:inline-block;margin-left:12px;vertical-align:middle;" class="npcflag-filter" <?= !empty($filter_npcflag_bits)?'open':'' ?>>
    <summary style="cursor:pointer;font-size:13px;"><?= __('app.creature.index.npcflag.summary') ?></summary>
    <?php
  $npcBitsMap = $flagsConfig['npcflag'] ?? [];
      $selectedBits=[]; if(!empty($filter_npcflag_bits)){ foreach(explode(',', $filter_npcflag_bits) as $sb){ $sb=trim($sb); if($sb!=='' && ctype_digit($sb)) $selectedBits[(int)$sb]=true; } }
    ?>
    <div style="background:#141b1f;border:1px solid #28323c;padding:8px 10px;margin-top:6px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:6px;max-width:520px;min-width:420px;">
      <?php foreach($npcBitsMap as $bit=>$label): ?>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;">
          <input type="checkbox" class="npcflag-bit" value="<?= (int)$bit ?>" <?= isset($selectedBits[$bit])?'checked':'' ?>> <span><?= htmlspecialchars($label) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
      <button type="button" class="btn btn-sm outline" id="npcflagApplyBtn"><?= __('app.creature.index.npcflag.apply') ?></button>
      <button type="button" class="btn btn-sm outline" id="npcflagClearBtn"><?= __('app.creature.index.npcflag.clear') ?></button>
      <span class="muted" style="font-size:11px;"><?= __('app.creature.index.npcflag.mode_hint') ?></span>
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
  <td title="<?= (int)$row['npcflag'] ?>" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" ><?= htmlspecialchars(mapNpcFlagLabel((int)$row['npcflag'],$NPCFLAG_LABELS)) ?></td>
      <td class="nowrap">
        <a class="btn-sm btn info outline" href="?<?= http_build_query((['edit_id'=>$row['entry']] + $_GET)) ?>"><?= __('app.creature.index.table.actions.edit') ?></a>
        <button class="btn-sm btn danger action-delete" data-id="<?= (int)$row['entry'] ?>"><?= __('app.creature.index.table.actions.delete') ?></button>
      </td>
      <td><button class="btn-sm btn outline action-verify" data-entry="<?= (int)$row['entry'] ?>"><?= __('app.creature.index.table.verify_button') ?></button></td>
    </tr>
  <?php endforeach; if(!$pager->items): ?>
    <tr><td colspan="9" style="text-align:center" class="text-muted"><?= __('app.creature.index.table.empty') ?></td></tr>
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
<div class="modal-backdrop" id="modal-new-creature" style="display:none">
  <div class="modal-panel small">
    <header><h3><?= __('app.creature.index.modals.new.title') ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <label><?= __('app.creature.index.modals.new.id_label') ?> <input type="number" id="newCreatureId"></label>
      <label style="margin-top:8px"><?= __('app.creature.index.modals.new.copy_label') ?> <input type="number" id="copyCreatureId"></label>
      <div class="muted" style="margin-top:4px"><?= __('app.creature.index.modals.new.copy_hint') ?></div>
    </div>
    <footer style="text-align:right;margin-top:12px">
      <button class="btn outline" data-close><?= __('app.creature.index.modals.new.cancel') ?></button>
      <button class="btn success" id="btn-create-creature"><?= __('app.creature.index.modals.new.confirm') ?></button>
    </footer>
  </div>
</div>

<!-- Log modal -->
<div class="modal-backdrop" id="modal-creature-log" style="display:none">
  <div class="modal-panel large">
    <header><h3><?= __('app.creature.index.modals.log.title') ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:10px;flex-wrap:wrap">
        <label style="display:flex;flex-direction:column;font-size:12px;color:#9bb0c0">
          <span style="margin-bottom:4px;color:#c8d6e5;font-size:13px"><?= __('app.creature.index.modals.log.type_label') ?></span>
          <select id="creatureLogType" style="min-width:140px">
            <option value="sql"><?= __('app.creature.index.modals.log.types.sql') ?></option>
            <option value="deleted"><?= __('app.creature.index.modals.log.types.deleted') ?></option>
            <option value="actions"><?= __('app.creature.index.modals.log.types.actions') ?></option>
          </select>
        </label>
        <button class="btn info outline" type="button" id="btn-refresh-creature-log"><?= __('app.creature.index.modals.log.refresh') ?></button>
      </div>
      <pre id="creatureLogBox" style="max-height:400px;overflow:auto;background:#111;color:#9f9;padding:8px"><?= __('app.creature.index.modals.log.empty') ?></pre>
    </div>
    <footer style="text-align:right;margin-top:8px">
      <button class="btn outline" data-close><?= __('app.creature.index.modals.log.close') ?></button>
    </footer>
  </div>
</div>

<!-- Verification modal -->
<div class="modal-backdrop" id="modal-verify" style="display:none">
  <div class="modal-panel large">
    <header><h3><?= __('app.creature.index.modals.verify.title') ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <div id="verifyDiag" class="muted" style="margin-bottom:6px"></div>
      <table class="table" id="verifyDiffTable"><thead><tr>
        <th><?= __('app.creature.index.modals.verify.headers.field') ?></th>
        <th><?= __('app.creature.index.modals.verify.headers.rendered') ?></th>
        <th><?= __('app.creature.index.modals.verify.headers.database') ?></th>
        <th><?= __('app.creature.index.modals.verify.headers.status') ?></th>
      </tr></thead><tbody></tbody></table>
      <div id="verifySuggestion" style="margin-top:10px"></div>
    </div>
    <footer style="text-align:right;margin-top:12px">
      <button class="btn" data-close><?= __('app.creature.index.modals.verify.close') ?></button>
      <button class="btn outline" id="verifyCopySQL" style="display:none"><?= __('app.creature.index.modals.verify.copy_sql') ?></button>
    </footer>
  </div>
</div>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
<script>
(function(){
  const hidden=document.getElementById('filter_npcflag_bits');
  const applyBtn=document.getElementById('npcflagApplyBtn');
  const clearBtn=document.getElementById('npcflagClearBtn');
  const form=document.querySelector('form.creature-filter-form');
  if(!hidden||!applyBtn||!clearBtn||!form) return;
  function collect(){
    const bits=[...form.querySelectorAll('.npcflag-bit:checked')].map(cb=>cb.value).filter(v=>v!=='');
    hidden.value=bits.join(',');
  }
  applyBtn.addEventListener('click',()=>{ collect(); form.submit(); });
  clearBtn.addEventListener('click',()=>{ form.querySelectorAll('.npcflag-bit:checked').forEach(cb=>cb.checked=false); hidden.value=''; form.submit(); });
})();

(function(){
  const reset=document.getElementById('btn-filter-reset');
  if(!reset) return;
  if(reset.__bound) return; reset.__bound=true;
  reset.addEventListener('click',()=>{
    const form=reset.closest('form'); if(!form) return;
    const defaults={ search_type:'name', search_value:'', filter_minlevel:'', filter_maxlevel:'', limit:'50', filter_npcflag_bits:'' };
    Object.keys(defaults).forEach(key=>{ const el=form.querySelector('[name="'+key+'"]'); if(el) el.value=defaults[key]; });
  // Reset checkboxes
    form.querySelectorAll('.npcflag-bit:checked').forEach(cb=>cb.checked=false);
    const hidden=form.querySelector('#filter_npcflag_bits'); if(hidden) hidden.value='';
    form.submit();
  });
})();
</script>
