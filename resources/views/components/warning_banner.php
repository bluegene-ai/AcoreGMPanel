<?php
/**
 * File: resources/views/components/warning_banner.php
 * Purpose: Renders warning flash banners pulled from the shared flash store.
 */

$warningMessages = function_exists('flash_pull_type') ? flash_pull_type('warn') : [];
if (!is_array($warningMessages) || !$warningMessages) {
    return;
}

$bannerClass = isset($warningBannerClass) && trim((string) $warningBannerClass) !== ''
    ? trim((string) $warningBannerClass)
    : 'layout-warning-banner';
?>
<div class="alert alert-warning <?= htmlspecialchars($bannerClass, ENT_QUOTES, 'UTF-8') ?>">
	<?php foreach ($warningMessages as $warningMessage): ?>
		<?php if ((string) $warningMessage === '') continue; ?>
		<div><?= htmlspecialchars((string) $warningMessage, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endforeach; ?>
</div>