<?php
/**
 * File: resources/views/components/page_header.php
 * Purpose: Shared page header component for standard pages.
 */

$__pageHeader = is_array($__pageHeader ?? null) ? $__pageHeader : [];
$__pageHeaderTitle = trim((string)($__pageHeader['title'] ?? ''));
$__pageHeaderIntro = trim((string)($__pageHeader['intro'] ?? ''));
$__pageHeaderIntroHint = trim((string)($__pageHeader['intro_hint'] ?? ''));
$__pageHeaderNote = trim((string)($__pageHeader['note'] ?? ''));
$__pageHeaderNoteHint = trim((string)($__pageHeader['note_hint'] ?? ''));
$__pageHeaderActions = is_array($__pageHeader['actions'] ?? null) ? $__pageHeader['actions'] : [];

    // 面包屑由 PageMetadata::resolve() 提供（每个视图都有登记）。
$__pageBreadcrumbs = is_array($__pageMeta['breadcrumbs'] ?? null) ? $__pageMeta['breadcrumbs'] : [];

if ($__pageHeaderTitle === '' && $__pageHeaderIntro === '' && $__pageHeaderNote === '' && $__pageHeaderActions === [] && $__pageBreadcrumbs === []) {
    return;
}
?>
<?php if ($__pageBreadcrumbs !== []): ?>
  <?php $__pageBreadcrumbLast = count($__pageBreadcrumbs) - 1; ?>
  <nav class="breadcrumb" aria-label="<?= htmlspecialchars(__('app.nav.breadcrumb')) ?>">
    <ol class="breadcrumb__list">
      <?php foreach ($__pageBreadcrumbs as $__pageBreadcrumbIndex => $__pageBreadcrumb): ?>
        <?php
          $__pageBreadcrumbLabel = (string) ($__pageBreadcrumb['label'] ?? '');
          $__pageBreadcrumbUrl = (string) ($__pageBreadcrumb['url'] ?? '');
          $__pageBreadcrumbCurrent = $__pageBreadcrumbIndex === $__pageBreadcrumbLast;
        ?>
        <?php if ($__pageBreadcrumbLabel === '') { continue; } ?>
        <li class="breadcrumb__item">
          <?php if ($__pageBreadcrumbCurrent || $__pageBreadcrumbUrl === ''): ?>
            <span aria-current="page"><?= htmlspecialchars($__pageBreadcrumbLabel) ?></span>
          <?php else: ?>
            <a href="<?= htmlspecialchars($__pageBreadcrumbUrl) ?>"><?= htmlspecialchars($__pageBreadcrumbLabel) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>
<?php endif; ?>
<div class="page-header">
  <div class="page-header__main">
    <?php if ($__pageHeaderTitle !== ''): ?>
      <h1 class="page-title"><?= htmlspecialchars($__pageHeaderTitle) ?></h1>
    <?php endif; ?>
    <?php if ($__pageHeaderIntro !== ''): ?>
      <p class="page-header__intro muted">
        <?= htmlspecialchars($__pageHeaderIntro) ?>
        <?php if ($__pageHeaderIntroHint !== ''): ?><?= panel_hint($__pageHeaderIntroHint) ?><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php if ($__pageHeaderNote !== ''): ?>
      <div class="page-header__note muted">
        <?= htmlspecialchars($__pageHeaderNote) ?>
        <?php if ($__pageHeaderNoteHint !== ''): ?><?= panel_hint($__pageHeaderNoteHint) ?><?php endif; ?>
      </div>
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
