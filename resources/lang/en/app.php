<?php
/**
 * File: resources/lang/en/app.php
 * Purpose: Provides functionality for the resources/lang/en module.
 */

declare(strict_types=1);

return [
    'app' => [
    'name' => 'Acore GM Panel',
    'title_suffix' => 'Acore GM Panel',
        'footer_copyright' => '© :year Acore Game Management Panel',
        'metrics_text' => 'Time :time · Memory +:memory',
        'metrics_title' => 'Page rendered in approximately :ms ms, peak memory :mb MB',
    ],
    'nav' => [
        'home' => 'Home',
        'account' => 'Accounts',
        'character' => 'Characters',
        'item' => 'Items',
        'creature' => 'Creatures',
        'quest' => 'Quests',
        'mail' => 'Mail',
        'mass_mail' => 'Mass Mail',
        'bag' => 'Bag Query',
        'item_owner' => 'Item Ownership',
        'soap' => 'SOAP',
        'smart_ai' => 'SmartAI',
        'logs' => 'Logs',
    ],
    'common' => [
        'performance' => 'Performance',
    'loading' => 'Loading...',
        'online_total_label' => 'Online/Total',
        'online_total_title' => 'Online players / total characters on this realm',
        'language' => 'Language',
        'languages' => [
            'zh_CN' => 'Chinese (Simplified)',
            'en' => 'English',
        ],
        'validation' => [
            'missing_id' => 'ID parameter is required',
            'missing_ip' => 'IP parameter is required',
            'missing_player' => 'Player parameter is required',
            'missing_params' => 'Missing required parameters',
            'required' => 'This field is required',
            'number' => 'Please enter a number',
            'min' => 'Value may not be less than :min',
            'max' => 'Value may not exceed :max',
            'length_max' => 'Length may not exceed :max characters',
            'id_required' => 'ID is required',
            'invalid_id' => 'Invalid ID',
            'no_valid_id' => 'No valid IDs provided',
        ],
        'errors' => [
            'query_failed' => 'Query failed: :message',
            'database' => 'Database error: :message',
            'not_found' => 'Not found',
        ],
        'api' => [
            'errors' => [
                'request_failed' => 'Request failed',
                'request_failed_retry' => 'Request failed, please try again later',
                'request_failed_message' => 'Request failed: :message',
                'request_failed_reason' => 'Request failed: :reason',
                'unknown' => 'Unknown error',
                'unauthorized' => 'Unauthorized',
            ],
            'success' => [
                'generic' => 'Operation completed successfully',
                'queued' => 'Task queued successfully',
            ],
        ],
    ],
    'pagination' => [
        'previous' => 'Previous page',
        'next' => 'Next page',
    ],
    'server' => [
        'label' => 'Realm',
        'auto_detect_base' => 'Detected base_path = :base, it will be persisted after setup finishes.',
        'base_mismatch' => 'Current URL prefix ":detected" differs from configured base_path ":configured". Please review deployment settings.',
        'normalized_warning' => 'Normalized the access path. Please use :base as the entry URL.',
        'default_option' => 'Realm #:id',
    ],
    'support' => [
        'ip_location' => [
            'labels' => [
                'private' => 'Private IP',
                'unknown' => 'Unknown location',
            ],
                'flags_labels' => [
                    'visible' => 'Visible',
                    'at_war' => 'At war',
                    'hidden' => 'Hidden',
                    'inactive' => 'Inactive',
                    'peace_forced' => 'Peace forced',
                    'unknown_20' => 'Unknown (0x20)',
                    'unknown_40' => 'Unknown (0x40)',
                    'rival' => 'Rival',
                ],
            'errors' => [
                'empty' => 'IP address cannot be empty',
                'invalid' => 'Invalid IP format',
                'provider_unreachable' => 'Unable to connect to the IP data service',
                'response_invalid' => 'Response format is invalid',
            'id_required' => 'ID is required',
            'invalid_id' => 'Invalid ID',
            'no_valid_id' => 'No valid IDs provided',
                'failed' => 'Lookup failed',
                'status_map' => [
                    0 => 'None',
                    1 => 'Complete',
                    2 => 'Failed',
                    3 => 'Incomplete',
                    4 => 'Failed',
                    5 => 'Rewarded',
                ],
                'failed_reason' => 'Lookup failed: :message',
                'mmdb_unavailable' => 'Local IP database unavailable (configure mmdb and install dependencies)',
                'mmdb_reader_missing' => 'Missing MaxMind reader (install the PHP maxminddb extension, or use Composer to install maxmind-db/reader)',
                'mmdb_file_missing' => 'Local IP database file not found (download GeoLite2-City.mmdb and place it under storage/ip_geo/)',
                'mmdb_open_failed' => 'Failed to open local IP database (check file permissions and PHP maxminddb extension)',
            ],
        ],
            'not_found' => 'Not found',
        'server_list' => [
            'default' => 'Default realm',
        ],
        'multi_server' => [
            'errors' => [
                'auth_config_missing' => 'Auth configuration missing for server #:server.',
            ],
        ],
        'srp' => [
            'errors' => [
                'gmp_missing' => 'GMP extension is not enabled; cannot generate SRP verifier (enable extension=gmp in php.ini).',
                'gmp_missing_binary' => 'GMP extension is not enabled; cannot generate SRP verifier (binary32).',
            ],
        ],
        'soap_executor' => [
            'errors' => [
                'empty_command' => 'Command cannot be empty.',
                'not_whitelisted' => 'Command is not allowed by the whitelist.',
                'request_failed' => 'SOAP request failed.',
                'unknown' => 'Unknown SOAP executor error.',
            ],
        ],
    ],
    'game_meta' => [
        'classes' => [
            1 => 'Warrior',
            2 => 'Paladin',
            3 => 'Hunter',
            4 => 'Rogue',
            5 => 'Priest',
            6 => 'Death Knight',
            7 => 'Shaman',
            8 => 'Mage',
            9 => 'Warlock',
            10 => 'Monk',
            11 => 'Druid',
            12 => 'Demon Hunter',
        ],
        'races' => [
            1 => 'Human',
            2 => 'Orc',
            3 => 'Dwarf',
            4 => 'Night Elf',
                'items_invalid' => 'Invalid items list format. Use itemId:qty (one per line)',
            5 => 'Undead',
            6 => 'Tauren',
            7 => 'Gnome',
            8 => 'Troll',
            10 => 'Blood Elf',
            11 => 'Draenei',
        ],
                    'send_item_gold' => 'Send items + gold',
        'qualities' => [
            0 => 'Poor',
            1 => 'Common',
            2 => 'Uncommon',
            3 => 'Rare',
            4 => 'Epic',
            5 => 'Legendary',
            6 => 'Artifact',
            7 => 'Heirloom',
        ],
        'fallbacks' => [
            'class' => 'Unknown #:id',
            'race' => 'Unknown #:id',
            'quality' => 'Quality #:id',
        ],
    ],
    'database' => [
        'errors' => [
            'config_missing' => 'Database configuration missing: :name',
            'connection_failed' => 'Failed to connect :database at :host:: :port (:error)',
            'server_config_missing' => 'Server configuration missing: :server (role :role)',
        ],
    ],
    'home' => [
        'page_title' => 'Welcome to the new panel',
        'intro' => 'This is the initial skeleton. Version: :version',
        'features' => [
            'unified_mvc' => 'Unified MVC routing',
            'migration' => 'Legacy modules (Account/Item/Creature/…) will be migrated progressively.',
        ],
        'readme_heading' => 'Project README',
        'readme_missing' => 'README file is not available.',
        'readme_source' => 'Source file: :file',
    ],
    'auth' => [
        'page_title' => 'Admin Sign-in',
        'login_title' => 'Sign in',
        'username' => 'Username',
        'password' => 'Password',
        'submit' => 'Sign in',
        'error_invalid' => 'Invalid username or password',
        'errors' => [
            'not_logged_in' => 'Sign-in required',
        ],
    ],
    'account' => [
        'page_title' => 'Account Management',
        'search' => [
            'type_username' => 'By username',
            'type_id' => 'By ID',
            'placeholder' => 'Search…',
            'submit' => 'Search',
            'load_all' => 'Load all accounts',
            'create' => 'Create account',
        ],
                'filters' => [
            'online' => 'Online status',
            'online_any' => 'All accounts',
            'online_only' => 'Online only',
            'online_offline' => 'Offline only',
            'ban' => 'Ban status',
            'ban_any' => 'All accounts',
            'ban_only' => 'Banned only',
            'ban_unbanned' => 'Not banned',
            'exclude_username' => 'Exclude text',
            'exclude_username_placeholder' => 'e.g. test',
        ],
        'feedback' => [
            'found' => ':total records found · Page :page of :pages',
            'empty' => 'No results',
            'enter_search' => 'Enter search criteria',
            'private_ip_disabled' => 'LAN IP lookup disabled',
        ],
        'table' => [
            'id' => 'ID',
            'username' => 'Username',
            'gm' => 'GM',
            'online' => 'Online',
            'last_login' => 'Last login',
            'last_ip' => 'Last IP',
            'ip_location' => 'IP location',
            'actions' => 'Actions',
        ],
        'status' => [
            'online' => 'Online',
            'offline' => 'Offline',
        ],
        'actions' => [
            'chars' => 'Characters',
            'gm' => 'GM',
            'ban' => 'Ban',
            'unban' => 'Unban',
            'password' => 'Reset password',
            'email' => 'Email',
            'rename' => 'Rename',
            'same_ip' => 'Accounts on IP',
            'kick' => 'Kick',
            'delete' => 'Delete',
        ],
        'ban' => [
            'badge' => 'Banned (:duration)',
            'tooltip' => "Reason: :reason\nStart: :start\nEnd: :end",
            'no_end' => 'Permanent',
            'permanent' => 'Permanent',
            'soon' => 'Ends soon',
            'under_minute' => 'Under 1 minute',
            'separator' => ' ',
            'duration' => [
                'day' => ':value day',
                'hour' => ':value hr',
                'minute' => ':value min',
            ],
            'prompt_hours' => 'Ban duration in hours (0 = permanent):',
            'error_hours' => 'Invalid duration',
            'prompt_reason' => 'Ban reason:',
            'default_reason' => 'Panel ban',
            'success' => 'Account banned successfully',
            'failure' => 'Failed to ban account',
            'confirm_unban' => 'Unban this account?',
            'unban_success' => 'Account unbanned',
            'unban_failure' => 'Failed to unban account',
        ],
        'ip_lookup' => [
            'private' => 'Private IP',
            'failed' => 'Lookup failed',
            'unknown' => 'Unknown location',
            'loading' => 'Looking up…',
        ],
        'characters' => [
            'title' => 'Character list - :name',
            'loading' => 'Loading…',
            'fetch_error' => 'Failed to load characters',
            'table' => [
                'guid' => 'GUID',
                'name' => 'Name',
                'level' => 'Level',
                'status' => 'Status',
            ],
            'kick_button' => 'Kick offline',
            'offline_tooltip' => 'Character offline, cannot kick',
            'empty' => 'No characters',
            'ban_badge' => 'Banned',
            'confirm_kick' => 'Kick character :name?',
            'kick_success' => 'Kick command dispatched: :name',
            'kick_failed' => 'Kick failed: :message',
            'fetch_failed' => 'Failed to load characters: :message',
        ],
        'gm' => [
            'prompt_level' => 'Set GM level (0-6):',
            'error_level' => 'Invalid GM level',
            'success' => 'GM level updated',
            'failure' => 'Failed to update GM level',
        ],
        'password' => [
            'prompt_new' => 'Enter new password (min 8 chars):',
            'error_empty' => 'Password cannot be empty',
            'error_length' => 'Password must be at least 8 characters',
            'prompt_confirm' => 'Re-enter new password:',
            'error_mismatch' => 'Passwords do not match',
            'success' => 'Password updated successfully (previous sessions invalidated)',
            'failure' => 'Failed to change password: :message',
            'failure_generic' => 'Unknown error',
        ],
        'email' => [
            'title' => 'Update email - :name',
            'labels' => [
                'email' => 'Email',
            ],
            'placeholders' => [
                'email' => 'example@domain.com',
            ],
            'actions' => [
                'cancel' => 'Cancel',
                'submit' => 'Save',
            ],
            'invalid' => 'Invalid email address',
            'not_supported' => 'Email column is not available in this schema',
            'blocked_online' => 'Cannot update email while account is online',
            'success' => 'Email updated',
        ],
        'rename' => [
            'title' => 'Rename account - :name',
            'labels' => [
                'username' => 'New username',
                'password' => 'New password',
                'password_confirm' => 'Confirm password',
            ],
            'actions' => [
                'cancel' => 'Cancel',
                'submit' => 'Save',
            ],
            'invalid_username' => 'Invalid username (1-20 chars)',
            'invalid_password' => 'Password must be at least 8 characters',
            'password_mismatch' => 'Passwords do not match',
            'password_reset_failed' => 'Password reset failed (cannot generate verifier)',
            'blocked_online' => 'Cannot rename while account is online',
            'taken' => 'Username is already taken',
            'success' => 'Username updated (:old → :new)',
        ],
        'create' => [
            'title' => 'Create account',
            'labels' => [
                'username' => 'Username',
                'password' => 'Password',
                'password_confirm' => 'Confirm password',
                'email' => 'Email (optional)',
                'gmlevel' => 'GM level',
            ],
            'placeholders' => [
                'username' => 'Case insensitive',
                'password' => 'At least 8 characters',
                'password_confirm' => 'Re-enter password',
                'email' => 'example@domain.com',
            ],
            'gm_options' => [
                'player' => '0 - Player',
                'one' => '1',
                'two' => '2',
                'three' => '3',
            ],
            'actions' => [
                'cancel' => 'Cancel',
                'submit' => 'Create',
            ],
            'status' => [
                'submitting' => 'Creating…',
            ],
            'errors' => [
                'username_required' => 'Please enter a username',
                'username_length' => 'Username may not exceed 32 characters',
                'password_length' => 'Password must be at least 8 characters',
                'password_mismatch' => 'Passwords do not match',
                'email_length' => 'Email address is too long',
                'email_invalid' => 'Invalid email address',
                'request_generic' => 'Creation failed',
            ],
            'success' => 'Account created: :name',
        ],
        'same_ip' => [
            'missing_ip' => 'No last IP available for this account',
            'title' => 'Accounts on IP - :ip',
            'loading' => 'Loading…',
            'empty' => 'No other accounts found for this IP',
            'table' => [
                'id' => 'ID',
                'username' => 'Username',
                'gm' => 'GM',
                'status' => 'Status',
                'last_login' => 'Last login',
                'ip_location' => 'IP location',
            ],
            'status' => [
                'banned' => 'Banned',
                'remaining' => 'Remaining: :value',
            ],
            'error_generic' => 'Failed to query accounts',
            'error' => 'Failed to query accounts: :message',
        ],
        'api' => [
            'validation' => [
                'username_min' => 'Username must be at least 3 characters long',
                'username_max' => 'Username may not exceed 32 characters',
                'password_min' => 'Password must be at least 8 characters long',
                'gm_range' => 'GM level must be between 0 and 6',
            ],
            'defaults' => [
                'no_reason' => 'No reason',
            ],
            'errors' => [
                'missing_username_column' => 'Account table is missing the username column',
                'username_exists' => 'Username already exists',
                'build_columns_failed' => 'Unable to build the account insert column set',
                'missing_account_id' => 'Unable to retrieve the newly created account ID',
                'password_set_failed' => 'Failed to set the account password',
                'create_failed' => 'Failed to create account: :message',
                'query_characters_failed' => 'Failed to query characters: :message',
                'password_schema_unsupported' => 'Password change failed: account schema does not support SRP or sha_pass_hash updates',
            ],
        ],
    ],

    'character' => [
        'index' => [
            'title' => 'Character Management',
            'search' => [
                'name_placeholder' => 'Name contains',
                'guid_placeholder' => 'GUID',
                'account_placeholder' => 'Account username',
                'level_min' => 'Min level',
                'level_max' => 'Max level',
                'submit' => 'Search',
                'load_all' => 'Load all characters',
            ],
            'filters' => [
                'online_any' => 'All accounts',
                'online_only' => 'Online only',
                'online_offline' => 'Offline only',
            ],
            'sort' => [
                'guid_desc' => 'GUID (newest first)',
                'logout_desc' => 'Last logout (newest first)',
                'level_desc' => 'Level (highest first)',
                'online_desc' => 'Online first',
            ],
            'feedback' => [
                'found' => ':total records · Page :page of :pages',
                'empty' => 'No results',
                'enter_search' => 'Enter search criteria',
            ],
            'table' => [
                'guid' => 'GUID',
                'name' => 'Name',
                'account' => 'Account',
                'level' => 'Level',
                'class' => 'Class',
                'race' => 'Race',
                'map' => 'Map',
                'zone' => 'Zone',
                'online' => 'Online',
                'last_logout' => 'Last logout',
                'actions' => 'Actions',
                'view' => 'View',
            ],
            'status' => [
                'online' => 'Online',
                'offline' => 'Offline',
                'banned' => 'Banned',
            ],
        ],
        'show' => [
            'title' => 'Character: :name (GUID :guid)',
            'title_not_found' => 'Character not found (GUID :guid)',
            'title_default' => 'Character details',
            'back' => 'Back to list',
            'not_found' => 'Character not found',
            'summary' => [
                'title' => 'Summary',
                'guid' => 'GUID',
                'name' => 'Name',
                'account' => 'Account',
                'level' => 'Level',
                'class' => 'Class',
                'race' => 'Race',
                'online' => 'Online',
                'map' => 'Map / Zone',
                'position' => 'Position',
                'money' => 'Money',
                'copper' => 'copper',
                'mail' => 'Mail (inbox)',
                'logout' => 'Last logout',
                'homebind' => 'Homebind',
                'homebind_none' => 'Not set',
                'gmlevel' => 'GM level',
                'ban' => 'Ban status',
            ],
            'status' => [
                'online' => 'Online',
                'offline' => 'Offline',
            ],
            'ban' => [
                'active' => 'Banned: :reason (ends: :end)',
                'permanent' => 'Permanent',
                'none' => 'Not banned',
            ],
            'inventory' => [
                'title' => 'Inventory (equip/bags/bank)',
                'bag' => 'Bag',
                'slot' => 'Slot',
                'item_guid' => 'Item GUID',
                'entry' => 'Item entry',
                'count' => 'Count',
                'random' => 'Random prop',
                'durability' => 'Durability',
                'text' => 'Text',
                'empty' => 'No inventory data',
            ],
            'skills' => [
                'title' => 'Skills',
                'skill' => 'Skill',
                'value' => 'Value',
                'max' => 'Max',
                'empty' => 'No skills recorded',
            ],
            'spells' => [
                'title' => 'Spells',
                'spell' => 'Spell',
                'active' => 'Active',
                'disabled' => 'Disabled',
                'empty' => 'No spells recorded',
            ],
            'reputations' => [
                'title' => 'Reputations',
                'faction' => 'Faction',
                'standing' => 'Standing',
                'flags' => 'Flags',
                'empty' => 'No reputation rows',
            ],
            'quests' => [
                'title' => 'Quests',
                'regular' => 'Active / progress',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'quest' => 'Quest',
                'status' => 'Status',
                'timer' => 'Timer',
                'mob_counts' => 'Mob counts',
                'item_counts' => 'Item counts',
                'empty' => 'No quest progress',
                'empty_daily' => 'No daily quest records',
                'empty_weekly' => 'No weekly quest records',
            ],
            'auras' => [
                'title' => 'Auras',
                'caster' => 'Caster GUID',
                'item' => 'Item GUID',
                'spell' => 'Spell',
                'mask' => 'Effect mask',
                'amounts' => 'Amounts',
                'charges' => 'Charges',
                'duration' => 'Max duration',
                'remaining' => 'Remaining',
                'empty' => 'No auras',
            ],
            'cooldowns' => [
                'title' => 'Cooldowns',
                'spell' => 'Spell',
                'item' => 'Item',
                'time' => 'Timestamp',
                'category' => 'Category',
                'empty' => 'No cooldown records',
            ],
            'achievements' => [
                'title' => 'Achievements',
                'unlocks' => 'Unlocked',
                'progress' => 'Progress',
                'achievement' => 'Achievement',
                'criteria' => 'Criteria',
                'counter' => 'Counter',
                'date' => 'Date',
                'empty_unlocks' => 'No unlocked achievements',
                'empty_progress' => 'No progress rows',
            ],
            'bool' => [
                'yes' => 'Yes',
                'no' => 'No',
            ],
        ],
        'actions' => [
            'title' => 'Actions',
            'group_stats' => 'Stats',
            'group_moderation' => 'Moderation',
            'group_movement' => 'Movement',
            'group_tools' => 'Tools',

            'default_reason' => 'Panel ban',
            'set_level' => 'Set level',
            'set_gold' => 'Set gold',
            'level_label' => 'Level',
            'gold_label' => 'Money (copper)',
            'set' => 'Set',

            'ban_label' => 'Ban character',
            'ban' => 'Ban',
            'unban' => 'Unban',
            'ban_hours' => 'Hours',
            'reason_placeholder' => 'Reason',
            'teleport' => 'Teleport',
            'teleport_label' => 'Teleport',
            'teleport_preset_placeholder' => 'Quick locations',
            'teleport_presets' => [
                'stormwind' => 'Stormwind',
                'ironforge' => 'Ironforge',
                'darnassus' => 'Darnassus',
                'exodar' => 'The Exodar',
                'orgrimmar' => 'Orgrimmar',
                'undercity' => 'Undercity',
                'thunder_bluff' => 'Thunder Bluff',
                'silvermoon' => 'Silvermoon',
                'dalaran' => 'Dalaran',
                'shattrath' => 'Shattrath',
            ],
            'teleport_map' => 'Map',
            'teleport_zone' => 'Zone',
            'teleport_x' => 'X',
            'teleport_y' => 'Y',
            'teleport_z' => 'Z',
            'unstuck' => 'Unstuck',
            'reset_talents' => 'Reset talents',
            'reset_spells' => 'Reset spells',
            'reset_cooldowns' => 'Reset cooldowns',
            'rename_flag' => 'Flag rename',
            'delete' => 'Delete',
            'confirm_delete' => 'Are you sure you want to delete this character?',
            'success' => 'Operation succeeded',
            'failed' => 'Operation failed',
            'blocked_online' => 'Character is online; please kick first.',
        ],

        'controls' => [
            'expand_all' => 'Expand all',
            'collapse_all' => 'Collapse all',
            'filter_placeholder' => 'Filter rows...',
            'filter_no_results' => 'No rows match the filter',
        ],
    ],
    'alerts' => [
        'not_installed_redirect' => 'Installation not finished; redirecting to the setup wizard...',
        'bootstrap' => [
            'auto_detect_base_path' => 'Detected base_path = :base; it will be persisted after setup finishes.',
            'base_path_mismatch' => 'Current access prefix ":detected" differs from configured base_path ":configured". Please review deployment settings.',
            'normalized_path' => 'Normalized the access path. Please use :target as the panel entry.',
            'auto_write_base_path' => 'Persisted base_path = :base in config/generated/app.php automatically.',
        ],
    ],
    'cli' => [
        'normalize_config' => [
            'missing_dir' => 'Config directory not found: :path',
            'fixed' => 'Fixed: :file',
            'skipped_failed' => 'Skipped (replace failed): :file',
            'summary' => 'Finished. Fixed files: :fixed, skipped: :skipped',
        ],
    ],
    'errors' => [
        'internal_server_error_title' => 'Internal Server Error',
    ],
    'soap' => [
        'page_title' => 'SOAP Command Wizard',
        'intro' => 'A curated list of AzerothCore GM commands. Pick one and follow the wizard to fill parameters and execute.',
        'search_label' => 'Search commands',
        'search_placeholder' => 'Type keywords or command fragments',
        'summary' => [
            'title' => 'Select a command',
            'hint' => 'Choose a category and command on the left. Use search to filter, and switch realms through the top dropdown.',
        ],
        'target_hint' => 'Requires selecting a target in-game before executing.',
        'steps' => [
            'fill' => 'Step 1: Provide parameters',
            'confirm' => 'Step 2: Confirm command',
        ],
        'preview_label' => 'Pending command',
        'actions' => [
            'copy' => 'Copy command',
            'execute' => 'Execute command',
        ],
        'output_title' => 'Execution result',
        'legacy' => [
            'errors' => [
                'curl_failed' => 'Failed to contact the SOAP endpoint.',
                'curl_error_unknown' => 'Unknown cURL error.',
                'http_error' => 'SOAP request returned HTTP status :code.',
            ],
        ],
        'modules' => [
            'soap' => [
                'feedback' => [
                    'execute_success' => 'Executed successfully',
                    'execute_failed' => 'Execution failed',
                ],
            ],
        ],
        'wizard' => [
            'errors' => [
                'command_not_found' => 'Command definition not found',
                'command_missing' => 'The command is not available or has been retired',
                'argument_required' => 'This field is required',
                'validation_failed' => 'Validation failed',
        'errors' => [
            'init_failed' => 'Mail module initialization failed',
            'exception' => 'Mail module error',
        ],
        'api' => [
            'errors' => [
                'unauthorized' => 'Unauthorized',
                'invalid_id' => 'Invalid ID',
                'missing_id' => 'ID is required',
                'no_valid_id' => 'No valid IDs provided',
                'not_found' => 'Not found',
                'delete_restricted' => 'Delete failed: only system or GM mails can be removed.',
                'repository_not_ready' => 'Repository not initialized',
            ],
            'success' => [
                'marked_read' => 'Mail marked as read',
                'no_changes' => 'No changes',
                'bulk_marked' => 'Marked :count mails as read',
                'deleted_single' => 'Mail deleted (system/GM only)',
                'bulk_deleted' => 'Deleted :count mails',
                'bulk_deleted_blocked_suffix' => '(blocked :count)',
            ],
        ],
                'template_missing_list' => 'Missing template parameters: :fields',
                'template_incomplete' => 'Command template is incomplete',
                'number_required' => 'Please enter a numeric value',
                'number_invalid' => 'The value must be a number',
                'number_too_small' => 'Value may not be less than :min',
                'number_too_large' => 'Value may not exceed :max',
                'invalid_option' => 'Invalid option selected',
            ],
            'catalog' => [
                'categories' => [
                    'general' => [
                        'label' => 'General',
                        'summary' => 'Server status, announcements, and GM utilities',
                    ],
                    'account' => [
                        'label' => 'Account management',
                        'summary' => 'Account GM levels, locking, and bans',
                    ],
                    'character' => [
                        'label' => 'Character management',
                        'summary' => 'Character level, appearance, and status control',
                    ],
                    'teleport' => [
                        'label' => 'Teleport / position',
                        'summary' => 'Teleportation and positioning commands for characters or GMs',
                    ],
                    'item' => [
                        'label' => 'Items / equipment',
                        'summary' => 'Add or remove items for the target',
                    ],
                    'spell' => [
                        'label' => 'Spells / skills',
                        'summary' => 'Grant or remove spells, skills, or talents',
                    ],
                    'quest' => [
                        'label' => 'Quests',
                        'summary' => 'Grant, complete, or remove quests',
                    ],
                    'misc' => [
                        'label' => 'Appearance / status',
                        'summary' => 'Miscellaneous commands such as morphing or editing money',
                    ],
                ],
                'commands' => [
                    'server-info' => [
                        'description' => 'Show core information, build time, online players, and uptime.',
                    ],
                    'server-motd' => [
                        'description' => 'View or update the server MOTD (login message).',
                        'arguments' => [
                            'message' => [
                                'label' => 'Announcement message',
                                'placeholder' => 'Leave empty to display the current MOTD',
                            ],
                        ],
                    ],
                    'announce-global' => [
                        'description' => 'Broadcast a system announcement to all players.',
                        'arguments' => [
                            'message' => [
                                'label' => 'Announcement message',
                                'placeholder' => 'Enter the announcement text',
                            ],
                        ],
                    ],
                    'announce-name' => [
                        'description' => 'Send an announcement that displays the GM name.',
                        'arguments' => [
                            'message' => [
                                'label' => 'Announcement message',
                            ],
                        ],
                    ],
                    'notify' => [
                        'description' => 'Show a center-screen notification to all players.',
                        'arguments' => [
                            'message' => [
                                'label' => 'Notification text',
                            ],
                        ],
                    ],
                    'gm-visible' => [
                        'description' => 'Toggle GM visibility in the world.',
                        'arguments' => [
                            'state' => [
                                'label' => 'Visibility state',
                                'options' => [
                                    'on' => 'on - Enable GM invisibility',
                                    'off' => 'off - Disable GM invisibility',
                                ],
                            ],
                        ],
                    ],
                    'account-set-gmlevel' => [
                        'description' => 'Set the GM level for an account.',
                        'arguments' => [
                            'account' => [
                                'label' => 'Account username',
                            ],
                            'level' => [
                                'label' => 'GM level',
                                'options' => [
                                    '0' => '0 - Player',
                                    '1' => '1 - Junior GM',
                                    '2' => '2 - Full GM',
                                    '3' => '3 - Administrator',
                                ],
                            ],
                            'realm' => [
                                'label' => 'Realm ID (optional)',
                                'placeholder' => 'Leave empty to apply to all realms',
                            ],
                        ],
                    ],
                    'account-set-password' => [
                        'description' => 'Reset an account password.',
                        'arguments' => [
                            'account' => [
                                'label' => 'Account username',
                            ],
                            'password' => [
                                'label' => 'New password',
                            ],
                        ],
                    ],
                    'account-lock' => [
                        'description' => 'Enable or disable account locking.',
                        'arguments' => [
                            'account' => [
                                'label' => 'Account username',
                            ],
                            'state' => [
                                'label' => 'Lock state',
                                'options' => [
                                    'on' => 'on - Lock logins',
                                    'off' => 'off - Unlock logins',
                                ],
                            ],
                        ],
                    ],
                    'ban-account' => [
                        'description' => 'Ban an account for a duration with an optional reason.',
                        'arguments' => [
                            'account' => [
                                'label' => 'Account username',
                            ],
                            'duration' => [
                                'label' => 'Ban duration',
                                'placeholder' => 'For example 3d, 12h, or permanent',
                            ],
                            'reason' => [
                                'label' => 'Reason (optional)',
                            ],
                        ],
                    ],
                    'unban-account' => [
                        'description' => 'Lift an account ban.',
                        'arguments' => [
                            'account' => [
                                'label' => 'Account username',
                            ],
                        ],
                    ],
                    'character-level' => [
                        'description' => 'Set a character\'s level.',
                        'arguments' => [
                            'name' => [
                                'label' => 'Character name',
                            ],
                            'level' => [
                                'label' => 'Level',
                            ],
                        ],
                    ],
                    'character-rename' => [
                        'description' => 'Force a character to rename on next login.',
                        'arguments' => [
                            'name' => [
                                'label' => 'Character name',
                            ],
                        ],
                    ],
                    'character-customize' => [
                        'description' => 'Force a character to customize appearance on next login.',
                        'arguments' => [
                            'name' => [
                                'label' => 'Character name',
                            ],
                        ],
                    ],
                    'character-revive' => [
                        'description' => 'Revive a dead character.',
                        'arguments' => [
                            'name' => [
                                'label' => 'Character name',
                            ],
                        ],
                    ],
                    'character-lookup' => [
                        'description' => 'Search characters by name pattern.',
                        'arguments' => [
                            'pattern' => [
                                'label' => 'Character name keyword',
                            ],
                        ],
                    ],
                    'tele-name' => [
                        'description' => 'Teleport to a predefined location (must exist in the database).',
                        'arguments' => [
                            'location' => [
                                'label' => 'Location name',
                            ],
                        ],
                    ],
                    'tele-worldport' => [
                        'description' => 'Teleport to explicit map coordinates. Confirm the coordinates before use.',
                        'arguments' => [
                            'map' => [
                                'label' => 'Map ID',
                            ],
                            'x' => [
                                'label' => 'X coordinate',
                            ],
                            'y' => [
                                'label' => 'Y coordinate',
                            ],
                            'z' => [
                                'label' => 'Z coordinate',
                            ],
                            'o' => [
                                'label' => 'Facing (optional)',
                            ],
                        ],
                        'notes' => [
                            'ensure_valid' => 'Ensure the coordinates are valid to avoid disconnects or stuck characters.',
                        ],
                    ],
                    'go-creature' => [
                        'description' => 'Teleport to the location of a creature GUID.',
                        'arguments' => [
                            'guid' => [
                                'label' => 'Creature GUID',
                            ],
                        ],
                    ],
                    'go-object' => [
                        'description' => 'Teleport to the location of a game object GUID.',
                        'arguments' => [
                            'guid' => [
                                'label' => 'Game object GUID',
                            ],
                        ],
                    ],
                    'summon-player' => [
                        'description' => 'Summon a player to the GM\'s position.',
                        'arguments' => [
                            'player' => [
                                'label' => 'Player name',
                            ],
                        ],
                        'notes' => [
                            'require_online' => 'Target player must be online.',
                        ],
                    ],
                    'additem' => [
                        'description' => 'Give the selected player a specific item.',
                        'arguments' => [
                            'item' => [
                                'label' => 'Item ID',
                            ],
                            'count' => [
                                'label' => 'Count (optional)',
                            ],
                        ],
                    ],
                    'additemset' => [
                        'description' => 'Give the selected player an entire item set.',
                        'arguments' => [
                            'itemset' => [
                                'label' => 'Item set ID',
                            ],
                        ],
                    ],
                    'removeitem' => [
                        'description' => 'Remove an item from the selected player.',
                        'arguments' => [
                            'item' => [
                                'label' => 'Item ID',
                            ],
                            'count' => [
                                'label' => 'Count (optional)',
                            ],
                        ],
                    ],
                    'learn-spell' => [
                        'description' => 'Teach the selected character a spell or skill.',
                        'arguments' => [
                            'spell' => [
                                'label' => 'Spell ID',
                            ],
                        ],
                    ],
                    'unlearn-spell' => [
                        'description' => 'Remove a spell or skill from the selected character.',
                        'arguments' => [
                            'spell' => [
                                'label' => 'Spell ID',
                            ],
                        ],
                    ],
                    'talent-reset' => [
                        'description' => 'Reset the selected character\'s talents.',
                    ],
                    'quest-add' => [
                        'description' => 'Add a quest to the selected character.',
                        'arguments' => [
                            'quest' => [
                                'label' => 'Quest ID',
                            ],
                        ],
                    ],
                    'quest-complete' => [
                        'description' => 'Complete a quest for the selected character.',
                        'arguments' => [
                            'quest' => [
                                'label' => 'Quest ID',
                            ],
                        ],
                    ],
                    'quest-remove' => [
                        'description' => 'Remove a quest from the selected character.',
                        'arguments' => [
                            'quest' => [
                                'label' => 'Quest ID',
                            ],
                        ],
                    ],
                    'morph' => [
                        'description' => 'Morph the selected character into a display ID.',
                        'arguments' => [
                            'display' => [
                                'label' => 'Display ID',
                            ],
                        ],
                    ],
                    'demorph' => [
                        'description' => 'Restore the selected character\'s original model.',
                    ],
                    'modify-money' => [
                        'description' => 'Add or remove copper from the selected character; positive adds, negative removes.',
                        'arguments' => [
                            'amount' => [
                                'label' => 'Copper amount (can be negative)',
                                'placeholder' => 'e.g. 100000 (10 gold) or -5000',
                            ],
                        ],
                    ],
                    'modify-speed' => [
                        'description' => 'Adjust the selected character\'s movement speed multiplier.',
                        'arguments' => [
                            'multiplier' => [
                                'label' => 'Speed multiplier',
                                'placeholder' => '1 = normal, 2 = double',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'executor' => [
            'errors' => [
                'empty' => 'Command cannot be empty',
                'not_whitelisted' => 'Command is not whitelisted',
                'request_failed' => 'Request failed',
                'unknown' => 'Unknown error',
            ],
        ],
    ],
    'flags' => [
        'regular' => [
            'not_lootable' => 'Not lootable',
            'conjured' => 'Conjured',
            'openable' => 'Openable',
            'indestructible' => 'Indestructible',
            'no_equip_cooldown' => 'No equip cooldown',
            'wrapper_container' => 'Wrapper container',
            'party_loot_shared' => 'Shared party loot',
            'refundable' => 'Refundable',
            'unique_equipped' => 'Unique-equipped',
            'arena_usable' => 'Usable in arenas',
            'throwable' => 'Throwable',
            'shapeshift_usable' => 'Usable while shapeshifted',
            'profession_recipe' => 'Profession recipe',
            'account_bound' => 'Account bound',
            'ignore_reagent' => 'Ignore reagent requirements',
            'millable' => 'Millable',
        ],
        'extra' => [
            'horde_only' => 'Horde only',
            'alliance_only' => 'Alliance only',
            'extended_cost_requires_gold' => 'Extended cost requires gold',
            'disable_need_roll' => 'Disable Need roll',
            'disable_need_roll_alt' => 'Disable Need roll (alt)',
            'standard_pricing' => 'Standard pricing',
            'battle_net_bound' => 'Battle.net bound',
        ],
        'custom' => [
            'real_time_duration' => 'Real-time duration',
            'ignore_quest_status' => 'Ignore quest status',
            'party_loot_rules' => 'Party loot rules',
        ],
        'separator' => ', ',
        'empty' => '(none)',
    ],
    'smartai' => [
        'page_title' => 'SmartAI Wizard',
        'intro' => 'Build events, actions, and targets following the AzerothCore Smart Scripts spec, then generate executable SQL.',
        'sidebar' => [
            'nav_title' => 'Step guide',
            'steps' => [
                'base' => 'Basics',
                'event' => 'Select Event',
                'action' => 'Configure Action',
                'target' => 'Target & Preview',
            ],
            'quick_view' => 'At a glance',
            'view_wiki' => 'View official Wiki',
            'updated_at' => 'Catalog updated: :date',
        ],
        'base' => [
            'title' => 'Basics',
            'description' => 'Define the script scope and shared fields (entry, chance, phase, etc.).',
        ],
        'segment' => [
            'add' => 'Add segment',
            'hint' => 'Each segment owns its event, action, and target, executed sequentially.',
        ],
        'event' => [
            'title' => 'Select Event',
            'description' => 'Events decide when the script runs. Pick a type and fill parameters based on the Wiki.',
        ],
        'action' => [
            'title' => 'Configure Action',
            'description' => 'Actions run when the event fires; combine spells, dialogues, summons, and more.',
        ],
        'target' => [
            'title' => 'Target & Preview',
            'description' => 'Choose targets and generate SQL for execution or download.',
        ],
        'preview' => [
            'title' => 'SQL Preview',
            'generate' => 'Generate SQL',
            'copy' => 'Copy',
            'placeholder' => '-- Finish previous steps and click Generate first',
        ],
        'footer' => [
            'prev' => 'Previous',
            'next' => 'Next',
            'step_indicator' => 'Step :current of :total',
        ],
        'catalog' => [
            'metadata' => [
                'notes' => [
                    'Field and parameter semantics follow the AzerothCore Wiki.',
            'exclude_username' => 'Username not contains',
            'exclude_username_placeholder' => 'e.g. test',
                    'Generated SQL can be applied to the smart_scripts table.',
                ],
            ],
            'source_types' => [
                '0' => [
                    'label' => 'Creature',
                ],
                '1' => [
                    'label' => 'GameObject',
                ],
                '2' => [
                    'label' => 'AreaTrigger',
                ],
                '3' => [
                    'label' => 'Event',
                ],
                '9' => [
                    'label' => 'Timed ActionList',
                ],
            ],
            'base' => [
                'entryorguid' => [
                    'label' => 'Entry / GUID',
                    'hint' => 'Provide the entry or guid based on the selected source type.',
                ],
                'source_type' => [
                    'label' => 'Source Type',
                    'hint' => 'Script source (Creature/GameObject/Timed ActionList/etc.).',
                ],
                'id' => [
                    'label' => 'ID',
                    'hint' => 'Script index within the same entry/source_type.',
                ],
                'link' => [
                    'label' => 'Link',
                    'hint' => 'Link to a previous script ID (0 = no link).',
                ],
                'event_phase_mask' => [
                    'label' => 'Phase Mask',
                    'hint' => 'Event phase mask (bitmask).',
                ],
                'event_chance' => [
                    'label' => 'Chance',
                    'hint' => 'Trigger chance (0-100).',
                ],
                'event_flags' => [
                    'label' => 'Event Flags',
                    'hint' => 'Event flags (bitmask).',
                ],
                'comment' => [
                    'label' => 'Comment',
                    'hint' => 'Optional comment.',
                ],
                'include_delete' => [
                    'label' => 'Include DELETE',
                    'hint' => 'Include a statement to delete previous scripts when generating SQL.',
                ],
            ],
        ],
        'builder' => [
            'messages' => [
                'validation_failed' => 'Parameter validation failed',
            ],
            'errors' => [
                'base' => [
                    'entryorguid' => 'Please enter a valid entry or GUID.',
                    'source_type' => 'Unsupported source_type. Please choose one of the dropdown options.',
                    'event_chance' => 'Chance must be between 0 and 100.',
                    'event_flags' => 'Event flags may not be negative.',
                    'id_negative' => 'Script ID may not be negative.',
                    'link_negative' => 'Link may not be negative.',
                    'phase_negative' => 'Phase mask may not be negative.',
                ],
                'segment' => [
                    'event_required' => 'At least one event is required.',
                ],
                'event' => [
                    'type' => 'Please select an event type.',
                ],
                'action' => [
                    'type' => 'Please select an action type.',
                ],
                'target' => [
                    'type' => 'Please select a target type.',
                ],
            ],
        ],
    ],
    'logs' => [
        'page_title' => 'Unified Log Management',
        'intro' => 'Centralized view of panel module logs with quick filters, auto refresh, and raw output.',
        'fields' => [
            'module' => 'Module',
            'type' => 'Type',
            'limit' => 'Row limit',
        ],
        'actions' => [
            'load' => 'Load',
            'auto_refresh' => 'Enable auto refresh',
        ],
        'table' => [
            'headers' => [
                'time' => 'Time',
                'server' => 'Server',
                'actor' => 'Actor',
                'summary' => 'Summary',
            ],
            'loading' => 'Loading…',
        ],
        'raw' => [
            'title' => 'Raw log output',
            'empty' => '-- Waiting for data --',
        ],
        'index' => [
            'page_title' => 'Log Viewer',
            'errors' => [
                'invalid_module' => 'Invalid module or type',
                'read_failed' => 'Failed to read logs: :message',
                'unauthorized' => 'Unauthorized',
            ],
        ],
        'manager' => [
            'summary' => [
                'impact' => 'Impact: :count',
                'impact_paren' => ' (Impact: :count)',
                'error_prefix' => ' | ERR: :message',
            ],
            'pipe_sql' => [
                'summary' => ':type :status (Affected: :affected)',
                'sql_suffix' => ' | :sql',
                'error_suffix' => ' | ERR: :error',
            ],
        ],
        'config' => [
            'modules' => [
                'item_owner' => [
                    'label' => 'Item Ownership',
                    'description' => 'Bulk delete and replace history.',
                    'types' => [
                        'actions' => [
                            'label' => 'Action records',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'mail' => [
        'page_title' => 'Mail Management',
        'filters' => [
            'sender' => 'Sender',
            'receiver' => 'Receiver',
            'subject' => 'Subject contains',
            'unread_all' => 'All statuses',
            'unread_only' => 'Unread only',
            'attachments_all' => 'Attachments (all)',
            'attachments_only' => 'Attachments only',
            'expiring' => 'Expiring (days)',
            'actions' => [
                'search' => 'Search',
                'reset' => 'Reset',
                'refresh' => 'Refresh',
                'log' => 'View logs',
            ],
        ],
        'toolbar' => [
            'bulk_read' => 'Mark selected as read',
            'bulk_delete' => 'Delete selected (system/GM)',
            'total' => 'Total:',
        ],
        'table' => [
            'headers' => [
                'id' => 'ID',
                'sender' => 'Sender',
                'receiver' => 'Receiver',
                'subject' => 'Subject',
                'money' => 'Money',
                'attachments' => 'Attachments',
                'expire' => 'Expires',
                'status' => 'Status',
                'actions' => 'Actions',
            ],
            'attachments' => [
                'has' => 'Yes',
            ],
            'status' => [
                'unread' => 'Unread',
                'read' => 'Read',
            ],
            'actions' => [
                'view' => 'View',
                'mark_read' => 'Mark as read',
                'delete' => 'Delete',
            ],
            'expire' => [
                'none' => '—',
                'expired' => 'Expired',
                'in_days' => 'In :days days',
            ],
            'loading' => 'Loading…',
            'empty' => 'No records',
        ],
        'log_modal' => [
            'title' => 'Mail action log',
            'type_label' => 'Log type',
            'types' => [
                'sql' => 'SQL execution',
                'deleted' => 'Deleted records',
            ],
            'limit_label' => 'Limit',
            'limits' => [
                'recent' => 'Last :count entries',
            ],
            'refresh' => 'Refresh',
            'empty' => '-- No logs --',
            'close' => 'Close',
        ],
        'detail' => [
            'title' => 'Mail detail',
            'loading' => 'Loading…',
            'labels' => [
                'sender' => 'Sender',
                'receiver' => 'Receiver',
                'money' => 'Money',
                'expire' => 'Expires',
                'status' => 'Status',
                'attachment_count' => 'Attachments',
                'subject' => 'Subject:',
                'attachments' => 'Attachments:',
            ],
            'status' => [
                'unread' => 'Unread',
                'read' => 'Read',
            ],
            'expire' => [
                'expired' => 'Expired',
                'today' => 'Expires today',
                'day_singular' => 'Expires in :days day',
                'day_plural' => 'Expires in :days days',
            ],
            'no_subject' => '(No subject)',
            'no_body' => '(No content)',
            'attachments_yes' => 'Yes',
            'attachments_no' => 'No',
            'attachments_none' => 'No attachments',
            'close' => 'Close',
        ],
        'actions' => [
            'view' => 'View',
            'mark_read' => 'Mark as read',
            'delete' => 'Delete',
        ],
        'status' => [
            'load_failed' => 'Failed to load list',
            'mark_read_done' => 'Mail marked as read',
            'mark_failed' => 'Failed to mark as read',
            'bulk_mark_done' => 'Marked :count mails as read',
            'bulk_mark_failed' => 'Bulk mark failed',
            'delete_done' => 'Mail deleted',
            'delete_failed' => 'Delete failed',
            'bulk_delete_done' => 'Deleted :count mails',
            'bulk_delete_failed' => 'Bulk delete failed',
            'logs_failed' => 'Failed to load mail logs',
            'detail_failed' => 'Failed to load mail detail',
            'action_failed' => 'Operation failed',
        ],
        'confirm' => [
            'delete_one' => 'Delete this mail (system/GM)?',
            'delete_selected' => 'Delete selected mails?',
        ],
        'logs' => [
            'loading' => '-- Loading --',
            'empty' => '-- No logs --',
            'failed' => '-- Load failed --',
            'error' => 'Failed to load logs',
            'meta' => ':file | Lines: :count',
            'meta_with_server' => ':file | Lines: :count | Server: :server',
        ],
        'tail_log' => [
            'unknown_type' => 'Unknown log type',
            'sql_entry' => '[:time][:server] :user :operation :status (Affected: :affected)',
            'sql_suffix' => ' | :sql',
            'sql_error_suffix' => ' | ERR: :error',
            'action_entry' => '[:time][:server] :user :action ID: :id',
            'action_snapshot_suffix' => ' SQL: :snapshot',
        ],
        'stats' => [
            'summary' => 'Unread estimate: :unread | Expiring in 7 days: :expiring',
        ],
        'errors' => [
            'init_failed' => 'Mail module initialization failed',
            'exception' => 'Mail module error',
        ],
        'api' => [
            'errors' => [
                'unauthorized' => 'Unauthorized',
                'invalid_id' => 'Invalid ID',
                'missing_id' => 'ID is required',
                'no_valid_id' => 'No valid IDs provided',
                'not_found' => 'Not found',
                'delete_restricted' => 'Delete failed: only system or GM mails can be removed.',
                'repository_not_ready' => 'Repository not initialized',
            ],
            'success' => [
                'marked_read' => 'Mail marked as read',
                'no_changes' => 'No changes',
                'bulk_marked' => 'Marked :count mails as read',
                'deleted_single' => 'Mail deleted (system/GM only)',
                'bulk_deleted' => 'Deleted :count mails',
                'bulk_deleted_blocked_suffix' => '(blocked :count)',
            ],
        ],
    ],
    'mass_mail' => [
        'index' => [
            'page_title' => 'Mass Mail Console',
            'sections' => [
                'announce' => [
                    'title' => 'Broadcast announcement',
                    'message_label' => 'Announcement message',
                    'message_placeholder' => 'Enter the announcement message',
                    'submit' => 'Send announcement',
                    'hint' => 'Executes .announce and .notify together.',
                ],
                'boost' => [
                    'title' => 'Character boost package',
                    'character_label' => 'Character name',
                    'character_placeholder' => 'Enter the character name',
                    'level_label' => 'Target level',
                    'level_options' => [
                        '60' => 'Level 60',
                        '70' => 'Level 70',
                        '80' => 'Level 80',
                    ],
                    'summary_label' => 'Package summary',
                    'summary_prefill' => "500 gold (5,000,000 copper)\nNetherweave Bag ×3 (#21841)\nSea Turtle ×1 (#23720)\nClass-specific Tier 2 set (auto-detected)",
                    'submit' => 'Execute boost',
                ],
                'send' => [
                    'title' => 'Mass mail / items / gold',
                    'action_label' => 'Action',
                    'action_placeholder' => '-- Select --',
                    'action_options' => [
                        'send_mail' => 'Mail (text only)',
                        'send_item' => 'Items (multiple)',
                        'send_gold' => 'Gold (via mail)',
                        'send_item_gold' => 'Items + Gold',
                    ],
                    'target_label' => 'Target type',
                    'target_options' => [
                        'online' => 'Online characters',
                        'custom' => 'Custom list',
                    ],
                    'subject_label' => 'Subject',
                    'subject_default' => 'PureLand',
                    'body_label' => 'Body',
                    'body_default' => 'Message from the staff. Enjoy your time in Azeroth!',
                    'items_label' => 'Items list',
                    'item_id_label' => 'Item ID',
                    'quantity_label' => 'Quantity',
                    'add_item' => 'Add item',
                    'remove_item' => 'Remove',
                    'items_placeholder' => "",
                    'items_hint' => 'Add multiple rows; each row has an item ID and a quantity.',
                    'gold_label' => 'Gold (copper units)',
                    'gold_preview_placeholder' => '—',
                    'custom_list_label' => 'Custom character list (one per line)',
                    'submit' => 'Execute mass send',
                    'hint' => 'Up to 2,000 recipients per batch. Failed characters are marked with ! in the log.',
                ],
                'logs' => [
                    'title' => 'Recent activity log',
                    'limit_label' => 'Show',
                    'limit_options' => [
                        '30' => 'Latest 30',
                        '50' => 'Latest 50',
                        '100' => 'Latest 100',
                    ],
                    'refresh' => 'Refresh',
                    'table' => [
                        'headers' => [
                            'time' => 'Time',
                            'type' => 'Type',
                            'details' => 'Details',
                            'targets' => 'Targets',
                            'success_fail' => 'Success / Fail',
                            'status' => 'OK',
                            'recipients' => 'Recipients',
                        ],
                        'item_prefix' => 'Item #:id',
                        'items_label' => 'Items: :value',
                        'item_name_separator' => ' - ',
                        'item_quantity_prefix' => ' ×',
                        'gold_units' => [
                            'gold' => ':value gold',
                            'silver' => ':value silver',
                            'copper' => ':value copper',
                            'separator' => '',
                        ],
                        'gold_label' => 'Gold: :value',
                        'error_prefix' => 'Error: ',
                        'recipients_placeholder' => '—',
                        'empty' => 'No log entries',
                    ],
                ],
            ],
            'confirm' => [
                'title' => 'Confirm mass action',
                'hint_html' => 'Enter <code>CONFIRM</code> to continue',
                'input_placeholder' => 'CONFIRM',
                'cancel' => 'Cancel',
                'submit' => 'Proceed',
            ],
        ],
        'announce' => [
            'validation' => [
                'empty' => 'Please enter the announcement message',
            ],
        ],
        'feedback' => [
            'done' => 'Completed',
        ],
        'errors' => [
            'network' => 'Network error',
            'parse_failed' => 'Failed to parse response',
        ],
        'status' => [
            'sending' => 'Sending…',
        ],
        'confirm' => [
            'heading' => 'About to execute <strong>:action</strong>',
            'subject' => 'Subject: :value',
            'items' => 'Items: :items',
            'gold' => 'Gold (copper): :amount',
            'target_type' => 'Target type: :value',
            'custom_count' => 'Custom characters: :count',
            'online' => 'Online characters: count will be resolved during send',
            'footer' => 'Batched sending is enabled (batch size = 200). Confirm to continue.',
        ],
        'logs' => [
            'empty' => 'No log entries',
            'item_label' => 'Item #:id',
            'items_label' => 'Items: :value',
            'item_name_separator' => ' - ',
            'item_quantity_prefix' => ' ×',
            'gold_label' => 'Gold: :value',
            'error_prefix' => 'Error: ',
        ],
        'boost' => [
            'summary' => [
                'gold' => '500 gold (:copper copper)',
                'bag' => 'Netherweave Bag ×:count (#21841)',
                'mount' => 'Sea Turtle ×:count (#23720)',
                'set' => 'Class-specific Tier 2 set (auto-detected)',
            ],
            'validation' => [
                'name' => 'Please enter a character name',
                'level' => 'Please choose a target level',
            ],
            'status' => [
                'executing' => 'Executing…',
            ],
        ],
        'gold' => [
            'units' => [
                'gold' => 'Gold',
                'silver' => 'Silver',
                'copper' => 'Copper',
            ],
        ],
        'service' => [
            'announce' => [
                'message_required' => 'Announcement message cannot be empty',
                'success' => 'Announcement sent',
                'partial' => 'Announcement sent with errors',
            ],
            'bulk' => [
                'subject_required' => 'Mail subject cannot be empty',
                'no_targets' => 'No recipients selected',
                'target_limit' => 'Recipient count exceeds limit (maximum :max)',
                'item_invalid' => 'Item ID or quantity is invalid',
                'item_missing' => 'Item not found (ID :id)',
                'gold_invalid' => 'Amount must be greater than 0',
                'unknown_action' => 'Unknown action type',
                'action_labels' => [
                    'send_mail' => 'Send mail',
                    'send_item' => 'Send items',
                    'send_gold' => 'Send gold',
                ],
                'summary' => 'Action: :action · Batches: :batches · Targets: :targets · Success: :success · Fail: :fail',
                'sample_errors' => 'Sample errors: :errors',
            ],
            'boost' => [
                'name_required' => 'Character name cannot be empty',
                'level_unsupported' => 'Unsupported target level',
                'character_missing' => 'Character not found or class unsupported',
                'config_empty' => 'Boost package configuration is empty',
                'unknown_error' => 'Unknown error',
                'command_failed' => ':label failed: :reason',
                'success' => 'Character :name boosted to level :level and package delivered.',
                'partial' => 'Boost completed with errors: :errors',
                'mail' => [
                    'subject' => 'Boost package',
                    'body_items' => 'Boost package items attached.',
                    'body_gold' => 'Boost package gold attached.',
                ],
                'commands' => [
                    'items' => 'Send items',
                    'gold' => 'Send gold',
                    'level' => 'Set level',
                ],
                'class_labels' => [
                    'warrior' => 'Warrior',
                    'paladin' => 'Paladin',
                    'hunter' => 'Hunter',
                    'rogue' => 'Rogue',
                    'priest' => 'Priest',
                    'shaman' => 'Shaman',
                    'mage' => 'Mage',
                    'warlock' => 'Warlock',
                    'druid' => 'Druid',
                ],
                'log_subject' => 'Boost package sent to :name',
                'log_item_label' => ':class boost items',
            ],
        ],
    ],
    'item_owner' => [
        'page_title' => 'Item Ownership',
        'search' => [
            'title' => 'Search items',
            'subtitle' => 'Look up which characters own a specific item by name or entry.',
            'keyword_label' => 'Keyword',
            'keyword_placeholder' => 'Name or entry',
            'submit' => 'Search',
            'status' => [
                'loading' => 'Loading…',
            ],
            'results' => [
                'entry' => 'Entry',
                'name' => 'Name',
                'quality' => 'Quality',
                'stackable' => 'Stack size',
                'actions' => 'Actions',
                'placeholder' => 'Search results will appear here.',
                'view' => 'View ownership',
                'empty' => 'No items found',
            ],
            'validation' => [
                'empty' => 'Please enter a keyword',
            ],
            'error' => [
                'failed' => 'Search failed',
            ],
        ],
        'results' => [
            'title_empty' => 'Select an item',
            'subtitle_empty' => 'Search for an item to view ownership details.',
            'title_loading' => 'Loading ownership…',
            'title_error' => 'Failed to load ownership',
            'subtitle_totals' => ':characters characters · :instances stacks · total :count items',
            'status' => [
                'loading' => 'Loading…',
            ],
            'error' => [
                'load_failed' => 'Failed to load ownership',
            ],
            'characters' => [
                'title' => 'Characters',
                'name' => 'Name',
                'level' => 'Level',
                'class' => 'Class',
                'total' => 'Total count',
                'placeholder' => 'No characters found',
            ],
            'instances' => [
                'title' => 'Item instances',
                'instance' => 'Instance GUID',
                'character' => 'Character',
                'count' => 'Count',
                'location' => 'Location',
                'container' => 'Container',
                'placeholder' => 'No item instances found',
            ],
        ],
        'actions' => [
            'delete_selected' => 'Delete selected',
            'replace_selected' => 'Replace selected',
            'confirm_delete' => 'Delete selected item instances?',
            'delete_success' => 'Selected item instances deleted',
            'delete_failed' => 'Failed to delete item instances',
            'replace_success' => 'Selected item instances replaced',
            'replace_failed' => 'Failed to replace item instances',
        ],
        'modal' => [
            'replace' => [
                'title' => 'Replace item',
                'entry_label' => 'New item entry',
                'entry_placeholder' => 'Enter item entry',
                'entry_hint' => 'Applies the new item entry to every selected instance.',
                'cancel' => 'Cancel',
                'confirm' => 'Apply',
                'validation' => [
                    'entry' => 'Enter a valid item entry',
                ],
            ],
        ],
        'quality' => [
            'unknown' => 'Unknown',
            '0' => 'Poor',
            '1' => 'Common',
            '2' => 'Uncommon',
            '3' => 'Rare',
            '4' => 'Epic',
            '5' => 'Legendary',
            '6' => 'Artifact',
            '7' => 'Heirloom',
        ],
        'api' => [
            'errors' => [
                'invalid_entry' => 'Invalid item entry.',
                'entry_not_found' => 'Item not found.',
                'empty_selection' => 'Select at least one item instance.',
                'instances_not_found' => 'Selected item instances were not found.',
                'invalid_instance' => 'Invalid item instance.',
                'unknown_action' => 'Unknown action.',
                'invalid_new_entry' => 'Enter a valid replacement item entry.',
                'new_entry_not_found' => 'Replacement item entry not found.',
                'stack_too_large' => 'Stack size :stack exceeds limit :limit.',
                'delete_partial' => 'Deleted :success instances, :failed failed.',
                'replace_partial' => 'Replaced :success instances, :failed failed.',
                'replace_failed' => 'Failed to update the item instance.',
            ],
            'success' => [
                'delete_done' => 'Deleted :count item instances.',
                'replace_done' => 'Replaced :count item instances.',
            ],
        ],
        'locations' => [
            'equipment' => [
                'head' => 'Head',
                'neck' => 'Neck',
                'shoulders' => 'Shoulders',
                'body' => 'Shirt',
                'chest' => 'Chest',
                'waist' => 'Waist',
            'delete' => 'Delete',
                'legs' => 'Legs',
        'bulk' => [
            'select_all' => 'Select all',
            'delete' => 'Bulk delete',
            'ban' => 'Bulk ban',
            'unban' => 'Bulk unban',
            'no_selection' => 'Please select at least one item',
        ],
        'delete' => [
            'confirm' => 'Delete this account? This will also delete all characters under the account. This cannot be undone.',
            'success' => 'Deleted',
            'blocked_online' => 'Online character detected (:name). Please kick first.',
            'characters_failed' => 'Failed to delete characters: :message',
            'account_failed' => 'Failed to delete account: :message',
        ],
                'feet' => 'Feet',
                'wrist' => 'Wrist',
                'hands' => 'Hands',
                'finger1' => 'Finger 1',
                'finger2' => 'Finger 2',
                'trinket1' => 'Trinket 1',
                'trinket2' => 'Trinket 2',
                'back' => 'Back',
                'main_hand' => 'Main Hand',
                'off_hand' => 'Off Hand',
                'ranged' => 'Ranged/Relic',
                'tabard' => 'Tabard',
            ],
            'inventory' => [
                'backpack' => 'Backpack slot :slot',
                'bank_main' => 'Bank slot :slot',
                'keyring' => 'Keyring slot :slot',
                'currency' => 'Currency slot :slot',
                'bag_slot' => 'Bag slot :slot',
                'bag_inner' => 'Bag :bag slot :slot',
                'unknown' => 'Inventory (unknown slot)',
            ],
            'bank' => [
                'bag_slot' => 'Bank bag :slot',
                'bag_inner' => 'Bank bag :bag slot :slot',
            ],
        ],
    ],
    'creature' => [
        'index' => [
            'page_title' => 'Creature Management',
            'filters' => [
                'search_type' => [
                    'name' => 'By name',
                    'id' => 'By ID',
                ],
                'placeholders' => [
                    'search_value' => 'Keyword or ID',
                    'min_level' => 'Min level',
                    'max_level' => 'Max level',
                ],
                'buttons' => [
                    'search' => 'Search',
                    'reset' => 'Reset',
                    'create' => 'Create',
                    'log' => 'View logs',
                ],
            ],
            'npcflag' => [
                'summary' => 'NPC flag filter',
                'apply' => 'Apply',
                'clear' => 'Clear',
                'mode_hint' => 'Mode: all selected bits must be present (AND)',
            ],
            'table' => [
                'headers' => [
                    'id' => 'ID',
                    'name' => 'Name',
                    'subname' => 'Subname',
                    'min_level' => 'Min level',
                    'max_level' => 'Max level',
                    'faction' => 'Faction',
                    'npcflag' => 'NPC flags',
                    'actions' => 'Actions',
                    'verify' => 'Verify',
                ],
                'actions' => [
                    'edit' => 'Edit',
                    'delete' => 'Delete',
                ],
                'verify_button' => 'Verify',
                'empty' => 'No results',
            ],
            'modals' => [
                'new' => [
                    'title' => 'Create creature',
                    'id_label' => 'New ID*',
                    'copy_label' => 'Copy from (optional)',
                    'copy_hint' => 'Leave copy ID empty to create a blank template.',
                    'cancel' => 'Cancel',
                    'confirm' => 'Create',
                ],
                'log' => [
                    'title' => 'Creature logs',
                    'type_label' => 'Log type',
                    'types' => [
                        'sql' => 'SQL execution',
                        'deleted' => 'Delete snapshots',
                        'actions' => 'Action trace',
                    ],
                    'refresh' => 'Refresh',
                    'empty' => '-- No logs yet --',
                    'close' => 'Close',
                ],
                'verify' => [
                    'title' => 'Row verification',
                    'headers' => [
                        'field' => 'Field',
                        'rendered' => 'Rendered value',
                        'database' => 'Database value',
                        'status' => 'Status',
                    ],
                    'close' => 'Close',
                    'copy_sql' => 'Copy UPDATE statement',
                ],
            ],
        ],
        'edit' => [
            'title' => 'Edit creature #:id',
            'actions' => [
                'back' => 'Back to list',
                'compact' => 'Compact',
                'delete' => 'Delete',
                'save' => 'Save',
                'diff_sql' => 'Diff SQL',
                'exec_sql' => 'Execute SQL',
                'copy' => 'Copy',
                'execute' => 'Execute',
                'add_model' => 'Add model',
                'edit_model' => 'Edit',
                'delete_model' => 'Delete',
                'cancel' => 'Cancel',
            ],
            'labels' => [
                'only_changes' => 'Only changes',
            ],
            'toolbar' => [
                'changed_fields' => 'Changed fields:',
            ],
            'diff' => [
                'title' => 'Diff SQL preview',
                'hint' => 'Click "Diff SQL" to generate; only modified columns are included. Empty string -> NULL. LIMIT 1 is added automatically.',
                'placeholder' => '-- Not generated yet --',
            ],
            'models' => [
                'heading' => 'Model list (creature_template_model)',
                'table' => [
                    'index' => 'Index',
                    'display_id' => 'Display ID',
                    'scale' => 'Scale',
                    'probability' => 'Probability',
                    'verified_build' => 'Verified build',
                    'actions' => 'Actions',
                ],
                'empty' => 'No models',
            ],
            'modal' => [
                'title' => 'Model',
                'display_id' => 'Display ID',
                'scale' => 'Scale',
                'probability' => 'Probability (0-1)',
                'verified_build' => 'Verified build',
            ],
            'rank_enum' => [
                0 => 'Normal',
                1 => 'Elite',
                2 => 'Rare Elite',
                3 => 'Boss',
                4 => 'Rare',
            ],
            'type_enum' => [
                0 => 'None',
                1 => 'Beast',
                2 => 'Dragonkin',
                3 => 'Demon',
                4 => 'Elemental',
                5 => 'Giant',
                6 => 'Undead',
                7 => 'Humanoid',
                8 => 'Critter',
                9 => 'Mechanical',
                10 => 'Not specified',
                11 => 'Totem',
                12 => 'Companion',
                13 => 'Gas Cloud',
            ],
        ],
        'config' => [
            'groups' => [
                'base' => [
                    'label' => 'Base information',
                    'fields' => [
                        'name' => [
                            'label' => 'Name',
                        ],
                        'subname' => [
                            'label' => 'Subname',
                        ],
                        'minlevel' => [
                            'label' => 'Minimum level',
                        ],
                        'maxlevel' => [
                            'label' => 'Maximum level',
                        ],
                        'exp' => [
                            'label' => 'Experience type (exp)',
                            'help' => '0=None, 1=Normal, 2=Elite',
                        ],
                        'faction' => [
                            'label' => 'Faction ID (faction)',
                        ],
                        'scale' => [
                            'label' => 'Model scale (scale)',
                        ],
                        'speed_walk' => [
                            'label' => 'Walk speed (speed_walk)',
                        ],
                        'speed_run' => [
                            'label' => 'Run speed (speed_run)',
                        ],
                        'rank' => [
                            'label' => 'Rank type (rank)',
                        ],
                        'type' => [
                            'label' => 'Creature type (type)',
                        ],
                    ],
                ],
                'combat' => [
                    'label' => 'Combat parameters',
                    'fields' => [
                        'dmgschool' => [
                            'label' => 'Damage school (dmgschool)',
                        ],
                        'baseattacktime' => [
                            'label' => 'Melee attack interval (ms)',
                        ],
                        'rangeattacktime' => [
                            'label' => 'Ranged attack speed (ms)',
                        ],
                        'mindmg' => [
                            'label' => 'Melee minimum damage (mindmg)',
                        ],
                        'maxdmg' => [
                            'label' => 'Melee maximum damage (maxdmg)',
                        ],
                        'dmg_multiplier' => [
                            'label' => 'Damage modifier (dmg_multiplier)',
                        ],
                        'basevariance' => [
                            'label' => 'Damage variance (basevariance)',
                        ],
                        'rangevariance' => [
                            'label' => 'Ranged damage variance (rangevariance)',
                        ],
                        'attackpower' => [
                            'label' => 'Melee attack power (attackpower)',
                        ],
                        'rangedattackpower' => [
                            'label' => 'Ranged attack power (rangedattackpower)',
                        ],
                    ],
                ],
                'vitals' => [
                    'label' => 'Health / Mana / Resistances',
                    'fields' => [
                        'healthmodifier' => [
                            'label' => 'Health modifier (healthmodifier)',
                        ],
                        'manamodifier' => [
                            'label' => 'Mana modifier (manamodifier)',
                        ],
                        'armormodifier' => [
                            'label' => 'Armor modifier (armormodifier)',
                        ],
                        'resistance1' => [
                            'label' => 'Holy resistance (resistance1)',
                        ],
                        'resistance2' => [
                            'label' => 'Fire resistance (resistance2)',
                        ],
                        'resistance3' => [
                            'label' => 'Nature resistance (resistance3)',
                        ],
                        'resistance4' => [
                            'label' => 'Frost resistance (resistance4)',
                        ],
                        'resistance5' => [
                            'label' => 'Shadow resistance (resistance5)',
                        ],
                        'resistance6' => [
                            'label' => 'Arcane resistance (resistance6)',
                        ],
                    ],
                ],
                'drops' => [
                    'label' => 'Drops / Economy',
                    'fields' => [
                        'lootid' => [
                            'label' => 'Standard loot ID (lootid)',
                        ],
                        'pickpocketloot' => [
                            'label' => 'Pickpocket loot ID (pickpocketloot)',
                        ],
                        'skinloot' => [
                            'label' => 'Skinning loot ID (skinloot)',
                        ],
                        'mingold' => [
                            'label' => 'Gold minimum (mingold)',
                        ],
                        'maxgold' => [
                            'label' => 'Gold maximum (maxgold)',
                        ],
                    ],
                ],
                'ai' => [
                    'label' => 'AI / Scripts',
                    'fields' => [
                        'ainame' => [
                            'label' => 'AI name (ainame)',
                        ],
                        'scriptname' => [
                            'label' => 'Script name (scriptname)',
                        ],
                        'gossip_menu_id' => [
                            'label' => 'Gossip menu ID (gossip_menu_id)',
                        ],
                        'movementtype' => [
                            'label' => 'Movement type (movementtype)',
                        ],
                    ],
                ],
                'flags' => [
                    'label' => 'Flags / Bitmasks',
                    'fields' => [
                        'npcflag' => [
                            'label' => 'NPC flags (npcflag)',
                        ],
                        'unit_flags' => [
                            'label' => 'Unit flags (unit_flags)',
                        ],
                        'unit_flags2' => [
                            'label' => 'Unit flags 2 (unit_flags2)',
                        ],
                        'type_flags' => [
                            'label' => 'Type flags (type_flags)',
                        ],
                        'flags_extra' => [
                            'label' => 'Extra flags (flags_extra)',
                        ],
                        'dynamicflags' => [
                            'label' => 'Dynamic flags (dynamicflags)',
                        ],
                    ],
                ],
            ],
            'flags' => [
                'npcflag' => [
                    0 => 'Gossip',
                    1 => 'QuestGiver',
                    2 => 'Trainer',
                    3 => 'ClassTrainer',
                    4 => 'ProfessionTrainer',
                    5 => 'Vendor',
                    6 => 'VendorAmmo',
                    7 => 'VendorFood',
                    8 => 'VendorPoison',
                    9 => 'VendorReagent',
                    10 => 'Repair',
                    11 => 'FlightMaster',
                    12 => 'SpiritHealer',
                    13 => 'SpiritGuide',
                    14 => 'Innkeeper',
                    15 => 'Banker',
                    16 => 'Petitioner',
                    17 => 'TabardDesigner',
                    18 => 'BattleMaster',
                    19 => 'Auctioneer',
                    20 => 'StableMaster',
                    21 => 'GuildBanker',
                    22 => 'Spellclick',
                    23 => 'Mailbox',
                ],
                'unit_flags' => [
                    0 => 'ServerControlled',
                    1 => 'NonAttackable',
                    2 => 'RemoveClientControl',
                    3 => 'PlayerControlled',
                    4 => 'Rename',
                    5 => 'Preparation',
                    6 => 'Unk6',
                    7 => 'NotAttackable1',
                    8 => 'ImmunePC',
                    9 => 'ImmuneNPC',
                    10 => 'Looting',
                    11 => 'PetInCombat',
                    12 => 'PvP',
                    13 => 'Silenced',
                    14 => 'CannotSwim',
                    15 => 'CanSwim',
                    16 => 'NonAttackable2',
                    17 => 'Pacified',
                    18 => 'Stunned',
                    19 => 'InCombat',
                    20 => 'TaxiFlight',
                    21 => 'Disarmed',
                ],
                'unit_flags2' => [
                    0 => 'FeignDeath',
                    1 => 'HideBody',
                    2 => 'Unk2',
                    3 => 'NoSelect',
                    4 => 'InteractWhileDead',
                    5 => 'ForceMovement',
                    6 => 'DisarmOffhand',
                    7 => 'Unk7',
                    8 => 'AllowChangingTalents',
                    9 => 'NotTauntable',
                ],
                'type_flags' => [
                    0 => 'Tameable',
                    1 => 'GhostVisible',
                    2 => 'Boss',
                    3 => 'DoNotPlayWoundParryAnim',
                    4 => 'NoLoot',
                    5 => 'NoXP',
                    6 => 'Trigger',
                    7 => 'Guard',
                ],
                'flags_extra' => [
                    0 => 'InstanceBind',
                    1 => 'Civilian',
                    2 => 'NoAggro',
                    3 => 'NoInteract',
                    4 => 'TameablePet',
                    5 => 'DeadInteract',
                    6 => 'ForceGossip',
                ],
                'dynamicflags' => [
                    0 => 'Glow',
                    1 => 'Lootable',
                    2 => 'TrackUnit',
                    3 => 'Tapped',
                    4 => 'TappedByPlayer',
                    5 => 'SpecialInfo',
                ],
            ],
            'factions' => [
                14 => 'Monster',
                35 => 'Friendly Guard',
                68 => 'Generic Friendly',
                69 => 'Player: Alliance',
                70 => 'Player: Horde',
                74 => 'Stormwind Guard',
                84 => 'Ironforge Guard',
                120 => 'Thunder Bluff Guard',
                121 => 'Orgrimmar Guard',
                122 => 'Darnassus Guard',
                123 => 'Gnomeregan Guard',
                124 => 'Undercity Guard',
                169 => 'Steamwheedle Port',
                469 => 'Alliance',
                529 => 'Argent Dawn',
                530 => 'Horde',
                609 => 'Cenarion Circle',
                910 => 'Darkmoon Faire',
                934 => 'The Frostborn',
                935 => 'Valiance Expedition',
            ],
        ],
        'repository' => [
            'errors' => [
                'invalid_new_id' => 'Invalid new ID.',
                'id_exists' => 'ID already exists.',
                'copy_source_missing' => 'Source entry does not exist.',
                'copy_failed' => 'Failed to copy creature template.',
                'create_failed' => 'Failed to create creature template.',
                'invalid_id' => 'Invalid ID.',
                'no_rows_deleted' => 'No rows were deleted.',
                'no_changes' => 'No changes to apply.',
                'no_valid_fields' => 'No valid columns provided.',
                'no_value_changes' => 'Values remain unchanged.',
                'update_failed' => 'Update failed.',
                'model_invalid' => 'Invalid model data.',
                'model_index_limit' => 'Model index limit reached.',
                'model_add_failed' => 'Failed to add model.',
                'model_update_failed' => 'Failed to update model.',
                'model_delete_failed' => 'No models were deleted.',
                'sql_empty' => 'SQL statement cannot be empty.',
                'sql_multi' => 'Multiple statements are not allowed.',
                'sql_parse_column' => 'Unable to parse column: :column',
                'sql_invalid_column' => 'Column :column is not allowed.',
                'sql_update_where' => 'UPDATE must end with WHERE entry = <number> (optional LIMIT 1).',
                'sql_only_update_insert' => 'Only UPDATE or INSERT creature_template statements are allowed.',
                'sql_exec_error' => 'Execution error: :error',
            ],
            'success' => [
                'copied' => 'Creature template copied from #:source.',
                'created' => 'Creature template created.',
                'deleted' => 'Deleted successfully (ID #:id).',
                'updated' => 'Update completed.',
                'model_added' => 'Model added successfully.',
                'model_updated' => 'Model updated successfully.',
                'model_deleted' => 'Model deleted successfully.',
                'sql_action_inserted' => 'Inserted',
                'sql_action_affected' => 'Affected',
                'sql_rows' => ':action rows: :count',
            ],
            'info_labels' => [
                0 => 'Normal',
                1 => 'Group',
                21 => 'Profession',
                41 => 'PvP',
                62 => 'Raid',
                81 => 'Dungeon',
                82 => 'Event',
                83 => 'Legendary',
                84 => 'Escort',
                85 => 'Heroic',
                88 => 'Raid (10 player)',
                89 => 'Raid (25 player)',
            ],
        ],
    ],
    'item' => [
        'page_title' => 'Item Management',
        'quality' => [
            'unknown' => 'Unknown',
        ],
        'meta' => [
            'qualities' => [
                0 => 'Poor',
                1 => 'Common',
                2 => 'Uncommon',
                3 => 'Rare',
                4 => 'Epic',
                5 => 'Legendary',
                6 => 'Artifact',
                7 => 'Heirloom',
            ],
            'classes' => [
                0 => 'Consumable',
                1 => 'Container',
                2 => 'Weapon',
                3 => 'Gem',
                4 => 'Armor',
                5 => 'Reagent',
                6 => 'Projectile',
                7 => 'Trade Goods',
                8 => 'Generic (deprecated)',
                9 => 'Recipe',
                10 => 'Currency (deprecated)',
                11 => 'Quiver',
                12 => 'Quest',
                13 => 'Key',
                14 => 'Permanent (deprecated)',
                15 => 'Miscellaneous',
                16 => 'Glyph',
            ],
            'subclasses' => [
                0 => [
                    0 => 'Consumable',
                    1 => 'Potion',
                    2 => 'Elixir',
                    3 => 'Flask',
                    4 => 'Scroll',
                    5 => 'Food & Drink',
                    6 => 'Item Enhancement',
                    7 => 'Bandage',
                    8 => 'Other',
                ],
                1 => [
                    0 => 'Bag',
                    1 => 'Soul Bag',
                    2 => 'Herb Bag',
                    3 => 'Enchanting Bag',
                    4 => 'Engineering Bag',
                    5 => 'Gem Bag',
                    6 => 'Mining Bag',
                    7 => 'Leatherworking Bag',
                    8 => 'Inscription Bag',
                ],
                2 => [
                    0 => 'One-Handed Axe',
                    1 => 'Two-Handed Axe',
                    2 => 'Bow',
                    3 => 'Gun',
                    4 => 'One-Handed Mace',
                    5 => 'Two-Handed Mace',
                    6 => 'Polearm',
                    7 => 'One-Handed Sword',
                    8 => 'Two-Handed Sword',
                    9 => 'Obsolete',
                    10 => 'Staff',
                    13 => 'Fist Weapon',
                    14 => 'Miscellaneous Weapon',
                    15 => 'Dagger',
                    16 => 'Thrown',
                    17 => 'Spear',
                    18 => 'Crossbow',
                    19 => 'Wand',
                    20 => 'Fishing Pole',
                ],
                3 => [
                    0 => 'Red',
                    1 => 'Blue',
                    2 => 'Yellow',
                    3 => 'Purple',
                    4 => 'Green',
                    5 => 'Orange',
                    6 => 'Meta',
                    7 => 'Simple',
                    8 => 'Prismatic',
                ],
                4 => [
                    0 => 'Miscellaneous',
                    1 => 'Cloth',
                    2 => 'Leather',
                    3 => 'Mail',
                    4 => 'Plate',
                    5 => 'Buckler (deprecated)',
                    6 => 'Shield',
                    7 => 'Libram',
                    8 => 'Idol',
                    9 => 'Totem',
                    10 => 'Sigil',
                ],
                5 => [
                    0 => 'Reagent',
                ],
                6 => [
                    0 => 'Wand (deprecated)',
                    1 => 'Bolt (deprecated)',
                    2 => 'Arrow',
                    3 => 'Bullet',
                    4 => 'Thrown (deprecated)',
                ],
                7 => [
                    0 => 'Trade Goods',
                    1 => 'Parts',
                    2 => 'Explosives',
                    3 => 'Devices',
                    4 => 'Jewelcrafting',
                    5 => 'Cloth',
                    6 => 'Leather',
                    7 => 'Metal & Stone',
                    8 => 'Meat',
                    9 => 'Herb',
                    10 => 'Elemental',
                    11 => 'Other',
                    12 => 'Enchanting',
                    13 => 'Materials',
                    14 => 'Armor Enchantment',
                    15 => 'Weapon Enchantment',
                ],
                8 => [
                    0 => 'Generic (deprecated)',
                ],
                9 => [
                    0 => 'Book',
                    1 => 'Leatherworking',
                    2 => 'Tailoring',
                    3 => 'Engineering',
                    4 => 'Blacksmithing',
                    5 => 'Cooking',
                    6 => 'Alchemy',
                    7 => 'First Aid',
                    8 => 'Enchanting',
                    9 => 'Fishing',
                    10 => 'Jewelcrafting',
                    11 => 'Inscription',
                ],
                10 => [
                    0 => 'Currency (deprecated)',
                ],
                11 => [
                    0 => 'Quiver (deprecated)',
                    1 => 'Ammo Pouch (deprecated)',
                    2 => 'Quiver',
                    3 => 'Ammo Pouch',
                ],
                12 => [
                    0 => 'Quest',
                ],
                13 => [
                    0 => 'Key',
                    1 => 'Lockpick',
                ],
                14 => [
                    0 => 'Permanent (deprecated)',
                ],
                15 => [
                    0 => 'Junk',
                    1 => 'Reagent',
                    2 => 'Companion Pet',
                    3 => 'Holiday',
                    4 => 'Other',
                    5 => 'Mount',
                ],
                16 => [
                    1 => 'Warrior Glyph',
                    2 => 'Paladin Glyph',
                    3 => 'Hunter Glyph',
                    4 => 'Rogue Glyph',
                    5 => 'Priest Glyph',
                    6 => 'Death Knight Glyph',
                    7 => 'Shaman Glyph',
                    8 => 'Mage Glyph',
                    9 => 'Warlock Glyph',
                    11 => 'Druid Glyph',
                ],
            ],
        ],
        'filter' => [
            'type_name' => 'By name',
            'type_id' => 'By ID',
            'keyword_placeholder' => 'Keyword or ID',
            'quality_all' => 'All qualities',
            'class_all' => 'All classes',
            'subclass_all' => 'All subclasses',
            'submit' => 'Search',
            'reset' => 'Reset',
            'reset_title' => 'Clear filters',
            'create' => 'New item',
            'sql_log' => 'SQL log',
        ],
        'table' => [
            'id' => 'ID',
            'name' => 'Name',
            'quality' => 'Quality',
            'class' => 'Class',
            'subclass' => 'Subclass',
            'level' => 'Level',
            'actions' => 'Actions',
            'empty' => 'No results',
        ],
        'tooltip' => [
            'quality' => 'Quality: :quality (:value)',
        ],
        'actions' => [
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],
        'modal' => [
            'common' => [
                'cancel' => 'Cancel',
                'close' => 'Close',
            ],
            'new' => [
                'title' => 'Create item',
                'id_label' => 'New ID*',
                'copy_label' => 'Copy from (optional)',
                'copy_hint' => 'Leave copy ID empty to create a blank template.',
                'class' => 'Class',
                'subclass' => 'Subclass',
                'submit' => 'Create',
            ],
            'log' => [
                'title' => 'Item log viewer',
                'type_label' => 'Log type',
                'type_sql' => 'SQL execution',
                'type_deleted' => 'Delete snapshots',
                'type_actions' => 'Action trace',
                'refresh' => 'Refresh',
                'placeholder' => '-- No logs yet --',
            ],
        ],
        'edit' => [
            'title' => 'Edit item #:id',
            'page_title' => 'Edit item #:id',
            'back_to_list' => 'Back to list',
            'compact' => [
                'normal' => 'Normal',
                'compact' => 'Compact',
            ],
            'delete' => 'Delete',
            'save' => 'Save',
            'diff_sql' => 'Diff SQL',
            'flags' => [
                'title' => 'Flags',
                'choose' => 'Select',
                'loading' => '(Loading...)',
            ],
            'description' => 'Description',
            'diff' => [
                'title' => 'Diff preview',
                'full_mode' => 'Include all columns',
                'hint' => 'An UPDATE statement is generated automatically whenever fields change.',
                'placeholder' => '-- No changes --',
                'exec_title' => 'Execution result',
                'sample_title' => 'Sample row',
            ],
            'actions' => [
                'copy' => 'Copy',
                'execute' => 'Execute',
                'clear' => 'Clear',
                'hide' => 'Hide',
                'copy_json' => 'Copy JSON',
            ],
            'group_fallback' => 'Section :index',
        ],
        'api' => [
            'errors' => [
                'invalid_id' => 'Invalid item ID.',
                'not_found' => 'Item not found.',
                'log_type_unknown' => 'Unknown log type.',
            ],
        ],
        'config' => [
            'groups' => [
                'base' => [
                    'label' => 'Base information',
                    'fields' => [
                        'name' => [
                            'label' => 'Name',
                        ],
                        'quality' => [
                            'label' => 'Quality',
                        ],
                        'class' => [
                            'label' => 'Class',
                        ],
                        'subclass' => [
                            'label' => 'Subclass',
                        ],
                        'itemlevel' => [
                            'label' => 'Item level',
                        ],
                        'requiredlevel' => [
                            'label' => 'Required level',
                        ],
                        'stackable' => [
                            'label' => 'Max stack size',
                        ],
                        'maxcount' => [
                            'label' => 'Max personal count (maxcount)',
                        ],
                        'containerslots' => [
                            'label' => 'Container slots',
                        ],
                        'inventorytype' => [
                            'label' => 'Inventory type (inventorytype)',
                        ],
                        'allowableclass' => [
                            'label' => 'Allowed classes (allowableclass)',
                        ],
                        'allowablerace' => [
                            'label' => 'Allowed races (allowablerace)',
                        ],
                    ],
                ],
                'combat' => [
                    'label' => 'Damage / Speed',
                    'fields' => [
                        'dmg_min1' => [
                            'label' => 'Min damage 1',
                        ],
                        'dmg_max1' => [
                            'label' => 'Max damage 1',
                        ],
                        'delay' => [
                            'label' => 'Attack speed (ms)',
                        ],
                        'ammo_type' => [
                            'label' => 'Ammo type (ammo_type)',
                        ],
                        'range_mod' => [
                            'label' => 'Range modifier (range_mod)',
                        ],
                    ],
                ],
                'resist' => [
                    'label' => 'Armor / Resistances',
                    'fields' => [
                        'armor' => [
                            'label' => 'Armor (armor)',
                        ],
                        'holy_res' => [
                            'label' => 'Holy resistance',
                        ],
                        'fire_res' => [
                            'label' => 'Fire resistance',
                        ],
                        'nature_res' => [
                            'label' => 'Nature resistance',
                        ],
                        'frost_res' => [
                            'label' => 'Frost resistance',
                        ],
                        'shadow_res' => [
                            'label' => 'Shadow resistance',
                        ],
                        'arcane_res' => [
                            'label' => 'Arcane resistance',
                        ],
                    ],
                ],
                'req' => [
                    'label' => 'Requirements / Limits',
                    'fields' => [
                        'requiredskill' => [
                            'label' => 'Required skill ID',
                        ],
                        'requiredskillrank' => [
                            'label' => 'Required skill rank',
                        ],
                        'requiredspell' => [
                            'label' => 'Required spell (requiredspell)',
                        ],
                        'requiredreputationfaction' => [
                            'label' => 'Reputation faction ID',
                        ],
                        'requiredreputationrank' => [
                            'label' => 'Reputation rank',
                        ],
                        'bonding' => [
                            'label' => 'Bonding type (bonding)',
                        ],
                        'startquest' => [
                            'label' => 'Start quest ID',
                        ],
                    ],
                ],
                'socket' => [
                    'label' => 'Sockets / Gems',
                    'fields' => [
                        'socketbonus' => [
                            'label' => 'Socket bonus (socketbonus)',
                        ],
                        'gemproperties' => [
                            'label' => 'Gem properties ID (gemproperties)',
                        ],
                    ],
                ],
                'economy' => [
                    'label' => 'Economy / Other',
                    'fields' => [
                        'buyprice' => [
                            'label' => 'Buy price (buyprice)',
                        ],
                        'sellprice' => [
                            'label' => 'Sell price (sellprice)',
                        ],
                        'minMoneyLoot' => [
                            'label' => 'Min money loot',
                        ],
                        'maxMoneyLoot' => [
                            'label' => 'Max money loot',
                        ],
                        'duration' => [
                            'label' => 'Duration (duration)',
                        ],
                        'randomproperty' => [
                            'label' => 'Random property ID (randomproperty)',
                        ],
                        'randomsuffix' => [
                            'label' => 'Random suffix ID (randomsuffix)',
                        ],
                        'material' => [
                            'label' => 'Material (material)',
                        ],
                        'sheath' => [
                            'label' => 'Sheath (sheath)',
                        ],
                        'bagfamily' => [
                            'label' => 'Bag family (bagfamily)',
                        ],
                    ],
                ],
                'stats' => [
                    'label' => 'Base stats (Stats)',
                    'repeat' => [
                        'pattern' => [
                'api' => [
                    'errors' => [
                        'unauthorized' => 'Unauthorized',
                        'invalid_arguments' => 'Invalid arguments provided.',
                    ],
                ],
                            'stat_type' => [
                                'label' => 'Stat type {n}',
                            ],
                            'stat_value' => [
                                'label' => 'Stat value {n}',
                            ],
                        ],
                        'trailing' => [
                            'scalingstatdistribution' => [
                                'label' => 'Scaling distribution (scalingstatdistribution)',
                            ],
                            'scalingstatvalue' => [
                                'label' => 'Scaling value (scalingstatvalue)',
                            ],
                        ],
                    ],
                ],
                'spells' => [
                    'label' => 'Spell triggers (first 3)',
                    'repeat' => [
                        'pattern' => [
                            'spellid' => [
                                'label' => 'Spell ID {n}',
                            ],
                            'spelltrigger' => [
                                'label' => 'Trigger type {n}',
                            ],
                            'spellcharges' => [
                                'label' => 'Charges {n}',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'repository' => [
            'messages' => [
                'copy_created' => 'Item created from copy.',
                'created' => 'Item created.',
                'delete_success' => 'Deleted item #:id.',
                'delete_none' => 'No rows were deleted.',
                'no_changes' => 'No changes to apply.',
                'no_valid_fields' => 'No valid columns provided.',
                'no_values_changed' => 'Values remain unchanged.',
                'rows_inserted' => 'Rows inserted: :count',
                'rows_affected' => 'Rows affected: :count',
                'update_done' => 'Update completed.',
            ],
            'errors' => [
                'invalid_new_id' => 'Invalid new ID.',
                'id_exists' => 'ID already exists.',
                'copy_source_missing' => 'Source item not found.',
                'copy_failed' => 'Failed to create item from copy.',
                'create_failed' => 'Failed to create item.',
                'invalid_id' => 'Invalid ID.',
                'update_failed' => 'Update failed.',
                'sql_empty' => 'SQL cannot be empty.',
                'sql_multiple' => 'Multiple statements are not allowed.',
                'sql_parse_column' => 'Unable to parse column assignment: :column',
                'sql_invalid_column' => 'Column :column is not allowed.',
                'sql_update_where' => 'UPDATE must end with WHERE entry = <number> and contain no additional conditions.',
                'sql_only_update_insert' => 'Only UPDATE or INSERT item_template statements are allowed.',
                'exec_failed' => 'Execution failed: :error',
            ],
        ],
    ],
    'quest' => [
        'common' => [
            'na' => '—',
        ],
        'index' => [
            'page_title' => 'Quest Management',
            'filters' => [
                'id_placeholder' => 'Quest ID',
                'title_placeholder' => 'Title contains',
                'min_level_placeholder' => 'Min level',
                'level_placeholder' => 'Level',
                'type_all' => 'All types',
                'actions' => [
                    'search' => 'Search',
                    'reset' => 'Reset',
                    'create' => 'Create',
                    'log' => 'View logs',
                ],
            ],
            'table' => [
                'headers' => [
                    'id' => 'ID',
                    'title' => 'Title',
                    'min_level' => 'Min level',
                    'level' => 'Level',
                    'type' => 'Type',
                    'reward_xp' => 'Reward XP',
                    'reward_money' => 'Reward gold',
                    'reward_items' => 'Reward items',
                    'actions' => 'Actions',
                ],
                'empty' => 'No results',
                'quest_info_unknown' => 'Info :id',
                'quest_info_default' => 'Normal',
                'reward_xp_difficulty' => 'Difficulty :value',
                'reward_money_difficulty' => 'Difficulty :value',
                'reward_items_fixed' => 'Fixed rewards',
                'reward_items_choice' => 'Choice rewards',
                'reward_item_title' => 'ID :id',
                'actions' => [
                    'edit' => 'Edit',
                    'delete' => 'Delete',
                ],
            ],
            'modals' => [
                'new' => [
                    'title' => 'Create quest',
                    'id_label' => 'New ID*',
                    'copy_label' => 'Copy from (optional)',
                    'copy_hint' => 'Leave blank to start from an empty template.',
                    'cancel' => 'Cancel',
                    'confirm' => 'Create',
                ],
            ],
        ],
        'messages' => [
            'not_found_title' => 'Quest not found',
            'not_found' => 'Quest not found.',
        ],
        'api' => [
            'errors' => [
                'invalid_id' => 'Invalid quest ID.',
            ],
        ],
        'log_modal' => [
            'title' => 'Quest logs',
            'type_label' => 'Log type',
            'types' => [
                'sql' => 'SQL execution',
                'deleted' => 'Delete snapshots',
                'actions' => 'Action trace',
            ],
            'refresh' => 'Refresh',
            'empty' => '-- No logs yet --',
            'close' => 'Close',
        ],
        'aggregate' => [
            'warnings' => [
                'no_changes' => 'No differences to generate.',
            ],
            'errors' => [
                'invalid_id' => 'Invalid quest ID.',
                'template_id_mismatch' => 'Template ID does not match the requested quest.',
                'hash_mismatch' => 'Quest has been modified by another session.',
                'validation_failed' => 'Validation failed.',
                'field_required' => 'Field :field cannot be empty.',
                'field_numeric' => ':field must be numeric.',
                'index_missing' => 'Index is required.',
                'index_range' => 'Index must be between :min and :max.',
                'index_duplicate' => 'Index :value is duplicated.',
                'choice_limit' => 'A maximum of :limit selectable rewards is allowed.',
                'array_required' => 'An array is required.',
                'positive_integer' => 'A positive integer is required.',
                'duplicate_id' => 'Duplicate ID :id detected.',
                'no_changes_payload' => 'No changes provided.',
                'save_failed' => 'Save failed: :reason',
            ],
        ],
        'repository' => [
            'info_labels' => [
                '0' => 'General',
                '1' => 'Group',
                '21' => 'Life',
                '41' => 'PvP',
                '62' => 'Raid',
                '81' => 'Dungeon',
                '82' => 'World Event',
                '83' => 'Legendary',
                '84' => 'Escort',
                '85' => 'Heroic',
                '88' => 'Raid (10 player)',
                '89' => 'Raid (25 player)',
            ],
            'money' => [
                'separator' => ' ',
                'gold' => ':value gold',
                'silver' => ':value silver',
                'copper' => ':value copper',
            ],
            'defaults' => [
                'log_title' => 'New Quest :id',
            ],
            'messages' => [
                'copy_created' => 'Quest created from copy.',
                'created' => 'Quest created.',
                'delete_success' => 'Deleted quest #:id.',
                'delete_none' => 'No rows were deleted.',
                'no_changes' => 'No changes provided.',
                'no_valid_fields' => 'No valid fields provided.',
                'no_values_changed' => 'No values changed.',
                'update_done' => 'Update completed.',
            ],
            'errors' => [
                'invalid_new_id' => 'Invalid new ID.',
                'id_exists' => 'ID already exists.',
                'copy_source_missing' => 'Source quest not found.',
                'copy_failed' => 'Failed to create quest from copy.',
                'create_failed' => 'Failed to create quest.',
                'invalid_id' => 'Invalid quest ID.',
                'update_failed' => 'Update failed.',
                'sql_empty' => 'SQL cannot be empty.',
                'sql_multiple' => 'Multiple statements are not allowed.',
                'sql_parse_column' => 'Failed to parse column assignment: :column',
                'sql_invalid_column' => 'Column :column is not allowed.',
                'sql_update_where' => 'UPDATE queries must end with WHERE ID = <number> [LIMIT 1].',
                'sql_only_update_insert' => 'Only UPDATE or INSERT on quest_template is allowed.',
                'sql_exec_error' => 'Execution failed: :error',
                'log_unknown_type' => 'Unknown log type.',
                'log_open_failed' => 'Unable to open log file.',
            ],
            'sql' => [
                'insert_label' => 'Insert',
                'update_label' => 'Update',
                'affected' => ':operation rows affected: :count',
            ],
        ],
        'edit' => [
            'page_title' => 'Edit quest #:id',
            'toolbar' => [
                'back' => 'Back to list',
                'log' => 'View logs',
                'execute_sql' => 'Run SQL',
                'copy_sql' => 'Copy SQL',
            ],
            'diff' => [
                'title' => 'Diff SQL preview',
                'hint' => 'Auto-generated UPDATE with changed columns only, protected by LIMIT 1.',
                'empty' => '-- No changes --',
            ],
            'tabs' => [
                'general' => 'General',
                'objectives' => 'Objectives',
                'requirements' => 'Requirements',
                'rewards' => 'Rewards',
                'internal' => 'Internal',
            ],
            'nav' => [
                'title' => 'Group navigation',
            ],
            'mini_diff' => [
                'title' => 'Change details',
                'empty' => 'No changes yet',
                'table' => [
                    'field' => 'Field',
                    'value' => 'Old → New',
                ],
                'collapse' => 'Collapse',
                'reset' => 'Reset',
                'reset_title' => 'Reset to current database values',
            ],
            'fields' => [
                'bitmask' => [
                    'undo_tooltip' => 'Restore original value',
                ],
            ],
        ],
        'config' => [
            'fields' => [
                'enums' => [
                    'quest_type' => [
                        0 => 'Normal',
                        1 => 'Group',
                        2 => 'PvP',
                        3 => 'Elite',
                        4 => 'Dungeon',
                        5 => 'Raid',
                        6 => 'Legendary',
                        7 => 'Event',
                    ],
                ],
                'bitmasks' => [
                    'races' => [
                        0 => 'Human',
                        1 => 'Orc',
                        2 => 'Undead',
                        3 => 'Night Elf',
                        4 => 'Tauren',
                        5 => 'Gnome',
                        6 => 'Troll',
                        7 => 'Blood Elf',
                        8 => 'Draenei',
                    ],
                    'classes' => [
                        0 => 'Warrior',
                        1 => 'Paladin',
                        2 => 'Hunter',
                        3 => 'Rogue',
                        4 => 'Priest',
                        5 => 'Death Knight',
                        6 => 'Shaman',
                        7 => 'Mage',
                        8 => 'Warlock',
                        9 => 'Druid',
                    ],
                    'quest_flags' => [
                        0 => 'Daily quest',
                        1 => 'Sharable',
                        2 => 'Timed',
                        3 => 'Repeatable',
                        4 => 'Escort',
                        5 => 'Raid (legacy)',
                        6 => 'Monthly',
                        7 => 'Seasonal (holiday)',
                        8 => 'Guild / event reserved',
                        9 => 'Heroic dungeon only',
                        10 => 'Dungeon finder',
                        11 => 'Raid finder',
                        12 => 'Account shared',
                        13 => 'Weekly',
                        14 => 'Scenario campaign',
                        15 => 'Account weekly',
                        16 => 'World quest (example)',
                        17 => 'Hidden tracking',
                        18 => 'Auto accept',
                        19 => 'Auto complete',
                        20 => 'Cannot abandon',
                        21 => 'Show progress area',
                        22 => 'Hide in quest log',
                        23 => 'Event-only',
                        24 => 'Class specific',
                        25 => 'Race specific',
                        26 => 'Account once only',
                        27 => 'Keep quest items',
                        28 => 'Acceptable in raid',
                        29 => 'Scenario challenge',
                        30 => 'Epic marker',
                        31 => 'Internal reserved',
                    ],
                    'quest_special_flags' => [
                        0 => 'Handled by script',
                        1 => 'Internal DB use',
                        2 => 'Repeatable (internal)',
                        3 => 'Requires GM',
                        4 => 'Cannot abandon in instance',
                        5 => 'Ignore faction check',
                        6 => 'Start item validation',
                        7 => 'Delayed reward',
                        8 => 'Share progress in party',
                        9 => 'Tracking only (no rewards)',
                        10 => 'Hidden from regular players',
                        11 => 'Account unique',
                        12 => 'Character unique',
                        13 => 'Extra script trigger',
                        14 => 'Server event trigger',
                        15 => 'Debug / test',
                    ],
                ],
                'groups' => [
                    'basic' => [
                        'label' => 'Basics',
                    ],
                    'objectives' => [
                        'label' => 'Objective texts',
                    ],
                    'requirements' => [
                        'label' => 'Requirements / conditions',
                    ],
                    'flags' => [
                        'label' => 'Flags',
                    ],
                    'rewards' => [
                        'label' => 'Rewards',
                    ],
                    'internal' => [
                        'label' => 'Internal / scripts',
                    ],
                ],
                'form' => [
                    'LogTitle' => [
                        'label' => 'Log title',
                    ],
                    'QuestDescription' => [
                        'label' => 'Quest description',
                    ],
                    'QuestLevel' => [
                        'label' => 'Quest level',
                    ],
                    'MinLevel' => [
                        'label' => 'Minimum level',
                    ],
                    'QuestType' => [
                        'label' => 'Quest type',
                    ],
                    'ObjectiveText1' => [
                        'label' => 'Objective text 1',
                    ],
                    'ObjectiveText2' => [
                        'label' => 'Objective text 2',
                    ],
                    'ObjectiveText3' => [
                        'label' => 'Objective text 3',
                    ],
                    'ObjectiveText4' => [
                        'label' => 'Objective text 4',
                    ],
                    'AllowableRaces' => [
                        'label' => 'Allowed races (bitmask)',
                    ],
                    'RequiredClasses' => [
                        'label' => 'Required classes (bitmask)',
                    ],
                    'TimeAllowed' => [
                        'label' => 'Time allowed (seconds)',
                        'help' => '0 means no time limit.',
                    ],
                    'Flags' => [
                        'label' => 'Primary flags',
                    ],
                    'SpecialFlags' => [
                        'label' => 'Special flags',
                        'help' => 'Additional behavior bits outside of the primary flags.',
                    ],
                    'RewardMoney' => [
                        'label' => 'Reward money',
                    ],
                    'RewardSpell' => [
                        'label' => 'Reward spell',
                    ],
                    'RewardItem1' => [
                        'label' => 'Reward item 1',
                    ],
                    'RewardAmount1' => [
                        'label' => 'Amount 1',
                    ],
                    'RewardItem2' => [
                        'label' => 'Reward item 2',
                    ],
                    'RewardAmount2' => [
                        'label' => 'Amount 2',
                    ],
                    'RewardItem3' => [
                        'label' => 'Reward item 3',
                    ],
                    'RewardAmount3' => [
                        'label' => 'Amount 3',
                    ],
                    'RewardItem4' => [
                        'label' => 'Reward item 4',
                    ],
                    'RewardAmount4' => [
                        'label' => 'Amount 4',
                    ],
                    'SrcSpell' => [
                        'label' => 'Source spell',
                    ],
                    'StartItem' => [
                        'label' => 'Start item',
                    ],
                    'RewardMailTemplateId' => [
                        'label' => 'Reward mail template',
                    ],
                ],
            ],
            'metadata' => [
                'template_groups' => [
                    'identity' => [
                        'label' => 'Identity & texts',
                    ],
                    'progression' => [
                        'label' => 'Progression & levels',
                    ],
                    'requirements' => [
                        'label' => 'Factions & requirements',
                    ],
                    'objectives' => [
                        'label' => 'Objectives',
                    ],
                    'rewards' => [
                        'label' => 'Reward configuration',
                    ],
                    'flags' => [
                        'label' => 'Flags & guidance',
                    ],
                ],
                'narrative_tables' => [
                    'details' => [
                        'label' => 'Detail texts',
                    ],
                    'request' => [
                        'label' => 'Request item texts',
                    ],
                    'offer' => [
                        'label' => 'Reward texts',
                    ],
                ],
                'reward_tables' => [
                    'choice_items' => [
                        'label' => 'Choice rewards',
                    ],
                    'items' => [
                        'label' => 'Fixed rewards',
                    ],
                    'currencies' => [
                        'label' => 'Currency rewards',
                    ],
                    'factions' => [
                        'label' => 'Reputation rewards',
                    ],
                ],
            ],
        ],
    ],
    'realm' => [
        'errors' => [
            'not_logged_in' => 'You must sign in before switching realms.',
            'not_found' => 'Realm not found.',
        ],
    ],
    'setup' => [
        'layout' => [
            'page_title' => 'Setup Wizard - Acore GM Panel',
            'intro' => 'Follow the wizard to verify requirements, configure databases, and create the administrator account.',
            'step_titles' => [
                1 => 'Environment check',
                2 => 'Mode & databases',
                3 => 'Connection test',
                4 => 'Administrator',
                5 => 'Finish',
            ],
            'stepper_label' => 'Setup steps',
        ],
        'flash' => [
            'already_installed' => 'System already installed. Delete config/generated/install.lock to reinstall.',
            'install_success_debug' => 'Installation complete. To enable debug mode, edit config/generated/app.php and set debug to true.',
        ],
        'env' => [
            'title' => 'Step 1 · Environment check',
            'hint' => 'Ensure the server meets all requirements before continuing.',
            'pill' => 'Environment',
            'checks' => [
                'php_version' => 'PHP version',
                'pdo_mysql' => 'PDO MySQL extension',
                'soap' => 'SOAP extension',
                'mbstring' => 'mbstring extension',
                'config_writable' => 'Config directory writable',
            ],
            'requirements' => [
                'writable' => 'Writable',
            ],
            'messages' => [
                'write_failed' => 'Unable to write (check directory permissions)',
                'create_failed' => 'Failed to create (check parent directory permissions)',
                'created' => '(created)',
            ],
            'check_passed' => 'All checks passed! Choose your language to continue.',
            'check_failed' => 'Some checks failed. Please fix them and run the scan again.',
            'retry' => 'Run checks again',
            'language_title' => 'Language selection',
            'language_intro' => 'Pick a language for the rest of the setup wizard.',
            'language_hint' => 'Interface language · :code',
            'language_submit' => 'Next: configuration mode',
            'language_submit_fail' => 'Failed to save the language selection. Please try again.',
            'invalid_locale' => 'Invalid language choice.',
        ],
        'mode' => [
            'step_title' => 'Step :current of :total · Mode & databases',
            'section' => [
                'mode' => [
                    'title' => 'Choose deployment mode',
                    'hint' => 'Pick the layout that matches how many realms you plan to host.',
                    'pill' => 'Mode',
                    'aria_group' => 'Deployment modes',
                ],
                'auth' => [
                    'title' => 'Main auth database',
                    'hint' => 'This connection is always required and used as the default fallback for other services.',
                    'pill' => 'Auth',
                ],
                'shared_db' => [
                    'title' => 'Shared realm defaults',
                    'hint' => 'Configure reusable ports and credentials that realms can inherit.',
                    'pill' => 'Shared DBs',
                    'toggle_aria' => 'Shared database mode',
                    'toggle_shared' => 'Use shared defaults',
                    'toggle_custom' => 'Configure per realm only',
                    'characters' => [
                        'title' => 'Characters defaults',
                    ],
                    'world' => [
                        'title' => 'World defaults',
                    ],
                    'summary_shared' => 'Realms inherit these connection defaults unless overridden.',
                    'summary_custom' => 'Per-realm forms must provide full database credentials.',
                ],
                'single_realm' => [
                    'title' => 'Single realm databases',
                    'hint' => 'Configure the characters and world databases for the primary realm.',
                    'pill' => 'Single realm',
                    'advanced_auth' => 'Advanced credentials',
                    'characters' => [
                        'title' => 'Characters database',
                    ],
                    'world' => [
                        'title' => 'World database',
                    ],
                ],
                'realms' => [
                    'title' => 'Realms list',
                    'hint' => 'Detected realms will appear here; you can also add them manually.',
                    'pill' => 'Realms',
                ],
                'shared_realm' => [
                    'note_shared' => 'Realms will start with the shared database or SOAP defaults. Override individual fields as needed.',
                    'note_custom' => 'Shared defaults disabled. Each realm must include complete database and SOAP credentials.',
                ],
                'soap' => [
                    'title' => 'Global SOAP service',
                    'hint' => 'Configure the default SOAP endpoint used by realm commands.',
                    'pill' => 'SOAP',
                ],
                'shared_soap' => [
                    'toggle_aria' => 'Shared SOAP mode',
                    'toggle_shared' => 'Use shared SOAP defaults',
                    'toggle_custom' => 'Set SOAP per realm',
                    'summary_shared' => 'Realms inherit this SOAP connection by default.',
                    'summary_custom' => 'Realms must define their own SOAP connection.',
                ],
            ],
            'cards' => [
                'single' => [
                    'title' => 'Single realm',
                    'badge' => 'Quick start',
                    'desc' => 'One realm sharing the main auth database.',
                    'aria' => 'Select single realm deployment',
                    'tags' => [
                        'shared_account' => 'Shared auth DB',
                        'single_realm' => '1 realm',
                        'low_maintenance' => 'Low maintenance',
                    ],
                    'errors' => [
                        'username_required' => 'Username cannot be empty.',
                        'password_required' => 'Password cannot be empty.',
                        'password_mismatch' => 'Passwords do not match.',
                    ],
                ],
                'multi' => [
                    'title' => 'Multi realm (shared auth)',
                    'badge' => 'Popular',
                    'desc' => 'Share auth, configure characters and world databases per realm.',
                    'aria' => 'Select multi realm shared-auth deployment',
                    'tags' => [
                    'errors' => [
                        'create_config_dir' => 'Unable to create config directory: :path',
                        'write_failed' => 'Failed to write file: :file',
                    ],
                        'shared_auth' => 'Shared auth DB',
                'api' => [
                    'realms' => [
                        'missing_auth_db' => 'Auth database name is required.',
                        'connection_failed' => 'Connection or query failed: :error',
                    ],
                ],
                        'split_characters' => 'Per-realm characters DB',
                        'port_reuse' => 'Reuse main ports',
                    ],
                ],
                'multi_full' => [
                    'title' => 'Full isolation',
                    'badge' => 'Advanced',
                    'desc' => 'Dedicated auth, characters, and world databases per realm.',
                    'aria' => 'Select fully isolated multi realm deployment',
                    'tags' => [
                        'full_isolation' => 'Isolated databases',
                        'security' => 'Higher security',
                        'high_complexity' => 'Most complex',
                    ],
                ],
            ],
            'matrix' => [
                'aria' => 'Mode comparison',
                'head' => [
                    'type' => 'Deployment',
                    'auth_db' => 'Auth DB',
                    'auth_port' => 'Auth port',
                    'auth_credentials' => 'Auth credentials',
                    'characters_db' => 'Characters DB',
                    'characters_port' => 'Characters port',
                    'characters_credentials' => 'Characters credentials',
                    'world_db' => 'World DB',
                    'world_port' => 'World port',
                    'world_credentials' => 'World credentials',
                    'soap_credentials' => 'SOAP credentials',
                    'soap_port' => 'SOAP port',
                ],
                'rows' => [
                    'single' => [
                        'type' => 'Single realm',
                        'auth_db' => 'Shared main auth',
                        'auth_port' => 'Uses main port',
                        'auth_credentials' => 'Uses main credentials',
                        'characters_db' => 'Single database',
                        'characters_port' => 'Custom port',
                        'characters_credentials' => 'Optional override',
                        'world_db' => 'Single database',
                        'world_port' => 'Custom port',
                        'world_credentials' => 'Optional override',
                        'soap_credentials' => 'Manual entry',
                        'soap_port' => 'Manual entry',
                    ],
                    'multi' => [
                        'type' => 'Multiple realms (shared auth)',
                        'auth_db' => 'Shared main auth',
                        'auth_port' => 'Uses main port',
                        'auth_credentials' => 'Uses main credentials',
                        'characters_db' => 'Per realm',
                        'characters_port' => 'Per realm',
                        'characters_credentials' => 'Optional per realm',
                        'world_db' => 'Per realm',
                        'world_port' => 'Per realm',
                        'world_credentials' => 'Optional per realm',
                        'soap_credentials' => 'Per realm',
                        'soap_port' => 'Per realm',
                    ],
                    'multi_full' => [
                        'type' => 'Multiple realms (isolated auth)',
                        'auth_db' => 'Per realm auth database',
                        'auth_port' => 'Per realm port',
                        'auth_credentials' => 'Per realm credentials',
                        'characters_db' => 'Per realm',
                        'characters_port' => 'Per realm',
                        'characters_credentials' => 'Per realm',
                        'world_db' => 'Per realm',
                        'world_port' => 'Per realm',
                        'world_credentials' => 'Per realm',
                        'soap_credentials' => 'Per realm',
                        'soap_port' => 'Per realm',
                    ],
                ],
                'hint' => 'Use this matrix to compare maintenance needs before choosing a mode.',
            ],
            'fields' => [
                'host' => 'Host',
                'port' => 'Port',
                'database' => 'Database',
                'user' => 'Username',
                'password' => 'Password',
                'uri' => 'URI',
            ],
            'placeholders' => [
                'inherit_auth' => 'Inherit from auth connection',
            ],
            'actions' => [
                'refresh' => 'Discover realms',
                'manual' => 'Add realm manually',
                'tip' => 'Tip: Run worldserver before refreshing to auto-detect realms.',
                'refresh_fail' => 'Failed to load realms.',
                'request_fail' => 'Request failed. Please try again.',
                'save_fail' => 'Save failed. Check the form and try again.',
                'unknown_error' => 'Unknown error.',
                'manual_disabled' => 'Realms discovered automatically; remove entries to re-enable manual add.',
            ],
            'realm' => [
                'title_prefix' => 'Realm :index',
                'remove' => 'Remove',
                'name_label' => 'Realm name',
                'name_placeholder' => 'e.g. Azeroth',
                'inherit' => 'Inherit main credentials',
                'auth' => 'Auth overrides',
                'auth_placeholders' => [
                    'inherit_main' => 'Leave blank to inherit main auth settings',
                ],
                'characters' => [
                    'title' => 'Characters database',
                ],
                'world' => [
                    'title' => 'World database',
                ],
                'soap' => [
                    'title' => 'SOAP credentials',
                    'host' => 'Host',
                    'port' => 'Port',
                    'user' => 'Username',
                    'password' => 'Password',
                    'uri' => 'URI',
                ],
                'soap_placeholder' => 'Leave blank to inherit global SOAP settings',
                'empty' => 'No realms configured yet.',
                'summary' => ':count realm(s) configured.',
                'summary_ids' => 'IDs: :ids',
                'meta' => [
                    'id' => 'ID :value',
                    'port' => 'Port :value',
                ],
                'refresh_fail' => 'Failed to load realms.',
                'request_fail' => 'Request failed. Please try again.',
                'save_fail' => 'Save failed. Check the form and try again.',
                'unknown_error' => 'Unknown error.',
            ],
            'footer' => [
                'hint' => 'These settings can be adjusted later from the control panel.',
                'submit' => 'Save and continue',
                'back' => 'Back to environment check',
            ],
        ],
        'test' => [
            'title' => 'Step :current / :total · Connection test',
            'success' => 'All connections succeeded.',
            'next_admin' => 'Next: Administrator',
            'failure' => 'Some checks failed, please go back and adjust.',
            'back' => 'Back to edit',
        ],
        'status' => [
            'ok' => 'OK',
            'fail' => 'FAIL',
        ],
        'admin' => [
            'step_title' => 'Step :current of :total · Administrator',
            'fields' => [
                'username' => 'Username',
                'password' => 'Password',
                'password_confirm' => 'Confirm password',
            ],
            'validation' => [
                'username_required' => 'Username is required',
                'password_required' => 'Password is required',
                'password_mismatch' => 'Passwords do not match',
            ],
            'submit' => 'Save and generate config',
            'back' => 'Back to connection test',
            'save_failed' => 'Failed to save administrator settings. Please try again.',
        ],
        'finish' => [
            'step_title' => 'Step :current of :total · Finish',
            'success' => 'Configuration file generated successfully. Remove /setup (or keep install.lock) to prevent reinstalling.',
            'enter_panel' => 'Enter panel',
            'failure' => 'Generation failed: :errors',
            'back' => 'Back to administrator',
            'errors' => [
                'config_dir_create_failed' => 'Unable to create config directory ":path"',
                'write_failed' => 'Write failed: :file',
            ],
        ],
        'api' => [
            'realms' => [
                'missing_auth_database' => 'Auth database name is required',
                'connection_failed' => 'Connection or query failed: :message',
            ],
        ],
    ],
    'bag_query' => [
        'page_title' => 'Bag / Item Query',
        'form' => [
            'type_label' => 'Search type',
            'type_character_name' => 'Character name (fuzzy)',
            'type_username' => 'Account username',
            'value_label' => 'Search value',
            'value_placeholder' => 'Enter character or account',
            'submit' => 'Search',
        ],
        'chars' => [
            'title' => 'Character list',
            'subtitle' => 'Select a character to view bag details',
            'table' => [
                'guid' => 'GUID',
                'name' => 'Name',
                'level' => 'Level',
                'race' => 'Race',
                'account' => 'Account',
                'actions' => 'Actions',
                'empty' => 'Waiting for search…',
            ],
        ],
        'items' => [
            'title' => 'Item list',
            'subtitle_empty' => 'No character selected',
            'filter_placeholder' => 'Filter item name',
            'table' => [
                'instance_guid' => 'Instance GUID',
                'item_id' => 'Item ID',
                'name' => 'Name',
                'count' => 'Count',
                'slot' => 'Bag / slot',
                'actions' => 'Actions',
                'empty' => 'No character selected',
            ],
        ],
        'modal' => [
            'title' => 'Delete / Reduce item',
            'quantity_label' => 'Quantity',
            'quantity_hint' => 'If the quantity is greater than or equal to the current stack, the instance will be deleted.',
            'cancel' => 'Cancel',
            'confirm' => 'Confirm',
        ],
    ],
    'js' => [
        'common' => [
            'loading' => 'Loading…',
            'no_data' => 'No data',
            'search_placeholder' => 'Search…',
            'errors' => [
                'network' => 'Network error',
                'timeout' => 'Request timed out',
                'invalid_json' => 'Invalid JSON',
                'unknown' => 'Unknown error',
            ],
            'actions' => [
                'close' => 'Close',
                'confirm' => 'Confirm',
                'cancel' => 'Cancel',
                'retry' => 'Retry',
            ],
            'yes' => 'Yes',
            'no' => 'No',
        ],
        'modules' => [
            'logs' => [
                'summary' => [
                    'module' => 'Module: ',
                    'type' => 'Type: ',
                    'source' => 'Source: ',
                    'display' => 'Showing: ',
                    'separator' => ' | ',
                ],
                'status' => [
                    'no_entries' => 'No log entries',
                    'panel_not_ready' => 'Panel API is not ready, please verify panel.js is loaded correctly.',
                    'panel_waiting' => 'Panel API is initializing, please wait…',
                    'load_failed' => 'Load failed',
                    'no_raw' => '-- No log --',
                    'request_error' => 'Request error',
                    'exception_prefix' => '[EXCEPTION] ',
                    'error_prefix' => '[ERROR] ',
                    'info_prefix' => '[INFO] ',
                ],
                'actions' => [
                    'auto_on' => 'Enable auto refresh',
                    'auto_off' => 'Disable auto refresh',
                ],
            ],
            'soap' => [
                'meta' => [
                    'updated_at' => 'Catalog updated: :date',
                    'source_link' => 'GM Commands',
                    'source_label' => 'Source: :link',
                    'separator' => ' · ',
                ],
                'categories' => [
                    'all' => [
                        'label' => 'All commands',
                        'summary' => 'Show all curated commands',
                    ],
                ],
                'list' => [
                    'empty' => 'No matching commands found',
                ],
                'risk' => [
                    'badge' => [
                        'low' => 'Low risk',
                        'medium' => 'Medium risk',
                        'high' => 'High risk',
                        'unknown' => 'Unknown risk',
                    ],
                    'short' => [
                        'low' => 'L',
                        'medium' => 'M',
                        'high' => 'H',
                        'unknown' => '?',
                    ],
                ],
                'fields' => [
                    'empty' => 'This command has no extra parameters.',
                ],
                'errors' => [
                    'missing_required' => 'Missing required fields.',
                    'unknown_response' => 'Unknown response',
                ],
                'form' => [
                    'error_joiner' => ', ',
                ],
                'feedback' => [
                    'execute_success' => 'Executed successfully',
                    'execute_failed' => 'Execution failed',
                ],
                'output' => [
                    'unknown_time' => 'Unknown duration',
                    'meta' => 'Status: :code · Time: :time',
                    'empty' => '(no output)',
                ],
                'copy' => [
                    'empty' => 'Nothing to copy',
                    'success' => 'Copied to clipboard',
                    'failure' => 'Copy failed',
                ],
            ],
            'smartai' => [
                'segments' => [
                    'move_up_title' => 'Move up',
                    'move_down_title' => 'Move down',
                    'delete_segment_title' => 'Delete segment',
                    'default_label' => 'Segment :number',
                    'empty_prompt' => 'Please add a segment.',
                ],
                'search' => [
                    'placeholder' => 'Search keywords or ID',
                ],
                'list' => [
                    'empty' => 'No matches found',
                ],
                'selector' => [
                    'select_type' => 'Select a type.',
                    'no_params' => 'This type has no extra parameters.',
                ],
                'validation' => [
                    'entry_required' => 'Please enter a valid entry.',
                    'entry_invalid' => 'A valid entry is required.',
                    'segment_required' => 'Please add at least one segment.',
                    'event_required_next' => 'Select an event type before continuing.',
                    'event_required' => 'Please select an event type.',
                    'event_required_all' => 'Please select an event type for every segment.',
                    'action_required_next' => 'Select an action type before continuing.',
                    'action_required' => 'Please select an action type.',
                    'action_required_all' => 'Please select an action type for every segment.',
                    'target_required_next' => 'Select a target type before continuing.',
                    'target_required' => 'Please select a target type.',
                    'target_required_all' => 'Please select a target type for every segment.',
                ],
                'api' => [
                    'no_response' => 'No response from server',
                ],
                'preview' => [
                    'placeholder' => '-- No SQL generated --',
                    'error_placeholder' => '-- Generation failed, check form errors --',
                ],
                'summary' => [
                    'segments' => 'Segments: :count',
                    'event' => 'Event: :name',
                    'action' => 'Action: :name',
                    'target' => 'Target: :name',
                ],
                'feedback' => [
                    'generate_success' => 'SQL generated successfully',
                    'generate_failed' => 'Generation failed',
                    'copy_success' => 'Copied to clipboard',
                    'copy_failed' => 'Copy failed, please copy manually',
                ],
                'errors' => [
                    'request_failed' => 'Request failed',
                ],
            ],
            'bag_query' => [
                'quality' => [
                    '0' => 'Poor',
                    '1' => 'Common',
                    '2' => 'Uncommon',
                    '3' => 'Rare',
                    '4' => 'Epic',
                    '5' => 'Legendary',
                    '6' => 'Artifact',
                    '7' => 'Heirloom',
                ],
                'classes' => [
                    'warrior' => 'Warrior',
                    'paladin' => 'Paladin',
                    'hunter' => 'Hunter',
                    'rogue' => 'Rogue',
                    'priest' => 'Priest',
                    'death-knight' => 'Death Knight',
                    'shaman' => 'Shaman',
                    'mage' => 'Mage',
                    'warlock' => 'Warlock',
                    'monk' => 'Monk',
                    'druid' => 'Druid',
                    'demon-hunter' => 'Demon Hunter',
                ],
                'errors' => [
                    'parse_failed' => 'Failed to parse response',
                    'network' => 'Network error',
                ],
                'status' => [
                    'loading' => 'Loading…',
                ],
                'search' => [
                    'validation' => [
                        'empty' => 'Please enter a search value',
                    ],
                    'error' => [
                        'failed' => 'Query failed',
                    ],
                    'empty' => 'No results',
                ],
                'items' => [
                    'subtitle' => [
                        'none' => 'No character selected',
                        'current_name' => 'Current character: :name',
                        'current_guid' => 'Current character GUID :guid',
                        'with_status' => ':base (:status)',
                    ],
                    'placeholder' => [
                        'none' => 'No character selected',
                    ],
                    'filter' => [
                        'placeholder' => 'Filter items by name',
                    ],
                    'empty' => 'No items found',
                    'quality' => [
                        'unknown' => 'Unknown',
                    ],
                    'error' => [
                        'load_failed' => 'Failed to load items',
                    ],
                ],
                'actions' => [
                    'view' => 'View',
                    'delete' => 'Delete',
                    'processing' => 'Processing…',
                ],
                'delete' => [
                    'info' => 'Item <strong>#:entry :name</strong> current count <strong>:count</strong><br>Instance GUID: :inst',
                    'validation' => [
                        'quantity' => 'Quantity must be greater than 0 and no more than stack count',
                    ],
                    'success' => 'Item deleted',
                    'error' => 'Operation failed',
                ],
            ],
            'item_owner' => [
                'search' => [
                    'validation' => [
                        'empty' => 'Please enter a keyword',
                    ],
                    'error' => [
                        'failed' => 'Search failed',
                    ],
                    'status' => [
                        'loading' => 'Loading…',
                    ],
                    'results' => [
                        'empty' => 'No items found',
                        'view' => 'View ownership',
                    ],
                ],
                'results' => [
                    'title_empty' => 'Select an item',
                    'subtitle_empty' => 'Search for an item to view ownership details.',
                    'title_loading' => 'Loading ownership…',
                    'title_error' => 'Failed to load ownership',
                    'subtitle_totals' => ':characters characters · :instances stacks · total :count items',
                    'status' => [
                        'loading' => 'Loading…',
                    ],
                    'error' => [
                        'load_failed' => 'Failed to load ownership',
                    ],
                    'characters' => [
                        'placeholder' => 'No characters found',
                    ],
                    'instances' => [
                        'placeholder' => 'No item instances found',
                    ],
                ],
                'actions' => [
                    'confirm_delete' => 'Delete selected item instances?',
                    'delete_success' => 'Selected item instances deleted',
                    'delete_failed' => 'Failed to delete item instances',
                    'replace_success' => 'Selected item instances replaced',
                    'replace_failed' => 'Failed to replace item instances',
                ],
                'modal' => [
                    'replace' => [
                        'validation' => [
                            'entry' => 'Enter a valid item entry',
                        ],
                    ],
                ],
                'quality' => [
                    'unknown' => 'Unknown',
                    '0' => 'Poor',
                    '1' => 'Common',
                    '2' => 'Uncommon',
                    '3' => 'Rare',
                    '4' => 'Epic',
                    '5' => 'Legendary',
                    '6' => 'Artifact',
                    '7' => 'Heirloom',
                ],
            ],
            'mail' => [
                'actions' => [
                    'view' => 'View',
                    'delete' => 'Delete',
                    'mark_read' => 'Mark read',
                ],
                'confirm' => [
                    'delete_one' => 'Delete this mail?',
                    'delete_selected' => 'Delete selected mails?',
                ],
                'detail' => [
                    'attachments_none' => 'No',
                    'attachments_yes' => 'Yes',
                    'expire' => [
                        'expired' => 'Expired',
                        'today' => 'Expires today',
                    ],
                    'no_body' => '(no content)',
                    'no_subject' => '(no subject)',
                    'status' => [
                        'read' => 'Read',
                        'unread' => 'Unread',
                    ],
                ],
                'logs' => [
                    'empty' => 'No logs',
                    'failed' => 'Failed to load logs',
                    'loading' => 'Loading…',
                    'meta' => ':file | Lines: :count',
                    'meta_with_server' => ':file | Lines: :count | Server: :server',
                ],
                'stats' => [
                    'summary' => 'Unread estimate: :unread | Expiring in 7 days: :expiring',
                ],
                'table' => [
                    'loading' => 'Loading…',
                    'empty' => 'No mails',
                ],
            ],
            'mass_mail' => [
                'errors' => [
                    'network' => 'Network error',
                    'parse_failed' => 'Failed to parse response',
                    'request_failed_retry' => 'Request failed, please try again later',
                ],
                'feedback' => [
                    'done' => 'Done',
                ],
                'status' => [
                    'sending' => 'Sending…',
                ],
                'announce' => [
                    'validation' => [
                        'empty' => 'Please enter an announcement message',
                    ],
                ],
                'send' => [
                    'gold_preview_placeholder' => '—',
                ],
                'confirm' => [
                    'heading' => 'You are about to execute <strong>:action</strong>',
                    'subject' => 'Subject: :value',
                    'items' => 'Items: :items',
                    'gold' => 'Gold (copper): :amount',
                    'target_type' => 'Target type: :value',
                    'custom_count' => 'Custom characters: :count',
                    'online' => 'Online characters: real-time count (fetched on send)',
                    'footer' => 'Batch sending (size = 200) is enabled. Please double-check before continuing.',
                ],
                'gold' => [
                    'units' => [
                        'gold' => 'Gold',
                        'silver' => 'Silver',
                        'copper' => 'Copper',
                    ],
                ],
                'logs' => [
                    'empty' => 'No logs yet',
                    'error_prefix' => 'Error: ',
                    'items_label' => 'Items: :value',
                    'item_label' => 'Item: #:id',
                    'gold_label' => 'Gold: :value',
                    'item_name_separator' => ' - ',
                    'item_quantity_prefix' => ' ×',
                ],
                'boost' => [
                    'summary' => [
                        'gold' => '500 gold (:copper copper)',
                        'bag' => 'Netherweave Bag ×:count (#21841)',
                        'mount' => 'Sea Turtle ×:count (#23720)',
                        'set' => 'Class-specific Tier 2 set (auto-detected)',
                    ],
                    'validation' => [
                        'name' => 'Please enter a character name',
                        'level' => 'Please choose a target level',
                    ],
                    'status' => [
                        'executing' => 'Executing…',
                    ],
                ],
            ],
            'creature' => [
                'create' => [
                    'enter_new_id' => 'Please enter a new ID',
                    'success_redirect' => 'Creature created, redirecting...',
                    'failure' => 'Failed to create creature',
                    'failure_with_reason' => 'Creation failed: :reason',
                ],
                'logs' => [
                    'loading_placeholder' => '-- Loading... --',
                    'empty_placeholder' => '-- No logs --',
                    'load_failed_placeholder' => '-- Load failed --',
                    'load_failed' => 'Failed to load logs',
                    'load_failed_with_reason' => 'Failed to load logs: :reason',
                ],
                'list' => [
                    'confirm_delete' => 'Delete creature :id?',
                    'delete_success' => 'Creature deleted',
                    'delete_failed' => 'Failed to delete creature',
                    'delete_failed_with_reason' => 'Failed to delete creature: :reason',
                ],
                'diff' => [
                    'group_change_count' => '(:count changes)',
                    'no_changes_placeholder' => '-- No changes --',
                    'copy_sql_success' => 'SQL copied',
                ],
                'common' => [
                    'copy_failed' => 'Copy failed',
                ],
                'errors' => [
                    'panel_api_not_ready' => 'Panel API is not ready',
                ],
                'exec' => [
                    'actions' => [
                        'clear' => 'Clear',
                        'hide' => 'Hide',
                        'copy_json' => 'Copy JSON',
                        'copy_sql' => 'Copy SQL',
                    ],
                    'result_heading' => 'Execution result',
                    'rows_affected' => 'Rows: :count',
                    'sample_row_heading' => 'Sample row',
                    'sql_prefix' => 'SQL: :sql',
                    'copy_json_success' => 'JSON copied',
                    'copy_sql_success' => 'SQL copied',
                    'default_success' => 'Execution succeeded',
                    'default_error' => 'Execution failed',
                    'failure_with_reason' => ':prefix: :reason',
                    'sql_empty_notify' => 'SQL is empty, cannot execute',
                    'sql_empty_response' => 'SQL is empty',
                    'confirm_run_diff' => "Execute the following SQL?\n:sql",
                    'no_diff_sql' => 'No diff SQL to execute',
                    'diff_sql_success' => 'Diff SQL executed successfully',
                    'prompt_sql' => 'Enter a single UPDATE/INSERT SQL statement',
                    'only_update_insert' => 'Only UPDATE or INSERT statements are allowed',
                    'status' => [
                        'save_success' => 'Save succeeded',
                        'run_success' => 'Execution succeeded',
                        'save_failed' => 'Save failed',
                        'run_failed' => 'Execution failed',
                    ],
                ],
                'models' => [
                    'confirm_delete' => 'Delete this model?',
                    'save_success' => 'Model saved successfully',
                    'save_failed' => 'Failed to save model',
                    'save_failed_with_reason' => 'Failed to save model: :reason',
                    'delete_success' => 'Model deleted',
                    'delete_failed' => 'Failed to delete model',
                    'delete_failed_with_reason' => 'Failed to delete model: :reason',
                ],
                'save' => [
                    'no_changes' => 'No changes to save',
                    'success' => 'Saved successfully',
                    'failed' => 'Failed to save',
                    'failed_with_reason' => 'Failed to save: :reason',
                    'confirm_delete_creature' => 'Delete creature :id?',
                    'delete_success' => 'Deleted successfully',
                    'delete_failed' => 'Failed to delete',
                    'delete_failed_with_reason' => 'Failed to delete: :reason',
                ],
                'verify' => [
                    'failure' => 'Verification failed',
                    'failure_with_reason' => 'Verification failed: :reason',
                    'diff_bad' => 'Different',
                    'diff_ok' => 'Match',
                    'diff_summary' => 'Detected :count mismatches',
                    'copy_update' => 'Copy UPDATE statement',
                    'copied' => 'Copied',
                    'row_match' => 'Row matches database',
                ],
                'nav' => [
                    'auto_group_title' => 'Group :index',
                ],
                'compact' => [
                    'mode' => [
                        'normal' => 'Normal',
                        'compact' => 'Compact',
                    ],
                ],
                'bitmask' => [
                    'modal_title' => 'Bitmask selection',
                    'search_placeholder' => 'Search...',
                    'select_all' => 'Select all',
                    'clear' => 'Clear',
                    'tips' => 'Tip: checking will update the value immediately. Use search to filter descriptions.',
                    'close' => 'Close',
                    'field_title' => ':field (:value)',
                    'trigger' => 'Bits',
                ],
            ],
            'item' => [
                'common' => [
                    'copy_success' => 'Copied',
                ],
                'create' => [
                    'enter_new_id' => 'Please enter a new ID',
                    'success_redirect' => 'Item created, redirecting...',
                    'failure' => 'Failed to create item',
                    'failure_with_reason' => 'Creation failed: :reason',
                    'subclass' => [
                        'loading_option' => 'Loading...',
                    ],
                ],
                'list' => [
                    'confirm_delete' => 'Delete item #:id?',
                    'delete_success' => 'Item deleted',
                    'delete_failed' => 'Failed to delete item',
                    'delete_failed_with_reason' => 'Failed to delete item: :reason',
                    'subclass' => [
                        'all_option' => '(All subclasses)',
                        'loading_option' => 'Loading...',
                    ],
                ],
                'diff' => [
                    'no_changes_comment' => '-- No changes (modify the form and retry)',
                    'no_changes_placeholder' => '-- No changes --',
                    'no_changes_to_execute' => 'No changes to execute',
                    'comment' => [
                        'class_fallback_name' => 'Class :id',
                        'class_label' => 'class',
                        'subclass_fallback_name' => 'Subclass :id',
                        'subclass_label' => 'subclass',
                    ],
                    'modal' => [
                        'title' => 'UPDATE Preview',
                        'copy_button' => 'Copy',
                        'close_button' => 'Close',
                    ],
                ],
                'exec' => [
                    'only_item_template_update' => 'Only UPDATE on item_template is allowed',
                    'confirm_run_diff' => 'Run the current SQL?',
                    'status' => [
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ],
                    'timing' => 'Duration :duration',
                    'summary' => [
                        'rows_label' => 'Rows affected:',
                    ],
                    'default_error' => 'Execution failed',
                    'warning_prefix' => 'WARNING:',
                    'error_prefix' => 'ERROR:',
                    'messages' => [
                        'none' => '-- No warnings',
                        'check_above' => '-- See error above',
                    ],
                    'run_success' => 'Execution succeeded',
                    'run_failed_with_reason' => 'Execution failed: :reason',
                    'copy_json_success' => 'JSON copied',
                    'request_exception' => 'Request exception: :reason',
                ],
                'logs' => [
                    'loading_placeholder' => '-- Loading... --',
                    'empty_placeholder' => '-- No logs --',
                    'load_failed_placeholder' => '-- Load failed --',
                    'load_failed' => 'Failed to load logs',
                    'load_failed_with_reason' => 'Failed to load logs: :reason',
                ],
                'save' => [
                    'no_changes' => 'No changes to save',
                    'success' => 'Saved successfully',
                    'failed' => 'Save failed',
                    'failed_with_reason' => 'Save failed: :reason',
                    'confirm_delete_item' => 'Delete item #:id?',
                    'delete_success' => 'Item deleted',
                    'delete_failed' => 'Failed to delete item',
                ],
            ],
			'account' => [
				'errors' => [
					'request_failed_message' => 'Request failed. Please try again.',
					'request_failed' => 'Request failed',
				],
				'ip_lookup' => [
					'private' => 'Private IP',
					'failed' => 'Lookup failed',
					'unknown' => 'Unknown location',
					'loading' => 'Looking up…',
				],
			],
            'quest' => [
                'api' => [
                    'not_ready' => 'Panel API is not ready',
                ],
                'logs' => [
                    'loading_placeholder' => '-- Loading... --',
                    'empty_placeholder' => '-- No logs --',
                    'error_placeholder' => '-- Load failed --',
                    'load_failed' => 'Failed to load logs',
                    'load_failed_with_reason' => 'Failed to load logs: :reason',
                ],
                'create' => [
                    'enter_new_id' => 'Please enter a new quest ID',
                    'success_redirect' => 'Quest created, redirecting...',
                    'failed' => 'Failed to create quest',
                    'failed_with_reason' => 'Failed to create quest: :reason',
                ],
                'list' => [
                    'confirm_delete' => 'Delete quest :id?',
                    'delete_success' => 'Quest deleted',
                    'delete_failed' => 'Failed to delete quest',
                    'delete_failed_with_reason' => 'Failed to delete quest: :reason',
                ],
                'editor' => [
                    'no_changes_comment' => '-- No changes --',
                    'no_sql_available' => 'No SQL to execute',
                    'confirm_execute' => 'Run current UPDATE?',
                    'exec_success' => 'SQL executed',
                    'exec_failed' => 'Execution failed',
                    'exec_failed_with_reason' => 'Execution failed: :reason',
                    'copy_sql_success' => 'SQL copied',
                    'copy_sql_failed_with_reason' => 'Copy failed: :reason',
                    'diff_count' => ':count changes',
                    'rows_label' => 'Rows:',
                    'reset_prompt' => 'Reset all changes and reload current database row?',
                    'reset_success' => 'Changes reset',
                    'reset_failed' => 'Reset failed',
                    'reset_failed_with_reason' => 'Reset failed: :reason',
                    'restore_field' => 'Restored :field',
                    'refresh_failed_console' => 'Failed to refresh quest data',
                ],
                'mini' => [
                    'revert_tooltip' => 'Restore this field',
                    'collapse' => 'Collapse',
                    'expand' => 'Expand',
                ],
                'core' => [
                    'no_changes_sql_comment' => '-- No changes --',
                ],
            ],
            'smartai' => [
                'segments' => [
                    'move_up_title' => 'Move up',
                    'move_down_title' => 'Move down',
                    'delete_segment_title' => 'Delete segment',
                    'default_label' => 'Segment :number',
                    'empty_prompt' => 'Please add a segment.',
                ],
                'search' => [
                    'placeholder' => 'Search keywords or ID',
                ],
                'list' => [
                    'empty' => 'No matches found',
                ],
                'selector' => [
                    'select_type' => 'Select a type.',
                    'no_params' => 'This type has no extra parameters.',
                ],
                'validation' => [
                    'entry_required' => 'Please enter a valid entry.',
                    'entry_invalid' => 'A valid entry is required.',
                    'segment_required' => 'Please add at least one segment.',
                    'event_required_next' => 'Select an event type before continuing.',
                    'event_required' => 'Please select an event type.',
                    'event_required_all' => 'Please select an event type for every segment.',
                    'action_required_next' => 'Select an action type before continuing.',
                    'action_required' => 'Please select an action type.',
                    'action_required_all' => 'Please select an action type for every segment.',
                    'target_required_next' => 'Select a target type before continuing.',
                    'target_required' => 'Please select a target type.',
                    'target_required_all' => 'Please select a target type for every segment.',
                ],
                'api' => [
                    'no_response' => 'No response from server',
                ],
                'preview' => [
                    'placeholder' => '-- No SQL generated --',
                    'error_placeholder' => '-- Generation failed, check form errors --',
                ],
                'summary' => [
                    'segments' => 'Segments: :count',
                    'event' => 'Event: :name',
                    'action' => 'Action: :name',
                    'target' => 'Target: :name',
                ],
                'feedback' => [
                    'generate_success' => 'SQL generated successfully',
                    'generate_failed' => 'Generation failed',
                    'request_failed' => 'Request failed',
                    'copy_success' => 'Copied to clipboard',
                    'copy_failed' => 'Copy failed, please copy manually',
                ],
            ],
            'bitmask' => [
                'popup' => [
                    'title' => 'Bitmask: :name',
                ],
                'help' => [
                    'toggle_tip' => 'Click to toggle bits. Hold Shift and drag to multi-select.',
                ],
                'actions' => [
                    'close' => 'Close',
                    'clear' => 'Clear',
                    'apply' => 'Apply',
                    'undo' => 'Reset',
                ],
                'labels' => [
                    'joiner' => ', ',
                    'none' => '(none)',
                ],
                'status' => [
                    'current_value' => 'Current value: :value',
                ],
                'filter' => [
                    'placeholder' => 'Filter keywords...',
                ],
                'controls' => [
                    'select_all' => 'Select all',
                    'select_none' => 'Clear all',
                    'select_invert' => 'Invert',
                ],
                'option' => [
                    'label' => '(:bit) :name',
                ],
                'modal' => [
                    'title' => 'Edit :target',
                ],
            ],
            'soap' => [
                'meta' => [
                    'updated_at' => 'Command catalog updated at :date',
                    'source_link' => 'GM Commands',
                    'source_label' => 'Reference: :link',
                    'separator' => ' · ',
                ],
                'categories' => [
                    'all' => [
                        'label' => 'All commands',
                        'summary' => 'Show all catalogued commands',
                    ],
                ],
                'list' => [
                    'empty' => 'No matching commands',
                ],
                'risk' => [
                    'badge' => [
                        'low' => 'Low risk',
                        'medium' => 'Medium risk',
                        'high' => 'High risk',
                        'unknown' => 'Unknown risk',
                    ],
                    'short' => [
                        'low' => 'L',
                        'medium' => 'M',
                        'high' => 'H',
                        'unknown' => '?',
                    ],
                ],
                'fields' => [
                    'empty' => 'This command requires no additional parameters.',
                ],
                'errors' => [
                    'missing_required' => 'Please fill all required fields.',
                    'unknown_response' => 'Unknown response',
                ],
                'form' => [
                    'error_joiner' => ', ',
                ],
                'feedback' => [
                    'execute_success' => 'Command executed successfully',
                    'execute_failed' => 'Command failed',
                ],
                'output' => [
                    'unknown_time' => 'Unknown time',
                    'meta' => 'Status: :code · Time: :time',
                    'empty' => '(No output)',
                ],
                'copy' => [
                    'empty' => 'Nothing to copy',
                    'success' => 'Copied to clipboard',
                    'failure' => 'Copy failed',
                ],
            ],
        ],
    ],
    'audit' => [
        'api' => [
            'errors' => [
                'read_failed' => 'Failed to read records',
            ],
        ],
    ],
];

