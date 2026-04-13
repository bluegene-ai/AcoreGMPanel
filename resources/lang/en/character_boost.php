<?php
return [
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
        'generated' => [
            'title' => 'Generated Output',
            'hint' => 'One code per line. Copy & distribute as needed.',
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
                    'table' => [
                        'empty' => 'No redeem codes',
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
                        'download_ok' => 'OK',
                        'download_ok_count' => 'OK (:count)',
                        'template_named' => ':name (#:id)',
                        'template_fallback' => 'Template #:id',
                    ],
                    'usage' => [
                        'realm_suffix' => ' (realm :id)',
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
