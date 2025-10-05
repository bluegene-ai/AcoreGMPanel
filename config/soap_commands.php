<?php
/**
 * File: config/soap_commands.php
 * Purpose: Provides functionality for the config module.
 */

return [
    'metadata' => [
        'source' => 'https://www.azerothcore.org/wiki/gm-commands',
        'updated_at' => '2025-09-29',
    ],
    'categories' => [
        [
            'id' => 'general',
            'label' => 'lang:app.soap.wizard.catalog.categories.general.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.general.summary',
            'commands' => [
                [
                    'key' => 'server-info',
                    'name' => '.server info',
                    'description' => 'lang:app.soap.wizard.catalog.commands.server-info.description',
                    'template' => '.server info',
                    'arguments' => [],
                    'risk' => 'low',
                ],
                [
                    'key' => 'server-motd',
                    'name' => '.server motd',
                    'description' => 'lang:app.soap.wizard.catalog.commands.server-motd.description',
                    'template' => '.server motd {message?}',
                    'arguments' => [
                        [
                            'key' => 'message',
                            'label' => 'lang:app.soap.wizard.catalog.commands.server-motd.arguments.message.label',
                            'type' => 'textarea',
                            'required' => false,
                            'wrap' => 'quotes',
                            'placeholder' => 'lang:app.soap.wizard.catalog.commands.server-motd.arguments.message.placeholder',
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'announce-global',
                    'name' => '.announce',
                    'description' => 'lang:app.soap.wizard.catalog.commands.announce-global.description',
                    'template' => '.announce {message}',
                    'arguments' => [
                        [
                            'key' => 'message',
                            'label' => 'lang:app.soap.wizard.catalog.commands.announce-global.arguments.message.label',
                            'type' => 'textarea',
                            'required' => true,
                            'wrap' => 'quotes',
                            'placeholder' => 'lang:app.soap.wizard.catalog.commands.announce-global.arguments.message.placeholder',
                        ],
                    ],
                    'risk' => 'low',
                ],
                [
                    'key' => 'announce-name',
                    'name' => '.nameannounce',
                    'description' => 'lang:app.soap.wizard.catalog.commands.announce-name.description',
                    'template' => '.nameannounce {message}',
                    'arguments' => [
                        [
                            'key' => 'message',
                            'label' => 'lang:app.soap.wizard.catalog.commands.announce-name.arguments.message.label',
                            'type' => 'textarea',
                            'required' => true,
                            'wrap' => 'quotes',
                        ],
                    ],
                    'risk' => 'low',
                ],
                [
                    'key' => 'notify',
                    'name' => '.notify',
                    'description' => 'lang:app.soap.wizard.catalog.commands.notify.description',
                    'template' => '.notify {message}',
                    'arguments' => [
                        [
                            'key' => 'message',
                            'label' => 'lang:app.soap.wizard.catalog.commands.notify.arguments.message.label',
                            'type' => 'textarea',
                            'required' => true,
                            'wrap' => 'quotes',
                        ],
                    ],
                    'risk' => 'low',
                ],
                [
                    'key' => 'gm-visible',
                    'name' => '.gm visible',
                    'description' => 'lang:app.soap.wizard.catalog.commands.gm-visible.description',
                    'template' => '.gm visible {state}',
                    'arguments' => [
                        [
                            'key' => 'state',
                            'label' => 'lang:app.soap.wizard.catalog.commands.gm-visible.arguments.state.label',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                ['value' => 'on', 'label' => 'lang:app.soap.wizard.catalog.commands.gm-visible.arguments.state.options.on'],
                                ['value' => 'off', 'label' => 'lang:app.soap.wizard.catalog.commands.gm-visible.arguments.state.options.off'],
                            ],
                        ],
                    ],
                    'risk' => 'low',
                ],
            ],
        ],
        [
            'id' => 'account',
            'label' => 'lang:app.soap.wizard.catalog.categories.account.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.account.summary',
            'commands' => [
                [
                    'key' => 'account-set-gmlevel',
                    'name' => '.account set gmlevel',
                    'description' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.description',
                    'template' => '.account set gmlevel {account} {level} {realm?}',
                    'arguments' => [
                        [
                            'key' => 'account',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.account.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'level',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.level.label',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                ['value' => '0', 'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.level.options.0'],
                                ['value' => '1', 'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.level.options.1'],
                                ['value' => '2', 'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.level.options.2'],
                                ['value' => '3', 'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.level.options.3'],
                            ],
                        ],
                        [
                            'key' => 'realm',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.realm.label',
                            'type' => 'number',
                            'required' => false,
                            'placeholder' => 'lang:app.soap.wizard.catalog.commands.account-set-gmlevel.arguments.realm.placeholder',
                        ],
                    ],
                    'risk' => 'high',
                ],
                [
                    'key' => 'account-set-password',
                    'name' => '.account set password',
                    'description' => 'lang:app.soap.wizard.catalog.commands.account-set-password.description',
                    'template' => '.account set password {account} {password}',
                    'arguments' => [
                        [
                            'key' => 'account',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-set-password.arguments.account.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'password',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-set-password.arguments.password.label',
                            'type' => 'password',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'high',
                ],
                [
                    'key' => 'account-lock',
                    'name' => '.account lock account',
                    'description' => 'lang:app.soap.wizard.catalog.commands.account-lock.description',
                    'template' => '.account lock account {account} {state}',
                    'arguments' => [
                        [
                            'key' => 'account',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-lock.arguments.account.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'state',
                            'label' => 'lang:app.soap.wizard.catalog.commands.account-lock.arguments.state.label',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                ['value' => 'on', 'label' => 'lang:app.soap.wizard.catalog.commands.account-lock.arguments.state.options.on'],
                                ['value' => 'off', 'label' => 'lang:app.soap.wizard.catalog.commands.account-lock.arguments.state.options.off'],
                            ],
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'ban-account',
                    'name' => '.ban account',
                    'description' => 'lang:app.soap.wizard.catalog.commands.ban-account.description',
                    'template' => '.ban account {account} {duration} {reason?}',
                    'arguments' => [
                        [
                            'key' => 'account',
                            'label' => 'lang:app.soap.wizard.catalog.commands.ban-account.arguments.account.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'duration',
                            'label' => 'lang:app.soap.wizard.catalog.commands.ban-account.arguments.duration.label',
                            'type' => 'text',
                            'required' => true,
                            'placeholder' => 'lang:app.soap.wizard.catalog.commands.ban-account.arguments.duration.placeholder',
                        ],
                        [
                            'key' => 'reason',
                            'label' => 'lang:app.soap.wizard.catalog.commands.ban-account.arguments.reason.label',
                            'type' => 'text',
                            'required' => false,
                            'wrap' => 'quotes',
                        ],
                    ],
                    'risk' => 'high',
                ],
                [
                    'key' => 'unban-account',
                    'name' => '.unban account',
                    'description' => 'lang:app.soap.wizard.catalog.commands.unban-account.description',
                    'template' => '.unban account {account}',
                    'arguments' => [
                        [
                            'key' => 'account',
                            'label' => 'lang:app.soap.wizard.catalog.commands.unban-account.arguments.account.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
            ],
        ],
        [
            'id' => 'character',
            'label' => 'lang:app.soap.wizard.catalog.categories.character.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.character.summary',
            'commands' => [
                [
                    'key' => 'character-level',
                    'name' => '.character level',
                    'description' => 'lang:app.soap.wizard.catalog.commands.character-level.description',
                    'template' => '.character level {name} {level}',
                    'arguments' => [
                        [
                            'key' => 'name',
                            'label' => 'lang:app.soap.wizard.catalog.commands.character-level.arguments.name.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'level',
                            'label' => 'lang:app.soap.wizard.catalog.commands.character-level.arguments.level.label',
                            'type' => 'number',
                            'required' => true,
                            'min' => 1,
                            'max' => 80,
                        ],
                    ],
                    'risk' => 'high',
                ],
                [
                    'key' => 'character-rename',
                    'name' => '.character rename',
                    'description' => 'lang:app.soap.wizard.catalog.commands.character-rename.description',
                    'template' => '.character rename {name}',
                    'arguments' => [
                        [
                            'key' => 'name',
                            'label' => 'lang:app.soap.wizard.catalog.commands.character-rename.arguments.name.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'character-customize',
                    'name' => '.character customize',
                    'description' => 'lang:app.soap.wizard.catalog.commands.character-customize.description',
                    'template' => '.character customize {name}',
                    'arguments' => [
                        [
                            'key' => 'name',
                            'label' => 'lang:app.soap.wizard.catalog.commands.character-customize.arguments.name.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'character-revive',
                    'name' => '.character revive',
                    'description' => 'lang:app.soap.wizard.catalog.commands.character-revive.description',
                    'template' => '.character revive {name}',
                    'arguments' => [
                        [
                            'key' => 'name',
                            'label' => 'lang:app.soap.wizard.catalog.commands.character-revive.arguments.name.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'character-lookup',
                    'name' => '.lookup player',
                    'description' => 'lang:app.soap.wizard.catalog.commands.character-lookup.description',
                    'template' => '.lookup player {pattern}',
                    'arguments' => [
                        [
                            'key' => 'pattern',
                            'label' => 'lang:app.soap.wizard.catalog.commands.character-lookup.arguments.pattern.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'low',
                ],
            ],
        ],
        [
            'id' => 'teleport',
            'label' => 'lang:app.soap.wizard.catalog.categories.teleport.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.teleport.summary',
            'commands' => [
                [
                    'key' => 'tele-name',
                    'name' => '.tele',
                    'description' => 'lang:app.soap.wizard.catalog.commands.tele-name.description',
                    'template' => '.tele {location}',
                    'arguments' => [
                        [
                            'key' => 'location',
                            'label' => 'lang:app.soap.wizard.catalog.commands.tele-name.arguments.location.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'tele-worldport',
                    'name' => '.worldport',
                    'description' => 'lang:app.soap.wizard.catalog.commands.tele-worldport.description',
                    'template' => '.worldport {map} {x} {y} {z} {o?}',
                    'arguments' => [
                        [
                            'key' => 'map',
                            'label' => 'lang:app.soap.wizard.catalog.commands.tele-worldport.arguments.map.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                        [
                            'key' => 'x',
                            'label' => 'lang:app.soap.wizard.catalog.commands.tele-worldport.arguments.x.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'y',
                            'label' => 'lang:app.soap.wizard.catalog.commands.tele-worldport.arguments.y.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'z',
                            'label' => 'lang:app.soap.wizard.catalog.commands.tele-worldport.arguments.z.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                        [
                            'key' => 'o',
                            'label' => 'lang:app.soap.wizard.catalog.commands.tele-worldport.arguments.o.label',
                            'type' => 'text',
                            'required' => false,
                        ],
                    ],
                    'risk' => 'high',
                    'notes' => ['lang:app.soap.wizard.catalog.commands.tele-worldport.notes.ensure_valid'],
                ],
                [
                    'key' => 'go-creature',
                    'name' => '.go creature',
                    'description' => 'lang:app.soap.wizard.catalog.commands.go-creature.description',
                    'template' => '.go creature {guid}',
                    'arguments' => [
                        [
                            'key' => 'guid',
                            'label' => 'lang:app.soap.wizard.catalog.commands.go-creature.arguments.guid.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'go-object',
                    'name' => '.go object',
                    'description' => 'lang:app.soap.wizard.catalog.commands.go-object.description',
                    'template' => '.go object {guid}',
                    'arguments' => [
                        [
                            'key' => 'guid',
                            'label' => 'lang:app.soap.wizard.catalog.commands.go-object.arguments.guid.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                ],
                [
                    'key' => 'summon-player',
                    'name' => '.tele name',
                    'description' => 'lang:app.soap.wizard.catalog.commands.summon-player.description',
                    'template' => '.tele name {player}',
                    'arguments' => [
                        [
                            'key' => 'player',
                            'label' => 'lang:app.soap.wizard.catalog.commands.summon-player.arguments.player.label',
                            'type' => 'text',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'notes' => ['lang:app.soap.wizard.catalog.commands.summon-player.notes.require_online'],
                ],
            ],
        ],
        [
            'id' => 'item',
            'label' => 'lang:app.soap.wizard.catalog.categories.item.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.item.summary',
            'commands' => [
                [
                    'key' => 'additem',
                    'name' => '.additem',
                    'description' => 'lang:app.soap.wizard.catalog.commands.additem.description',
                    'template' => '.additem {item} {count?}',
                    'arguments' => [
                        [
                            'key' => 'item',
                            'label' => 'lang:app.soap.wizard.catalog.commands.additem.arguments.item.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                        [
                            'key' => 'count',
                            'label' => 'lang:app.soap.wizard.catalog.commands.additem.arguments.count.label',
                            'type' => 'number',
                            'required' => false,
                            'default' => 1,
                        ],
                    ],
                    'risk' => 'high',
                    'requires_target' => true,
                ],
                [
                    'key' => 'additemset',
                    'name' => '.additemset',
                    'description' => 'lang:app.soap.wizard.catalog.commands.additemset.description',
                    'template' => '.additemset {itemset}',
                    'arguments' => [
                        [
                            'key' => 'itemset',
                            'label' => 'lang:app.soap.wizard.catalog.commands.additemset.arguments.itemset.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'high',
                    'requires_target' => true,
                ],
                [
                    'key' => 'removeitem',
                    'name' => '.removeitem',
                    'description' => 'lang:app.soap.wizard.catalog.commands.removeitem.description',
                    'template' => '.removeitem {item} {count?}',
                    'arguments' => [
                        [
                            'key' => 'item',
                            'label' => 'lang:app.soap.wizard.catalog.commands.removeitem.arguments.item.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                        [
                            'key' => 'count',
                            'label' => 'lang:app.soap.wizard.catalog.commands.removeitem.arguments.count.label',
                            'type' => 'number',
                            'required' => false,
                        ],
                    ],
                    'risk' => 'high',
                    'requires_target' => true,
                ],
            ],
        ],
        [
            'id' => 'spell',
            'label' => 'lang:app.soap.wizard.catalog.categories.spell.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.spell.summary',
            'commands' => [
                [
                    'key' => 'learn-spell',
                    'name' => '.learn',
                    'description' => 'lang:app.soap.wizard.catalog.commands.learn-spell.description',
                    'template' => '.learn {spell}',
                    'arguments' => [
                        [
                            'key' => 'spell',
                            'label' => 'lang:app.soap.wizard.catalog.commands.learn-spell.arguments.spell.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
                [
                    'key' => 'unlearn-spell',
                    'name' => '.unlearn',
                    'description' => 'lang:app.soap.wizard.catalog.commands.unlearn-spell.description',
                    'template' => '.unlearn {spell}',
                    'arguments' => [
                        [
                            'key' => 'spell',
                            'label' => 'lang:app.soap.wizard.catalog.commands.unlearn-spell.arguments.spell.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
                [
                    'key' => 'talent-reset',
                    'name' => '.reset talents',
                    'description' => 'lang:app.soap.wizard.catalog.commands.talent-reset.description',
                    'template' => '.reset talents',
                    'arguments' => [],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
            ],
        ],
        [
            'id' => 'quest',
            'label' => 'lang:app.soap.wizard.catalog.categories.quest.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.quest.summary',
            'commands' => [
                [
                    'key' => 'quest-add',
                    'name' => '.quest add',
                    'description' => 'lang:app.soap.wizard.catalog.commands.quest-add.description',
                    'template' => '.quest add {quest}',
                    'arguments' => [
                        [
                            'key' => 'quest',
                            'label' => 'lang:app.soap.wizard.catalog.commands.quest-add.arguments.quest.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
                [
                    'key' => 'quest-complete',
                    'name' => '.quest complete',
                    'description' => 'lang:app.soap.wizard.catalog.commands.quest-complete.description',
                    'template' => '.quest complete {quest}',
                    'arguments' => [
                        [
                            'key' => 'quest',
                            'label' => 'lang:app.soap.wizard.catalog.commands.quest-complete.arguments.quest.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
                [
                    'key' => 'quest-remove',
                    'name' => '.quest remove',
                    'description' => 'lang:app.soap.wizard.catalog.commands.quest-remove.description',
                    'template' => '.quest remove {quest}',
                    'arguments' => [
                        [
                            'key' => 'quest',
                            'label' => 'lang:app.soap.wizard.catalog.commands.quest-remove.arguments.quest.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
            ],
        ],
        [
            'id' => 'misc',
            'label' => 'lang:app.soap.wizard.catalog.categories.misc.label',
            'summary' => 'lang:app.soap.wizard.catalog.categories.misc.summary',
            'commands' => [
                [
                    'key' => 'morph',
                    'name' => '.morph',
                    'description' => 'lang:app.soap.wizard.catalog.commands.morph.description',
                    'template' => '.morph {display}',
                    'arguments' => [
                        [
                            'key' => 'display',
                            'label' => 'lang:app.soap.wizard.catalog.commands.morph.arguments.display.label',
                            'type' => 'number',
                            'required' => true,
                        ],
                    ],
                    'risk' => 'medium',
                    'requires_target' => true,
                ],
                [
                    'key' => 'demorph',
                    'name' => '.demorph',
                    'description' => 'lang:app.soap.wizard.catalog.commands.demorph.description',
                    'template' => '.demorph',
                    'arguments' => [],
                    'risk' => 'low',
                    'requires_target' => true,
                ],
                [
                    'key' => 'modify-money',
                    'name' => '.modify money',
                    'description' => 'lang:app.soap.wizard.catalog.commands.modify-money.description',
                    'template' => '.modify money {amount}',
                    'arguments' => [
                        [
                            'key' => 'amount',
                            'label' => 'lang:app.soap.wizard.catalog.commands.modify-money.arguments.amount.label',
                            'type' => 'text',
                            'required' => true,
                            'placeholder' => 'lang:app.soap.wizard.catalog.commands.modify-money.arguments.amount.placeholder',
                        ],
                    ],
                    'risk' => 'high',
                    'requires_target' => true,
                ],
                [
                    'key' => 'modify-speed',
                    'name' => '.modify speed all',
                    'description' => 'lang:app.soap.wizard.catalog.commands.modify-speed.description',
                    'template' => '.modify speed all {multiplier}',
                    'arguments' => [
                        [
                            'key' => 'multiplier',
                            'label' => 'lang:app.soap.wizard.catalog.commands.modify-speed.arguments.multiplier.label',
                            'type' => 'text',
                            'required' => true,
                            'placeholder' => 'lang:app.soap.wizard.catalog.commands.modify-speed.arguments.multiplier.placeholder',
                        ],
                    ],
                    'risk' => 'high',
                    'requires_target' => true,
                ],
            ],
        ],
    ],
];

