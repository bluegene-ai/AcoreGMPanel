<?php
/**
 * File: resources/views/setup/mode.php
 * Purpose: Setup wizard step2 with mode-driven dynamic database forms.
 */

$page = is_array($setupPage ?? null) ? $setupPage : [];
$sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
$cardsData = is_array($page['cards'] ?? null) ? $page['cards'] : [];
$serverText = is_array($page['server'] ?? null) ? $page['server'] : [];
$realmText = is_array($page['realm'] ?? null) ? $page['realm'] : [];
$actionsText = is_array($page['actions'] ?? null) ? $page['actions'] : [];
$messagesText = is_array($page['messages'] ?? null) ? $page['messages'] : [];
$footer = is_array($page['footer'] ?? null) ? $page['footer'] : [];

$s = $state ?? [];
$mode = $s['mode'] ?? 'single';

$defaultDb = static function(array $value = [], string $database = ''): array {
    return [
        'host' => $value['host'] ?? '127.0.0.1',
        'port' => (int)($value['port'] ?? 3306),
        'database' => $value['database'] ?? $database,
        'username' => $value['username'] ?? 'root',
        'password' => $value['password'] ?? '',
    ];
};

$defaultSoap = static function(array $value = []): array {
    return [
        'host' => $value['host'] ?? '127.0.0.1',
        'port' => (int)($value['port'] ?? 7878),
        'username' => $value['username'] ?? 'soap_user',
        'password' => $value['password'] ?? 'soap_pass',
        'uri' => $value['uri'] ?? 'urn:AC',
    ];
};

$sharedAuth = $defaultDb($s['auth'] ?? [], 'auth');
$defaultServerGroup = [
    'name' => '',
    'realm_id' => 1,
    'port' => 0,
    'auth' => $defaultDb($s['auth'] ?? [], 'auth'),
    'characters' => $defaultDb($s['characters'] ?? [], 'characters'),
    'world' => $defaultDb($s['world'] ?? [], 'world'),
    'soap' => $defaultSoap($s['soap'] ?? []),
];

$serverGroups = [];
if ($mode !== 'multi') {
    if (!empty($s['realms']) && is_array($s['realms'])) {
        foreach ($s['realms'] as $idx => $server) {
            $serverGroups[] = [
                'name' => $server['name'] ?? '',
                'realm_id' => (int)($server['realm_id'] ?? ($idx + 1)),
                'port' => (int)($server['port'] ?? 0),
                'auth' => $defaultDb($server['auth'] ?? [], 'auth'),
                'characters' => $defaultDb($server['characters'] ?? [], 'characters'),
                'world' => $defaultDb($server['world'] ?? [], 'world'),
                'soap' => $defaultSoap($server['soap'] ?? []),
            ];
        }
    }
    if (!$serverGroups) {
        $serverGroups[] = $defaultServerGroup;
    }
}

$verifiedRealms = [];
if ($mode === 'multi' && !empty($s['realms']) && is_array($s['realms'])) {
    foreach ($s['realms'] as $idx => $realm) {
        $verifiedRealms[] = [
            'name' => $realm['name'] ?? '',
            'realm_id' => (int)($realm['realm_id'] ?? ($idx + 1)),
            'port' => (int)($realm['port'] ?? 0),
            'characters' => $defaultDb($realm['characters'] ?? [], 'characters'),
            'world' => $defaultDb($realm['world'] ?? [], 'world'),
            'soap' => $defaultSoap($realm['soap'] ?? []),
        ];
    }
}

$modeCards = [
    'single' => [
        'value' => 'single',
    'title' => $cardsData['single']['title'] ?? __('app.setup.mode.cards.single.title'),
    'badge' => $cardsData['single']['badge'] ?? __('app.setup.mode.cards.single.badge'),
    'desc' => $cardsData['single']['desc'] ?? __('app.setup.mode.cards.single.desc'),
    ],
    'multi' => [
        'value' => 'multi',
    'title' => $cardsData['multi']['title'] ?? __('app.setup.mode.cards.multi.title'),
    'badge' => $cardsData['multi']['badge'] ?? __('app.setup.mode.cards.multi.badge'),
    'desc' => $cardsData['multi']['desc'] ?? __('app.setup.mode.cards.multi.desc'),
    ],
    'multi-full' => [
        'value' => 'multi-full',
    'title' => $cardsData['multi_full']['title'] ?? __('app.setup.mode.cards.multi_full.title'),
    'badge' => $cardsData['multi_full']['badge'] ?? __('app.setup.mode.cards.multi_full.badge'),
    'desc' => $cardsData['multi_full']['desc'] ?? __('app.setup.mode.cards.multi_full.desc'),
    ],
];

