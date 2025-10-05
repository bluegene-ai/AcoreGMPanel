<?php
/**
 * File: resources/views/home/index.php
 * Purpose: Provides functionality for the resources/views/home module.
 */

  ?>
<?php include __DIR__.'/../layouts/base_top.php'; ?>
<?php $pageTitle = $title ?? __('app.home.readme_heading'); ?>
<h1 class="page-title" style="margin-bottom:20px;"><?= htmlspecialchars($pageTitle) ?></h1>

<?php if (!empty($readmeHtml)): ?>
  <div class="markdown-wrapper" style="background:#0b1620;border:1px solid #122130;border-radius:8px;padding:24px;box-shadow:0 8px 18px rgba(0,0,0,.25);">
    <article class="markdown-body">
      <?= $readmeHtml ?>
    </article>
    <?php if (!empty($readmeSource)): ?>
      <p class="readme-source" style="margin-top:24px;font-size:13px;color:#6ea8ff;">
        <?= htmlspecialchars(__('app.home.readme_source', ['file' => $readmeSource])) ?>
      </p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p style="color:#8ea2b2;">
    <?= htmlspecialchars(__('app.home.readme_missing')) ?>
  </p>
<?php endif; ?>

<style>
.markdown-body {
  color: #d3e3f5;
  line-height: 1.7;
}
.markdown-body h1,
.markdown-body h2,
.markdown-body h3 {
  color: #f2f5f9;
  margin-top: 28px;
  margin-bottom: 16px;
}
.markdown-body h1:first-child {
  margin-top: 0;
}
.markdown-body p {
  margin-bottom: 16px;
}
.markdown-body ul {
  padding-left: 20px;
  margin-bottom: 16px;
}
.markdown-body li {
  margin-bottom: 8px;
}
.markdown-body table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}
.markdown-body th,
.markdown-body td {
  border: 1px solid #1f3142;
  padding: 8px 12px;
  text-align: left;
}
.markdown-body th {
  background: #122130;
}
.markdown-body code {
  background: rgba(15, 25, 35, 0.8);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 90%;
}
.markdown-body pre {
  background: rgba(8, 14, 21, 0.85);
  border: 1px solid #1b2a3a;
  border-radius: 8px;
  padding: 16px;
  overflow: auto;
  margin-bottom: 20px;
}
.markdown-body a {
  color: #79c0ff;
  text-decoration: none;
}
.markdown-body a:hover {
  text-decoration: underline;
}
</style>

<?php include __DIR__.'/../layouts/base_bottom.php'; ?>
