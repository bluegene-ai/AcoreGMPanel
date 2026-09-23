<?php
/**
 * File: config/trivia.php
 * Purpose: Configuration for the "Trivia reward" (聊天答题) module.
 *
 * 数据表由 Lua 脚本 TriviaReward.lua 在 custom_db_name 指定的库里自动建立（CREATE TABLE IF NOT EXISTS），
 * 面板只负责读写，不建表；表缺失时页面会给出提示。
 */

declare(strict_types=1);

return [
    // 必须与 TriviaReward.lua 里的 Config.dbName 一致（默认 ac_eluna，与 RAF / 活动 Boss 同库）
    'custom_db_name' => 'ac_eluna',

    'settings_table' => 'trivia_reward_settings',
    'questions_table' => 'trivia_reward_questions',
    'presets_table' => 'trivia_reward_presets',
    'winners_table' => 'trivia_reward_winners',

    // SOAP 单次执行超时（秒）：worldserver 卡顿时不要让页面等太久
    'soap' => [
        'timeout_connect' => 3,
        'timeout_total' => 8,
    ],

    'page_size' => 30,
    'page_size_options' => [20, 30, 50, 100],
    'winner_limit' => 30,

    // 内置频道 ID（解析自本服客户端 ChatChannels.dbc），与脚本内置对照表一致
    'channels' => [
        ['id' => 1, 'label' => '综合'],
        ['id' => 2, 'label' => '交易'],
        ['id' => 22, 'label' => '本地防务'],
        ['id' => 23, 'label' => '世界防务'],
        ['id' => 25, 'label' => '公会招募'],
        ['id' => 26, 'label' => '寻求组队'],
    ],

    // 选项标号预设（写入 option_labels，逗号分隔；脚本也支持不带逗号的 "甲乙丙丁"）
    'label_presets' => [
        ['value' => 'A,B,C,D', 'label' => 'A / B / C / D'],
        ['value' => '甲,乙,丙,丁', 'label' => '甲 / 乙 / 丙 / 丁'],
        ['value' => '1,2,3,4', 'label' => '1 / 2 / 3 / 4'],
        ['value' => '是,否', 'label' => '是 / 否'],
    ],

    // 选项排版预设（写入 option_format，两个 %s：标号、选项文本）
    'format_presets' => [
        ['value' => '%s) %s', 'label' => 'A) 选项文本'],
        ['value' => '%s、%s', 'label' => '甲、选项文本'],
        ['value' => '%s：%s', 'label' => '甲：选项文本'],
    ],

    'reward_modes' => ['question', 'pool'],

    // 数值上下限（控制器统一按这里收敛，防止面板写进离谱的值）
    'limits' => [
        'interval_seconds' => [60, 86400],
        'answer_seconds' => [10, 600],
        'remind_every_seconds' => [0, 600],
        'first_delay_seconds' => [0, 86400],
        'min_players_online' => [0, 1000],
        'min_level' => [1, 255],
        'attempts_per_player' => [0, 20],
        'min_gm_rank_for_command' => [0, 4],
        'sender_guid' => [0, 4294967295],
        'mail_stationery' => [0, 255],
        'item_link_locale' => [0, 8],
        'reward_money' => [0, 2147483647],
        'reward_item_count' => [1, 1000],
    ],
];
