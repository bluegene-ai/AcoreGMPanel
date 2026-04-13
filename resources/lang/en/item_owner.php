<?php
return [
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
        0 => 'Poor',
        1 => 'Common',
        2 => 'Uncommon',
        3 => 'Rare',
        4 => 'Epic',
        5 => 'Legendary',
        6 => 'Artifact',
        7 => 'Heirloom',
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
            'delete' => [
                'confirm' => 'Delete this account? This will also delete all characters under the account. This cannot be undone.',
                'success' => 'Deleted',
                'blocked_online' => 'Online character detected (:name). Please kick first.',
                'characters_failed' => 'Failed to delete characters: :message',
                'account_failed' => 'Failed to delete account: :message',
            ],
            'legs' => 'Legs',
            'bulk' => [
                'select_all' => 'Select all',
                'delete' => 'Bulk delete',
                'ban' => 'Bulk ban',
                'unban' => 'Bulk unban',
                'no_selection' => 'Please select at least one item',
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
    'js' => [
        'modules' => [
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
                    0 => 'Poor',
                    1 => 'Common',
                    2 => 'Uncommon',
                    3 => 'Rare',
                    4 => 'Epic',
                    5 => 'Legendary',
                    6 => 'Artifact',
                    7 => 'Heirloom',
                ],
            ],
        ],
    ],
];
