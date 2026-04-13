<?php
return array (
  'app' => 
  array (
    'name' => 'Acore GM Panel',
    'title_suffix' => 'Acore GM Panel',
    'footer_copyright' => '(c) :year Acore Game Management Panel',
    'metrics_text' => 'Time :time | Memory +:memory',
    'metrics_title' => 'Page rendered in approximately :ms ms, peak memory :mb MB',
  ),
  'nav' => 
  array (
    'home' => 'Home',
    'account' => 'Accounts',
    'character' => 'Characters',
    'character_boost' => 'Boost',
    'item' => 'Items',
    'creature' => 'Creatures',
    'quest' => 'Quests',
    'mail' => 'Mail',
    'mass_mail' => 'Mass Mail',
    'bag' => 'Bag Query',
    'item_owner' => 'Item Ownership',
    'soap' => 'SOAP',
    'smart_ai' => 'SmartAI',
    'aegis' => 'Aegis Anti-Cheat',
    'logs' => 'Logs',
  ),
  'common' => 
  array (
    'performance' => 'Performance',
    'loading' => 'Loading…',
    'online_total_label' => 'Online / Total',
    'online_total_title' => 'Online players / total characters on this realm',
    'language' => 'Language',
    'languages' => 
    array (
      'zh_CN' => 'Chinese (Simplified)',
      'en' => 'English',
    ),
    'validation' => 
    array (
      'missing_id' => 'Missing id',
      'missing_ip' => 'Missing IP',
      'missing_player' => 'Missing player',
      'missing_params' => 'Missing required parameters',
      'required' => 'This field is required',
      'number' => 'Please enter a number',
      'min' => 'Value may not be less than :min',
      'max' => 'Value may not exceed :max',
      'length_max' => 'Length may not exceed :max characters',
      'id_required' => 'ID is required',
      'invalid_id' => 'Invalid ID',
      'no_valid_id' => 'No valid IDs provided',
    ),
    'errors' => 
    array (
      'query_failed' => 'Query failed: :message',
      'database' => 'Database error: :message',
      'not_found' => 'Not found',
    ),
    'api' => 
    array (
      'errors' => 
      array (
        'request_failed' => 'Request failed',
        'request_failed_retry' => 'Request failed, please try again later',
        'request_failed_message' => 'Request failed: :message',
        'request_failed_reason' => 'Request failed: :reason',
        'unknown' => 'Unknown error',
        'unauthorized' => 'Unauthorized',
        'forbidden' => 'Forbidden',
      ),
      'success' => 
      array (
        'generic' => 'Operation completed successfully',
        'queued' => 'Task queued successfully',
      ),
    ),
      'capabilities' => 
      array (
        'page_limited' => 'Some actions are hidden for the current account.',
        'section_hidden' => ':section is hidden because the required capability is missing.',
        'read_only' => 'This page is available in read-only mode.',
        'no_actions' => 'No actions are available for the current account.',
      ),
  ),
  'pagination' => 
  array (
    'previous' => 'Previous page',
    'next' => 'Next page',
  ),
  'server' => 
  array (
    'label' => 'Realm',
    'default_option' => 'Realm #:id',
  ),
  'database' => 
  array (
    'errors' => 
    array (
      'config_missing' => 'Database configuration not found: :name',
      'connection_failed' => 'Database connection failed: :database @ :host:: :port (:error)',
      'server_config_missing' => 'Realm configuration not found: :server (role :role)',
    ),
  ),
  'cli' => 
  array (
    'normalize_config' => 
    array (
      'title' => 'Normalize configuration',
      'done' => 'Configuration normalization completed.',
      'missing_dir' => 'Config directory not found: :path',
      'fixed' => 'Fixed: :file',
      'skipped_failed' => 'Skipped (replace failed): :file',
      'summary' => 'Done. Fixed files: :fixed, unchanged: :skipped',
    ),
  ),
  'errors' => 
  array (
    403 => 'Forbidden',
    404 => 'Page not found',
    'internal_server_error_title' => 'Internal Server Error',
  ),
  'flags' => 
  array (
    'genders' => 
    array (
      0 => 'Male',
      1 => 'Female',
    ),
    'online' => 
    array (
      0 => 'Offline',
      1 => 'Online',
    ),
  ),
  'js' => 
  array (
    'common' => 
    array (
      'loading' => 'Loading…',
      'no_data' => 'No data',
      'search_placeholder' => 'Search…',
      'errors' => 
      array (
        'network' => 'Network error',
        'timeout' => 'Request timed out',
        'invalid_json' => 'Invalid JSON',
        'unknown' => 'Unknown error',
      ),
      'actions' => 
      array (
        'close' => 'Close',
        'confirm' => 'Confirm',
        'cancel' => 'Cancel',
        'retry' => 'Retry',
      ),
      'yes' => 'Yes',
      'no' => 'No',
    ),
  ),
);
