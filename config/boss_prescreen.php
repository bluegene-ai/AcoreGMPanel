<?php

/**
 * 技能池预筛（SkillPrescreen）的规则参数与枚举口径。
 *
 * 阈值与白名单都在这里调，不改 PHP 代码。目标类型 / 属性位 / 效果 / 光环的枚举值
 * **只从核心头文件读**（不给兜底值：读不到就是环境没配好，预筛会显性失败而不是猜）。
 *
 * 两个路径属于部署相关值，不写进本文件（跟踪的配置只放通用默认）：
 *   BOSS_LUA_PATH          被检脚本 boss.lua；留空 = 预筛显性失败
 *   CORE_SHARED_DEFINES    核心 SharedDefines.h（TARGET_* / SPELL_ATTR0_* / SPELL_EFFECT_*）；留空 = 显性失败
 *   CORE_AURA_DEFINES      核心 SpellAuraDefines.h（SPELL_AURA_*）；留空 = 显性失败
 * 也可以写进被忽略的 config/generated/boss_prescreen.php（同名键覆盖本文件）。
 */
return [
    // 规则版本：改了阈值/白名单就要抬版本，它参与缓存键（DBC 数值表与结果缓存都会失效重算）
    'rule_version' => 'prescreen-v1',

    // 被检脚本（部署相关，见文件头）
    'boss_lua_path' => (string) (getenv('BOSS_LUA_PATH') ?: ''),

    // 核心头文件（部署相关，见文件头）：枚举真源，缺一个就显性失败
    'core_shared_defines' => (string) (getenv('CORE_SHARED_DEFINES') ?: ''),
    'core_aura_defines' => (string) (getenv('CORE_AURA_DEFINES') ?: ''),

    // 结果缓存文件（相对面板根目录）
    'store_path' => 'storage/cache/boss_skill_prescreen.json',

    'thresholds' => [
        // 射程上限（码）：超过即红（历史事故：>99yd 的技能会把整张地图拉进来）
        'max_range_yards' => 99,
        // 效果半径提示线（码）：达到即黄
        'warn_radius_yards' => 40,
        // 读条时长提示线（毫秒）：超过即黄
        'max_cast_ms' => 3000,
        // priority 允许区间
        'priority_min' => 1,
        'priority_max' => 8,
        /**
         * 0/0 射程不判红的 RangeIndex：SpellRange 1 = "Self Only"（自身 0 码），
         * 自身中心 AoE / 自身增益都用它，属正常内容。其它 RangeIndex 出现 0/0 才按
         * "无距离限制" 判红（doc §4 R3 的原意）。
         */
        'self_only_range_indexes' => [1],
    ],

    /**
     * target 允许值（R13）与目标类型白/黑名单（R5，按名字判断，值从核心头文件取）。
     *
     * allow_names / allow_prefixes：可以出现的隐式目标类型 —— 施法者自身、当前敌对目标、
     * 以及由"施法者/当前目标"派生的位置（TARGET_DEST_CASTER* / TARGET_DEST_TARGET_*），
     * 锥形敌对类按前缀放行。
     * deny_* 优先于 allow_*：友方/队伍/团队/宠物类一律红（Boss 没有队伍，这类目标会空转）。
     */
    'targets' => [
        'target_values' => ['victim', 'self'],
        'allow_names' => [
            'TARGET_UNIT_CASTER',
            'TARGET_UNIT_TARGET_ENEMY',
            'TARGET_UNIT_TARGET_ANY',
            'TARGET_UNIT_SRC_AREA_ENEMY',
            'TARGET_UNIT_DEST_AREA_ENEMY',
            'TARGET_DEST_TARGET_ENEMY',
            'TARGET_DEST_TARGET_ANY',
            'TARGET_DEST_TARGET_RANDOM',
            'TARGET_DEST_CASTER',
            'TARGET_DEST_CASTER_FRONT',
            'TARGET_DEST_CASTER_RANDOM',
            'TARGET_DEST_DYNOBJ_ENEMY',
            'TARGET_DEST_DB',
            'TARGET_DEST_DEST',
            'TARGET_SRC_CASTER',
        ],
        'allow_prefixes' => [
            'TARGET_UNIT_CONE_ENEMY',
            // 位置类：由施法者或当前目标派生的落点（前/后/左/右/随机）
            'TARGET_DEST_CASTER',
            'TARGET_DEST_TARGET_',
        ],
        'deny_names' => [
            'TARGET_UNIT_TARGET_PARTY',
            'TARGET_UNIT_TARGET_RAID',
            'TARGET_UNIT_TARGET_CHAINHEAL_ALLY',
            'TARGET_UNIT_CASTER_AREA_PARTY',
            'TARGET_UNIT_TARGET_MINIPET',
            'TARGET_UNIT_PET',
            'TARGET_UNIT_MASTER',
            'TARGET_UNIT_CHANNEL_TARGET',
        ],
        'deny_substrings' => ['_PARTY', '_RAID', '_ALLY', 'CHAINHEAL', 'MINIPET'],
    ],

    /**
     * 枚举名字（值从核心头文件读；这里只列"要用的名字"，方便对着 DBCStructure.h 复核）。
     * attributes/effects/auras 用于 R6 / R7。
     */
    'enum_names' => [
        'attributes' => [
            'SPELL_ATTR0_PASSIVE',
            'SPELL_ATTR0_ON_NEXT_SWING',
            'SPELL_ATTR0_ON_NEXT_SWING_NO_DAMAGE',
        ],
        'effects' => [
            // R7 黄：召唤 / 机关 / 传送类效果
            'SPELL_EFFECT_SUMMON',
            'SPELL_EFFECT_TRANS_DOOR',
            'SPELL_EFFECT_SUMMON_PET',
            'SPELL_EFFECT_SUMMON_OBJECT_WILD',
            'SPELL_EFFECT_SUMMON_OBJECT_SLOT1',
        ],
        'auras' => [
            // R7 黄：控制载具类光环
            'SPELL_AURA_CONTROL_VEHICLE',
        ],
    ],

    /**
     * 数值基线（doc §5 的实测值）：条目数与这里差得太多就提示"脚本结构可能变了"。
     * 结构变化本身不是红，只是要人复核解析器。
     */
    'baseline' => [
        'preset_count' => 10,
        'pool_entries' => 144,
        'opening_entries' => 30,
        'combo_chains' => 60,
        'combo_refs' => 180,
        'interrupt_entries' => 7,
        'phase_spells' => 2,
        'unique_spell_ids' => 93,
    ],

    // 世界库要查的三张表（R11）：列名以各表实际结构为准（spell_target_position 用 ID）
    'world_tables' => [
        'script' => ['table' => 'spell_script_names', 'column' => 'spell_id'],
        'required' => ['table' => 'spell_required', 'column' => 'spell_id'],
        'target_position' => ['table' => 'spell_target_position', 'column' => 'ID'],
    ],
];
