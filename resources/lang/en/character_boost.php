<?php
return [
    'admin' => [
        'page_title' => 'Boost Management',
        'note' => 'realm_id=:id — apply boosts and review templates and redeem codes from one place.',
        'apply' => [
            'title' => 'Apply a boost',
            'note' => 'realm_id=:realm. Locate the character by name or GUID, then pick a template or set a target level.',
            'note_short' => 'realm_id=:realm. Locate the character by name or GUID.',
            'hint' => 'The boost raises the level first and then delivers the template items and gold. Templates are safer than a manual level; prefer them.',
            'hint_short' => 'Templates are safer than a manual level; prefer them.',
        ],
        'fields' => [
            'character_name' => 'Character name',
            'character_name_placeholder' => 'e.g. Arthas',
            'guid' => 'Character GUID',
            'guid_placeholder' => 'Optional; takes priority when set',
            'template' => 'Boost template',
            'template_none' => 'No template (level only)',
            'target_level' => 'Target level',
            'target_level_placeholder' => 'e.g. 80',
            'target_level_from_template' => 'Set by template',
        ],
        'actions' => [
            'preview' => 'Preview rewards',
            'apply' => 'Apply boost',
            'working' => 'Working…',
        ],
        'preview' => [
            'title' => 'Will be delivered',
            'character' => 'Character',
            'level' => 'Level',
            'template' => 'Template',
            'items' => 'Rewards',
            'items_none' => 'Level change only, no items',
            'money' => 'Gold',
        ],
        'overview' => [
            'title' => 'Overview',
            'templates' => 'Templates',
            'code_total' => 'Redeem codes',
            'code_unused' => 'Unused',
            'code_used' => 'Used',
        ],
        'links' => [
            'templates' => 'Manage templates',
            'codes' => 'Manage redeem codes',
        ],
        'history' => [
            'title' => 'Recent boosts',
            'refresh' => 'Refresh',
            'empty' => 'No boosts recorded yet',
            'columns' => [
                'time' => 'Time',
                'character' => 'Character',
                'rewards' => 'Rewards',
                'status' => 'Status',
            ],
        ],
        'applied' => 'Boost applied to :name.',
        'errors' => [
            'character_required' => 'Enter a character name or GUID.',
            'target_required' => 'Pick a boost template or enter a target level.',
            'character_missing' => 'Character not found.',
        ],
    ],
    'codes' => [
        'title' => 'Boost Redeem Code Generator',
        'fields' => [
            'realm' => 'Realm',
            'template' => 'Boost Template',
            'template_all' => 'All templates',
            'count' => 'Count',
            'output' => 'Output',
            'download' => 'Also download txt file',
        ],
        'hint' => [
            'realm_from_server' => 'Follows the server switcher in the header',
            'count_limit' => 'Max 10000 per run. Use batches for large amounts.',
            'download' => 'When checked, codes are saved to DB and a txt file is downloaded.',
        ],
        'actions' => [
            'generate' => 'Generate Codes',
        ],
        'generate' => [
            'title' => 'Generate Redeem Codes',
            'quick' => 'Quick count',
            'safety' => 'Codes are written to the database immediately — check template and count first.',
        ],
        'generated' => [
            'title' => 'Generated Output',
            'hint' => 'One code per line. Copy or download to distribute.',
            'copy' => 'Copy all',
            'collapse' => 'Collapse',
        ],
        'success' => 'Generated :count redeem codes.',
        'errors' => [
            'invalid_count' => 'Invalid count (range 1-10000).',
            'no_templates' => 'No templates available on this realm.',
            'invalid_template' => 'Invalid template for this realm.',
        ],
        'manage' => [
            'title' => 'Redeem Code Management',
            'hint' => 'Only unused redeem codes can be deleted. Used codes are shown for record only.',
            'fields' => [
                'template' => 'Boost Template',
                'status' => 'Usage status',
                'status_all' => 'All statuses',
                'status_unused' => 'Unused only',
                'status_used' => 'Used only',
                'search' => 'Quick filter',
                'search_placeholder' => 'Filter this page by code / character / IP',
                'per_page' => 'Rows per page',
                'unused_only' => 'Filter',
                'unused_only_label' => 'Unused only',
            ],
            'stats' => [
                'title' => 'Stats',
                'total' => 'Total',
                'unused' => 'Unused',
                'used' => 'Used',
            ],
            'columns' => [
                'id' => 'ID',
                'template' => 'Template',
                'code' => 'Code',
                'status' => 'Status',
                'used_by' => 'Usage',
                'used_at' => 'Used At',
                'created_at' => 'Created At',
                'actions' => 'Actions',
            ],
            'actions' => [
                'refresh' => 'Refresh',
                'purge_unused' => 'Purge Unused',
            ],
            'deleted' => 'Deleted unused redeem code.',
            'purged' => 'Purged :count unused redeem codes.',
            'errors' => [
                'delete_failed' => 'Delete failed (maybe already used / not found / realm mismatch).',
            ],
        ],
    ],
    'templates' => [
        'title' => 'Boost Templates',
        'create_title' => 'Create Boost Template',
        'edit_title' => 'Edit Boost Template # :id',
        'edit_title_not_found' => 'Template not found',
        'create_heading' => 'Create Boost Template',
        'edit_heading' => 'Edit Boost Template # :id',
        'columns' => [
            'name' => 'Name',
            'target_level' => 'Target Level',
            'money_gold' => 'Gold',
            'items' => 'Item rewards',
            'class_rewards' => 'Class set rewards',
            'require_match' => 'Account Level Guard',
            'actions' => 'Actions',
        ],
        'fields' => [
            'name' => 'Name',
            'target_level' => 'Target Level',
            'money_gold' => 'Gold (in gold)',
            'require_match' => 'Account Level Guard',
            'require_match_label' => 'Require account max level >= target level',
            'items' => 'Items (one per line: entry:qty)',
            'class_rewards' => 'Class reward tiers (one per line, e.g. t2)',
        ],
        'hint' => [
            'realm' => 'Current realm_id = :id',
            'items_format' => 'Example: 29434:1 (qty optional, default 1).',
            'class_rewards' => 'Example: t2 (will send preset class rewards).',
        ],
        'actions' => [
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'save' => 'Save',
            'back' => 'Back',
            'codes' => 'Redeem codes',
            'public_redeem' => 'Open public redeem page',
        ],
        'empty' => 'No templates',
        'saved' => 'Template saved.',
        'deleted' => 'Template deleted.',
        'errors' => [
            'invalid_payload' => 'Invalid payload (name/level/gold).',
            'save_failed' => 'Save failed (duplicate name or not found).',
            'delete_failed' => 'Delete failed (not found or realm mismatch).',
        ],
    ],
    'redeem' => [
        'title' => 'Redeem Boost Code',
        'fields' => [
            'realm' => 'Realm',
            'template' => 'Boost Template',
            'template_loading' => 'Loading...',
            'character_name' => 'Character Name',
            'code' => 'Redeem Code',
        ],
        'hint' => [
            'template_auto' => 'Template is determined by the redeem code (shown here for reference only).',
        ],
        'actions' => [
            'submit' => 'Redeem & Boost',
        ],
        'success' => 'Redeemed successfully. Boost has been applied.',
        'errors' => [
            'invalid_code_format' => 'Invalid code format (must be 16 alphanumeric characters).',
            'invalid_realm' => 'Invalid realm.',
            'code_not_found' => 'Code not found.',
            'code_used' => 'Code has already been used.',
            'invalid_template' => 'Invalid template for this code.',
            'character_not_found' => 'Character not found.',
        ],
    ],
    'js' => [
        'modules' => [
            'character_boost' => [
                'common' => [
                    'ok' => 'OK',
                    'error' => 'Error',
                    'failed' => 'Failed',
                    'invalid_response' => 'Invalid response',
                    'network_error' => 'Network error',
                    'loading' => 'Loading…',
                ],
                'templates' => [
                    'confirm' => [
                        'delete' => 'Delete template #:id?',
                    ],
                ],
                'codes' => [
                    'generating' => 'Generating…',
                    'table' => [
                        'empty' => 'No redeem codes',
                    ],
                    'manage' => [
                        'no_match' => 'No codes on this page match the filter',
                    ],
                    'status' => [
                        'used' => 'USED',
                        'unused' => 'UNUSED',
                    ],
                    'actions' => [
                        'delete_unused' => 'Delete',
                    ],
                    'confirm' => [
                        'purge_unused' => 'Delete ALL unused redeem codes?',
                        'delete_unused' => 'Delete this unused redeem code?',
                    ],
                    'pager' => [
                        'summary' => 'Page :page / :pages · :total',
                    ],
                    'generated' => [
                        'count' => ':count codes',
                        'download_ok' => 'OK',
                        'download_ok_count' => 'OK (:count)',
                        'template_named' => ':name (#:id)',
                        'template_fallback' => 'Template #:id',
                        'copied' => 'Copied to clipboard',
                        'copy_failed' => 'Copy failed, please select the text manually',
                        'copy_empty' => 'Nothing to copy yet',
                    ],
                    'usage' => [
                        'realm_suffix' => ' (realm :id)',
                    ],
                    'errors' => [
                        'invalid_count' => 'Enter a valid count',
                        'count_too_large' => 'At most 10000 codes per run',
                    ],
                ],
                'redeem' => [
                    'templates' => [
                        'empty' => 'No templates',
                    ],
                    'realms' => [
                        'option' => 'Realm :id',
                    ],
                    'errors' => [
                        'load_options_failed' => 'Failed to load options',
                    ],
                ],
            ],
        ],
    ],
];
