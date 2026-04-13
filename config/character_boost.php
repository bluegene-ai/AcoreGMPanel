<?php

return [
    // 角色职业 -> T4 Token 组
    'class_groups' => [
        1 => 'defender', // Warrior
        2 => 'champion', // Paladin
        3 => 'hero',     // Hunter
        4 => 'champion', // Rogue
        5 => 'defender', // Priest
        6 => null,       // Death Knight (not available in T4)
        7 => 'champion', // Shaman
        8 => 'hero',     // Mage
        9 => 'hero',     // Warlock
        11 => 'defender', // Druid
    ],

    // T4 Token 套装部位 -> (group => itemEntry)
    'token_sets' => [
        'helm' => ['hero' => 29759, 'champion' => 29760, 'defender' => 29761],
        'chest' => ['hero' => 29755, 'champion' => 29754, 'defender' => 29753],
        'gloves' => ['hero' => 29756, 'champion' => 29757, 'defender' => 29758],
        'shoulders' => ['hero' => 29762, 'champion' => 29763, 'defender' => 29764],
        'legs' => ['hero' => 29765, 'champion' => 29766, 'defender' => 29767],
    ],

    // T2 套装：职业 -> itemEntry 列表
    't2_sets' => [
        1 => [16959, 16960, 16961, 16962, 16963, 16964, 16965, 16966],
        2 => [16951, 16952, 16953, 16954, 16955, 16956, 16957, 16958],
        3 => [16935, 16936, 16937, 16938, 16939, 16940, 16941, 16942],
        4 => [16832, 16905, 16906, 16907, 16908, 16909, 16910, 16911],
        5 => [16919, 16920, 16921, 16922, 16923, 16924, 16925, 16926],
        6 => [],
        7 => [16943, 16944, 16945, 16946, 16947, 16948, 16949, 16950],
        8 => [16818, 16912, 16913, 16914, 16915, 16916, 16917, 16918],
        9 => [16927, 16928, 16929, 16930, 16931, 16932, 16933, 16934],
        11 => [16897, 16898, 16899, 16900, 16901, 16902, 16903, 16904],
    ],

    // 默认直升奖励：补给包与金币
    'default_rewards' => [
        'bag_item' => 21841,
        'bag_count' => 4,
        'gold_copper' => 5000000, // 500 gold
    ],
];
