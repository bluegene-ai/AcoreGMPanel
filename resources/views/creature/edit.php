<?php
/**
 * File: resources/views/creature/edit.php
 * Purpose: Provides functionality for the resources/views/creature module.
 * Functions:
 *   - creature_localize_config_value()
 */

  $module='creature';
  use Acme\Panel\Support\ConfigLocalization;
  include __DIR__.'/../layouts/base_top.php';
  $id=(int)$creature['entry'];
  $creatureCfg = include __DIR__.'/../../../config/creature.php';
  $creatureCfg = is_array($creatureCfg) ? $creatureCfg : [];
  $creatureCfg = ConfigLocalization::localizeArray($creatureCfg);
  $groups = $creatureCfg['groups'] ?? [];
  $rankEnum=[
    0=>__('app.creature.edit.rank_enum.0'),
    1=>__('app.creature.edit.rank_enum.1'),
    2=>__('app.creature.edit.rank_enum.2'),
    3=>__('app.creature.edit.rank_enum.3'),
    4=>__('app.creature.edit.rank_enum.4'),
  ];
  $typeEnum=[
    0=>__('app.creature.edit.type_enum.0'),
    1=>__('app.creature.edit.type_enum.1'),
    2=>__('app.creature.edit.type_enum.2'),
    3=>__('app.creature.edit.type_enum.3'),
    4=>__('app.creature.edit.type_enum.4'),
    5=>__('app.creature.edit.type_enum.5'),
    6=>__('app.creature.edit.type_enum.6'),
    7=>__('app.creature.edit.type_enum.7'),
    8=>__('app.creature.edit.type_enum.8'),
    9=>__('app.creature.edit.type_enum.9'),
    10=>__('app.creature.edit.type_enum.10'),
    11=>__('app.creature.edit.type_enum.11'),
    12=>__('app.creature.edit.type_enum.12'),
    13=>__('app.creature.edit.type_enum.13'),
  ];
  $flagConfig = $creatureCfg['flags'] ?? [];
  $serverParam = isset($_GET['server']) ? (int)$_GET['server'] : null;
