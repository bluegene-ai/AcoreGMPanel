<?php
/**
 * File: resources/views/setup/env.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

$page = is_array($setupPage ?? null) ? $setupPage : [];
$section = is_array($page['section'] ?? null) ? $page['section'] : [];
$language = is_array($page['language'] ?? null) ? $page['language'] : [];
$messages = is_array($page['messages'] ?? null) ? $page['messages'] : [];
$actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
?>
<section class="setup-section" aria-labelledby="setup-env-title">
  <div class="setup-section__header">
    <div>
      <h2 class="setup-section__title" id="setup-env-title"><?= htmlspecialchars((string)($section['title'] ?? __('app.setup.env.title'))) ?></h2>
      <p class="setup-section__hint"><?= htmlspecialchars((string)($section['hint'] ?? __('app.setup.env.hint'))) ?></p>
    </div>
    <span class="setup-section__pill"><?= htmlspecialchars((string)($section['pill'] ?? __('app.setup.env.pill'))) ?></span>
  </div>
  <div class="table-like">
    <?php foreach($checks as $k=>$c): ?>
      <?php $label = __('app.setup.env.checks.' . $k, [], $k); ?>
      <div class="table-like__row">
        <span><?= htmlspecialchars($label) ?><?= isset($c['require']) && $c['require']!=='' ? ' (' . htmlspecialchars($c['require']) . ')' : '' ?><?= isset($c['msg']) && $c['msg']? ' · '.htmlspecialchars($c['msg']):'' ?></span>
  <span class="badge <?= $c['ok']?'ok':'fail' ?>"><?= htmlspecialchars($c['ok'] ? __('app.setup.status.ok') : __('app.setup.status.fail')) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if($allOk): ?>
    <div class="alert success"><?= htmlspecialchars((string)($messages['passed'] ?? __('app.setup.env.check_passed'))) ?></div>
    <form id="setup-lang-form" class="setup-actions setup-lang" method="post" action="<?= url('/setup/post') ?>" data-submit-fail="<?= htmlspecialchars((string)($language['submit_fail'] ?? __('app.setup.env.language_submit_fail'))) ?>">
      <?= Acme\Panel\Support\Csrf::field() ?>
      <input type="hidden" name="action" value="lang_save">
      <div class="setup-lang__intro">
        <h3 class="setup-lang__title"><?= htmlspecialchars((string)($language['title'] ?? __('app.setup.env.language_title'))) ?></h3>
        <p class="setup-section__hint"><?= htmlspecialchars((string)($language['intro'] ?? __('app.setup.env.language_intro'))) ?></p>
      </div>
      <div class="mode-cards setup-lang__cards" role="radiogroup" aria-label="<?= htmlspecialchars((string)($language['title'] ?? __('app.setup.env.language_title'))) ?>">
        <?php foreach($locales as $locale): ?>
          <?php
            $isActive = $currentLocale === $locale;
            $localeLabel = __('app.common.languages.' . $locale, [], $locale);
            $localeCode = strtoupper(str_replace('_','-', $locale));
          ?>
          <label class="mode-card <?= $isActive ? 'active' : '' ?>" data-locale-card>
            <input type="radio" name="locale" value="<?= htmlspecialchars($locale) ?>" <?= $isActive?'checked':'' ?> aria-label="<?= htmlspecialchars($localeLabel) ?>">
            <div class="mode-card__title"><?= htmlspecialchars($localeLabel) ?></div>
            <p class="mode-card__desc"><?= htmlspecialchars(__('app.setup.env.language_hint', ['code'=>$localeCode])) ?></p>
          </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn primary" data-action="lang-submit"><?= htmlspecialchars((string)($language['submit'] ?? __('app.setup.env.language_submit'))) ?></button>
    </form>
  <?php else: ?>
    <div class="alert error"><?= htmlspecialchars((string)($messages['failed'] ?? __('app.setup.env.check_failed'))) ?></div>
    <div class="setup-actions">
      <a class="btn secondary" href="<?= url('/setup?step=1') ?>"><?= htmlspecialchars((string)($actions['retry'] ?? __('app.setup.env.retry'))) ?></a>
    </div>
  <?php endif; ?>
</section>
