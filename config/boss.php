<?php

return [
    'custom_db_name' => 'ac_eluna',
    'runtime_key' => 'current',
    'config_table' => 'boss_activity_config',
    'decimal_scale' => 100,
    'event_limit' => 18,
    'contributor_limit' => 18,

    // 多区（多个 realm 共用一套 auth）：每个区各自跑一份 worldserver 与一份 boss.lua，
    // 活动 Boss 的配置 / 运行态 / 事件 / 贡献都按区独立。绑定关系由 server_overrides
    // 描述（键 = config/generated/servers.php 里的 server 索引）。
    //
    // 这个列表 = **已经部署了 boss.lua 的区**；不在列表内的区服：
    // - dashboard 追加 critical warning（"本区未部署"）
    // - apiAction / apiConfigSave 直接返回 422，不发 SOAP 命令
    // 留空 = 所有已配置区都支持。
    //
    // 具体区的索引 / 库名属于**部署信息**，请写在 config/generated/boss.php 里
    // （该文件不入库，git pull 不会覆盖）。例：
    //   return ['supported_server_ids' => [0, 1],
    //           'server_overrides' => [0 => ['custom_db_name' => '<realm_a_db>']]];
    'supported_server_ids' => [],

    // 每个区一条：**runtime_key 就是该区 boss.lua §2 的 key**（BOSS_RUNTIME_KEY /
    // BOSS_CONFIG_KEY，两行必须相同）。多区共用同一个库，靠这个 key 分租四张表：
    //   boss_activity_config / _ext / _runtime  → 主键就是 state_key
    //   boss_activity_events / _contributors    → state_key 列（老库由 boss.lua 自动补列+索引）
    // 所以 custom_db_name 通常各区都一样（共用库），真正必须**每区不同**的是 runtime_key；
    // 两个区用同一个 key 就是共用同一份配置/运行态/事件。
    // 留空 = 所有区共用顶层的 custom_db_name / runtime_key（单区部署就是这样）。
    'server_overrides' => [],

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

    // 二级 Tab → 字段组（组名与 boss.lua §3 配置区的分组一致）
    'ext_tabs' => [
        'yells' => ['yells'],
        'taunts' => ['taunts'],
        'ai' => ['ai', 'phase'],
        'patrol' => ['patrol', 'minion'],
        'support' => ['helper', 'class', 'tier'],
    ],

    // 扩展配置字段 schema（= boss.lua §3 BOSS_CONFIG_SCHEMA_EXT 的镜像，列名必须一致）
    // kind: text 单行 / lines 多行逐条 / keyedlines 多行"键=值" / keyedintlist 多行"键=ID,ID"
    //       intlist 逗号分隔 ID / int 整数（min/max 边界与 Lua 描述表一致）/ bool 开关
    'ext_fields' => [
        'yells' => [
            ['name' => 'boss_spawn_yell', 'kind' => 'text', 'maxlength' => 255, 'hint' => true],
            ['name' => 'boss_enter_combat_yell', 'kind' => 'text', 'maxlength' => 255],
            ['name' => 'ally_spawn_yell', 'kind' => 'text', 'maxlength' => 255],
            ['name' => 'boss_respawn_yell', 'kind' => 'text', 'maxlength' => 255],
            ['name' => 'boss_gm_spawn_yell', 'kind' => 'text', 'maxlength' => 255],
        ],
        'taunts' => [
            ['name' => 'taunt_cooldown_seconds', 'kind' => 'int', 'min' => 1, 'max' => 3600, 'hint' => true],
            ['name' => 'random_taunt_chance', 'kind' => 'int', 'min' => 0, 'max' => 100, 'hint' => true],
            ['name' => 'taunt_phase2_yells_text', 'kind' => 'lines', 'rows' => 4, 'hint' => true],
            ['name' => 'taunt_phase3_yells_text', 'kind' => 'lines', 'rows' => 4],
            ['name' => 'taunt_critical_hp_yells_text', 'kind' => 'lines', 'rows' => 3],
            ['name' => 'taunt_skill_cast_yells_text', 'kind' => 'keyedlines', 'rows' => 6, 'hint' => true],
            ['name' => 'taunt_target_switch_yells_text', 'kind' => 'lines', 'rows' => 4],
            ['name' => 'taunt_interrupt_yells_text', 'kind' => 'lines', 'rows' => 4],
            ['name' => 'taunt_kill_yells_text', 'kind' => 'lines', 'rows' => 4],
            ['name' => 'taunt_low_hp_yells_text', 'kind' => 'lines', 'rows' => 4],
            ['name' => 'taunt_healer_kill_yells_text', 'kind' => 'lines', 'rows' => 3],
            ['name' => 'taunt_summon_minion_yells_text', 'kind' => 'lines', 'rows' => 3],
            ['name' => 'taunt_combo_yells_text', 'kind' => 'keyedlines', 'rows' => 6, 'hint' => true],
            ['name' => 'taunt_long_combat_yells_text', 'kind' => 'lines', 'rows' => 3],
        ],
        'ai' => [
            ['name' => 'ai_update_interval_ms', 'kind' => 'int', 'min' => 200, 'max' => 60000, 'hint' => true],
        ],
        'phase' => [
            ['name' => 'phase2_hp_threshold', 'kind' => 'int', 'min' => 1, 'max' => 99, 'hint' => true],
            ['name' => 'phase3_hp_threshold', 'kind' => 'int', 'min' => 1, 'max' => 99],
            ['name' => 'critical_hp_threshold', 'kind' => 'int', 'min' => 1, 'max' => 99],
            ['name' => 'low_hp_taunt_threshold', 'kind' => 'int', 'min' => 1, 'max' => 100],
            ['name' => 'low_hp_taunt_cooldown_ms', 'kind' => 'int', 'min' => 1000, 'max' => 600000],
            ['name' => 'long_combat_taunt_interval_ms', 'kind' => 'int', 'min' => 5000, 'max' => 3600000],
            ['name' => 'target_reeval_loops', 'kind' => 'int', 'min' => 1, 'max' => 100],
            ['name' => 'phase2_summon_count_min', 'kind' => 'int', 'min' => 0, 'max' => 20],
            ['name' => 'phase2_summon_count_max', 'kind' => 'int', 'min' => 0, 'max' => 20],
            ['name' => 'phase3_summon_count', 'kind' => 'int', 'min' => 0, 'max' => 20],
            ['name' => 'phase2_spell_id', 'kind' => 'int', 'min' => 0, 'max' => 2000000, 'hint' => true],
            ['name' => 'phase3_spell_id', 'kind' => 'int', 'min' => 0, 'max' => 2000000, 'hint' => true],
        ],
        'patrol' => [
            ['name' => 'patrol_enabled', 'kind' => 'bool', 'hint' => true],
            ['name' => 'patrol_radius', 'kind' => 'int', 'min' => 0, 'max' => 1000],
            ['name' => 'patrol_leash_radius', 'kind' => 'int', 'min' => 0, 'max' => 2000],
            ['name' => 'patrol_interval_ms', 'kind' => 'int', 'min' => 500, 'max' => 3600000],
        ],
        'minion' => [
            ['name' => 'minion_ai_enabled', 'kind' => 'bool', 'hint' => true],
            ['name' => 'minion_ai_interval_ms', 'kind' => 'int', 'min' => 200, 'max' => 60000],
            ['name' => 'minion_target_range', 'kind' => 'int', 'min' => 1, 'max' => 200],
        ],
        'helper' => [
            ['name' => 'helper_entries_text', 'kind' => 'intlist', 'keep_default_when_empty' => true, 'hint' => true],
            ['name' => 'ally_helper_entry', 'kind' => 'int', 'min' => 1, 'max' => 2000000, 'hint' => true],
        ],
        'class' => [
            ['name' => 'class_types_text', 'kind' => 'keyedlines', 'rows' => 4, 'keep_default_when_empty' => true, 'hint' => true],
            ['name' => 'class_reward_items_text', 'kind' => 'keyedintlist', 'rows' => 6, 'keep_default_when_empty' => true, 'hint' => true],
        ],
        'tier' => [
            ['name' => 'managed_tier_entries_text', 'kind' => 'intlist', 'keep_default_when_empty' => true, 'hint' => true],
        ],
    ],

    // 出厂默认值（= boss.lua §3 的默认值；仅在 ext 表/行缺失时用于展示）
    'ext_defaults' => [
        'boss_spawn_yell' => ' 让 {BOSS_NAME} 来打爆这个垃圾服务器！',
        'boss_enter_combat_yell' => '可恶，竟敢对我动手！',
        'ally_spawn_yell' => '保卫净土的时候到了！援护勇士，击倒这恶徒！',
        'boss_respawn_yell' => '{BOSS_NAME}再临！',
        'boss_gm_spawn_yell' => '小虫子们，来战！',
        'taunt_cooldown_seconds' => 8,
        'random_taunt_chance' => 15,
        'taunt_phase2_yells_text' => "哈哈哈，热身结束了！\n你们就这点本事吗？太让我失望了！\n现在，游戏正式开始！\n不错嘛，值得我认真一点！",
        'taunt_phase3_yells_text' => "你们激怒我了！准备受死吧！\n这是你们逼我的！毁灭吧！\n我的力量...正在觉醒！\n颤抖吧，凡人！感受真正的恐惧！",
        'taunt_critical_hp_yells_text' => "不...不可能！\n该死...我不会输给你们这些蝼蚁！\n就算死，我也要拉个垫背的！",
        'taunt_skill_cast_yells_text' => "冰冻之地=脚下结冰了，快动！\n冰焰=冰与火的轨迹，会把你们切开！\n冰霜斩击=灼烧你的灵魂！\n冰霜新星=冻在原地！\n冰霜炸弹=碎冰穿心！\n冰霜箭雨=寒霜会覆盖你们所有人！\n剧毒废料=废料漫开了，别往里踩！\n剧毒新星=毒雾会淹没你们！\n吞噬烈焰=火舌舔地！\n哨兵震爆=法术还没读完？先吃下这一下！\n寒冰巨弹=脚下留神！\n岩石碎片=碎石会自己找上你们！\n恐惧尖啸=尖叫会撕开你们的阵型！\n战车冲撞=撞翻你们！\n无意义之触=你的存在，连威胁都算不上！\n无面者印记=被标记的人，离队友远一点！\n暗影冲击=黑暗正从天上砸下来！\n暗影陷阱=别站那儿！\n死亡凋零=死亡会从你们脚下蔓延！\n死亡符文=别踩符文！\n毒液箭=这一箭，带毒！\n灵魂风暴=黑暗膨胀！\n灼烧吐息=呼吸之间，尽是焦土！\n灼烧烈焰=烈焰会把你们的法术和护甲一起烧穿！\n烈焰余烬=脚下的火，可不会等你！\n烈焰升腾=连环轰炸，享受吧！\n烈焰喷涌=烈焰吞噬一切！\n熔化护甲=你的护甲像纸一样！\n白茫=看不见路？那就死在风雪里！\n碎石轰击=石屑乱飞！\n穿刺=这一击，穿心！\n穿刺顺劈=近身就是找死！\n践踏=站稳了，地面要塌了！\n软泥抛掷=接住这团烂东西吧！\n闪电新星=别站这么近，统统导电！\n闪电链=电流串起你们！\n陨星拳=拳头落下时，别怪我没提醒！\n音波尖啸=奥能爆裂！\n骇人咆哮=在恐惧里四散奔逃吧！\n骨刃分劈=靠近我的人，全都一起受死！\n黑暗奔涌=黑暗在我体内暴涨，你们挡不住！",
        'taunt_target_switch_yells_text' => "{PLAYER_NAME}，下一个就是你了！\n{PLAYER_NAME}，你以为躲得掉吗？\n{CLASS}，让我看看你的本事！\n嘿，{PLAYER_NAME}，来陪我玩玩！\n换个人欺负一下，就你了{PLAYER_NAME}！",
        'taunt_interrupt_yells_text' => "读条被打断的感觉如何，{PLAYER_NAME}？\n想施法？门都没有！\n你的技能CD了，我的可没有！\n打断成功！这就是职业素养！",
        'taunt_kill_yells_text' => "{PLAYER_NAME}，太弱了！\n下一个！\n这就是挑战我的下场！\n{CLASS}也不过如此嘛！\n灵魂归我了，{PLAYER_NAME}！\n又解决一个，还有谁？",
        'taunt_low_hp_yells_text' => "{PLAYER_NAME}，你快不行了，放弃吧！\n血量这么低还敢站在我面前？\n{PLAYER_NAME}，需要我叫救护车吗？\n再补一刀就死了，真可怜！",
        'taunt_healer_kill_yells_text' => "治疗死了，你们还能撑多久？\n没奶了，等死吧你们！\n第一个杀治疗，这是常识！",
        'taunt_summon_minion_yells_text' => "我的仆从们，上！\n以多欺少？不，这叫战术！\n小家伙们，陪他们玩玩！",
        'taunt_combo_yells_text' => "冰雷点杀=冻住你，再劈碎你！\n减速爆发=减速，然后毁灭！\n反治疗链=治疗？我专治各种治疗！\n墓地封锁=地上、天上、前面，全是死路！\n寒毒压溃=又冷又毒，你们撑不住的！\n恐惧清场=跑吧，跑到尽头也是死！\n控制链=别想跑！\n毒刃收口=挂上毒，再慢慢收割！\n毒雾驱散=散开？毒雾会替我追上你们！\n灰烬逼走=落脚点？我全给你们烧掉！\n烈拳处决=挨过这拳，再谈活命！\n焚场风暴=全场着火，看你们怎么躲！\n爆发链=见识一下真正的力量！\n猎杀终曲=逃得再远，也只是最后一段路！\n白茫封场=风雪一起落下，谁都别想稳站！\n眩晕链=动不了了吧？\n破法齐射=法师们，抬头看看是谁在猎杀你们！\n碎阵压锋=先碎掉你们前排，再碾过去！\n腐蚀点杀=标记已经落下，你逃不掉！\n轰炸终曲=最后这轮轰炸，把你们全部埋掉！\n追击链=风筝我？做梦！\n重压处决=跪下，然后去死！\n雷岩合围=雷霆和山岩，一起压垮你们！\n黑潮封咏=黑潮已起，谁都别想完整读完一个法术！",
        'taunt_long_combat_yells_text' => "你们是在给我挠痒痒吗？\n战斗拖得越久，你们越没胜算！\n我的耐心是有限的！",
        'ai_update_interval_ms' => 1500,
        'phase2_hp_threshold' => 70,
        'phase3_hp_threshold' => 20,
        'critical_hp_threshold' => 10,
        'low_hp_taunt_threshold' => 30,
        'low_hp_taunt_cooldown_ms' => 20000,
        'long_combat_taunt_interval_ms' => 60000,
        'target_reeval_loops' => 3,
        'phase2_summon_count_min' => 1,
        'phase2_summon_count_max' => 2,
        'phase3_summon_count' => 2,
        'phase2_spell_id' => 1044,
        'phase3_spell_id' => 8599,
        'patrol_enabled' => 1,
        'patrol_radius' => 50,
        'patrol_leash_radius' => 100,
        'patrol_interval_ms' => 9000,
        'minion_ai_enabled' => 1,
        'minion_ai_interval_ms' => 1800,
        'minion_target_range' => 40,
        'helper_entries_text' => '16244,15976,16018,16165',
        'ally_helper_entry' => 20977,
        'class_types_text' => "1=melee\n11=healer\n2=healer\n3=ranged\n4=melee\n5=healer\n6=melee\n7=healer\n8=ranged\n9=ranged",
        'class_reward_items_text' => "1=40611,40614,40617,40620,40623,40256,40371,39257,40431,40257,40372\n2=40622,40619,40616,40613,40610,40256,40371,39257,40431,40257,40372,40258,40382,39299\n3=40611,40614,40617,40620,40623,40256,40371,39257,40431\n4=40624,40621,40618,40615,40612,40256,40371,39257,40431\n5=40622,40619,40616,40613,40610,40255,40373,40432,40258,40382,39299\n6=40624,40621,40618,40615,40612,40256,40371,39257,40431,40257,40372\n7=40611,40614,40617,40620,40623,40255,40373,40432,40256,40371,39257,40431,40258,40382,39299\n8=40624,40621,40618,40615,40612,40255,40373,40432,39299\n9=40622,40619,40616,40613,40610,40255,40373,40432,39299\n11=40624,40621,40618,40615,40612,40255,40373,40432,40256,40371,39257,40431,40257,40372,40258,40382,39299",
        'managed_tier_entries_text' => '190090,190091,190092,190093',
    ],
];