?>
<script>window.CREATURE_FLAG_CONFIG = <?= json_encode($flagConfig,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>

<div class="page-toolbar item-toolbar creature-toolbar">
  <div class="toolbar-line top-line">
    <h1 class="page-title no-margin"><?= htmlspecialchars(__('app.creature.edit.title', ['id' => $id])) ?> <small style="font-size:12px;color:#6f7a84;margin-left:6px;"><?= htmlspecialchars($creature['name']??'') ?></small></h1>
    <div class="toolbar-spacer"></div>
    <div class="toolbar-actions primary-actions">
  <a class="btn outline" href="<?= url('/creature?'.htmlspecialchars($cancel_query.($serverParam!==null && strpos($cancel_query,'server=')===false?($cancel_query?'&':''). 'server='.$serverParam:''))) ?>"><?= htmlspecialchars(__('app.creature.edit.actions.back')) ?></a>
  <button class="btn outline" type="button" id="btn-creature-compact"><?= htmlspecialchars(__('app.creature.edit.actions.compact')) ?></button>
  <button class="btn danger" type="button" id="btn-delete-creature" data-id="<?= $id ?>"><?= htmlspecialchars(__('app.creature.edit.actions.delete')) ?></button>
  <button class="btn success" type="button" id="btn-save-creature"><?= htmlspecialchars(__('app.creature.edit.actions.save')) ?></button>
  <button class="btn info outline" type="button" id="btn-gen-update"><?= htmlspecialchars(__('app.creature.edit.actions.diff_sql')) ?></button>
  <button class="btn outline" type="button" id="btn-exec-sql"><?= htmlspecialchars(__('app.creature.edit.actions.exec_sql')) ?></button>
  <label class="btn outline" style="display:inline-flex;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" id="toggle-show-diff" style="margin:0"> <?= htmlspecialchars(__('app.creature.edit.labels.only_changes')) ?></label>
    </div>
  </div>
  <div class="toolbar-line nav-line">
    <div class="toolbar-nav" id="creature-section-nav"></div>
    <div class="muted" style="margin-left:auto;font-size:12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center" id="creatureDiffSummary">
      <span><?= htmlspecialchars(__('app.creature.edit.toolbar.changed_fields')) ?> <strong id="creatureDiffCount">0</strong></span>
    </div>
  </div>
</div>

<div id="creature-feedback" class="panel-flash panel-flash--inline"></div>

<form id="form-creature-edit" data-entry="<?= $id ?>" class="item-edit-grid creature-edit-grid">
  <input type="hidden" name="entry" value="<?= $id ?>">
  <?php foreach($groups as $gKey=>$group): $groupId='cg_'.$gKey; ?>
    <details open id="<?= $groupId ?>" data-group="<?= htmlspecialchars($gKey) ?>" class="creature-group">
      <summary><?= htmlspecialchars($group['label']) ?> <span class="muted" data-group-diff-count style="font-size:11px"></span></summary>
      <div class="field-grid">
        <?php foreach(($group['fields']??[]) as $f): $fname=$f['name']; $val=$creature[$fname]??''; $orig=htmlspecialchars((string)$val); $type=$f['type']??'text'; $bitmask=!empty($f['bitmask']); $help=$f['help']??''; ?>
          <label data-field-wrapper data-name="<?= htmlspecialchars($fname) ?>">
            <span style="font-size:12px;font-weight:500;letter-spacing:.5px;"><?= htmlspecialchars($f['label']) ?></span>
            <?php if($fname==='rank'): ?>
              <select name="rank" data-orig="<?= (int)($creature['rank']??0) ?>">
                <?php $rvv=(int)($creature['rank']??0); foreach($rankEnum as $rv=>$rt): ?>
                  <option value="<?= $rv ?>" <?= $rvv===$rv?'selected':'' ?>><?= htmlspecialchars($rt) ?> (<?= $rv ?>)</option>
                <?php endforeach; ?>
              </select>
            <?php elseif($fname==='type'): ?>
              <select name="type" data-orig="<?= (int)($creature['type']??0) ?>">
                <?php $tvv=(int)($creature['type']??0); foreach($typeEnum as $tv=>$tt): ?>
                  <option value="<?= $tv ?>" <?= $tvv===$tv?'selected':'' ?>><?= htmlspecialchars($tt) ?> (<?= $tv ?>)</option>
                <?php endforeach; ?>
              </select>
            <?php else: if($type==='number'): ?>
              <input type="number" name="<?= htmlspecialchars($fname) ?>" value="<?= htmlspecialchars((string)$val) ?>" data-orig="<?= $orig ?>"<?= $bitmask?' data-bitmask="'.$fname.'"':'' ?>>
            <?php elseif($type==='textarea'): ?>
              <textarea name="<?= htmlspecialchars($fname) ?>" rows="2" data-orig="<?= $orig ?>"><?= htmlspecialchars((string)$val) ?></textarea>
            <?php else: ?>
              <input name="<?= htmlspecialchars($fname) ?>" value="<?= htmlspecialchars((string)$val) ?>" data-orig="<?= $orig ?>"<?= $bitmask?' data-bitmask="'.$fname.'"':'' ?>>
            <?php endif; endif; ?>
            <?php if($help): ?><div class="hint muted" style="font-size:11px;line-height:1.2;margin-top:2px"><?= htmlspecialchars($help) ?></div><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endforeach; ?>

  <section class="item-edit-span-2 sql-section" id="creatureSqlSection">
    <h2 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:16px;margin:0;">
      <span><?= htmlspecialchars(__('app.creature.edit.diff.title')) ?></span>
      <button type="button" class="btn info outline btn-sm" id="btn-copy-sql" disabled><?= htmlspecialchars(__('app.creature.edit.actions.copy')) ?></button>
      <button type="button" class="btn success btn-sm" id="btn-exec-preview-sql" disabled><?= htmlspecialchars(__('app.creature.edit.actions.execute')) ?></button>
    </h2>
    <div class="muted" style="font-size:12px;margin-bottom:6px;"><?= htmlspecialchars(__('app.creature.edit.diff.hint')) ?></div>
    <pre id="creatureSqlPreview" class="sql-result mono" style="min-height:90px;"><?= htmlspecialchars(__('app.creature.edit.diff.placeholder')) ?></pre>
    <div id="creatureSqlExecResult" class="sql-exec-result" style="margin-top:10px;display:none;border:1px solid #2d3b45;background:#121a21;padding:10px 12px;border-radius:6px;font-size:12px;line-height:1.5"></div>
  </section>
</form>

<h3 style="margin-top:30px"><?= htmlspecialchars(__('app.creature.edit.models.heading')) ?></h3>
<button class="btn success" id="btn-add-model" type="button"><?= htmlspecialchars(__('app.creature.edit.actions.add_model')) ?></button>
<table class="table" id="table-models" data-creature="<?= $id ?>">
  <thead><tr><th><?= htmlspecialchars(__('app.creature.edit.models.table.index')) ?></th><th><?= htmlspecialchars(__('app.creature.edit.models.table.display_id')) ?></th><th><?= htmlspecialchars(__('app.creature.edit.models.table.scale')) ?></th><th><?= htmlspecialchars(__('app.creature.edit.models.table.probability')) ?></th><th><?= htmlspecialchars(__('app.creature.edit.models.table.verified_build')) ?></th><th><?= htmlspecialchars(__('app.creature.edit.models.table.actions')) ?></th></tr></thead>
  <tbody>
    <?php foreach($models as $m): ?>
      <tr data-idx="<?= (int)$m['Idx'] ?>" data-display="<?= (int)$m['CreatureDisplayID'] ?>" data-scale="<?= htmlspecialchars((string)$m['DisplayScale']) ?>" data-prob="<?= htmlspecialchars((string)$m['Probability']) ?>" data-vb="<?= htmlspecialchars((string)$m['VerifiedBuild']) ?>">
        <td><?= (int)$m['Idx'] ?></td><td><?= (int)$m['CreatureDisplayID'] ?></td><td><?= htmlspecialchars((string)$m['DisplayScale']) ?></td><td><?= htmlspecialchars((string)$m['Probability']) ?></td><td><?= htmlspecialchars((string)($m['VerifiedBuild']??'')) ?></td>
  <td class="nowrap"><button class="btn-sm btn outline action-edit-model"><?= htmlspecialchars(__('app.creature.edit.actions.edit_model')) ?></button> <button class="btn-sm btn danger outline action-del-model"><?= htmlspecialchars(__('app.creature.edit.actions.delete_model')) ?></button></td>
      </tr>
    <?php endforeach; if(!$models): ?><tr><td colspan="6" style="text-align:center" class="muted"><?= htmlspecialchars(__('app.creature.edit.models.empty')) ?></td></tr><?php endif; ?>
  </tbody>
</table>

<!-- 模型 Modal -->
<div class="modal-backdrop" id="modal-model" style="display:none">
  <div class="modal-panel small">
    <header><h3><?= htmlspecialchars(__('app.creature.edit.modal.title')) ?></h3><button class="modal-close" data-close>&times;</button></header>
    <div class="modal-body">
      <input type="hidden" id="modelIdx" value="">
      <label><?= htmlspecialchars(__('app.creature.edit.modal.display_id')) ?> <input type="number" id="modelDisplayId" required></label>
      <label style="margin-top:6px"><?= htmlspecialchars(__('app.creature.edit.modal.scale')) ?> <input type="number" id="modelScale" step="0.01" value="1"></label>
      <label style="margin-top:6px"><?= htmlspecialchars(__('app.creature.edit.modal.probability')) ?> <input type="number" id="modelProb" step="0.01" min="0" max="1" value="1"></label>
      <label style="margin-top:6px"><?= htmlspecialchars(__('app.creature.edit.modal.verified_build')) ?> <input type="number" id="modelVb" value="12340"></label>
    </div>
    <footer style="text-align:right;margin-top:10px"><button class="btn outline" data-close><?= htmlspecialchars(__('app.creature.edit.actions.cancel')) ?></button><button class="btn success" id="btn-save-model"><?= htmlspecialchars(__('app.creature.edit.actions.save')) ?></button></footer>
  </div>
</div>
<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
