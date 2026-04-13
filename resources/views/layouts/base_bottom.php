<?php
/**
 * File: resources/views/layouts/base_bottom.php
 * Purpose: Provides functionality for the resources/views/layouts module.
 */

use Acme\Panel\Support\ModuleAssets;

$__pageModule = ModuleAssets::pageModule($__viewName ?? null, $module ?? null);
?>

</div>
</main>
</div>
</div>
<footer class="site-footer"><?= htmlspecialchars(__('app.app.footer_copyright', ['year' => date('Y')])) ?></footer>
<?php
	$__jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	$__pageCapabilities = isset($__pageCapabilities) && is_array($__pageCapabilities)
		? $__pageCapabilities
		: [];
	$__clientGlobals = ModuleAssets::clientGlobalsForPage($__pageModule, $__pageCapabilities);
?>
<?php foreach($__clientGlobals as $__clientGlobal): ?>
	<?php $__payloadJson = json_encode($__clientGlobal['value'] ?? null, $__jsonFlags); ?>
<script type="application/json" data-panel-json data-global="<?= htmlspecialchars((string)($__clientGlobal['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"<?= !empty($__clientGlobal['freeze']) ? ' data-freeze="true"' : '' ?>><?= $__payloadJson ?></script>
<?php endforeach; ?>
<?php
$__panelMetricsText = null;
$__panelMetricsTitle = null;
$__panelMetricsJson = null;
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
	$__panelMetricsJson = json_encode([
		'text' => $__panelMetricsText,
		'title' => $__panelMetricsTitle,
	], $__jsonFlags);
}
?>
<?php if($__panelMetricsText): ?>
<script type="application/json" data-panel-json data-global="PANEL_METRICS"><?= $__panelMetricsJson ?></script>
<?php endif; ?>
<?php $__panelScriptSrc = ModuleAssets::panelScriptUrl(); ?>
<script src="<?= $__panelScriptSrc ?>"></script>
</body></html>
