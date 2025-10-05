<?php
/**
 * File: resources/views/setup/env.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

 ob_start(); ?>
<section class="setup-section" aria-labelledby="setup-env-title">
  <div class="setup-section__header">
    <div>
      <h2 class="setup-section__title" id="setup-env-title"><?= htmlspecialchars(__('app.setup.env.title')) ?></h2>
      <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.env.hint')) ?></p>
    </div>
    <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.env.pill')) ?></span>
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
    <div class="alert success"><?= htmlspecialchars(__('app.setup.env.check_passed')) ?></div>
    <form id="setup-lang-form" class="setup-actions setup-lang" method="post" action="<?= url('/setup/post') ?>">
      <?= Acme\Panel\Support\Csrf::field() ?>
      <input type="hidden" name="action" value="lang_save">
      <div class="setup-lang__intro">
        <h3 class="setup-lang__title"><?= htmlspecialchars(__('app.setup.env.language_title')) ?></h3>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.env.language_intro')) ?></p>
      </div>
      <div class="mode-cards setup-lang__cards" role="radiogroup" aria-label="<?= htmlspecialchars(__('app.setup.env.language_title')) ?>">
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
      <button type="submit" class="btn primary" data-action="lang-submit"><?= htmlspecialchars(__('app.setup.env.language_submit')) ?></button>
    </form>
  <?php else: ?>
    <div class="alert error"><?= htmlspecialchars(__('app.setup.env.check_failed')) ?></div>
    <div class="setup-actions">
      <a class="btn secondary" href="<?= url('/setup?step=1') ?>"><?= htmlspecialchars(__('app.setup.env.retry')) ?></a>
    </div>
  <?php endif; ?>
</section>
<script>
(function(){
  const form = document.getElementById('setup-lang-form');
  if(!form) return;
  const cards = form.querySelectorAll('[data-locale-card]');
  const updateActive = () => {
    cards.forEach(c => {
      const radio = c.querySelector('input');
      c.classList.toggle('active', !!(radio && radio.checked));
    });
  };
  cards.forEach(card => {
    const input = card.querySelector('input[type="radio"]');
    if(!input) return;
    card.addEventListener('click', (event) => {
      if(event.target.tagName !== 'INPUT'){
        input.checked = true;
        updateActive();
      }
    });
    input.addEventListener('change', updateActive);
  });
  updateActive();

  form.addEventListener('submit', function(event){
    event.preventDefault();
    const fd = new FormData(form);
    fetch('<?= url('/setup/post') ?>',{method:'POST',body:fd})
      .then(resp => resp.json())
      .then(json => {
        if(json && json.success){
          window.location.href = json.redirect;
        } else {
          alert((json && json.message) || '<?= addslashes(__('app.setup.env.language_submit_fail')) ?>');
        }
      })
      .catch(() => alert('<?= addslashes(__('app.setup.env.language_submit_fail')) ?>'));
  });
})();
</script>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php'; ?>
