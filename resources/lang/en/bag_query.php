<?php
return [
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
    'js' => [
        'modules' => [
            'bag_query' => [
                'quality' => [
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
        ],
    ],
];
