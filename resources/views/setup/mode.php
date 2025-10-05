<?php
/**
 * File: resources/views/setup/mode.php
 * Purpose: Provides functionality for the resources/views/setup module.
 */

ob_start();
$s = $state;
$mode = $s['mode'] ?? 'single';
$authDefaults = $s['auth'] ?? [];
$shared = $s['shared'] ?? [];
$sharedDbMode = $shared['db']['mode'] ?? 'shared';
$sharedDbCharacters = $shared['db']['characters'] ?? [];
$sharedDbWorld = $shared['db']['world'] ?? [];
$sharedDbCharacters += [
  'port' => $authDefaults['port'] ?? 3306,
  'username' => $authDefaults['username'] ?? '',
  'password' => $authDefaults['password'] ?? '',
];
$sharedDbWorld += [
  'port' => $sharedDbCharacters['port'] ?? ($authDefaults['port'] ?? 3306),
  'username' => $sharedDbCharacters['username'] ?? ($authDefaults['username'] ?? ''),
  'password' => $sharedDbCharacters['password'] ?? ($authDefaults['password'] ?? ''),
];
$sharedSoapMode = $shared['soap']['mode'] ?? 'shared';
$globalSoap = $s['soap'] ?? [];
$sharedSoap = [
  'host' => $globalSoap['host'] ?? '127.0.0.1',
  'port' => $globalSoap['port'] ?? 7878,
  'username' => $globalSoap['username'] ?? 'soap_user',
  'password' => $globalSoap['password'] ?? 'soap_pass',
  'uri' => $globalSoap['uri'] ?? 'urn:AC',
];

$cardTagsSingle = [
    __('app.setup.mode.cards.single.tags.shared_account'),
    __('app.setup.mode.cards.single.tags.single_realm'),
    __('app.setup.mode.cards.single.tags.low_maintenance'),
];
$cardTagsMulti = [
    __('app.setup.mode.cards.multi.tags.shared_auth'),
    __('app.setup.mode.cards.multi.tags.split_characters'),
    __('app.setup.mode.cards.multi.tags.port_reuse'),
];
$cardTagsMultiFull = [
    __('app.setup.mode.cards.multi_full.tags.full_isolation'),
    __('app.setup.mode.cards.multi_full.tags.security'),
    __('app.setup.mode.cards.multi_full.tags.high_complexity'),
];

$modeCards = [
    'single' => [
        'value' => 'single',
        'title' => __('app.setup.mode.cards.single.title'),
        'badge' => __('app.setup.mode.cards.single.badge'),
        'desc' => __('app.setup.mode.cards.single.desc'),
        'aria' => __('app.setup.mode.cards.single.aria'),
        'tags' => $cardTagsSingle,
    ],
    'multi' => [
        'value' => 'multi',
        'title' => __('app.setup.mode.cards.multi.title'),
        'badge' => __('app.setup.mode.cards.multi.badge'),
        'desc' => __('app.setup.mode.cards.multi.desc'),
        'aria' => __('app.setup.mode.cards.multi.aria'),
        'tags' => $cardTagsMulti,
    ],
    'multi_full' => [
        'value' => 'multi-full',
        'title' => __('app.setup.mode.cards.multi_full.title'),
        'badge' => __('app.setup.mode.cards.multi_full.badge'),
        'desc' => __('app.setup.mode.cards.multi_full.desc'),
        'aria' => __('app.setup.mode.cards.multi_full.aria'),
        'tags' => $cardTagsMultiFull,
    ],
];

$matrixColumns = [
    'type',
    'auth_db',
    'auth_port',
    'auth_credentials',
    'characters_db',
    'characters_port',
    'characters_credentials',
    'world_db',
    'world_port',
    'world_credentials',
    'soap_credentials',
    'soap_port',
];
$matrixHead = array_map(fn ($key) => __('app.setup.mode.matrix.head.' . $key), $matrixColumns);
$matrixRows = [
    array_map(fn ($key) => __('app.setup.mode.matrix.rows.single.' . $key), $matrixColumns),
    array_map(fn ($key) => __('app.setup.mode.matrix.rows.multi.' . $key), $matrixColumns),
    array_map(fn ($key) => __('app.setup.mode.matrix.rows.multi_full.' . $key), $matrixColumns),
];

