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

	// Determine which JS module script will be loaded for this page.
	$__scriptModule = null;
	if(isset($module) && is_string($module)){
		$__scriptModule = preg_replace('/[^A-Za-z0-9_]/', '', $module);
		if($__scriptModule === '') $__scriptModule = null;
	}
	$__keepModules = [];
	if($__scriptModule){
		$__keepModules = [$__scriptModule];
		if($__scriptModule === 'smart_ai_wizard') $__keepModules = ['smartai'];
		if($__scriptModule === 'soap_wizard') $__keepModules = ['soap'];
		// Some pages load extra JS module scripts dynamically.
		if($__scriptModule === 'item') $__keepModules[] = 'bitmask';
		if($__scriptModule === 'character') $__keepModules[] = 'bag_query';
	}
	$__keepModules = array_values(array_unique(array_filter($__keepModules)));
	$__keepMap = $__keepModules ? array_fill_keys($__keepModules, true) : [];

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
	];

	if($__keepModules){
		$jsLocale['modules'] = [];
	}

	if(isset($__keepMap['creature'])){
		$jsLocale['modules']['creature'] = \Acme\Panel\Core\Lang::getArray('app.js.modules.creature');
	}

	if(isset($__keepMap['item'])){
		$jsLocale['modules']['item'] = \Acme\Panel\Core\Lang::getArray('app.js.modules.item');
	}

	if(isset($__keepMap['account'])){
		$jsLocale['modules']['account'] = \Acme\Panel\Core\Lang::getArray('app.account');
	}

	if(isset($jsLocale['modules']) && is_array($jsLocale['modules'])){
		foreach(['logs','smartai','bag_query','quest','item_owner','bitmask','mail','mass_mail','soap'] as $__name){
			if(isset($__keepMap[$__name])){
				$jsLocale['modules'][$__name] = \Acme\Panel\Core\Lang::getArray('app.js.modules.' . $__name);
			}
		}
	}
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
