<?php
return [
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
            0 => 'General',
            1 => 'Group',
            21 => 'Life',
            41 => 'PvP',
            62 => 'Raid',
            81 => 'Dungeon',
            82 => 'World Event',
            83 => 'Legendary',
            84 => 'Escort',
            85 => 'Heroic',
            88 => 'Raid (10 player)',
            89 => 'Raid (25 player)',
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
    'js' => [
        'modules' => [
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
        ],
    ],
];