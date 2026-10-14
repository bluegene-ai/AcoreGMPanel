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
		<?php $warningFull = trim((string) $warningMessage); $warningBrief = panel_brief($warningFull); ?>
		<div<?= $warningBrief === $warningFull ? '' : ' title="' . htmlspecialchars($warningFull, ENT_QUOTES, 'UTF-8') . '"' ?>><?= htmlspecialchars($warningBrief, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endforeach; ?>
</div>