$jsLocale = [
  'server' => [
    'title_prefix' => $serverText['title_prefix'] ?? __('app.setup.mode.server.title_prefix'),
    'remove' => $serverText['remove'] ?? __('app.setup.mode.server.remove'),
    'name_label' => $serverText['name_label'] ?? __('app.setup.mode.server.name_label'),
    'name_placeholder' => $serverText['name_placeholder'] ?? __('app.setup.mode.server.name_placeholder'),
    'auth_title' => $serverText['auth_title'] ?? __('app.setup.mode.server.auth_title'),
    'characters_title' => $serverText['characters_title'] ?? __('app.setup.mode.server.characters_title'),
    'world_title' => $serverText['world_title'] ?? __('app.setup.mode.server.world_title'),
    'soap_title' => $serverText['soap_title'] ?? __('app.setup.mode.server.soap_title'),
  ],
  'realm' => [
    'title_prefix' => $realmText['title_prefix'] ?? __('app.setup.mode.realm.title_prefix'),
    'meta_id' => $realmText['meta']['id'] ?? __('app.setup.mode.realm.meta.id'),
    'meta_port' => $realmText['meta']['port'] ?? __('app.setup.mode.realm.meta.port'),
    'characters_title' => $realmText['characters']['title'] ?? __('app.setup.mode.realm.characters.title'),
    'world_title' => $realmText['world']['title'] ?? __('app.setup.mode.realm.world.title'),
    'soap_title' => $realmText['soap']['title'] ?? __('app.setup.mode.realm.soap.title'),
    'empty' => $realmText['empty'] ?? __('app.setup.mode.realm.empty'),
    'verify_success' => $messagesText['verify_success'] ?? __('app.setup.mode.messages.verify_success', ['count' => ':count']),
    'verify_empty' => $messagesText['verify_empty'] ?? __('app.setup.mode.messages.verify_empty'),
    'verify_fail' => $messagesText['verify_fail'] ?? __('app.setup.mode.messages.verify_fail'),
  ],
    'fields' => [
        'host' => __('app.setup.mode.fields.host'),
        'port' => __('app.setup.mode.fields.port'),
        'database' => __('app.setup.mode.fields.database'),
        'user' => __('app.setup.mode.fields.user'),
        'password' => __('app.setup.mode.fields.password'),
        'uri' => __('app.setup.mode.fields.uri'),
    ],
    'actions' => [
      'add_server' => $actionsText['add_server'] ?? __('app.setup.mode.actions.add_server'),
      'verify' => $actionsText['verify'] ?? __('app.setup.mode.actions.verify'),
      'verifying' => $actionsText['verifying'] ?? __('app.setup.mode.actions.verifying'),
      'request_fail' => $actionsText['request_fail'] ?? __('app.setup.mode.actions.request_fail'),
      'save_fail' => $actionsText['save_fail'] ?? __('app.setup.mode.actions.save_fail'),
      'unknown_error' => $actionsText['unknown_error'] ?? __('app.setup.mode.actions.unknown_error'),
    ],
];
?>

  <h3><?= htmlspecialchars((string)($page['heading'] ?? __('app.setup.mode.step_title', ['current' => 2, 'total' => 5]))) ?></h3>

