<?php
/**
 * File: resources/views/home/index.php
 * Purpose: Provides functionality for the resources/views/home module.
 */

  ?>
<?php include __DIR__.'/../components/page_header.php'; ?>

<?php if (!empty($readmeHtml)): ?>
  <div class="markdown-wrapper">
    <article class="markdown-body">
      <?= $readmeHtml ?>
    </article>
    <?php if (!empty($readmeSource)): ?>
      <p class="readme-source">
        <?= htmlspecialchars(__('app.home.readme_source', ['file' => $readmeSource])) ?>
      </p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p class="home-empty-copy">
    <?= htmlspecialchars(__('app.home.readme_missing')) ?>
  </p>
<?php endif; ?>

