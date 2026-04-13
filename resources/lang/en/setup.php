<?php
return array (
  'layout' => 
  array (
    'page_title' => 'Setup Wizard - Acore GM Panel',
    'intro' => 'Follow the wizard to verify requirements, configure databases, and create the administrator account.',
    'step_titles' => 
    array (
      1 => 'Environment check',
      2 => 'Mode & databases',
      3 => 'Connection test',
      4 => 'Administrator',
      5 => 'Finish',
    ),
    'stepper_label' => 'Setup steps',
  ),
  'flash' => 
  array (
    'already_installed' => 'System already installed. Delete config/generated/install.lock to reinstall.',
    'install_success_debug' => 'Installation complete. To enable debug mode, edit config/generated/app.php and set debug to true.',
  ),
  'env' => 
  array (
    'title' => 'Step 1 · Environment check',
    'hint' => 'Ensure the server meets all requirements before continuing.',
    'pill' => 'Environment',
    'checks' => 
    array (
      'php_version' => 'PHP version',
      'pdo_mysql' => 'PDO MySQL extension',
      'soap' => 'SOAP extension',
      'mbstring' => 'mbstring extension',
      'config_writable' => 'Config directory writable',
    ),
    'requirements' => 
    array (
      'writable' => 'Writable',
    ),
    'messages' => 
    array (
      'write_failed' => 'Unable to write (check directory permissions)',
      'create_failed' => 'Failed to create (check parent directory permissions)',
      'created' => '(created)',
    ),
    'check_passed' => 'All checks passed! Choose your language to continue.',
    'check_failed' => 'Some checks failed. Please fix them and run the scan again.',
    'retry' => 'Run checks again',
    'language_title' => 'Language selection',
    'language_intro' => 'Pick a language for the rest of the setup wizard.',
    'language_hint' => 'Interface language · :code',
    'language_submit' => 'Next: configuration mode',
    'language_submit_fail' => 'Failed to save the language selection. Please try again.',
    'invalid_locale' => 'Invalid language choice.',
  ),
  'mode' => 
  array (
    'step_title' => 'Step :current of :total · Mode & databases',
    'section' => 
    array (
      'mode' => 
      array (
        'title' => 'Choose deployment mode',
        'hint' => 'Select the server layout that matches your deployment.',
        'pill' => 'Mode',
        'aria_group' => 'Deployment modes',
      ),
      'server_groups' => 
      array (
        'title' => 'Server groups',
        'hint' => 'Single-server and multi-server modes both maintain full Auth, Characters, World, and SOAP settings here.',
        'pill' => 'Servers',
        'summary' => 'Add more server groups when needed, or remove unused ones.',
      ),
      'auth' => 
      array (
        'title' => 'Shared auth database',
        'hint' => 'For one-auth multi-realm mode, verify the auth connection first. The wizard will read realmlist and generate the remaining forms automatically.',
        'pill' => 'Auth',
      ),
      'realm_groups' => 
      array (
        'title' => 'Generated realm configuration',
        'hint' => 'After verification, each realm from realmlist gets its own Characters, World, and SOAP configuration block.',
        'pill' => 'Realms',
      ),
    ),
    'cards' => 
    array (
      'single' => 
      array (
        'title' => 'Single server',
        'badge' => 'Default',
        'desc' => 'Maintain one complete server configuration for a single-server deployment.',
      ),
      'multi' => 
      array (
        'title' => 'One auth, many realms',
        'badge' => 'Shared auth',
        'desc' => 'Verify one shared auth database, then generate Characters, World, and SOAP settings for each realm from realmlist.',
      ),
      'multi_full' => 
      array (
        'title' => 'Many servers, many realms',
        'badge' => 'Isolated',
        'desc' => 'Each server group keeps its own Auth, Characters, World, and SOAP configuration.',
      ),
    ),
    'fields' => 
    array (
      'host' => 'Host',
      'port' => 'Port',
      'database' => 'Database',
      'user' => 'Username',
      'password' => 'Password',
      'uri' => 'URI',
    ),
    'actions' => 
    array (
      'add_server' => 'Add server',
      'verify' => 'Verify auth and read realmlist',
      'verifying' => 'Verifying...',
      'request_fail' => 'Request failed. Please try again.',
      'save_fail' => 'Save failed. Check the form and try again.',
      'unknown_error' => 'Unknown error.',
    ),
    'server' => 
    array (
      'title_prefix' => 'Server :index',
      'remove' => 'Remove',
      'name_label' => 'Server name',
      'name_placeholder' => 'Example: Main / PVP-01',
      'auth_title' => 'Auth database',
      'characters_title' => 'Characters database',
      'world_title' => 'World database',
      'soap_title' => 'SOAP configuration',
    ),
    'realm' => 
    array (
      'title_prefix' => 'Realm :index',
      'empty' => 'No realms loaded yet. Verify the auth database first.',
      'remove' => 'Remove',
      'meta' => 
      array (
        'id' => 'ID :value',
        'port' => 'Port :value',
      ),
      'characters' => 
      array (
        'title' => 'Characters database',
      ),
      'world' => 
      array (
        'title' => 'World database',
      ),
      'soap' => 
      array (
        'title' => 'SOAP configuration',
      ),
    ),
    'messages' => 
    array (
      'verify_success' => 'Verification succeeded. Generated :count realm configuration(s).',
      'verify_empty' => 'Auth verification succeeded, but realmlist is empty.',
      'verify_fail' => 'Auth verification failed. Check the database connection and permissions.',
    ),
    'footer' => 
    array (
      'hint' => 'These values feed directly into the next connection test and can still be adjusted after installation.',
      'submit' => 'Save and continue',
      'back' => 'Back to environment check',
    ),
  ),
  'test' => 
  array (
    'title' => 'Step :current / :total · Connection test',
    'success' => 'All connections succeeded.',
    'next_admin' => 'Next: Administrator',
    'failure' => 'Some checks failed, please go back and adjust.',
    'group_ok' => 'Databases ready',
    'group_fail' => 'Database check failed',
    'soap_warning' => 'SOAP verification failed, some features will be unavailable!',
    'back' => 'Back to edit',
  ),
  'status' => 
  array (
    'ok' => 'OK',
    'fail' => 'FAIL',
  ),
  'admin' => 
  array (
    'step_title' => 'Step :current of :total · Administrator',
    'fields' => 
    array (
      'username' => 'Username',
      'password' => 'Password',
      'password_confirm' => 'Confirm password',
    ),
    'validation' => 
    array (
      'username_required' => 'Username is required',
      'password_required' => 'Password is required',
      'password_mismatch' => 'Passwords do not match',
    ),
    'submit' => 'Save and generate config',
    'back' => 'Back to connection test',
    'save_failed' => 'Failed to save administrator settings. Please try again.',
  ),
  'finish' => 
  array (
    'step_title' => 'Step :current of :total · Finish',
    'success' => 'Configuration file generated successfully. Remove /setup (or keep install.lock) to prevent reinstalling.',
    'enter_panel' => 'Enter panel',
    'failure' => 'Generation failed: :errors',
    'back' => 'Back to administrator',
    'errors' => 
    array (
      'config_dir_create_failed' => 'Unable to create config directory ":path"',
      'write_failed' => 'Write failed: :file',
    ),
  ),
  'api' => 
  array (
    'realms' => 
    array (
      'missing_auth_db' => 'Auth database name is required.',
      'connection_failed' => 'Connection or query failed: :error',
    ),
  ),
);