<form id="mode-form" class="setup-form" method="post" action="<?= url('/setup/post') ?>" data-realms-url="<?= url('/setup/api/realms') ?>">
  <?= Acme\Panel\Support\Csrf::field() ?>
  <input type="hidden" name="action" value="mode_save">

  <section class="setup-section">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars((string)($sections['mode']['title'] ?? __('app.setup.mode.section.mode.title'))) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars((string)($sections['mode']['hint'] ?? __('app.setup.mode.section.mode.hint'))) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars((string)($sections['mode']['pill'] ?? __('app.setup.mode.section.mode.pill'))) ?></span>
    </div>

    <div class="mode-cards" role="radiogroup" aria-label="<?= htmlspecialchars((string)($sections['mode']['aria_group'] ?? __('app.setup.mode.section.mode.aria_group'))) ?>">
      <?php foreach ($modeCards as $card): ?>
        <?php $active = $mode === $card['value']; ?>
        <label class="mode-card <?= $active ? 'active' : '' ?>" data-mode-card="<?= htmlspecialchars($card['value']) ?>">
          <input type="radio" name="mode" value="<?= htmlspecialchars($card['value']) ?>" <?= $active ? 'checked' : '' ?>>
          <div class="mode-card__title">
            <?= htmlspecialchars($card['title']) ?>
            <span class="mode-card__badge"><?= htmlspecialchars($card['badge']) ?></span>
          </div>
          <p class="mode-card__desc"><?= htmlspecialchars($card['desc']) ?></p>
        </label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="setup-section" data-mode-panel="single,multi-full">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars((string)($sections['server_groups']['title'] ?? __('app.setup.mode.section.server_groups.title'))) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars((string)($sections['server_groups']['hint'] ?? __('app.setup.mode.section.server_groups.hint'))) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars((string)($sections['server_groups']['pill'] ?? __('app.setup.mode.section.server_groups.pill'))) ?></span>
    </div>
    <div class="setup-inline-actions">
      <button type="button" class="btn sm secondary" id="add-server-group"><?= htmlspecialchars((string)($actionsText['add_server'] ?? __('app.setup.mode.actions.add_server'))) ?></button>
      <span class="setup-summary"><?= htmlspecialchars((string)($sections['server_groups']['summary'] ?? __('app.setup.mode.section.server_groups.summary'))) ?></span>
    </div>
    <div id="setup-server-groups" class="realm-grid"></div>
  </section>

  <section class="setup-section" data-mode-panel="multi">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars((string)($sections['auth']['title'] ?? __('app.setup.mode.section.auth.title'))) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars((string)($sections['auth']['hint'] ?? __('app.setup.mode.section.auth.hint'))) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars((string)($sections['auth']['pill'] ?? __('app.setup.mode.section.auth.pill'))) ?></span>
    </div>
    <div class="setup-grid setup-grid--compact">
      <div class="setup-field">
        <label for="auth_host"><?= htmlspecialchars(__('app.setup.mode.fields.host')) ?></label>
        <input id="auth_host" name="auth_host" value="<?= htmlspecialchars($sharedAuth['host']) ?>" placeholder="127.0.0.1">
      </div>
      <div class="setup-field">
        <label for="auth_port"><?= htmlspecialchars(__('app.setup.mode.fields.port')) ?></label>
        <input id="auth_port" type="number" name="auth_port" value="<?= htmlspecialchars((string)$sharedAuth['port']) ?>" min="1" max="65535">
      </div>
      <div class="setup-field">
        <label for="auth_db"><?= htmlspecialchars(__('app.setup.mode.fields.database')) ?></label>
        <input id="auth_db" name="auth_db" value="<?= htmlspecialchars($sharedAuth['database']) ?>" placeholder="auth">
      </div>
      <div class="setup-field">
        <label for="auth_user"><?= htmlspecialchars(__('app.setup.mode.fields.user')) ?></label>
        <input id="auth_user" name="auth_user" value="<?= htmlspecialchars($sharedAuth['username']) ?>" placeholder="root">
      </div>
      <div class="setup-field">
        <label for="auth_pass"><?= htmlspecialchars(__('app.setup.mode.fields.password')) ?></label>
        <input id="auth_pass" type="password" name="auth_pass" value="<?= htmlspecialchars($sharedAuth['password']) ?>" placeholder="••••••">
      </div>
    </div>
    <div class="setup-inline-actions setup-inline-actions--spaced setup-inline-actions--auth-verify">
      <button type="button" class="btn sm primary" id="verify-auth-realms"><?= htmlspecialchars((string)($actionsText['verify'] ?? __('app.setup.mode.actions.verify'))) ?></button>
      <span id="verify-auth-status" class="setup-summary"></span>
    </div>
  </section>

  <section class="setup-section" data-mode-panel="multi">
    <div class="setup-section__header">
      <div>
        <h2 class="setup-section__title"><?= htmlspecialchars((string)($sections['realm_groups']['title'] ?? __('app.setup.mode.section.realm_groups.title'))) ?></h2>
        <p class="setup-section__hint"><?= htmlspecialchars((string)($sections['realm_groups']['hint'] ?? __('app.setup.mode.section.realm_groups.hint'))) ?></p>
      </div>
      <span class="setup-section__pill"><?= htmlspecialchars((string)($sections['realm_groups']['pill'] ?? __('app.setup.mode.section.realm_groups.pill'))) ?></span>
    </div>
    <div id="setup-generated-realms" class="realm-grid"></div>
  </section>

  <footer class="setup-footer">
    <div class="setup-disclaimer"><?= htmlspecialchars((string)($footer['hint'] ?? __('app.setup.mode.footer.hint'))) ?></div>
    <div class="setup-actions">
      <button class="btn primary" type="submit"><?= htmlspecialchars((string)($footer['submit'] ?? __('app.setup.mode.footer.submit'))) ?></button>
      <a href="<?= url('/setup?step=1') ?>" class="btn secondary"><?= htmlspecialchars((string)($footer['back'] ?? __('app.setup.mode.footer.back'))) ?></a>
    </div>
  </footer>
</form>

<script type="application/json" data-setup-json="SETUP_MODE_CONFIG"><?= json_encode([
  'mode' => $mode,
  'verifyUrl' => url('/setup/api/realms'),
  'serverGroups' => $serverGroups,
  'realms' => $verifiedRealms,
  'sharedAuth' => $sharedAuth,
  'locale' => $jsLocale,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
