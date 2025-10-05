<?php
/**
 * File: resources/views/layouts/base_bottom.php
 * Purpose: Provides functionality for the resources/views/layouts module.
 */
?>

</main>
</div>
<footer class="site-footer"><?= htmlspecialchars(__('app.app.footer_copyright', ['year' => date('Y')])) ?></footer>
<script>window.__CSRF_TOKEN = <?= json_encode(\Acme\Panel\Support\Csrf::token()) ?>;</script>
<?php



	$qualityCn = \Acme\Panel\Core\ItemQuality::allLocalized();
	$qualityCodes = [];
	foreach(array_keys($qualityCn) as $qNum){ $qualityCodes[$qNum]=\Acme\Panel\Core\ItemQuality::code($qNum); }
	$classNames = \Acme\Panel\Core\ItemMeta::classes();



	$subNames = [];
	$flagsReg = \Acme\Panel\Core\ItemFlags::regular();
	$flagsExtra = \Acme\Panel\Core\ItemFlags::extra();
	$flagsCustom = \Acme\Panel\Core\ItemFlags::custom();
	$jsLocale = [
		'common' => [
			'loading' => __('app.js.common.loading'),
			'no_data' => __('app.js.common.no_data'),
			'search_placeholder' => __('app.js.common.search_placeholder'),
			'errors' => [
				'network' => __('app.js.common.errors.network'),
				'timeout' => __('app.js.common.errors.timeout'),
				'invalid_json' => __('app.js.common.errors.invalid_json'),
				'unknown' => __('app.js.common.errors.unknown'),
			],
			'api' => [
				'errors' => [
					'request_failed' => __('app.common.api.errors.request_failed'),
					'request_failed_retry' => __('app.common.api.errors.request_failed_retry'),
					'request_failed_message' => __('app.common.api.errors.request_failed_message'),
					'request_failed_reason' => __('app.common.api.errors.request_failed_reason'),
					'unknown' => __('app.common.api.errors.unknown'),
				],
				'success' => [
					'generic' => __('app.common.api.success.generic'),
					'queued' => __('app.common.api.success.queued'),
				],
			],
			'actions' => [
				'close' => __('app.js.common.actions.close'),
				'confirm' => __('app.js.common.actions.confirm'),
				'cancel' => __('app.js.common.actions.cancel'),
				'retry' => __('app.js.common.actions.retry'),
			],
			'yes' => __('app.js.common.yes'),
			'no' => __('app.js.common.no'),
			],
			'modules' => [
				'creature' => [
					'create' => [
						'enter_new_id' => __('app.js.modules.creature.create.enter_new_id'),
						'success_redirect' => __('app.js.modules.creature.create.success_redirect'),
						'failure' => __('app.js.modules.creature.create.failure'),
						'failure_with_reason' => __('app.js.modules.creature.create.failure_with_reason'),
					],
					'logs' => [
						'loading_placeholder' => __('app.js.modules.creature.logs.loading_placeholder'),
						'empty_placeholder' => __('app.js.modules.creature.logs.empty_placeholder'),
						'load_failed_placeholder' => __('app.js.modules.creature.logs.load_failed_placeholder'),
						'load_failed' => __('app.js.modules.creature.logs.load_failed'),
						'load_failed_with_reason' => __('app.js.modules.creature.logs.load_failed_with_reason'),
					],
					'list' => [
						'confirm_delete' => __('app.js.modules.creature.list.confirm_delete'),
						'delete_success' => __('app.js.modules.creature.list.delete_success'),
						'delete_failed' => __('app.js.modules.creature.list.delete_failed'),
						'delete_failed_with_reason' => __('app.js.modules.creature.list.delete_failed_with_reason'),
					],
					'diff' => [
						'group_change_count' => __('app.js.modules.creature.diff.group_change_count'),
						'no_changes_placeholder' => __('app.js.modules.creature.diff.no_changes_placeholder'),
						'copy_sql_success' => __('app.js.modules.creature.diff.copy_sql_success'),
					],
					'common' => [
						'copy_failed' => __('app.js.modules.creature.common.copy_failed'),
					],
					'errors' => [
						'panel_api_not_ready' => __('app.js.modules.creature.errors.panel_api_not_ready'),
					],
					'exec' => [
						'actions' => [
							'clear' => __('app.js.modules.creature.exec.actions.clear'),
							'hide' => __('app.js.modules.creature.exec.actions.hide'),
							'copy_json' => __('app.js.modules.creature.exec.actions.copy_json'),
							'copy_sql' => __('app.js.modules.creature.exec.actions.copy_sql'),
						],
						'copy_json_success' => __('app.js.modules.creature.exec.copy_json_success'),
						'copy_sql_success' => __('app.js.modules.creature.exec.copy_sql_success'),
						'failure_with_reason' => __('app.js.modules.creature.exec.failure_with_reason'),
						'no_diff_sql' => __('app.js.modules.creature.exec.no_diff_sql'),
						'diff_sql_success' => __('app.js.modules.creature.exec.diff_sql_success'),
					],
					'models' => [
						'confirm_delete' => __('app.js.modules.creature.models.confirm_delete'),
						'save_success' => __('app.js.modules.creature.models.save_success'),
						'save_failed' => __('app.js.modules.creature.models.save_failed'),
						'save_failed_with_reason' => __('app.js.modules.creature.models.save_failed_with_reason'),
						'delete_success' => __('app.js.modules.creature.models.delete_success'),
						'delete_failed' => __('app.js.modules.creature.models.delete_failed'),
						'delete_failed_with_reason' => __('app.js.modules.creature.models.delete_failed_with_reason'),
					],
					'save' => [
						'no_changes' => __('app.js.modules.creature.save.no_changes'),
						'success' => __('app.js.modules.creature.save.success'),
						'failed' => __('app.js.modules.creature.save.failed'),
						'failed_with_reason' => __('app.js.modules.creature.save.failed_with_reason'),
						'confirm_delete_creature' => __('app.js.modules.creature.save.confirm_delete_creature'),
						'delete_success' => __('app.js.modules.creature.save.delete_success'),
						'delete_failed' => __('app.js.modules.creature.save.delete_failed'),
						'delete_failed_with_reason' => __('app.js.modules.creature.save.delete_failed_with_reason'),
					],
					'verify' => [
						'failure' => __('app.js.modules.creature.verify.failure'),
						'failure_with_reason' => __('app.js.modules.creature.verify.failure_with_reason'),
						'diff_bad' => __('app.js.modules.creature.verify.diff_bad'),
						'diff_ok' => __('app.js.modules.creature.verify.diff_ok'),
						'diff_summary' => __('app.js.modules.creature.verify.diff_summary'),
						'copy_update' => __('app.js.modules.creature.verify.copy_update'),
						'copied' => __('app.js.modules.creature.verify.copied'),
						'row_match' => __('app.js.modules.creature.verify.row_match'),
					],
					'nav' => [
						'auto_group_title' => __('app.js.modules.creature.nav.auto_group_title'),
					],
					'compact' => [
						'mode' => [
							'normal' => __('app.js.modules.creature.compact.mode.normal'),
							'compact' => __('app.js.modules.creature.compact.mode.compact'),
						],
					],
					'bitmask' => [
						'modal_title' => __('app.js.modules.creature.bitmask.modal_title'),
						'search_placeholder' => __('app.js.modules.creature.bitmask.search_placeholder'),
						'select_all' => __('app.js.modules.creature.bitmask.select_all'),
						'clear' => __('app.js.modules.creature.bitmask.clear'),
						'tips' => __('app.js.modules.creature.bitmask.tips'),
						'close' => __('app.js.modules.creature.bitmask.close'),
						'field_title' => __('app.js.modules.creature.bitmask.field_title'),
						'trigger' => __('app.js.modules.creature.bitmask.trigger'),
					],
				],
				'item' => [
					'errors' => [
						'request_failed_message' => __('app.common.api.errors.request_failed_message'),
						'request_failed' => __('app.common.api.errors.request_failed'),
						'request_failed_reason' => __('app.common.api.errors.request_failed_reason'),
					],
					'common' => [
						'copy_success' => __('app.js.modules.item.common.copy_success'),
					],
					'create' => [
						'enter_new_id' => __('app.js.modules.item.create.enter_new_id'),
						'success_redirect' => __('app.js.modules.item.create.success_redirect'),
						'failure' => __('app.js.modules.item.create.failure'),
						'failure_with_reason' => __('app.js.modules.item.create.failure_with_reason'),
						'subclass' => [
							'loading_option' => __('app.js.modules.item.create.subclass.loading_option'),
						],
					],
					'list' => [
						'confirm_delete' => __('app.js.modules.item.list.confirm_delete'),
						'delete_success' => __('app.js.modules.item.list.delete_success'),
						'delete_failed' => __('app.js.modules.item.list.delete_failed'),
						'delete_failed_with_reason' => __('app.js.modules.item.list.delete_failed_with_reason'),
						'subclass' => [
							'all_option' => __('app.js.modules.item.list.subclass.all_option'),
							'loading_option' => __('app.js.modules.item.list.subclass.loading_option'),
						],
					],
					'diff' => [
						'no_changes_comment' => __('app.js.modules.item.diff.no_changes_comment'),
						'no_changes_placeholder' => __('app.js.modules.item.diff.no_changes_placeholder'),
						'no_changes_to_execute' => __('app.js.modules.item.diff.no_changes_to_execute'),
						'comment' => [
							'class_fallback_name' => __('app.js.modules.item.diff.comment.class_fallback_name'),
							'class_label' => __('app.js.modules.item.diff.comment.class_label'),
							'subclass_fallback_name' => __('app.js.modules.item.diff.comment.subclass_fallback_name'),
							'subclass_label' => __('app.js.modules.item.diff.comment.subclass_label'),
						],
						'modal' => [
							'title' => __('app.js.modules.item.diff.modal.title'),
							'copy_button' => __('app.js.modules.item.diff.modal.copy_button'),
							'close_button' => __('app.js.modules.item.diff.modal.close_button'),
						],
					],
					'exec' => [
						'only_item_template_update' => __('app.js.modules.item.exec.only_item_template_update'),
						'confirm_run_diff' => __('app.js.modules.item.exec.confirm_run_diff'),
						'status' => [
							'success' => __('app.js.modules.item.exec.status.success'),
							'failed' => __('app.js.modules.item.exec.status.failed'),
						],
						'timing' => __('app.js.modules.item.exec.timing'),
						'summary' => [
							'rows_label' => __('app.js.modules.item.exec.summary.rows_label'),
						],
						'default_error' => __('app.js.modules.item.exec.default_error'),
						'warning_prefix' => __('app.js.modules.item.exec.warning_prefix'),
						'error_prefix' => __('app.js.modules.item.exec.error_prefix'),
						'messages' => [
							'none' => __('app.js.modules.item.exec.messages.none'),
							'check_above' => __('app.js.modules.item.exec.messages.check_above'),
						],
						'run_success' => __('app.js.modules.item.exec.run_success'),
						'run_failed_with_reason' => __('app.js.modules.item.exec.run_failed_with_reason'),
						'copy_json_success' => __('app.js.modules.item.exec.copy_json_success'),
						'request_exception' => __('app.js.modules.item.exec.request_exception'),
					],
					'logs' => [
						'loading_placeholder' => __('app.js.modules.item.logs.loading_placeholder'),
						'empty_placeholder' => __('app.js.modules.item.logs.empty_placeholder'),
						'load_failed_placeholder' => __('app.js.modules.item.logs.load_failed_placeholder'),
						'load_failed' => __('app.js.modules.item.logs.load_failed'),
						'load_failed_with_reason' => __('app.js.modules.item.logs.load_failed_with_reason'),
					],
					'save' => [
						'no_changes' => __('app.js.modules.item.save.no_changes'),
						'success' => __('app.js.modules.item.save.success'),
						'failed' => __('app.js.modules.item.save.failed'),
						'failed_with_reason' => __('app.js.modules.item.save.failed_with_reason'),
						'confirm_delete_item' => __('app.js.modules.item.save.confirm_delete_item'),
						'delete_success' => __('app.js.modules.item.save.delete_success'),
						'delete_failed' => __('app.js.modules.item.save.delete_failed'),
					],
				],
				'account' => [
					'errors' => [
						'request_failed_message' => __('app.common.api.errors.request_failed_message'),
						'request_failed' => __('app.common.api.errors.request_failed'),
					],
					'ip_lookup' => [
						'private' => __('app.account.ip_lookup.private'),
						'failed' => __('app.account.ip_lookup.failed'),
						'unknown' => __('app.account.ip_lookup.unknown'),
						'loading' => __('app.account.ip_lookup.loading'),
					],
					'ban' => [
						'permanent' => __('app.account.ban.permanent'),
						'soon' => __('app.account.ban.soon'),
						'duration' => [
							'day' => __('app.account.ban.duration.day'),
							'hour' => __('app.account.ban.duration.hour'),
							'minute' => __('app.account.ban.duration.minute'),
						],
						'under_minute' => __('app.account.ban.under_minute'),
						'separator' => __('app.account.ban.separator'),
						'tooltip' => __('app.account.ban.tooltip'),
						'badge' => __('app.account.ban.badge'),
						'no_end' => __('app.account.ban.no_end'),
						'prompt_hours' => __('app.account.ban.prompt_hours'),
						'error_hours' => __('app.account.ban.error_hours'),
						'prompt_reason' => __('app.account.ban.prompt_reason'),
						'default_reason' => __('app.account.ban.default_reason'),
						'success' => __('app.account.ban.success'),
						'failure' => __('app.account.ban.failure'),
						'confirm_unban' => __('app.account.ban.confirm_unban'),
						'unban_success' => __('app.account.ban.unban_success'),
						'unban_failure' => __('app.account.ban.unban_failure'),
					],
					'status' => [
						'online' => __('app.account.status.online'),
						'offline' => __('app.account.status.offline'),
					],
					'feedback' => [
						'private_ip_disabled' => __('app.account.feedback.private_ip_disabled'),
						'empty' => __('app.account.feedback.empty'),
					],
					'actions' => [
						'chars' => __('app.account.actions.chars'),
						'gm' => __('app.account.actions.gm'),
						'ban' => __('app.account.actions.ban'),
						'unban' => __('app.account.actions.unban'),
						'password' => __('app.account.actions.password'),
						'same_ip' => __('app.account.actions.same_ip'),
						'kick' => __('app.account.actions.kick'),
					],
					'characters' => [
						'title' => __('app.account.characters.title'),
						'loading' => __('app.account.characters.loading'),
						'fetch_error' => __('app.account.characters.fetch_error'),
						'table' => [
							'guid' => __('app.account.characters.table.guid'),
							'name' => __('app.account.characters.table.name'),
							'level' => __('app.account.characters.table.level'),
							'status' => __('app.account.characters.table.status'),
						],
						'kick_button' => __('app.account.characters.kick_button'),
						'offline_tooltip' => __('app.account.characters.offline_tooltip'),
						'empty' => __('app.account.characters.empty'),
						'ban_badge' => __('app.account.characters.ban_badge'),
						'confirm_kick' => __('app.account.characters.confirm_kick'),
						'kick_success' => __('app.account.characters.kick_success'),
						'kick_failed' => __('app.account.characters.kick_failed'),
						'fetch_failed' => __('app.account.characters.fetch_failed'),
					],
					'gm' => [
						'prompt_level' => __('app.account.gm.prompt_level'),
						'error_level' => __('app.account.gm.error_level'),
						'success' => __('app.account.gm.success'),
						'failure' => __('app.account.gm.failure'),
					],
					'password' => [
						'prompt_new' => __('app.account.password.prompt_new'),
						'error_empty' => __('app.account.password.error_empty'),
						'error_length' => __('app.account.password.error_length'),
						'prompt_confirm' => __('app.account.password.prompt_confirm'),
						'error_mismatch' => __('app.account.password.error_mismatch'),
						'success' => __('app.account.password.success'),
						'failure' => __('app.account.password.failure'),
						'failure_generic' => __('app.account.password.failure_generic'),
					],
					'create' => [
						'title' => __('app.account.create.title'),
						'labels' => [
							'username' => __('app.account.create.labels.username'),
							'password' => __('app.account.create.labels.password'),
							'password_confirm' => __('app.account.create.labels.password_confirm'),
							'email' => __('app.account.create.labels.email'),
							'gmlevel' => __('app.account.create.labels.gmlevel'),
						],
						'placeholders' => [
							'username' => __('app.account.create.placeholders.username'),
							'password' => __('app.account.create.placeholders.password'),
							'password_confirm' => __('app.account.create.placeholders.password_confirm'),
							'email' => __('app.account.create.placeholders.email'),
						],
						'gm_options' => [
							'player' => __('app.account.create.gm_options.player'),
							'one' => __('app.account.create.gm_options.one'),
							'two' => __('app.account.create.gm_options.two'),
							'three' => __('app.account.create.gm_options.three'),
						],
						'actions' => [
							'cancel' => __('app.account.create.actions.cancel'),
							'submit' => __('app.account.create.actions.submit'),
						],
						'status' => [
							'submitting' => __('app.account.create.status.submitting'),
						],
						'errors' => [
							'username_required' => __('app.account.create.errors.username_required'),
							'password_length' => __('app.account.create.errors.password_length'),
							'password_mismatch' => __('app.account.create.errors.password_mismatch'),
							'email_length' => __('app.account.create.errors.email_length'),
							'email_invalid' => __('app.account.create.errors.email_invalid'),
							'request_generic' => __('app.account.create.errors.request_generic'),
						],
						'success' => __('app.account.create.success'),
					],
					'same_ip' => [
						'missing_ip' => __('app.account.same_ip.missing_ip'),
						'title' => __('app.account.same_ip.title'),
						'loading' => __('app.account.same_ip.loading'),
						'empty' => __('app.account.same_ip.empty'),
						'table' => [
							'id' => __('app.account.same_ip.table.id'),
							'username' => __('app.account.same_ip.table.username'),
							'gm' => __('app.account.same_ip.table.gm'),
							'status' => __('app.account.same_ip.table.status'),
							'last_login' => __('app.account.same_ip.table.last_login'),
							'ip_location' => __('app.account.same_ip.table.ip_location'),
						],
						'status' => [
							'banned' => __('app.account.same_ip.status.banned'),
							'remaining' => __('app.account.same_ip.status.remaining'),
						],
						'error_generic' => __('app.account.same_ip.error_generic'),
						'error' => __('app.account.same_ip.error'),
					],
				],
			'logs' => [
				'summary' => [
					'module' => __('app.js.modules.logs.summary.module'),
					'type' => __('app.js.modules.logs.summary.type'),
					'source' => __('app.js.modules.logs.summary.source'),
					'display' => __('app.js.modules.logs.summary.display'),
					'separator' => __('app.js.modules.logs.summary.separator'),
				],
				'status' => [
					'no_entries' => __('app.js.modules.logs.status.no_entries'),
					'panel_not_ready' => __('app.js.modules.logs.status.panel_not_ready'),
					'panel_waiting' => __('app.js.modules.logs.status.panel_waiting'),
					'load_failed' => __('app.js.modules.logs.status.load_failed'),
					'no_raw' => __('app.js.modules.logs.status.no_raw'),
					'request_error' => __('app.js.modules.logs.status.request_error'),
					'exception_prefix' => __('app.js.modules.logs.status.exception_prefix'),
					'error_prefix' => __('app.js.modules.logs.status.error_prefix'),
					'info_prefix' => __('app.js.modules.logs.status.info_prefix'),
				],
				'actions' => [
					'auto_on' => __('app.js.modules.logs.actions.auto_on'),
					'auto_off' => __('app.js.modules.logs.actions.auto_off'),
				],
			],
			'mail' => \Acme\Panel\Core\Lang::getArray('app.mail'),
			'mass_mail' => \Acme\Panel\Core\Lang::getArray('app.mass_mail'),
			'soap' => [
				'meta' => [
					'updated_at' => __('app.js.modules.soap.meta.updated_at'),
					'source_link' => __('app.js.modules.soap.meta.source_link'),
					'source_label' => __('app.js.modules.soap.meta.source_label'),
					'separator' => __('app.js.modules.soap.meta.separator'),
				],
				'categories' => [
					'all' => [
						'label' => __('app.js.modules.soap.categories.all.label'),
						'summary' => __('app.js.modules.soap.categories.all.summary'),
					],
				],
				'list' => [
					'empty' => __('app.js.modules.soap.list.empty'),
				],
				'risk' => [
					'badge' => [
						'low' => __('app.js.modules.soap.risk.badge.low'),
						'medium' => __('app.js.modules.soap.risk.badge.medium'),
						'high' => __('app.js.modules.soap.risk.badge.high'),
						'unknown' => __('app.js.modules.soap.risk.badge.unknown'),
					],
					'short' => [
						'low' => __('app.js.modules.soap.risk.short.low'),
						'medium' => __('app.js.modules.soap.risk.short.medium'),
						'high' => __('app.js.modules.soap.risk.short.high'),
						'unknown' => __('app.js.modules.soap.risk.short.unknown'),
					],
				],
				'fields' => [
					'empty' => __('app.js.modules.soap.fields.empty'),
				],
				'errors' => [
					'missing_required' => __('app.js.modules.soap.errors.missing_required'),
					'unknown_response' => __('app.js.modules.soap.errors.unknown_response'),
				],
				'form' => [
					'error_joiner' => __('app.js.modules.soap.form.error_joiner'),
				],
				'feedback' => [
					'execute_success' => __('app.js.modules.soap.feedback.execute_success'),
					'execute_failed' => __('app.js.modules.soap.feedback.execute_failed'),
				],
				'output' => [
					'unknown_time' => __('app.js.modules.soap.output.unknown_time'),
					'meta' => __('app.js.modules.soap.output.meta'),
					'empty' => __('app.js.modules.soap.output.empty'),
				],
				'copy' => [
					'empty' => __('app.js.modules.soap.copy.empty'),
					'success' => __('app.js.modules.soap.copy.success'),
					'failure' => __('app.js.modules.soap.copy.failure'),
				],
			],
		],
	];
