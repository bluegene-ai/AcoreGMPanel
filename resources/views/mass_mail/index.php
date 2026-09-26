<?php
/**
 * File: resources/views/mass_mail/index.php
 * Purpose: Provides functionality for the resources/views/mass_mail module.
 */

?>
<?php
  $massMailCapabilities = $__pageCapabilities ?? [
    'compose' => $__can('mass_mail.compose'),
    'announce' => $__can('mass_mail.announce'),
    'send' => $__can('mass_mail.send'),
    'logs' => $__can('mass_mail.logs'),
  ];
  $__pageCapabilities = $massMailCapabilities;
  $capabilityNotice = $__canAll(['mass_mail.announce', 'mass_mail.send', 'mass_mail.logs'])
    ? null
    : __('app.common.capabilities.page_limited');
?>
<?php include __DIR__.'/../components/page_header.php'; ?>
<?php include __DIR__.'/../components/capability_notice.php'; ?>

<div id="mmFeedback" class="mm-feedback panel-flash panel-flash--inline" hidden></div>

<div class="massmail-layout">
  <?php if($massMailCapabilities['announce']): ?>
  <section class="massmail-card massmail-card--announce">
    <h3 class="massmail-card__title"><?= __('app.mass_mail.index.sections.announce.title') ?></h3>
    <form id="massAnnounceForm" class="massmail-form" novalidate>
      <div class="massmail-form-grid">
        <div class="massmail-field full-span">
          <label for="massAnnounceMessage"><?= __('app.mass_mail.index.sections.announce.message_label') ?></label>
          <textarea id="massAnnounceMessage" name="message" rows="3" placeholder="<?= htmlspecialchars(__('app.mass_mail.index.sections.announce.message_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required></textarea>
        </div>
      </div>
      <div class="massmail-actions">
        <button type="submit" class="btn" id="btnAnnounce"><?= __('app.mass_mail.index.sections.announce.submit') ?></button>
      </div>
    </form>
    <p class="massmail-hint muted small"><?= __('app.mass_mail.index.sections.announce.hint') ?></p>
  </section>
  <?php else: ?>
  <section class="massmail-card massmail-card--announce"><div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.mass_mail.index.sections.announce.title')])) ?></div></section>
  <?php endif; ?>

  <?php if($massMailCapabilities['send']): ?>
  <section class="massmail-card massmail-card--send">
    <h3 class="massmail-card__title"><?= __('app.mass_mail.index.sections.send.title') ?></h3>
    <form id="massSendForm" class="massmail-form" novalidate>
      <div class="massmail-form-grid">
        <div class="massmail-field">
          <label for="mmAction"><?= __('app.mass_mail.index.sections.send.action_label') ?></label>
          <select name="action" id="mmAction" required>
            <option value=""><?= __('app.mass_mail.index.sections.send.action_placeholder') ?></option>
            <option value="send_mail"><?= __('app.mass_mail.index.sections.send.action_options.send_mail') ?></option>
            <option value="send_item"><?= __('app.mass_mail.index.sections.send.action_options.send_item') ?></option>
            <option value="send_gold"><?= __('app.mass_mail.index.sections.send.action_options.send_gold') ?></option>
            <option value="send_item_gold"><?= __('app.mass_mail.index.sections.send.action_options.send_item_gold') ?></option>
          </select>
        </div>
        <div class="massmail-field">
          <label for="mmTargetType"><?= __('app.mass_mail.index.sections.send.target_label') ?></label>
          <select name="target_type" id="mmTargetType">
            <option value="online"><?= __('app.mass_mail.index.sections.send.target_options.online') ?></option>
            <option value="custom"><?= __('app.mass_mail.index.sections.send.target_options.custom') ?></option>
          </select>
        </div>
        <div class="massmail-field">
          <label><?= __('app.mass_mail.index.sections.send.recipients_label') ?></label>
          <div class="mm-recipients" id="mmRecipients">
            <span class="mm-recipients__count" id="mmRecipientsCount">—</span>
            <span class="mm-recipients__detail" id="mmRecipientsDetail"><?= htmlspecialchars(__('app.mass_mail.index.sections.send.recipients_idle')) ?></span>
            <button type="button" class="btn btn-sm outline" id="mmRecipientsRefresh"><?= htmlspecialchars(__('app.mass_mail.index.sections.send.recipients_refresh')) ?></button>
          </div>
        </div>
        <div class="massmail-field full-span">
          <label for="mmSubject"><?= __('app.mass_mail.index.sections.send.subject_label') ?></label>
          <input type="text" name="subject" id="mmSubject" value="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.subject_default'), ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="massmail-field full-span">
          <label for="mmBody"><?= __('app.mass_mail.index.sections.send.body_label') ?></label>
          <textarea name="body" id="mmBody" rows="4"><?= htmlspecialchars(__('app.mass_mail.index.sections.send.body_default'), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="massmail-field full-span massmail-cond" data-for="send_item|send_item_gold">
          <label for="mmItems"><?= __('app.mass_mail.index.sections.send.items_label') ?></label>

          <div
            id="mmItemsEditor"
            class="massmail-items"
            data-remove-label="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.remove_item'), ENT_QUOTES, 'UTF-8') ?>"
            data-name-loading="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.item_name_loading'), ENT_QUOTES, 'UTF-8') ?>"
            data-name-unknown="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.item_name_unknown'), ENT_QUOTES, 'UTF-8') ?>"
            data-name-empty="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.item_name_empty'), ENT_QUOTES, 'UTF-8') ?>"
            data-name-duplicate="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.item_duplicate'), ENT_QUOTES, 'UTF-8') ?>"
          >
            <input type="hidden" name="items" id="mmItems" value="">
            <div class="massmail-items__grid massmail-items__head">
              <div><?= __('app.mass_mail.index.sections.send.item_id_label') ?></div>
              <div><?= __('app.mass_mail.index.sections.send.item_name_label') ?></div>
              <div class="massmail-items__head-actions"><?= __('app.mass_mail.index.sections.send.quantity_label') ?></div>
            </div>
            <div id="mmItemsBody"></div>
            <div class="massmail-items__actions">
              <button type="button" class="btn btn-sm outline" id="mmItemsAdd"><?= __('app.mass_mail.index.sections.send.add_item') ?></button>
              <button type="button" class="btn btn-sm outline" id="mmItemsClear"><?= __('app.mass_mail.index.sections.send.clear_items') ?></button>
            </div>
            <div class="mm-items-summary" id="mmItemsSummary" hidden>
              <span class="mm-items-summary__label"><?= htmlspecialchars(__('app.mass_mail.index.sections.send.items_preview_label')) ?></span>
              <span class="mm-items-summary__value" id="mmItemsSummaryValue"></span>
            </div>
          </div>

          <div class="massmail-hint muted small"><?= htmlspecialchars(__('app.mass_mail.index.sections.send.items_hint_short')) ?><span class="panel-hint" title="<?= htmlspecialchars(__('app.mass_mail.index.sections.send.items_hint')) ?>">i</span></div>
        </div>
        <div class="massmail-field massmail-cond" data-for="send_gold|send_item_gold">
          <label for="goldAmount"><?= __('app.mass_mail.index.sections.send.gold_label') ?></label>
          <input type="number" name="amount" id="goldAmount" min="1">
          <div class="massmail-gold-preview" id="goldPreview"><?= __('app.mass_mail.index.sections.send.gold_preview_placeholder') ?></div>
        </div>
        <div class="massmail-field massmail-custom full-span massmail-cond" data-for="custom">
          <label for="mmCustomList"><?= __('app.mass_mail.index.sections.send.custom_list_label') ?></label>
          <textarea name="custom_char_list" id="mmCustomList" rows="3"></textarea>
          <div class="mm-custom-count muted small" id="mmCustomCount"><?= __('app.mass_mail.index.sections.send.custom_count_idle') ?></div>
        </div>
      </div>
      <div class="massmail-actions massmail-actions--primary">
        <button type="submit" class="btn primary" id="btnMassSend"><?= __('app.mass_mail.index.sections.send.submit') ?></button>
      </div>
      <p class="massmail-hint muted small"><?= __('app.mass_mail.index.sections.send.hint') ?></p>
    </form>
  </section>
  <?php else: ?>
  <section class="massmail-card massmail-card--send"><div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.mass_mail.index.sections.send.title')])) ?></div></section>
  <?php endif; ?>
</div>

<?php if($massMailCapabilities['logs']): ?>
<section class="massmail-card massmail-card--logs">
  <div class="massmail-logs__header">
    <h3 class="massmail-card__title m-0"><?= __('app.mass_mail.index.sections.logs.title') ?></h3>
    <div class="massmail-logs__controls">
      <label class="massmail-field massmail-field--compact" for="logLimit">
        <span><?= __('app.mass_mail.index.sections.logs.limit_label') ?></span>
        <select id="logLimit">
          <option value="30"><?= __('app.mass_mail.index.sections.logs.limit_options.30') ?></option>
          <option value="50"><?= __('app.mass_mail.index.sections.logs.limit_options.50') ?></option>
          <option value="100"><?= __('app.mass_mail.index.sections.logs.limit_options.100') ?></option>
        </select>
      </label>
      <label class="massmail-field massmail-field--compact" for="logFilter">
        <span><?= __('app.mass_mail.index.sections.logs.filter_label') ?></span>
        <input type="search" id="logFilter" placeholder="<?= htmlspecialchars(__('app.mass_mail.index.sections.logs.filter_placeholder'), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <button class="btn-sm btn" id="btnLogsRefresh"><?= __('app.mass_mail.index.sections.logs.refresh') ?></button>
    </div>
  </div>
  <div class="massmail-logs__table">
    <table class="table" id="massMailLogTable">
      <thead><tr>
        <th class="massmail-logs__col-time"><?= __('app.mass_mail.index.sections.logs.table.headers.time') ?></th>
        <th class="massmail-logs__col-type"><?= __('app.mass_mail.index.sections.logs.table.headers.type') ?></th>
        <th><?= __('app.mass_mail.index.sections.logs.table.headers.details') ?></th>
        <th class="massmail-logs__col-targets"><?= __('app.mass_mail.index.sections.logs.table.headers.targets') ?></th>
        <th class="massmail-logs__col-result"><?= __('app.mass_mail.index.sections.logs.table.headers.success_fail') ?></th>
        <th class="massmail-logs__col-status"><?= __('app.mass_mail.index.sections.logs.table.headers.status') ?></th>
        <th class="massmail-logs__col-recipients"><?= __('app.mass_mail.index.sections.logs.table.headers.recipients') ?></th>
      </tr></thead>
      <tbody>
        <?php foreach(($logs??[]) as $lg): $ok=(int)$lg['success']===1; ?>
          <tr class="<?= $ok? 'log-ok':'log-fail' ?>">
            <td><?= htmlspecialchars(substr($lg['created_at'],0,19)) ?></td>
            <td><?= htmlspecialchars($lg['action']) ?></td>
            <td>
              <div class="strong"><?= htmlspecialchars($lg['subject']) ?></div>
              <?php if(!empty($lg['items'])): ?>
                <div class="small muted"><?= __('app.mass_mail.index.sections.logs.table.items_label', ['value' => htmlspecialchars($lg['items'])]) ?></div>
              <?php elseif(!empty($lg['item_id'])): ?>
                <div class="small muted"><?= __('app.mass_mail.index.sections.logs.table.item_prefix', ['id' => (int)$lg['item_id']]) ?><?= $lg['item_name']? __('app.mass_mail.index.sections.logs.table.item_name_separator').htmlspecialchars($lg['item_name']):'' ?><?php if(!empty($lg['quantity'])): ?> <?= __('app.mass_mail.index.sections.logs.table.item_quantity_prefix') ?><?= (int)$lg['quantity'] ?><?php endif; ?></div>
              <?php endif; ?>
              <?php if(!empty($lg['amount'])):
                $c=(int)$lg['amount'];
                $g=intdiv($c,10000);
                $rem=$c%10000;
                $s=intdiv($rem,100);
                $b=$rem%100;
                $parts=[];
                if($g>0){ $parts[] = __('app.mass_mail.index.sections.logs.table.gold_units.gold', ['value'=>$g]); }
                if($s>0){ $parts[] = __('app.mass_mail.index.sections.logs.table.gold_units.silver', ['value'=>$s]); }
                if($b>0 || !$parts){ $parts[] = __('app.mass_mail.index.sections.logs.table.gold_units.copper', ['value'=>$b]); }
              ?>
                <div class="small muted"><?= __('app.mass_mail.index.sections.logs.table.gold_label', ['value' => implode(__('app.mass_mail.index.sections.logs.table.gold_units.separator'), $parts)]) ?></div>
              <?php endif; ?>
              <?php if(!$ok && !empty($lg['sample_errors'])): ?>
                <div class="small text-danger" title="<?= htmlspecialchars($lg['sample_errors']) ?>"><?= __('app.mass_mail.index.sections.logs.table.error_prefix') ?><?= htmlspecialchars(mb_strimwidth($lg['sample_errors'],0,60,'...','UTF-8')) ?></div>
              <?php endif; ?>
            </td>
            <td><?= (int)$lg['targets'] ?></td>
            <td><?= (int)$lg['success_count'] ?>/<?= (int)$lg['fail_count'] ?></td>
            <td><?= $ok? '✔':'✖' ?></td>
            <td><?php if(!empty($lg['recipients'])){ $disp=$lg['recipients']; $short=mb_strlen($disp)>40? mb_substr($disp,0,40).'…':$disp; echo '<span title="'.htmlspecialchars($disp).'">'.htmlspecialchars($short).'</span>'; } else { echo '<span class="muted">'.__('app.mass_mail.index.sections.logs.table.recipients_placeholder').'</span>'; } ?></td>
          </tr>
        <?php endforeach; if(empty($logs)): ?>
          <tr><td colspan="7" class="text-center muted"><?= __('app.mass_mail.index.sections.logs.table.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php else: ?>
<section class="massmail-card massmail-card--logs"><div class="panel-flash panel-flash--info panel-flash--inline is-visible"><?= htmlspecialchars(__('app.common.capabilities.section_hidden', ['section' => __('app.mass_mail.index.sections.logs.title')])) ?></div></section>
<?php endif; ?>

<!-- Risk confirmation modal -->
<div id="mmConfirmModal" class="modal-backdrop">
  <div class="modal-panel massmail-confirm">
    <header class="massmail-confirm__header">
      <h3 class="massmail-card__title"><?= __('app.mass_mail.index.confirm.title') ?></h3>
      <button type="button" class="modal-close" data-close>&times;</button>
    </header>
    <div class="modal-body" id="mmConfirmBody"></div>
    <footer class="massmail-confirm__footer">
      <div class="massmail-confirm__hint muted small"><?= __('app.mass_mail.index.confirm.hint_html') ?></div>
      <div class="massmail-confirm__actions">
        <input
          type="text"
          id="mmConfirmInput"
          placeholder="<?= htmlspecialchars(__('app.mass_mail.index.confirm.input_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="off"
          spellcheck="false"
        >
        <button class="btn outline" type="button" data-close><?= __('app.mass_mail.index.confirm.cancel') ?></button>
        <button class="btn primary" type="button" id="mmConfirmOk" disabled><?= __('app.mass_mail.index.confirm.submit') ?></button>
      </div>
    </footer>
  </div>
</div>

