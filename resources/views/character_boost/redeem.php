<?php
/**
 * File: resources/views/character_boost/redeem.php
 * Purpose: Public character boost redeem page.
 */

use Acme\Panel\Support\Csrf;

$endpointOptions = url('/public/character-boost/options');
$endpointRedeem = url('/public/character-boost/redeem');

include dirname(__DIR__) . '/components/page_header.php';
?>

<div class="panel cb-panel-section">
  <div id="boostRedeemFlash" class="panel-flash panel-flash--inline cb-flash-hidden"></div>

  <form id="boostRedeemForm" class="form" data-options-endpoint="<?= htmlspecialchars($endpointOptions) ?>" data-redeem-endpoint="<?= htmlspecialchars($endpointRedeem) ?>">
    <?= Csrf::field() ?>

    <div class="form-row">
      <label class="label" for="boostRedeemRealm"><?= htmlspecialchars(__('app.character_boost.redeem.fields.realm')) ?></label>
      <select id="boostRedeemRealm" name="realm_id" class="input" required>
        <option value=""><?= htmlspecialchars(__('app.common.loading')) ?></option>
      </select>
    </div>

    <div class="form-row">
      <label class="label" for="boostRedeemTemplate"><?= htmlspecialchars(__('app.character_boost.redeem.fields.template')) ?></label>
      <select id="boostRedeemTemplate" name="template_id" class="input" disabled>
        <option value=""><?= htmlspecialchars(__('app.character_boost.redeem.fields.template_loading')) ?></option>
      </select>
      <div class="help cb-help">
        <?= htmlspecialchars(__('app.character_boost.redeem.hint.template_auto')) ?>
      </div>
    </div>

    <div class="form-row">
      <label class="label" for="boostRedeemCharacter"><?= htmlspecialchars(__('app.character_boost.redeem.fields.character_name')) ?></label>
      <input id="boostRedeemCharacter" name="character_name" class="input" autocomplete="off" required />
    </div>

    <div class="form-row">
      <label class="label" for="boostRedeemCode"><?= htmlspecialchars(__('app.character_boost.redeem.fields.code')) ?></label>
      <input id="boostRedeemCode" name="code" class="input" autocomplete="off" maxlength="16" required />
    </div>

    <div class="form-row">
      <button class="btn btn-primary" type="submit"><?= htmlspecialchars(__('app.character_boost.redeem.actions.submit')) ?></button>
    </div>
  </form>
</div>