$jsLocale = [
    'realm' => [
        'title_prefix' => __('app.setup.mode.realm.title_prefix'),
        'remove' => __('app.setup.mode.realm.remove'),
        'name_label' => __('app.setup.mode.realm.name_label'),
        'name_placeholder' => __('app.setup.mode.realm.name_placeholder'),
        'inherit' => __('app.setup.mode.realm.inherit'),
        'auth' => __('app.setup.mode.realm.auth'),
        'auth_summary' => [
            'host' => __('app.setup.mode.fields.host'),
            'port' => __('app.setup.mode.fields.port'),
            'database' => __('app.setup.mode.fields.database'),
            'user' => __('app.setup.mode.fields.user'),
            'password' => __('app.setup.mode.fields.password'),
        ],
        'auth_placeholders' => [
            'inherit_main' => __('app.setup.mode.realm.auth_placeholders.inherit_main'),
        ],
        'characters' => [
            'title' => __('app.setup.mode.realm.characters.title'),
            'port' => __('app.setup.mode.fields.port'),
            'database' => __('app.setup.mode.fields.database'),
            'user' => __('app.setup.mode.fields.user'),
            'password' => __('app.setup.mode.fields.password'),
        ],
        'world' => [
            'title' => __('app.setup.mode.realm.world.title'),
            'port' => __('app.setup.mode.fields.port'),
            'database' => __('app.setup.mode.fields.database'),
            'user' => __('app.setup.mode.fields.user'),
            'password' => __('app.setup.mode.fields.password'),
        ],
        'soap' => [
            'title' => __('app.setup.mode.realm.soap.title'),
            'host' => __('app.setup.mode.fields.host'),
            'port' => __('app.setup.mode.fields.port'),
            'user' => __('app.setup.mode.fields.user'),
            'password' => __('app.setup.mode.fields.password'),
            'uri' => __('app.setup.mode.fields.uri'),
        ],
        'soap_placeholder' => __('app.setup.mode.realm.soap_placeholder'),
        'empty' => __('app.setup.mode.realm.empty'),
        'summary' => __('app.setup.mode.realm.summary'),
        'summary_ids' => __('app.setup.mode.realm.summary_ids'),
        'meta' => [
            'id' => __('app.setup.mode.realm.meta.id'),
            'port' => __('app.setup.mode.realm.meta.port'),
        ],
        'refresh_fail' => __('app.setup.mode.actions.refresh_fail'),
        'request_fail' => __('app.setup.mode.actions.request_fail'),
        'save_fail' => __('app.setup.mode.actions.save_fail'),
        'unknown_error' => __('app.setup.mode.actions.unknown_error'),
    ],
    'actions' => [
        'manual_disabled' => __('app.setup.mode.actions.manual_disabled'),
    ],
];
?>
<h3><?= htmlspecialchars(__('app.setup.mode.step_title', ['current' => 2, 'total' => 5])) ?></h3>
<form id="mode-form" class="setup-form">
  <?= Acme\Panel\Support\Csrf::field() ?>
  <input type="hidden" name="action" value="mode_save">

  <section class="setup-section">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars(__('app.setup.mode.section.mode.title')) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.mode.section.mode.hint')) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.mode.section.mode.pill')) ?></span>
    </div>
    <div class="mode-cards" role="radiogroup" aria-label="<?= htmlspecialchars(__('app.setup.mode.section.mode.aria_group')) ?>">
      <?php foreach ($modeCards as $card): ?>
        <?php $value = $card['value']; $isActive = $mode === $value; ?>
        <label class="mode-card <?= $isActive ? 'active' : '' ?>" data-mode="<?= htmlspecialchars($value) ?>">
          <input type="radio" name="mode" value="<?= htmlspecialchars($value) ?>" <?= $isActive ? 'checked' : '' ?> aria-label="<?= htmlspecialchars($card['aria']) ?>">
          <div class="mode-card__title">
            <?= htmlspecialchars($card['title']) ?>
            <?php if (!empty($card['badge'])): ?>
              <span class="mode-card__badge"><?= htmlspecialchars($card['badge']) ?></span>
            <?php endif; ?>
          </div>
          <p class="mode-card__desc"><?= htmlspecialchars($card['desc']) ?></p>
          <?php if (!empty($card['tags'])): ?>
            <div class="mode-card__meta">
              <?php foreach ($card['tags'] as $tag): ?>
                <span class="mode-card__tag"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="mode-matrix" aria-label="<?= htmlspecialchars(__('app.setup.mode.matrix.aria')) ?>">
    <table>
      <thead>
        <tr>
          <?php foreach ($matrixHead as $headCell): ?>
            <th><?= htmlspecialchars($headCell) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrixRows as $row): ?>
          <tr>
            <?php foreach ($row as $cell): ?>
              <td><?= htmlspecialchars($cell) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="setup-summary"><?= htmlspecialchars(__('app.setup.mode.matrix.hint')) ?></p>
  </section>

  <section class="setup-section">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars(__('app.setup.mode.section.auth.title')) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.mode.section.auth.hint')) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.mode.section.auth.pill')) ?></span>
    </div>
    <div class="setup-grid setup-grid--compact">
      <div class="setup-field">
        <label for="auth_host"><?= htmlspecialchars(__('app.setup.mode.fields.host')) ?></label>
        <input id="auth_host" name="auth_host" value="<?= htmlspecialchars($s['auth']['host'] ?? '127.0.0.1') ?>" placeholder="127.0.0.1">
      </div>
      <div class="setup-field">
        <label for="auth_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
        <input id="auth_port" type="number" name="auth_port" value="<?= htmlspecialchars((string)($s['auth']['port'] ?? 3306)) ?>" min="1" max="65535">
      </div>
      <div class="setup-field">
        <label for="auth_db"><?= htmlspecialchars(__('app.setup.mode.fields.database')) ?></label>
        <input id="auth_db" name="auth_db" value="<?= htmlspecialchars($s['auth']['database'] ?? 'auth_db') ?>" placeholder="auth">
      </div>
      <div class="setup-field">
        <label for="auth_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
        <input id="auth_user" name="auth_user" value="<?= htmlspecialchars($s['auth']['username'] ?? 'root') ?>" placeholder="root">
      </div>
      <div class="setup-field">
        <label for="auth_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
        <input id="auth_pass" type="password" name="auth_pass" value="<?= htmlspecialchars($s['auth']['password'] ?? '') ?>" placeholder="••••••">
      </div>
    </div>
  </section>

  <section class="setup-section" data-mode-visibility="multi">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars(__('app.setup.mode.section.shared_db.title')) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.mode.section.shared_db.hint')) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.mode.section.shared_db.pill')) ?></span>
    </div>
    <div class="setup-toggle" role="radiogroup" aria-label="<?= htmlspecialchars(__('app.setup.mode.section.shared_db.toggle_aria')) ?>">
      <label class="setup-toggle__option">
        <input type="radio" name="shared_db_mode" value="shared" <?= $sharedDbMode !== 'custom' ? 'checked' : '' ?>>
        <span><?= htmlspecialchars(__('app.setup.mode.section.shared_db.toggle_shared')) ?></span>
      </label>
      <label class="setup-toggle__option">
        <input type="radio" name="shared_db_mode" value="custom" <?= $sharedDbMode === 'custom' ? 'checked' : '' ?>>
        <span><?= htmlspecialchars(__('app.setup.mode.section.shared_db.toggle_custom')) ?></span>
      </label>
    </div>
    <div class="setup-grid setup-grid--compact" data-shared-db-fields>
      <div class="setup-subpanel">
        <h3><?= htmlspecialchars(__('app.setup.mode.section.shared_db.characters.title')) ?></h3>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field">
            <label for="shared_char_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
            <input id="shared_char_port" type="number" name="shared_char_port" value="<?= htmlspecialchars((string)($sharedDbCharacters['port'] ?? 3306)) ?>" min="1" max="65535">
          </div>
          <div class="setup-field">
            <label for="shared_char_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
            <input id="shared_char_user" name="shared_char_user" value="<?= htmlspecialchars($sharedDbCharacters['username'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
          </div>
          <div class="setup-field">
            <label for="shared_char_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
            <input id="shared_char_pass" type="password" name="shared_char_pass" value="<?= htmlspecialchars($sharedDbCharacters['password'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
          </div>
        </div>
      </div>
      <div class="setup-subpanel">
        <h3><?= htmlspecialchars(__('app.setup.mode.section.shared_db.world.title')) ?></h3>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field">
            <label for="shared_world_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
            <input id="shared_world_port" type="number" name="shared_world_port" value="<?= htmlspecialchars((string)($sharedDbWorld['port'] ?? 3306)) ?>" min="1" max="65535">
          </div>
          <div class="setup-field">
            <label for="shared_world_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
            <input id="shared_world_user" name="shared_world_user" value="<?= htmlspecialchars($sharedDbWorld['username'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
          </div>
          <div class="setup-field">
            <label for="shared_world_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
            <input id="shared_world_pass" type="password" name="shared_world_pass" value="<?= htmlspecialchars($sharedDbWorld['password'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
          </div>
        </div>
      </div>
    </div>
    <p
      class="setup-summary"
      data-shared-db-summary
      data-summary-shared="<?= htmlspecialchars(__('app.setup.mode.section.shared_db.summary_shared')) ?>"
      data-summary-custom="<?= htmlspecialchars(__('app.setup.mode.section.shared_db.summary_custom')) ?>"
    ></p>
  </section>

  <section class="setup-section" data-mode-visibility="single">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars(__('app.setup.mode.section.single_realm.title')) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.mode.section.single_realm.hint')) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.mode.section.single_realm.pill')) ?></span>
    </div>
    <div class="setup-grid">
      <div class="setup-subpanel">
        <h3><?= htmlspecialchars(__('app.setup.mode.section.single_realm.characters.title')) ?></h3>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field">
            <label for="char_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
            <input id="char_port" type="number" name="char_port" value="<?= htmlspecialchars((string)($s['characters']['port'] ?? 3306)) ?>" min="1" max="65535">
          </div>
          <div class="setup-field">
            <label for="char_db"><?= htmlspecialchars(__('app.setup.mode.fields.database')) ?></label>
            <input id="char_db" name="char_db" value="<?= htmlspecialchars($s['characters']['database'] ?? 'characters_db') ?>" placeholder="characters">
          </div>
        </div>
        <details class="setup-collapsible">
          <summary><?= htmlspecialchars(__('app.setup.mode.section.single_realm.advanced_auth')) ?></summary>
          <div class="setup-grid setup-grid--compact">
            <div class="setup-field">
              <label for="char_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
              <input id="char_user" name="char_user" value="<?= htmlspecialchars($s['characters']['username'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
            </div>
            <div class="setup-field">
              <label for="char_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
              <input id="char_pass" type="password" name="char_pass" value="<?= htmlspecialchars($s['characters']['password'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
            </div>
          </div>
        </details>
      </div>

      <div class="setup-subpanel">
        <h3><?= htmlspecialchars(__('app.setup.mode.section.single_realm.world.title')) ?></h3>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field">
            <label for="world_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
            <input id="world_port" type="number" name="world_port" value="<?= htmlspecialchars((string)($s['world']['port'] ?? 3306)) ?>" min="1" max="65535">
          </div>
          <div class="setup-field">
            <label for="world_db"><?= htmlspecialchars(__('app.setup.mode.fields.database')) ?></label>
            <input id="world_db" name="world_db" value="<?= htmlspecialchars($s['world']['database'] ?? 'world_db') ?>" placeholder="world">
          </div>
        </div>
        <details class="setup-collapsible">
          <summary><?= htmlspecialchars(__('app.setup.mode.section.single_realm.advanced_auth')) ?></summary>
          <div class="setup-grid setup-grid--compact">
            <div class="setup-field">
              <label for="world_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
              <input id="world_user" name="world_user" value="<?= htmlspecialchars($s['world']['username'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
            </div>
            <div class="setup-field">
              <label for="world_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
              <input id="world_pass" type="password" name="world_pass" value="<?= htmlspecialchars($s['world']['password'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('app.setup.mode.placeholders.inherit_auth')) ?>">
            </div>
          </div>
        </details>
      </div>
    </div>
  </section>

  <section class="setup-section" data-mode-visibility="multi,multi-full">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars(__('app.setup.mode.section.realms.title')) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.mode.section.realms.hint')) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.mode.section.realms.pill')) ?></span>
    </div>
    <div class="setup-inline-actions">
      <button type="button" class="btn sm minor" id="refresh-realms"><?= htmlspecialchars(__('app.setup.mode.actions.refresh')) ?></button>
      <button type="button" class="btn sm secondary" id="add-realm"><?= htmlspecialchars(__('app.setup.mode.actions.manual')) ?></button>
      <span class="setup-summary"><?= htmlspecialchars(__('app.setup.mode.actions.tip')) ?></span>
    </div>
    <p
      class="setup-summary"
      data-shared-mode-note
      data-note-shared="<?= htmlspecialchars(__('app.setup.mode.section.shared_realm.note_shared')) ?>"
      data-note-custom="<?= htmlspecialchars(__('app.setup.mode.section.shared_realm.note_custom')) ?>"
    ></p>
    <div id="realm-summary" class="setup-summary" style="margin-top:10px;"></div>
    <div id="realm-list" class="realm-grid"></div>
  </section>

  <section class="setup-section">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars(__('app.setup.mode.section.soap.title')) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars(__('app.setup.mode.section.soap.hint')) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars(__('app.setup.mode.section.soap.pill')) ?></span>
    </div>
    <div class="setup-toggle" data-mode-visibility="multi" role="radiogroup" aria-label="<?= htmlspecialchars(__('app.setup.mode.section.shared_soap.toggle_aria')) ?>">
      <label class="setup-toggle__option">
        <input type="radio" name="shared_soap_mode" value="shared" <?= $sharedSoapMode !== 'custom' ? 'checked' : '' ?>>
        <span><?= htmlspecialchars(__('app.setup.mode.section.shared_soap.toggle_shared')) ?></span>
      </label>
      <label class="setup-toggle__option">
        <input type="radio" name="shared_soap_mode" value="custom" <?= $sharedSoapMode === 'custom' ? 'checked' : '' ?>>
        <span><?= htmlspecialchars(__('app.setup.mode.section.shared_soap.toggle_custom')) ?></span>
      </label>
    </div>
    <div class="setup-grid setup-grid--compact">
      <div class="setup-field">
        <label for="soap_host"><?= htmlspecialchars(__('app.setup.mode.fields.host')) ?></label>
        <input id="soap_host" name="soap_host" value="<?= htmlspecialchars($sharedSoap['host']) ?>" placeholder="127.0.0.1">
      </div>
      <div class="setup-field">
        <label for="soap_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
        <input id="soap_port" type="number" name="soap_port" value="<?= htmlspecialchars((string)$sharedSoap['port']) ?>" min="1" max="65535">
      </div>
      <div class="setup-field">
        <label for="soap_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
        <input id="soap_user" name="soap_user" value="<?= htmlspecialchars($sharedSoap['username']) ?>" placeholder="soap_user">
      </div>
      <div class="setup-field">
        <label for="soap_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
        <input id="soap_pass" type="password" name="soap_pass" value="<?= htmlspecialchars($sharedSoap['password']) ?>" placeholder="••••••">
      </div>
      <div class="setup-field">
        <label for="soap_uri"><?= htmlspecialchars(__('app.setup.mode.fields.uri')) ?></label>
        <input id="soap_uri" name="soap_uri" value="<?= htmlspecialchars($sharedSoap['uri']) ?>" placeholder="urn:AC">
      </div>
    </div>
    <p
      class="setup-summary"
      data-shared-soap-summary
      data-summary-shared="<?= htmlspecialchars(__('app.setup.mode.section.shared_soap.summary_shared')) ?>"
      data-summary-custom="<?= htmlspecialchars(__('app.setup.mode.section.shared_soap.summary_custom')) ?>"
    ></p>
  </section>

  <footer class="setup-footer">
    <div class="setup-disclaimer"><?= htmlspecialchars(__('app.setup.mode.footer.hint')) ?></div>
    <div class="setup-actions">
      <button class="btn primary" type="submit"><?= htmlspecialchars(__('app.setup.mode.footer.submit')) ?></button>
      <a href="<?= url('/setup?step=1') ?>" class="btn secondary"><?= htmlspecialchars(__('app.setup.mode.footer.back')) ?></a>
    </div>
  </footer>
