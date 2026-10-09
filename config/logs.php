<?php
/**
 * File: config/logs.php
 * Purpose: 审计日志的存储参数、查询默认值与模块/动作目录。
 *
 * 日志一律落到 storage.database 的 storage.table（跨库访问：面板按区服连 characters/auth，
 * 表在共用的 Eluna 库里）。modules 块只提供服务端与前端共用的标签。
 */

return [
    'storage' => [
        // 日志表的访问连接：表名可跨库限定，用共用的 auth 即可。
        'connection' => 'auth',
        'database' => 'ac_eluna',
        'table' => 'panel_audit_log',
        // 日志库不可达时的兜底落盘文件（storage/logs 下），只在写库失败时产生。
        'fallback' => 'audit-fallback.log',
        // 保留天数：仅作为页面上的清理默认值，不会自动删除任何行。
        'retention_days' => 180,
    ],

    'defaults' => [
        'keyword' => '',
        'channel' => 'all',
        'module' => 'all',
        'action' => 'all',
        'actor' => 'all',
        'status' => 'all',
        'realm' => 'all',
        'range' => '7d',
        'sort' => 'desc',
    ],

    'limits' => [
        'per_page' => 50,
        'max_per_page' => 200,
        'facets' => 300,
        'purge_min_days' => 1,
        'purge_max_days' => 3650,
    ],

    // 时间范围预设：range 键 => strtotime 相对表达式（空串 = 不限时间）
    'ranges' => [
        '1h' => '-1 hour',
        '24h' => '-24 hours',
        '7d' => '-7 days',
        '30d' => '-30 days',
        '90d' => '-90 days',
        'all' => '',
    ],

    'channels' => [
        'audit' => 'lang:app.logs.channels.audit',
        'error' => 'lang:app.logs.channels.error',
        'system' => 'lang:app.logs.channels.system',
    ],

    'statuses' => [
        'ok' => 'lang:app.logs.statuses.ok',
        'fail' => 'lang:app.logs.statuses.fail',
        'denied' => 'lang:app.logs.statuses.denied',
    ],

    'modules' => [
        'auth' => [
            'label' => 'lang:app.logs.catalog.auth.label',
            'description' => 'lang:app.logs.catalog.auth.description',
            'actions' => [
                'login' => 'lang:app.logs.catalog.auth.actions.login',
                'login_failed' => 'lang:app.logs.catalog.auth.actions.login_failed',
                'logout' => 'lang:app.logs.catalog.auth.actions.logout',
            ],
        ],
        'account' => [
            'label' => 'lang:app.logs.catalog.account.label',
            'description' => 'lang:app.logs.catalog.account.description',
            'actions' => [
                'create' => 'lang:app.logs.catalog.account.actions.create',
                'delete' => 'lang:app.logs.catalog.account.actions.delete',
                'ban' => 'lang:app.logs.catalog.account.actions.ban',
                'unban' => 'lang:app.logs.catalog.account.actions.unban',
                'set_gm' => 'lang:app.logs.catalog.account.actions.set_gm',
                'update_email' => 'lang:app.logs.catalog.account.actions.update_email',
                'rename' => 'lang:app.logs.catalog.account.actions.rename',
                'change_password' => 'lang:app.logs.catalog.account.actions.change_password',
                'kick' => 'lang:app.logs.catalog.account.actions.kick',
            ],
        ],
        'character' => [
            'label' => 'lang:app.logs.catalog.character.label',
            'description' => 'lang:app.logs.catalog.character.description',
            'actions' => [
                'boost' => 'lang:app.logs.catalog.character.actions.boost',
                'boost_failed' => 'lang:app.logs.catalog.character.actions.boost_failed',
                'ban' => 'lang:app.logs.catalog.character.actions.ban',
                'unban' => 'lang:app.logs.catalog.character.actions.unban',
                'delete' => 'lang:app.logs.catalog.character.actions.delete',
                'set_level' => 'lang:app.logs.catalog.character.actions.set_level',
                'set_gold' => 'lang:app.logs.catalog.character.actions.set_gold',
                'kick' => 'lang:app.logs.catalog.character.actions.kick',
                'kick_before_teleport' => 'lang:app.logs.catalog.character.actions.kick_before_teleport',
                'teleport' => 'lang:app.logs.catalog.character.actions.teleport',
                'unstuck' => 'lang:app.logs.catalog.character.actions.unstuck',
                'reset_talents' => 'lang:app.logs.catalog.character.actions.reset_talents',
                'reset_spells' => 'lang:app.logs.catalog.character.actions.reset_spells',
                'reset_cooldowns' => 'lang:app.logs.catalog.character.actions.reset_cooldowns',
                'rename_flag' => 'lang:app.logs.catalog.character.actions.rename_flag',
            ],
        ],
        'character_boost' => [
            'label' => 'lang:app.logs.catalog.character_boost.label',
            'description' => 'lang:app.logs.catalog.character_boost.description',
            'actions' => [
                'generate_redeem_codes' => 'lang:app.logs.catalog.character_boost.actions.generate_redeem_codes',
                'delete_unused_redeem_code' => 'lang:app.logs.catalog.character_boost.actions.delete_unused_redeem_code',
                'purge_unused_redeem_codes' => 'lang:app.logs.catalog.character_boost.actions.purge_unused_redeem_codes',
                'save_template' => 'lang:app.logs.catalog.character_boost.actions.save_template',
                'delete_template' => 'lang:app.logs.catalog.character_boost.actions.delete_template',
                'public_redeem' => 'lang:app.logs.catalog.character_boost.actions.public_redeem',
                'public_redeem_failed' => 'lang:app.logs.catalog.character_boost.actions.public_redeem_failed',
            ],
        ],
        'item' => [
            'label' => 'lang:app.logs.catalog.item.label',
            'description' => 'lang:app.logs.catalog.item.description',
            'actions' => [
                'create' => 'lang:app.logs.catalog.item.actions.create',
                'update' => 'lang:app.logs.catalog.item.actions.update',
                'delete' => 'lang:app.logs.catalog.item.actions.delete',
                'exec_sql' => 'lang:app.logs.catalog.item.actions.exec_sql',
            ],
        ],
        'creature' => [
            'label' => 'lang:app.logs.catalog.creature.label',
            'description' => 'lang:app.logs.catalog.creature.description',
            'actions' => [
                'create' => 'lang:app.logs.catalog.creature.actions.create',
                'update' => 'lang:app.logs.catalog.creature.actions.update',
                'delete' => 'lang:app.logs.catalog.creature.actions.delete',
                'exec_sql' => 'lang:app.logs.catalog.creature.actions.exec_sql',
            ],
        ],
        'creature_model' => [
            'label' => 'lang:app.logs.catalog.creature_model.label',
            'description' => 'lang:app.logs.catalog.creature_model.description',
            'actions' => [
                'add' => 'lang:app.logs.catalog.creature_model.actions.add',
                'edit' => 'lang:app.logs.catalog.creature_model.actions.edit',
                'delete' => 'lang:app.logs.catalog.creature_model.actions.delete',
            ],
        ],
        'quest' => [
            'label' => 'lang:app.logs.catalog.quest.label',
            'description' => 'lang:app.logs.catalog.quest.description',
            'actions' => [
                'create' => 'lang:app.logs.catalog.quest.actions.create',
                'update' => 'lang:app.logs.catalog.quest.actions.update',
                'delete' => 'lang:app.logs.catalog.quest.actions.delete',
                'exec_sql' => 'lang:app.logs.catalog.quest.actions.exec_sql',
                'aggregate_save' => 'lang:app.logs.catalog.quest.actions.aggregate_save',
                'aggregate_save_fail' => 'lang:app.logs.catalog.quest.actions.aggregate_save_fail',
            ],
        ],
        'item_inventory' => [
            'label' => 'lang:app.logs.catalog.item_inventory.label',
            'description' => 'lang:app.logs.catalog.item_inventory.description',
            'actions' => [
                'search_characters' => 'lang:app.logs.catalog.item_inventory.actions.search_characters',
                'view_character_items' => 'lang:app.logs.catalog.item_inventory.actions.view_character_items',
                'view_ownership' => 'lang:app.logs.catalog.item_inventory.actions.view_ownership',
                'reduce_instance' => 'lang:app.logs.catalog.item_inventory.actions.reduce_instance',
                'bulk_delete' => 'lang:app.logs.catalog.item_inventory.actions.bulk_delete',
                'bulk_replace' => 'lang:app.logs.catalog.item_inventory.actions.bulk_replace',
            ],
        ],
        'mail' => [
            'label' => 'lang:app.logs.catalog.mail.label',
            'description' => 'lang:app.logs.catalog.mail.description',
            'actions' => [
                'view' => 'lang:app.logs.catalog.mail.actions.view',
                'mark_read' => 'lang:app.logs.catalog.mail.actions.mark_read',
                'mark_read_bulk' => 'lang:app.logs.catalog.mail.actions.mark_read_bulk',
                'delete' => 'lang:app.logs.catalog.mail.actions.delete',
                'delete_bulk' => 'lang:app.logs.catalog.mail.actions.delete_bulk',
            ],
        ],
        'massmail' => [
            'label' => 'lang:app.logs.catalog.massmail.label',
            'description' => 'lang:app.logs.catalog.massmail.description',
            'actions' => [
                'announce' => 'lang:app.logs.catalog.massmail.actions.announce',
                'send_mail' => 'lang:app.logs.catalog.massmail.actions.send_mail',
                'send_item' => 'lang:app.logs.catalog.massmail.actions.send_item',
                'send_gold' => 'lang:app.logs.catalog.massmail.actions.send_gold',
                'send_item_gold' => 'lang:app.logs.catalog.massmail.actions.send_item_gold',
            ],
        ],
        'boss' => [
            'label' => 'lang:app.logs.catalog.boss.label',
            'description' => 'lang:app.logs.catalog.boss.description',
            'actions' => [
                'command' => 'lang:app.logs.catalog.boss.actions.command',
                'save_config' => 'lang:app.logs.catalog.boss.actions.save_config',
                'save_ext_config' => 'lang:app.logs.catalog.boss.actions.save_ext_config',
                'copy_ext_config' => 'lang:app.logs.catalog.boss.actions.copy_ext_config',
                'save_reward_pools' => 'lang:app.logs.catalog.boss.actions.save_reward_pools',
            ],
        ],
        'trivia' => [
            'label' => 'lang:app.logs.catalog.trivia.label',
            'description' => 'lang:app.logs.catalog.trivia.description',
            'actions' => [
                'action' => 'lang:app.logs.catalog.trivia.actions.action',
                'save_settings' => 'lang:app.logs.catalog.trivia.actions.save_settings',
                'question_create' => 'lang:app.logs.catalog.trivia.actions.question_create',
                'question_update' => 'lang:app.logs.catalog.trivia.actions.question_update',
                'question_delete' => 'lang:app.logs.catalog.trivia.actions.question_delete',
                'question_enable' => 'lang:app.logs.catalog.trivia.actions.question_enable',
                'question_disable' => 'lang:app.logs.catalog.trivia.actions.question_disable',
                'question_import' => 'lang:app.logs.catalog.trivia.actions.question_import',
                'question_export' => 'lang:app.logs.catalog.trivia.actions.question_export',
                'preset_save' => 'lang:app.logs.catalog.trivia.actions.preset_save',
                'preset_delete' => 'lang:app.logs.catalog.trivia.actions.preset_delete',
                'winners_clear' => 'lang:app.logs.catalog.trivia.actions.winners_clear',
            ],
        ],
        'auctionator' => [
            'label' => 'lang:app.logs.catalog.auctionator.label',
            'description' => 'lang:app.logs.catalog.auctionator.description',
            'actions' => [
                'config_save' => 'lang:app.logs.catalog.auctionator.actions.config_save',
                'item_policy' => 'lang:app.logs.catalog.auctionator.actions.item_policy',
                'command' => 'lang:app.logs.catalog.auctionator.actions.command',
                'listing' => 'lang:app.logs.catalog.auctionator.actions.listing',
                'power' => 'lang:app.logs.catalog.auctionator.actions.power',
                'buyout' => 'lang:app.logs.catalog.auctionator.actions.buyout',
                'max_item_level' => 'lang:app.logs.catalog.auctionator.actions.max_item_level',
            ],
        ],
        'raf' => [
            'label' => 'lang:app.logs.catalog.raf.label',
            'description' => 'lang:app.logs.catalog.raf.description',
            'actions' => [
                'bind' => 'lang:app.logs.catalog.raf.actions.bind',
                'force_bind' => 'lang:app.logs.catalog.raf.actions.force_bind',
                'unbind' => 'lang:app.logs.catalog.raf.actions.unbind',
                'comment' => 'lang:app.logs.catalog.raf.actions.comment',
            ],
        ],
        'aegis' => [
            'label' => 'lang:app.logs.catalog.aegis.label',
            'description' => 'lang:app.logs.catalog.aegis.description',
            'actions' => [
                'command' => 'lang:app.logs.catalog.aegis.actions.command',
            ],
        ],
        'supervisor' => [
            'label' => 'lang:app.logs.catalog.supervisor.label',
            'description' => 'lang:app.logs.catalog.supervisor.description',
            'actions' => [
                'ping' => 'lang:app.logs.catalog.supervisor.actions.ping',
                'start' => 'lang:app.logs.catalog.supervisor.actions.start',
                'stop' => 'lang:app.logs.catalog.supervisor.actions.stop',
                'restart' => 'lang:app.logs.catalog.supervisor.actions.restart',
            ],
        ],
        'soap_exec' => [
            'label' => 'lang:app.logs.catalog.soap_exec.label',
            'description' => 'lang:app.logs.catalog.soap_exec.description',
            'actions' => [
                'command' => 'lang:app.logs.catalog.soap_exec.actions.command',
            ],
        ],
        'realm' => [
            'label' => 'lang:app.logs.catalog.realm.label',
            'description' => 'lang:app.logs.catalog.realm.description',
            'actions' => [
                'switch' => 'lang:app.logs.catalog.realm.actions.switch',
            ],
        ],
        'logs' => [
            'label' => 'lang:app.logs.catalog.logs.label',
            'description' => 'lang:app.logs.catalog.logs.description',
            'actions' => [
                'export' => 'lang:app.logs.catalog.logs.actions.export',
                'purge' => 'lang:app.logs.catalog.logs.actions.purge',
            ],
        ],
        'setup' => [
            'label' => 'lang:app.logs.catalog.setup.label',
            'description' => 'lang:app.logs.catalog.setup.description',
            'actions' => [
                'install' => 'lang:app.logs.catalog.setup.actions.install',
                'reconfigure' => 'lang:app.logs.catalog.setup.actions.reconfigure',
            ],
        ],
        'system' => [
            'label' => 'lang:app.logs.catalog.system.label',
            'description' => 'lang:app.logs.catalog.system.description',
            'actions' => [
                'exception' => 'lang:app.logs.catalog.system.actions.exception',
                'warning' => 'lang:app.logs.catalog.system.actions.warning',
                'notice' => 'lang:app.logs.catalog.system.actions.notice',
                'prune' => 'lang:app.logs.catalog.system.actions.prune',
                'storage_fallback' => 'lang:app.logs.catalog.system.actions.storage_fallback',
            ],
        ],
    ],
];
