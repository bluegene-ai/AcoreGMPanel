<?php
/**
 * File: resources/views/character_boost/template_edit.php
 * Purpose: Admin editor for a boost template.
 */

use Acme\Panel\Support\Csrf;

$realmId = (int) ($realm_id ?? 1);
$template = is_array($template ?? null) ? $template : null;
$error = $error ?? null;
$boostTemplateCapabilities = is_array($__pageCapabilities ?? null)
  ? $__pageCapabilities
  : [
    'templates' => $__can('boost.templates'),
    'codes' => $__can('boost.codes'),
  ];
$__pageCapabilities = $boostTemplateCapabilities;

$isEdit = $template && isset($template['id']);
$id = $isEdit ? (int) $template['id'] : 0;

$endpoint = url('/character-boost/api/templates/save');

$itemsLines = [];
if ($isEdit && !empty($template['items']) && is_array($template['items'])) {
    foreach ($template['items'] as $it) {
        $entry = (int) ($it['item_entry'] ?? 0);
        $qty = (int) ($it['quantity'] ?? 0);
        if ($entry > 0 && $qty > 0) {
            $itemsLines[] = $entry . ':' . $qty;
        }
    }
}

$tiers = [];
if ($isEdit && !empty($template['class_rewards']) && is_array($template['class_rewards'])) {
    foreach ($template['class_rewards'] as $rw) {
        $t = (string) ($rw['tier'] ?? '');
        $t = strtolower(trim($t));
        if ($t !== '') {
            $tiers[] = $t;
        }
    }
}
$tiers = array_values(array_unique($tiers));
$capabilityNotice = $boostTemplateCapabilities['templates'] ?? false
    ? null
    : __('app.common.capabilities.page_limited');
?>

<?php if($error): ?>
  <div class="panel-flash panel-flash--danger panel-flash--inline is-visible"><?= htmlspecialchars((string)$error) ?></div>
<?php endif; ?>

<?php include __DIR__ . '/../components/capability_notice.php'; ?>

<?php include __DIR__ . '/../components/page_header.php'; ?>

<div class="panel">
  <div id="boostTplEditFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

  <form id="boostTplEditForm" class="form" data-endpoint="<?= htmlspecialchars($endpoint) ?>">
    <?= Csrf::field() ?>
    <?php if($isEdit): ?>
      <input type="hidden" name="id" value="<?= (int)$id ?>" />
    <?php endif; ?>

    <div class="form-row">
      <label class="label" for="boostTplName"><?= htmlspecialchars(__('app.character_boost.templates.fields.name')) ?></label>
      <input id="boostTplName" name="name" class="input" value="<?= htmlspecialchars((string)($template['name'] ?? '')) ?>" required />
    </div>

    <div class="form-row">
      <label class="label" for="boostTplLevel"><?= htmlspecialchars(__('app.character_boost.templates.fields.target_level')) ?></label>
      <input id="boostTplLevel" name="target_level" type="number" class="input" min="1" max="255" value="<?= (int)($template['target_level'] ?? 80) ?>" required />
    </div>

    <div class="form-row">
      <label class="label" for="boostTplGold"><?= htmlspecialchars(__('app.character_boost.templates.fields.money_gold')) ?></label>
      <input id="boostTplGold" name="money_gold" type="number" class="input" min="0" value="<?= (int)($template['money_gold'] ?? 0) ?>" />
    </div>

    <div class="form-row">
      <label class="label"><?= htmlspecialchars(__('app.character_boost.templates.fields.require_match')) ?></label>
      <label class="cb-inline-check">
        <input type="checkbox" name="require_account_level_match" value="1" <?= ((int)($template['require_account_level_match'] ?? 0) === 1) ? 'checked' : '' ?> />
        <span><?= htmlspecialchars(__('app.character_boost.templates.fields.require_match_label')) ?></span>
      </label>
    </div>

    <div class="form-row">
      <label class="label" for="boostTplItems"><?= htmlspecialchars(__('app.character_boost.templates.fields.items')) ?></label>
      <textarea id="boostTplItems" name="items" class="input cb-mono-textarea cb-mono-textarea--lg"><?= htmlspecialchars(implode("\n", $itemsLines)) ?></textarea>
      <div class="help cb-help">
        <?= htmlspecialchars(__('app.character_boost.templates.hint.items_format')) ?>
      </div>
    </div>

    <div class="form-row">
      <label class="label" for="boostTplTiers"><?= htmlspecialchars(__('app.character_boost.templates.fields.class_rewards')) ?></label>
      <textarea id="boostTplTiers" name="class_rewards" class="input cb-mono-textarea cb-mono-textarea--md"><?= htmlspecialchars(implode("\n", $tiers)) ?></textarea>
      <div class="help cb-help">
        <?= htmlspecialchars(__('app.character_boost.templates.hint.class_rewards')) ?>
      </div>
    </div>

    <div class="form-row">
      <button class="btn btn-primary" type="submit"><?= htmlspecialchars(__('app.character_boost.templates.actions.save')) ?></button>
    </div>
  </form>
</div>