?>
<script>
window.PANEL_LOCALE = <?= json_encode($jsLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script>
window.APP_ENUMS = Object.freeze({
	qualities: <?= json_encode($qualityCn, JSON_UNESCAPED_UNICODE) ?>,
	qualityCodes: <?= json_encode($qualityCodes, JSON_UNESCAPED_UNICODE) ?>,
	classes: <?= json_encode($classNames, JSON_UNESCAPED_UNICODE) ?>,
	subclasses: <?= json_encode($subNames, JSON_UNESCAPED_UNICODE) ?>,
	flags: {
		regular: <?= json_encode($flagsReg, JSON_UNESCAPED_UNICODE) ?>,
		extra: <?= json_encode($flagsExtra, JSON_UNESCAPED_UNICODE) ?>,
		custom: <?= json_encode($flagsCustom, JSON_UNESCAPED_UNICODE) ?>
	}
});
</script>
<?php


?>
<script src="<?= function_exists('asset')?asset('js/panel.js'):'/assets/js/panel.js' ?>"></script>
<?php
$__panelMetricsText = null;
$__panelMetricsTitle = null;
if(defined('PANEL_START_TIME')){
	$__elapsedMs = (microtime(true) - PANEL_START_TIME) * 1000;
	$__elapsedLabel = $__elapsedMs >= 1000 ? (round($__elapsedMs/1000, 2) . ' s') : (round($__elapsedMs, 0) . ' ms');
	$__startMemory = defined('PANEL_START_MEMORY') ? PANEL_START_MEMORY : memory_get_usage(true);
	$__peakBytes = memory_get_peak_usage(true);
	$__deltaBytes = max($__peakBytes - $__startMemory, 0);
	if($__deltaBytes >= 1048576){
		$__memoryLabel = round($__deltaBytes / 1048576, 2) . ' MB';
	}else{
		$__memoryLabel = round($__deltaBytes / 1024, 0) . ' KB';
	}
	$__panelMetricsText = __('app.app.metrics_text', [
		'time' => $__elapsedLabel,
		'memory' => $__memoryLabel,
	]);
	$__panelMetricsTitle = __('app.app.metrics_title', [
		'ms' => round($__elapsedMs, 2),
		'mb' => round($__peakBytes / 1048576, 2),
	]);
}
?>
<?php if($__panelMetricsText): ?>
<script>
(function(){
	var el=document.getElementById('sidebar-metrics');
	if(!el) return;
	var span=el.querySelector('span');
	var text=<?= json_encode($__panelMetricsText, JSON_UNESCAPED_UNICODE) ?>;
	if(span){ span.textContent=text; } else { el.textContent=text; }
	el.setAttribute('title', <?= json_encode($__panelMetricsTitle, JSON_UNESCAPED_UNICODE) ?>);
})();
</script>
<?php endif; ?>
<script>
 (function(){
	 const m=document.body.getAttribute('data-module');
	 if(!m) return;
	 const s=document.createElement('script');
	 var base=(window.APP_BASE||'').replace(/\/$/,'');
	 s.src= (base?base:'') + '/assets/js/modules/'+m+'.js';
	 document.currentScript.parentNode.insertBefore(s, document.currentScript.nextSibling);
 })();
</script>
<script>
(function(){
	const select=document.getElementById('panelLanguageSelect');
	if(!select) return;
	select.addEventListener('change', function(){
		var base=window.location.href;
		var url=new URL(base, window.location.origin);
		url.searchParams.set('lang', this.value);
		window.location.href = url.toString();
	});
})();
</script>
</body></html>
