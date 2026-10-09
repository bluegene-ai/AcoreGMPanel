<?php
/**
 * File: resources/lang/en/logs.php
 * Purpose: Audit console copy plus the module/action catalogue labels shared with the backend.
 */

return [
  'page_title' => 'Audit log',
  'intro' => 'One record for every write, exception and system event in the panel; filter by realm, module, actor, time or keyword.',
  'storage_unavailable' => 'The audit database is unreachable; the page can only read cached data and new events fall back to storage/logs.',

  'fields' => [
    'keyword' => 'Keyword',
    'keyword_placeholder' => 'summary / target / actor / URI / detail',
    'channel' => 'Channel',
    'module' => 'Module',
    'action' => 'Action',
    'actor' => 'Actor',
    'status' => 'Status',
    'realm' => 'Realm',
    'range' => 'Range',
    'from' => 'From',
    'to' => 'To',
    'per_page' => 'Per page',
  ],

  'filters' => [
    'all' => 'All',
    'all_realms' => 'All realms',
  ],

  'actions' => [
    'search' => 'Search',
    'reset' => 'Reset',
    'auto_refresh' => 'Enable auto refresh',
    'export' => 'Export CSV',
    'purge' => 'Purge',
  ],

  'ranges' => [
    '1h' => 'Last hour',
    '24h' => 'Last 24 hours',
    '7d' => 'Last 7 days',
    '30d' => 'Last 30 days',
    '90d' => 'Last 90 days',
    'all' => 'All time',
    'custom' => 'Custom range',
  ],

  'purge' => [
    'hint' => 'Delete records by age. This cannot be undone.',
    'days' => 'Keep days',
    'confirm' => 'Purge now',
  ],

  'table' => [
    'headers' => [
      'time' => 'Time',
      'realm' => 'Realm',
      'actor' => 'Actor',
      'channel' => 'Channel',
      'module' => 'Module · action',
      'status' => 'Status',
      'target' => 'Target',
      'summary' => 'Summary',
    ],
    'loading' => 'Loading…',
  ],

  'detail' => [
    'title' => 'Record detail',
    'close' => 'Close',
  ],

  'api' => [
    'purge_summary' => 'Purged records older than :days days, removed :deleted',
    'purge_done' => 'Removed :deleted records older than :days days',
    'export_summary' => 'Exported :count audit records',
    'errors' => [
      'read_failed' => 'Failed to read the audit log',
      'purge_failed' => 'Failed to purge the audit log',
    ],
  ],

  'channels' => [
    'audit' => 'Action',
    'error' => 'Error',
    'system' => 'System',
  ],

  'statuses' => [
    'ok' => 'OK',
    'fail' => 'Failed',
    'denied' => 'Denied',
  ],

  'catalog' => [
    'auth' => [
      'label' => 'Sign-in',
      'description' => 'Panel sign-in and sign-out.',
      'actions' => [
        'login' => 'Sign in',
        'login_failed' => 'Sign-in failed',
        'logout' => 'Sign out',
      ],
    ],
    'account' => [
      'label' => 'Account',
      'description' => 'Account creation, bans, renames and passwords.',
      'actions' => [
        'create' => 'Create account',
        'delete' => 'Delete account',
        'ban' => 'Ban account',
        'unban' => 'Unban account',
        'bulk_ban' => 'Bulk ban',
        'bulk_unban' => 'Bulk unban',
        'bulk_delete' => 'Bulk delete',
        'set_gm' => 'Set GM level',
        'update_email' => 'Update email',
        'rename' => 'Rename account',
        'change_password' => 'Change password',
        'kick' => 'Kick',
      ],
    ],
    'character' => [
      'label' => 'Character',
      'description' => 'Levels, gold, teleports, bans and resets.',
      'actions' => [
        'boost' => 'Boost',
        'boost_failed' => 'Boost failed',
        'ban' => 'Ban character',
        'unban' => 'Unban character',
        'bulk_ban' => 'Bulk ban',
        'bulk_unban' => 'Bulk unban',
        'bulk_delete' => 'Bulk delete',
        'delete' => 'Delete character',
        'set_level' => 'Set level',
        'set_gold' => 'Set gold',
        'kick' => 'Kick',
        'kick_before_teleport' => 'Kick before teleport',
        'teleport' => 'Teleport',
        'unstuck' => 'Unstuck',
        'reset_talents' => 'Reset talents',
        'reset_spells' => 'Reset spells',
        'reset_cooldowns' => 'Reset cooldowns',
        'rename_flag' => 'Rename flag',
      ],
    ],
    'character_boost' => [
      'label' => 'Boost',
      'description' => 'Boost templates, redeem codes and redemptions.',
      'actions' => [
        'generate_redeem_codes' => 'Generate codes',
        'delete_unused_redeem_code' => 'Delete unused code',
        'purge_unused_redeem_codes' => 'Purge unused codes',
        'save_template' => 'Save template',
        'delete_template' => 'Delete template',
        'public_redeem' => 'Player redeem',
        'public_redeem_failed' => 'Player redeem failed',
      ],
    ],
    'item' => [
      'label' => 'Item',
      'description' => 'Item template create, update, delete and SQL.',
      'actions' => [
        'create' => 'Create item',
        'update' => 'Update item',
        'delete' => 'Delete item',
        'exec_sql' => 'Run SQL',
        'snapshot' => 'Row snapshot',
      ],
    ],
    'creature' => [
      'label' => 'Creature',
      'description' => 'Creature template create, update, delete and SQL.',
      'actions' => [
        'create' => 'Create creature',
        'update' => 'Update creature',
        'delete' => 'Delete creature',
        'exec_sql' => 'Run SQL',
        'snapshot' => 'Row snapshot',
        'repository_warning' => 'Repository warning',
      ],
    ],
    'creature_model' => [
      'label' => 'Creature model',
      'description' => 'Creature model rows.',
      'actions' => [
        'add' => 'Add model',
        'edit' => 'Edit model',
        'delete' => 'Delete model',
      ],
    ],
    'quest' => [
      'label' => 'Quest',
      'description' => 'Quest template and aggregate saves.',
      'actions' => [
        'create' => 'Create quest',
        'update' => 'Update quest',
        'delete' => 'Delete quest',
        'exec_sql' => 'Run SQL',
        'snapshot' => 'Row snapshot',
        'aggregate_save' => 'Save aggregate',
      ],
    ],
    'item_inventory' => [
      'label' => 'Items / inventory',
      'description' => 'Bag queries, ownership lookups and bulk edits.',
      'actions' => [
        'search_characters' => 'Search characters',
        'view_character_items' => 'View items',
        'view_ownership' => 'View ownership',
        'reduce_instance' => 'Reduce item',
        'bulk_delete' => 'Bulk delete',
        'bulk_replace' => 'Bulk replace',
        'read_degraded' => 'Query degraded',
        'mutation_failed' => 'Mutation failed',
        'instance_create_failed' => 'Instance create failed',
      ],
    ],
    'mail' => [
      'label' => 'Mail',
      'description' => 'Mail viewing, read flags and deletion.',
      'actions' => [
        'view' => 'View mail',
        'mark_read' => 'Mark read',
        'mark_read_bulk' => 'Bulk mark read',
        'delete' => 'Delete mail',
        'delete_bulk' => 'Bulk delete mail',
        'exec_sql' => 'Run SQL',
        'snapshot' => 'Delete snapshot',
      ],
    ],
    'massmail' => [
      'label' => 'Mass mail',
      'description' => 'Server announcements and bulk mail, items and gold.',
      'actions' => [
        'announce' => 'Announce',
        'send_mail' => 'Send mail',
        'send_item' => 'Send items',
        'send_gold' => 'Send gold',
        'send_item_gold' => 'Send items and gold',
      ],
    ],
    'boss' => [
      'label' => 'World boss',
      'description' => 'Boss config, extended config, reward pools and commands.',
      'actions' => [
        'command' => 'Control command',
        'save_config' => 'Save config',
        'save_ext_config' => 'Save extended config',
        'copy_ext_config' => 'Copy extended config',
        'save_reward_pools' => 'Save reward pools',
      ],
    ],
    'trivia' => [
      'label' => 'Trivia',
      'description' => 'Trivia switches, settings, questions and presets.',
      'actions' => [
        'action' => 'Control command',
        'save_settings' => 'Save settings',
        'question_create' => 'Create question',
        'question_update' => 'Update question',
        'question_delete' => 'Delete question',
        'question_enable' => 'Enable question',
        'question_disable' => 'Disable question',
        'question_import' => 'Import questions',
        'question_export' => 'Export questions',
        'preset_save' => 'Save preset',
        'preset_delete' => 'Delete preset',
        'winners_clear' => 'Clear winners',
      ],
    ],
    'auctionator' => [
      'label' => 'Auction house',
      'description' => 'Auctionator config, item policies and commands.',
      'actions' => [
        'config_save' => 'Save config',
        'item_policy' => 'Item policy',
        'command' => 'Control command',
        'listing' => 'Listing',
        'power' => 'Power',
        'buyout' => 'Buyout',
        'max_item_level' => 'Max item level',
        'repository_warning' => 'Repository warning',
      ],
    ],
    'raf' => [
      'label' => 'Recruit a friend',
      'description' => 'Recruit bindings, unbinds and comments.',
      'actions' => [
        'bind' => 'Bind',
        'force_bind' => 'Force bind',
        'unbind' => 'Unbind',
        'comment' => 'Comment',
      ],
    ],
    'aegis' => [
      'label' => 'Anti-cheat',
      'description' => 'Actions issued from the anti-cheat panel.',
      'actions' => [
        'command' => 'Action',
      ],
    ],
    'supervisor' => [
      'label' => 'Supervisor',
      'description' => 'Service process start, stop and heartbeats.',
      'actions' => [
        'ping' => 'Heartbeat',
        'start' => 'Start',
        'stop' => 'Stop',
        'restart' => 'Restart',
        'command' => 'Control command',
      ],
    ],
    'soap_exec' => [
      'label' => 'SOAP',
      'description' => 'Server commands issued over SOAP.',
      'actions' => [
        'command' => 'SOAP command',
      ],
    ],
    'realm' => [
      'label' => 'Realm',
      'description' => 'Panel realm switching.',
      'actions' => [
        'switch' => 'Switch realm',
      ],
    ],
    'setup' => [
      'label' => 'Setup',
      'description' => 'Panel installation and reconfiguration.',
      'actions' => [
        'install' => 'Install',
        'reconfigure' => 'Reconfigure',
      ],
    ],
    'logs' => [
      'label' => 'Audit log',
      'description' => 'Export and purge of the audit log itself.',
      'actions' => [
        'export' => 'Export log',
        'purge' => 'Purge log',
      ],
    ],
    'system' => [
      'label' => 'System',
      'description' => 'Framework-level exceptions and warnings.',
      'actions' => [
        'exception' => 'Uncaught exception',
        'warning' => 'Runtime warning',
        'notice' => 'Runtime notice',
        'prune' => 'Log prune',
        'storage_fallback' => 'Fallback file',
      ],
    ],
  ],

  'js' => [
    'modules' => [
      'logs' => [
        'filters' => [
          'all' => 'All',
        ],
        'actions' => [
          'auto_on' => 'Enable auto refresh',
          'auto_off' => 'Disable auto refresh',
        ],
        'summary' => [
          'total' => 'Matched',
          'range' => 'Time span',
        ],
        'pager' => [
          'prev' => 'Prev',
          'next' => 'Next',
          'range' => ':from-:to / :total',
        ],
        'status' => [
          'no_entries' => 'No log entries',
          'load_failed' => 'Load failed',
          'request_error' => 'Request error',
          'panel_waiting' => 'Panel API is initializing, please wait…',
        ],
        'detail' => [
          'detail_json' => 'Raw detail',
          'fields' => [
            'time' => 'Time',
            'channel' => 'Channel',
            'module' => 'Module · action',
            'status' => 'Status',
            'severity' => 'Severity',
            'actor' => 'Actor',
            'realm' => 'Realm',
            'target' => 'Target',
            'summary' => 'Summary',
            'ip' => 'Source IP',
            'request' => 'Request',
            'duration' => 'Duration',
            'user_agent' => 'User-Agent',
            'record_id' => 'Record id',
          ],
        ],
        'purge' => [
          'confirm_text' => 'Delete audit records older than :days days? This cannot be undone.',
          'invalid_days' => 'Enter a number of days greater than zero.',
          'done' => 'Purge finished.',
        ],
      ],
    ],
  ],
];
