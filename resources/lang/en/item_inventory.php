<?php
return [
    'page_title' => 'Items / Inventory',

    'tabs' => [
        'character' => 'By character',
        'item' => 'By item',
    ],

    'character' => [
        'form' => [
            'type_label' => 'Search by',
            'type_character_name' => 'Character name (partial)',
            'type_username' => 'Account username',
            'value_label' => 'Value',
            'value_placeholder' => 'Character or account name',
            'submit' => 'Search',
        ],
        'chars' => [
            'title' => 'Characters',
            'subtitle' => 'Pick a character to inspect their bags, bank and equipment',
            'table' => [
                'guid' => 'GUID',
                'name' => 'Name',
                'level' => 'Level',
                'race' => 'Race',
                'account' => 'Account',
                'actions' => 'Actions',
                'empty' => 'Waiting for a search…',
            ],
        ],
    ],

    'item' => [
        'form' => [
            'keyword_label' => 'Keyword',
            'keyword_placeholder' => 'Item name or item ID',
            'submit' => 'Search',
        ],
        'search' => [
            'title' => 'Item lookup',
            'subtitle' => 'Find which characters hold an item by name or ID.',
            'view' => 'View owners',
            'empty' => 'No items found',
            'table' => [
                'entry' => 'Item ID',
                'name' => 'Name',
                'quality' => 'Quality',
                'stackable' => 'Stack size',
                'actions' => 'Actions',
                'placeholder' => 'Search results will appear here.',
            ],
        ],
    ],

    'items' => [
        'title' => 'Item instances',
        'subtitle_empty' => 'No character selected',
        'filter_placeholder' => 'Filter items by name',
        'contains' => 'This bag contains items',
        'contains_count' => ':count inside',
        'open_in_editor' => 'Open in items management',
        'table' => [
            'instance_guid' => 'Instance GUID',
            'item_id' => 'Item ID',
            'name' => 'Name',
            'count' => 'Count',
            'location' => 'Location',
            'owners' => 'Owners',
            'actions' => 'Actions',
            'empty' => 'No character selected',
        ],
    ],

    'owners' => [
        'title_empty' => 'Select an item',
        'title_loading' => 'Loading ownership…',
        'title_error' => 'Failed to load ownership',
        'subtitle_empty' => 'Search an item first, then inspect its owners.',
        'subtitle_totals' => ':characters characters · :instances stacks · total :count',
        'table' => [
            'instance' => 'Instance GUID',
            'character' => 'Character',
            'count' => 'Count',
            'location' => 'Location',
            'container' => 'Container',
            'placeholder' => 'Ownership data not loaded yet.',
            'empty' => 'No instances to display',
        ],
        'actions' => [
            'delete_selected' => 'Delete selected',
            'replace_selected' => 'Replace selected',
        ],
        'pager' => [
            'prev' => 'Previous',
            'next' => 'Next',
            'info' => 'Page :page / :pages',
        ],
    ],

    'actions' => [
        'view' => 'View items',
        'delete' => 'Delete',
        'processing' => 'Processing…',
    ],

    'modal' => [
        'delete' => [
            'title' => 'Delete / reduce item',
            'quantity_label' => 'Quantity',
            'quantity_hint' => 'A quantity equal to the current stack deletes the instance; less only reduces it.',
            'destroy_contents' => 'This bag still holds items — destroy them as well',
            'container_requires_confirm' => 'This bag still holds :count item(s). Tick the box above to destroy them too, or lower the quantity.',
            'cancel' => 'Cancel',
            'confirm' => 'Confirm',
            'success' => 'Item processed',
            'error' => 'Operation failed',
            'validation' => [
                'quantity' => 'Quantity must be greater than 0 and no more than the current stack.',
            ],
        ],
        'bulk_delete' => [
            'title' => 'Confirm bulk delete',
            'info' => 'You are about to delete :count selected item instance(s). This cannot be undone.',
            'destroy_contents' => 'The selection contains bags — destroy the items inside them as well',
            'failed_title' => 'These instances could not be deleted:',
            'cancel' => 'Cancel',
            'confirm' => 'Delete',
            'success' => 'Delete completed',
            'error' => 'Delete failed',
        ],
        'replace' => [
            'title' => 'Replace items',
            'entry_label' => 'New item ID',
            'entry_placeholder' => 'Enter an item ID',
            'entry_hint' => 'Selected instances become this item. Stacks larger than the new item stack size are split into free slots of the same container.',
            'cancel' => 'Cancel',
            'confirm' => 'Apply',
            'success' => 'Replace completed',
            'error' => 'Replace failed',
            'validation' => [
                'entry' => 'Enter a valid item ID.',
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

    'locations' => [
        'equipment' => [
            'head' => 'Head',
            'neck' => 'Neck',
            'shoulders' => 'Shoulders',
            'body' => 'Shirt',
            'chest' => 'Chest',
            'waist' => 'Waist',
            'legs' => 'Legs',
            'feet' => 'Feet',
            'wrist' => 'Wrist',
            'hands' => 'Hands',
            'finger1' => 'Ring 1',
            'finger2' => 'Ring 2',
            'trinket1' => 'Trinket 1',
            'trinket2' => 'Trinket 2',
            'back' => 'Back',
            'main_hand' => 'Main hand',
            'off_hand' => 'Off hand',
            'ranged' => 'Ranged / relic',
            'tabard' => 'Tabard',
        ],
        'inventory' => [
            'backpack' => 'Backpack slot :slot',
            'bank_main' => 'Bank slot :slot',
            'keyring' => 'Keyring slot :slot',
            'currency' => 'Currency slot :slot',
            'bag_slot' => 'Bag slot :slot',
            'bag_inner' => 'Bag :bag slot :slot',
            'unknown' => 'Inventory slot :slot (unmapped)',
        ],
        'bank' => [
            'bag_slot' => 'Bank bag slot :slot',
            'bag_inner' => 'Bank bag :bag slot :slot',
        ],
    ],

    'api' => [
        'errors' => [
            'invalid_guid' => 'Invalid character GUID.',
            'invalid_entry' => 'Invalid item ID.',
            'entry_not_found' => 'Item not found.',
            'invalid_parameters' => 'Invalid parameters.',
            'instance_not_found' => 'Item instance not found, or it does not belong to the given character.',
            'invalid_instance' => 'Invalid item instance.',
            'instances_not_found' => 'The selected item instances do not exist or belong to no character.',
            'quantity_positive' => 'Quantity must be greater than 0.',
            'quantity_exceeds_stack' => 'Quantity exceeds the current stack (currently :count).',
            'inventory_mismatch' => 'The item instance and the character inventory record disagree.',
            'container_not_empty' => 'This item is a container holding :count item(s). Confirm destroying them and try again.',
            'container_not_empty_bulk' => 'The selection contains non-empty containers. Confirm destroying their contents and try again.',
            'not_enough_slots' => 'Not enough free slots in the destination (need :needed, have :available). Nothing was changed.',
            'instance_create_failed' => 'Could not create the item instance: :message',
            'reduce_failed' => 'Operation failed: :message',
            'delete_failed' => 'Delete failed.',
            'delete_partial' => 'Deleted :success instance(s), :failed failed — the batch was rolled back.',
            'empty_selection' => 'Select at least one item instance first.',
            'invalid_new_entry' => 'Enter a valid replacement item ID.',
            'new_entry_not_found' => 'The replacement item ID does not exist.',
            'replace_failed' => 'Could not replace the item instance.',
            'batch_contains_missing' => 'The selection contains an instance that no longer exists or belongs to nobody. Nothing was changed.',
            'unknown_action' => 'Unknown action.',
            'lookup_failed' => 'Item data lookup failed.',
        ],
        'success' => [
            'quantity_reduced' => 'Quantity reduced.',
            'item_deleted' => 'Item instance deleted.',
            'item_deleted_with_contents' => 'Item instance deleted along with :count contained item(s).',
            'delete_done' => 'Deleted :count item instance(s).',
            'replace_done' => 'Replaced :count item instance(s).',
            'replace_done_split' => 'Replaced :count item instance(s) and split them into :created new instance(s) to respect the stack size.',
        ],
    ],

    'js' => [
        'modules' => [
            'item_inventory' => [
                'errors' => [
                    'parse_failed' => 'Failed to parse response',
                    'network' => 'Network error',
                ],
                'status' => [
                    'loading' => 'Loading…',
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
                'character' => [
                    'validation' => [
                        'empty' => 'Please enter a search value',
                    ],
                    'empty' => 'No results',
                    'error' => [
                        'failed' => 'Query failed',
                    ],
                ],
                'item' => [
                    'validation' => [
                        'empty' => 'Please input a keyword',
                    ],
                    'search' => [
                        'empty' => 'No items found',
                        'view' => 'View owners',
                        'open_in_editor' => 'Open in items management',
                    ],
                    'error' => [
                        'failed' => 'Search failed',
                    ],
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
                    'empty' => 'No items found or bags are empty',
                    'contains' => 'This bag contains items',
                    'contains_count' => ':count inside',
                    'open_in_editor' => 'Open in items management',
                    'error' => [
                        'load_failed' => 'Failed to load items',
                    ],
                ],
                'owners' => [
                    'title_empty' => 'Select an item',
                    'title_loading' => 'Loading ownership…',
                    'title_error' => 'Failed to load ownership',
                    'subtitle_empty' => 'Search an item first, then inspect its owners.',
                    'subtitle_totals' => ':characters characters · :instances stacks · total :count',
                    'table' => [
                        'empty' => 'No instances to display',
                    ],
                    'pager' => [
                        'info' => 'Page :page / :pages',
                    ],
                    'error' => [
                        'load_failed' => 'Failed to load ownership',
                    ],
                ],
                'actions' => [
                    'view_items' => 'View items',
                    'view_owners' => 'View owners',
                    'delete' => 'Delete',
                    'processing' => 'Processing…',
                ],
                'modal' => [
                    'delete' => [
                        'info' => 'Item #:entry :name — current stack :count — instance GUID :inst',
                        'quantity_hint' => 'A quantity equal to the current stack deletes the instance; less only reduces it.',
                        'destroy_contents' => 'This bag still holds items — destroy them as well',
                        'container_requires_confirm' => 'This bag still holds :count item(s). Tick the box above to destroy them too, or lower the quantity.',
                        'success' => 'Item processed',
                        'error' => 'Operation failed',
                        'validation' => [
                            'quantity' => 'Quantity must be greater than 0 and no more than the current stack.',
                        ],
                    ],
                    'bulk_delete' => [
                        'info' => 'You are about to delete :count selected item instance(s). This cannot be undone.',
                        'destroy_contents' => 'The selection contains bags — destroy the items inside them as well',
                        'failed_title' => 'These instances could not be deleted:',
                        'success' => 'Delete completed',
                        'error' => 'Delete failed',
                    ],
                    'replace' => [
                        'success' => 'Replace completed',
                        'error' => 'Replace failed',
                        'validation' => [
                            'entry' => 'Enter a valid item ID.',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
