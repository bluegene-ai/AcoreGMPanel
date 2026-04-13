<?php
/**
 * File: resources/views/components/page_header.php
 * Purpose: Shared page header component for standard pages.
 */

$__pageHeader = is_array($__pageHeader ?? null) ? $__pageHeader : [];
$__pageHeaderTitle = trim((string)($__pageHeader['title'] ?? ''));
$__pageHeaderIntro = trim((string)($__pageHeader['intro'] ?? ''));
$__pageHeaderNote = trim((string)($__pageHeader['note'] ?? ''));
$__pageHeaderActions = is_array($__pageHeader['actions'] ?? null) ? $__pageHeader['actions'] : [];

if ($__pageHeaderTitle === '' && $__pageHeaderIntro === '' && $__pageHeaderNote === '' && $__pageHeaderActions === []) {
    return;
}
?>
<div class="page-header">
  <div class="page-header__main">
    <?php if ($__pageHeaderTitle !== ''): ?>
      <h1 class="page-title page-header__title"><?= htmlspecialchars($__pageHeaderTitle) ?></h1>
    <?php endif; ?>
    <?php if ($__pageHeaderIntro !== ''): ?>
      <p class="page-header__intro muted"><?= htmlspecialchars($__pageHeaderIntro) ?></p>
    <?php endif; ?>
    <?php if ($__pageHeaderNote !== ''): ?>
      <div class="page-header__note muted"><?= htmlspecialchars($__pageHeaderNote) ?></div>
    <?php endif; ?>
  </div>
  <?php if ($__pageHeaderActions !== []): ?>
    <div class="page-header__actions">
      <?php foreach ($__pageHeaderActions as $__pageHeaderAction): ?>
        <a
          href="<?= htmlspecialchars((string)($__pageHeaderAction['url'] ?? '#')) ?>"
          class="<?= htmlspecialchars((string)($__pageHeaderAction['class'] ?? 'btn')) ?>"
          <?= !empty($__pageHeaderAction['target']) ? ' target="' . htmlspecialchars((string)$__pageHeaderAction['target']) . '"' : '' ?>
          <?= !empty($__pageHeaderAction['rel']) ? ' rel="' . htmlspecialchars((string)$__pageHeaderAction['rel']) . '"' : '' ?>
        ><?= htmlspecialchars((string)($__pageHeaderAction['label'] ?? '')) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>