<?php
/**
 * File: config/logs.php
 * Purpose: Provides functionality for the config module.
 */

return [
    'defaults' => [
        'module' => 'item',
        'type' => 'sql',
        'limit' => 200,
        'max_limit' => 500,
    ],
    'modules' => [
        'account' => [
            'label' => 'lang:app.logs.config.modules.account.label',
            'description' => 'lang:app.logs.config.modules.account.description',
            'types' => [
                'actions' => [
                    'label' => 'lang:app.logs.config.modules.account.types.actions.label',
                    'file' => 'account_actions.log',
                    'format' => 'json_line',
                ],
            ],
        ],
        'bag_query' => [
            'label' => 'lang:app.logs.config.modules.bag_query.label',
            'description' => 'lang:app.logs.config.modules.bag_query.description',
            'types' => [
                'actions' => [
                    'label' => 'lang:app.logs.config.modules.bag_query.types.actions.label',
                    'file' => 'bag_query_actions.log',
                    'format' => 'json_line',
                ],
            ],
        ],
        'item' => [
            'label' => 'lang:app.logs.config.modules.item.label',
            'description' => 'lang:app.logs.config.modules.item.description',
            'types' => [
                'sql' => [
                    'label' => 'lang:app.logs.config.modules.item.types.sql.label',
                    'file' => 'item_sql.log',
                    'format' => 'item_sql',
                ],
                'actions' => [
                    'label' => 'lang:app.logs.config.modules.item.types.actions.label',
                    'file' => 'item_actions.log',
                    'format' => 'json_line',
                ],
                'deleted' => [
                    'label' => 'lang:app.logs.config.modules.item.types.deleted.label',
                    'file' => 'item_deleted.log',
                    'format' => 'json_line',
                ],
            ],
        ],
        'item_owner' => [
            'label' => 'lang:app.logs.config.modules.item_owner.label',
            'description' => 'lang:app.logs.config.modules.item_owner.description',
            'types' => [
                'actions' => [
                    'label' => 'lang:app.logs.config.modules.item_owner.types.actions.label',
                    'file' => 'item_ownership_actions.log',
                    'format' => 'json_line',
                ],
            ],
        ],
        'creature' => [
            'label' => 'lang:app.logs.config.modules.creature.label',
            'description' => 'lang:app.logs.config.modules.creature.description',
            'types' => [
                'sql' => [
                    'label' => 'lang:app.logs.config.modules.creature.types.sql.label',
                    'file' => 'creature_sql.log',
                    'format' => 'pipe_sql',
                ],
            ],
        ],
        'quest' => [
            'label' => 'lang:app.logs.config.modules.quest.label',
            'description' => 'lang:app.logs.config.modules.quest.description',
            'types' => [
                'sql' => [
                    'label' => 'lang:app.logs.config.modules.quest.types.sql.label',
                    'file' => 'quest_sql.log',
                    'format' => 'pipe_sql',
                ],
                'deleted' => [
                    'label' => 'lang:app.logs.config.modules.quest.types.deleted.label',
                    'file' => 'quest_deleted.log',
                    'format' => 'pipe_deleted',
                ],
            ],
        ],
        'mail' => [
            'label' => 'lang:app.logs.config.modules.mail.label',
            'description' => 'lang:app.logs.config.modules.mail.description',
            'types' => [
                'sql' => [
                    'label' => 'lang:app.logs.config.modules.mail.types.sql.label',
                    'file' => 'mail_sql.log',
                    'format' => 'pipe_sql',
                ],
                'deleted' => [
                    'label' => 'lang:app.logs.config.modules.mail.types.deleted.label',
                    'file' => 'mail_deleted.log',
                    'format' => 'pipe_deleted',
                ],
            ],
        ],
        'massmail' => [
            'label' => 'lang:app.logs.config.modules.massmail.label',
            'description' => 'lang:app.logs.config.modules.massmail.description',
            'types' => [
                'actions' => [
                    'label' => 'lang:app.logs.config.modules.massmail.types.actions.label',
                    'file' => 'massmail_actions.log',
                    'format' => 'massmail',
                ],
            ],
        ],
        'server' => [
            'label' => 'lang:app.logs.config.modules.server.label',
            'description' => 'lang:app.logs.config.modules.server.description',
            'types' => [
                'debug' => [
                    'label' => 'lang:app.logs.config.modules.server.types.debug.label',
                    'file' => 'server_switch_debug.log',
                    'format' => 'plain',
                ],
            ],
        ],
    ],
];