</form>

<script>
const formEl = document.getElementById('mode-form');
const modeCards = formEl.querySelectorAll('.mode-card');
const getMode = () => formEl.querySelector('input[name=mode]:checked')?.value || 'single';

function syncModeUI(selected) {
  modeCards.forEach(card => card.classList.toggle('active', card.dataset.mode === selected));
  formEl.querySelectorAll('[data-mode-visibility]').forEach(block => {
    const accept = (block.dataset.modeVisibility || '').split(',').map(s => s.trim()).filter(Boolean);
    if (!accept.length || accept.includes(selected)) {
      block.classList.remove('hidden');
    } else {
      block.classList.add('hidden');
    }
  });
  syncSharedSummaries();
}

modeCards.forEach(card => {
  const radio = card.querySelector('input[type=radio]');
  card.addEventListener('click', () => {
    if (!radio.checked) {
      radio.checked = true;
      radio.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  radio.addEventListener('change', () => syncModeUI(radio.value));
});

const realmList = document.getElementById('realm-list');
const realmSummary = document.getElementById('realm-summary');
const addBtn = document.getElementById('add-realm');
const refreshBtn = document.getElementById('refresh-realms');
const sharedConfig = <?= json_encode([
  'dbMode' => $sharedDbMode,
  'soapMode' => $sharedSoapMode,
  'db' => [
      'characters' => $sharedDbCharacters,
      'world' => $sharedDbWorld,
  ],
  'soap' => $sharedSoap,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let sharedDbMode = sharedConfig.dbMode || 'shared';
let sharedSoapMode = sharedConfig.soapMode || 'shared';
const sharedDbFields = formEl.querySelector('[data-shared-db-fields]');
const sharedDbSummaryEl = formEl.querySelector('[data-shared-db-summary]');
const sharedDbRadios = formEl.querySelectorAll('input[name="shared_db_mode"]');
const sharedSoapRadios = formEl.querySelectorAll('input[name="shared_soap_mode"]');
const sharedSoapSummaryEl = formEl.querySelector('[data-shared-soap-summary]');
const sharedRealmNoteEl = formEl.querySelector('[data-shared-mode-note]');
window._globalSoapPort = parseInt(sharedConfig.soap?.port ?? formEl.querySelector('input[name=soap_port]')?.value ?? '7878', 10);
window._mainAuthPort = parseInt(formEl.querySelector('input[name=auth_port]')?.value || '3306', 10);
let realms = <?= json_encode($s['realms'] ?? []) ?>;
const modeLocale = <?= json_encode($jsLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__MODE_LOCALE__ = modeLocale;
const realmLocale = modeLocale.realm || {};
const actionLocale = modeLocale.actions || {};

syncModeUI(getMode());

function applyPlaceholders(template, data) {
  if (!template) return '';
  return Object.keys(data).reduce((acc, key) => acc.split(`:${key}`).join(data[key]), template);
}

function formatMeta(label, value, fallbackLabel) {
  if (value === undefined || value === null || value === '') {
    return '';
  }
  if (!label) {
    return fallbackLabel ? `${fallbackLabel}: ${value}` : String(value);
  }
  const replaced = applyPlaceholders(label, { value });
  if (replaced === label && !label.includes(':value')) {
    return `${label}: ${value}`;
  }
  return replaced;
}

function renderRealmTitle(index) {
  const base = realmLocale.title_prefix || 'Realm :index';
  const rendered = applyPlaceholders(base, { index: index + 1 });
  if (!rendered || rendered === base) {
    return `Realm ${index + 1}`;
  }
  return rendered;
}

function realmTemplate(i, realm = {}) {
  const modeNow = getMode();
  const showAuth = modeNow === 'multi-full';
  const metaParts = [];
  if (realm.realm_id) metaParts.push(formatMeta(realmLocale.meta?.id, realm.realm_id, 'ID'));
  if (realm.port) metaParts.push(formatMeta(realmLocale.meta?.port, realm.port, 'Port'));
  const auth = realm.auth || {};
  const characters = realm.characters || {};
  const world = realm.world || {};
  const soap = realm.soap || {};
  const inheritMain = realmLocale.auth_placeholders?.inherit_main || '';
  const inheritText = realmLocale.inherit || '';
  const soapPlaceholder = realmLocale.soap_placeholder || '';
  const soapPortHint = Number.isFinite(window._globalSoapPort) ? window._globalSoapPort + i : '';
  const metaHtml = metaParts.length ? `<span class="realm-card__meta">${metaParts.join(' · ')}</span>` : '';

  return `
    <div class="realm-card" data-idx="${i}">
      <div class="realm-card__title">${renderRealmTitle(i)}${metaHtml}
        <button type="button" class="realm-card__remove" data-idx="${i}">${realmLocale.remove || '×'}</button>
      </div>
      <div class="setup-field">
        <label>${realmLocale.name_label || ''}
          <input name="realms[${i}][name]" value="${realm.name ?? ''}" placeholder="${realmLocale.name_placeholder || ''}" ${realm.name ? 'readonly' : ''}>
        </label>
      </div>
      <input type="hidden" name="realms[${i}][realm_id]" value="${realm.realm_id ?? ''}">
      <input type="hidden" name="realms[${i}][port]" value="${realm.port ?? ''}">
      ${showAuth ? `
        <details class="setup-collapsible" open>
          <summary>${realmLocale.auth || ''}</summary>
          <div class="setup-grid setup-grid--compact">
            <div class="setup-field"><label>${realmLocale.auth_summary?.host || ''}<input name="realms[${i}][auth][host]" value="${auth.host ?? ''}" placeholder="${inheritMain}"></label></div>
            <div class="setup-field"><label>${realmLocale.auth_summary?.port || ''}<input type="number" name="realms[${i}][auth][port]" value="${auth.port ?? ''}" placeholder="${window._mainAuthPort}"></label></div>
            <div class="setup-field"><label>${realmLocale.auth_summary?.database || ''}<input name="realms[${i}][auth][database]" value="${auth.database ?? ''}" placeholder="auth"></label></div>
            <div class="setup-field"><label>${realmLocale.auth_summary?.user || ''}<input name="realms[${i}][auth][username]" value="${auth.username ?? ''}" placeholder="${inheritMain}"></label></div>
            <div class="setup-field"><label>${realmLocale.auth_summary?.password || ''}<input type="password" name="realms[${i}][auth][password]" value="${auth.password ?? ''}" placeholder="${inheritMain}"></label></div>
          </div>
        </details>` : ''}
      <details class="setup-collapsible" open>
        <summary>${realmLocale.characters?.title || ''}</summary>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field"><label>${realmLocale.characters?.port || ''}<input type="number" name="realms[${i}][characters][port]" value="${characters.port ?? 3306}"></label></div>
          <div class="setup-field"><label>${realmLocale.characters?.database || ''}<input name="realms[${i}][characters][database]" value="${characters.database ?? ''}"></label></div>
          <div class="setup-field"><label>${realmLocale.characters?.user || ''}<input name="realms[${i}][characters][username]" value="${characters.username ?? ''}" placeholder="${inheritText}"></label></div>
          <div class="setup-field"><label>${realmLocale.characters?.password || ''}<input type="password" name="realms[${i}][characters][password]" value="${characters.password ?? ''}" placeholder="${inheritText}"></label></div>
        </div>
      </details>
      <details class="setup-collapsible" open>
        <summary>${realmLocale.world?.title || ''}</summary>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field"><label>${realmLocale.world?.port || ''}<input type="number" name="realms[${i}][world][port]" value="${world.port ?? 3306}"></label></div>
          <div class="setup-field"><label>${realmLocale.world?.database || ''}<input name="realms[${i}][world][database]" value="${world.database ?? ''}"></label></div>
          <div class="setup-field"><label>${realmLocale.world?.user || ''}<input name="realms[${i}][world][username]" value="${world.username ?? ''}" placeholder="${inheritText}"></label></div>
          <div class="setup-field"><label>${realmLocale.world?.password || ''}<input type="password" name="realms[${i}][world][password]" value="${world.password ?? ''}" placeholder="${inheritText}"></label></div>
        </div>
      </details>
      <details class="setup-collapsible">
        <summary>${realmLocale.soap?.title || ''}</summary>
        <div class="setup-grid setup-grid--compact">
          <div class="setup-field"><label>${realmLocale.soap?.host || ''}<input name="realms[${i}][soap][host]" value="${soap.host ?? ''}" placeholder="${soapPlaceholder}"></label></div>
          <div class="setup-field"><label>${realmLocale.soap?.port || ''}<input type="number" name="realms[${i}][soap][port]" value="${soap.port ?? ''}" placeholder="${soapPortHint}"></label></div>
          <div class="setup-field"><label>${realmLocale.soap?.user || ''}<input name="realms[${i}][soap][username]" value="${soap.username ?? ''}" placeholder="${soapPlaceholder}"></label></div>
          <div class="setup-field"><label>${realmLocale.soap?.password || ''}<input type="password" name="realms[${i}][soap][password]" value="${soap.password ?? ''}" placeholder="${soapPlaceholder}"></label></div>
          <div class="setup-field"><label>${realmLocale.soap?.uri || ''}<input name="realms[${i}][soap][uri]" value="${soap.uri ?? ''}" placeholder="urn:AC"></label></div>
        </div>
      </details>
    </div>
  `;
}

function renderRealms() {
  if (!realmList) return;
  realmList.innerHTML = '';
  realms.forEach((realm, idx) => {
    realmList.insertAdjacentHTML('beforeend', realmTemplate(idx, realm));
  });
  attachRealmEvents();
  updateRealmSummary();
}

function attachRealmEvents() {
  if (!realmList) return;
  realmList.querySelectorAll('.realm-card__remove').forEach(btn => {
    btn.addEventListener('click', () => {
      const idx = parseInt(btn.dataset.idx, 10);
      if (Number.isFinite(idx)) {
        realms.splice(idx, 1);
        renderRealms();
      }
    });
  });
}

function updateRealmSummary() {
  if (!realmSummary) return;
  if (!realms.length) {
    realmSummary.textContent = realmLocale.empty || '';
    return;
  }
  const ids = realms.map(r => r.realm_id).filter(Boolean);
  const placeholders = { count: realms.length, ids: ids.join(', ') };
  let summary = applyPlaceholders(realmLocale.summary || '', placeholders).trim();
  const tail = applyPlaceholders(realmLocale.summary_ids || '', placeholders).trim();
  if (tail && !summary.includes(placeholders.ids)) {
    summary = summary ? `${summary} ${tail}` : tail;
  }
  realmSummary.textContent = summary || placeholders.ids;
}

renderRealms();
syncSharedSummaries();

function getRadioSelection(radios, fallback) {
  let value = fallback;
  radios.forEach(radio => {
    if (radio.checked) {
      value = radio.value;
    }
  });
  return value;
}

function syncSharedSummaries() {
  sharedDbMode = getRadioSelection(sharedDbRadios, sharedDbMode);
  sharedSoapMode = getRadioSelection(sharedSoapRadios, sharedSoapMode);

  if (sharedDbSummaryEl) {
    const text = sharedDbMode === 'custom'
      ? sharedDbSummaryEl.dataset.summaryCustom
      : sharedDbSummaryEl.dataset.summaryShared;
    sharedDbSummaryEl.textContent = text || '';
  }

  if (sharedDbFields) {
    const disable = sharedDbMode === 'custom';
    sharedDbFields.querySelectorAll('input').forEach(input => {
      input.disabled = disable;
    });
    sharedDbFields.classList.toggle('is-disabled', disable);
  }

  if (sharedSoapSummaryEl) {
    const text = sharedSoapMode === 'custom'
      ? sharedSoapSummaryEl.dataset.summaryCustom
      : sharedSoapSummaryEl.dataset.summaryShared;
    sharedSoapSummaryEl.textContent = text || '';
  }

  if (sharedRealmNoteEl) {
    const useShared = sharedDbMode !== 'custom' || sharedSoapMode !== 'custom';
    const text = useShared
      ? sharedRealmNoteEl.dataset.noteShared
      : sharedRealmNoteEl.dataset.noteCustom;
    sharedRealmNoteEl.textContent = text || '';
  }
}

sharedDbRadios.forEach(radio => {
  radio.addEventListener('change', () => {
    sharedDbMode = radio.value;
    syncSharedSummaries();
  });
});

sharedSoapRadios.forEach(radio => {
  radio.addEventListener('change', () => {
    sharedSoapMode = radio.value;
    syncSharedSummaries();
  });
});

if (addBtn) {
  addBtn.addEventListener('click', () => {
    realms.push({});
    renderRealms();
    addBtn.disabled = false;
    addBtn.title = '';
  });
}

if (refreshBtn) {
  refreshBtn.addEventListener('click', () => {
    const fd = new FormData();
    ['host', 'port', 'db', 'user', 'pass'].forEach(key => {
      const el = formEl.querySelector(`[name=auth_${key}]`);
      if (el) fd.append(`auth_${key}`, el.value);
    });
    fd.append('mode', getMode());
    fetch('<?= url('/setup/api/realms') ?>', { method: 'POST', body: fd })
      .then(resp => resp.json())
      .then(json => {
        if (json.success) {
          realms = json.realms || [];
          renderRealms();
          if (addBtn) {
            addBtn.disabled = true;
            addBtn.title = actionLocale.manual_disabled || '';
          }
        } else {
          const message = json.message ? ` ${json.message}` : '';
          alert(`${realmLocale.refresh_fail || ''}${message}`.trim());
        }
      })
      .catch(() => alert(realmLocale.request_fail || 'Request failed'));
  });
}

formEl.addEventListener('submit', event => {
  event.preventDefault();
  const fd = new FormData(formEl);
  fetch('<?= url('/setup/post') ?>', { method: 'POST', body: fd })
    .then(resp => resp.json())
    .then(json => {
      if (json.success) {
        location.href = json.redirect;
      } else {
        alert(json.message || realmLocale.save_fail || realmLocale.unknown_error || 'Error');
      }
    })
    .catch(() => alert(realmLocale.request_fail || 'Request failed'));
});
</script>
<?php $content=ob_get_clean(); include __DIR__.'/layout.php'; ?>
