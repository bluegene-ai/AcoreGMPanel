<?php

return [
    'custom_db_name' => 'ac_eluna',
    'runtime_key' => 'current',
    'config_table' => 'boss_activity_config',
    'decimal_scale' => 100,
    'event_limit' => 18,
    'contributor_limit' => 18,

    // boss.lua 只部署在 80 区。这里的值是 ServerContext::currentId() 的取值
    // （即 config/generated/servers.php 的 server 索引），不在列表内的区服：
    // - dashboard 追加 critical warning
    // - apiAction / apiConfigSave 直接返回 422，不发 SOAP 命令
    'supported_server_ids' => [1],

    // 每个区服可覆盖全局的 ac_eluna 库名 / 运行时 state_key。
    // 目前仅 80 区部署 boss.lua，且沿用全局默认值。
    'server_overrides' => [
        1 => [
            'custom_db_name' => 'ac_eluna',
            'runtime_key' => 'current',
        ],
    ],

    // 难度档位（= ac_eluna.boss_activity_config.boss_entry）。
    // 190090–190093 是 acore_world80 上的活动 Boss 专用模板，AIName 为空、
    // 无 smart_scripts、无掉落；实际强度由模板的 HealthModifier/DamageModifier 决定。
    'tiers' => [
        190090 => [
            'key' => 'entry',
            'health_modifier' => 0.21,
            'damage_modifier' => 1.0,
        ],
        190091 => [
            'key' => 'standard',
            'health_modifier' => 0.60,
            'damage_modifier' => 2.0,
        ],
        190092 => [
            'key' => 'hard',
            'health_modifier' => 1.45,
            'damage_modifier' => 4.0,
        ],
        190093 => [
            'key' => 'raid',
            'health_modifier' => 3.60,
            'damage_modifier' => 7.0,
        ],
    ],

    // creature_classlevelstats(level=83, class=1).basehp2
    'tier_base_hp' => 13945,

    'default_tier_entry' => 190090,
    'preset_values' => [
        'storm_siege',
        'ember_storm',
        'frost_whiteout',
        'venom_pursuit',
        'grave_bombard',
        'spellbreak_bulwark',
    ],
    'difficulty_values' => [
        'easy',
        'standard',
        'hard',
        'raid',
    ],
    'defaults' => [
        'boss_entry' => 190090,
        'boss_name' => '净土年兽',
        'boss_level' => 83,
        'boss_scale_scaled' => 500,
        'boss_health_multiplier_scaled' => 2000,
        'boss_auras_text' => '21562,1126,467,20217',
        'ally_level' => 20,
        'ally_health_multiplier_scaled' => 150,
        'respawn_time_minutes' => 10,
        'minion_count_min' => 1,
        'minion_count_max' => 2,
        'skill_preset' => 'storm_siege',
        'skill_difficulty' => 'standard',
        'guaranteed_reward_enabled' => 1,
        'guaranteed_reward_notify' => 1,
        'max_random_reward_players' => 3,
        'class_reward_chance' => 60,
        'formula_reward_chance' => 10,
        'mount_reward_chance' => 15,
        'random_reward_mode' => 'weighted',
        'participation_range' => 80,
        'damage_weight' => 100,
        'healing_weight' => 80,
        'threat_weight' => 35,
        'presence_weight' => 10,
        'kill_weight' => 3,
        'guaranteed_item_id' => 40753,
        'guaranteed_item_count' => 2,
        'gold_min_copper' => 30000,
        'gold_max_copper' => 50000,
        'reward_items_text' => '38082,41600,51809,34067',
        'reward_formulas_text' => '45059,44491',
        'reward_mounts_text' => '32768,30480,13335,37719,49282,49290,19872,33977,33809,37828,43963,54068,33183,33189,35513,43964,19902,43963,46109,50250,49286,30609,54860,37012',
        'spawn_points_text' => "571,4353.573,-4411.8877,151.3909\n"
            . "571,1246.5499,-4311.5073,144.944\n"
            . "571,8093.9595,2827.9702,553.28033\n"
            . "571,6689.081,500.4722,401.2109\n"
            . "571,2975.7952,5373.769,62.121082\n"
            . "571,6005.9688,5612.9023,-71.26319\n"
            . "571,8355.781,-44.54596,815.31604",
    ],
];