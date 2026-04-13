<?php
return [
    'page_title' => '物品归属',
    'search' => [
        'title' => '物品查找',
        'subtitle' => '通过名称或物品ID查询哪些角色持有该物品。',
        'keyword_label' => '关键词',
        'keyword_placeholder' => '名称或物品ID',
        'submit' => '搜索',
        'status' => [
            'loading' => '加载中…',
        ],
        'results' => [
            'entry' => '物品ID',
            'name' => '名称',
            'quality' => '品质',
            'stackable' => '堆叠上限',
            'actions' => '操作',
            'placeholder' => '搜索结果将显示在此处。',
            'view' => '查看归属',
            'empty' => '未找到物品',
        ],
        'validation' => [
            'empty' => '请输入关键词',
        ],
        'error' => [
            'failed' => '搜索失败',
        ],
    ],
    'results' => [
        'title_empty' => '选择一个物品',
        'subtitle_empty' => '搜索物品以查看归属详情。',
        'title_loading' => '正在加载归属…',
        'title_error' => '归属加载失败',
        'subtitle_totals' => ':characters 个角色 · :instances 个堆叠 · 共计 :count 件',
        'status' => [
            'loading' => '加载中…',
        ],
        'error' => [
            'load_failed' => '归属加载失败',
        ],
        'characters' => [
            'title' => '角色',
            'name' => '角色名',
            'level' => '等级',
            'class' => '职业',
            'total' => '总数量',
            'placeholder' => '没有角色拥有该物品',
        ],
        'instances' => [
            'title' => '物品实例',
            'instance' => '实例GUID',
            'character' => '角色',
            'count' => '数量',
            'location' => '位置',
            'container' => '容器',
            'placeholder' => '没有可显示的实例',
        ],
    ],
    'actions' => [
        'delete_selected' => '删除选中',
        'replace_selected' => '替换选中',
        'confirm_delete' => '确定删除选中的物品实例？',
        'delete_success' => '已删除选中的物品实例',
        'delete_failed' => '删除物品实例失败',
        'replace_success' => '已替换选中的物品实例',
        'replace_failed' => '替换物品实例失败',
    ],
    'modal' => [
        'replace' => [
            'title' => '替换物品',
            'entry_label' => '新的物品ID',
            'entry_placeholder' => '输入物品ID',
            'entry_hint' => '所有选中的物品实例将替换为此物品。',
            'cancel' => '取消',
            'confirm' => '应用',
            'validation' => [
                'entry' => '请输入有效的物品ID',
            ],
        ],
    ],
    'quality' => [
        'unknown' => '未知',
        0 => '粗糙',
        1 => '普通',
        2 => '优秀',
        3 => '精良',
        4 => '史诗',
        5 => '传说',
        6 => '神器',
        7 => '传家宝',
    ],
    'api' => [
        'errors' => [
            'invalid_entry' => '无效的物品ID。',
            'entry_not_found' => '未找到物品。',
            'empty_selection' => '请先选择至少一个物品实例。',
            'instances_not_found' => '所选物品实例不存在或已被移除。',
            'invalid_instance' => '无效的物品实例。',
            'unknown_action' => '未知操作。',
            'invalid_new_entry' => '请输入有效的替换物品ID。',
            'new_entry_not_found' => '替换的物品ID不存在。',
            'stack_too_large' => '堆叠数量 :stack 超过上限 :limit。',
            'delete_partial' => '已删除 :success 个实例，:failed 个失败。',
            'replace_partial' => '已替换 :success 个实例，:failed 个失败。',
            'replace_failed' => '更新物品实例失败。',
        ],
        'success' => [
            'delete_done' => '已删除 :count 个物品实例。',
            'replace_done' => '已替换 :count 个物品实例。',
        ],
    ],
    'locations' => [
        'equipment' => [
            'head' => '头部',
            'neck' => '颈部',
            'shoulders' => '肩部',
            'body' => '衬衣',
            'chest' => '胸甲',
            'waist' => '腰部',
            'legs' => '腿部',
            'feet' => '脚部',
            'wrist' => '护腕',
            'hands' => '手部',
            'finger1' => '戒指1',
            'finger2' => '戒指2',
            'trinket1' => '饰品1',
            'trinket2' => '饰品2',
            'back' => '背部',
            'main_hand' => '主手',
            'off_hand' => '副手',
            'ranged' => '远程/圣物',
            'tabard' => '战袍',
        ],
        'inventory' => [
            'backpack' => '背包槽位 :slot',
            'bank_main' => '银行槽位 :slot',
            'keyring' => '钥匙链槽位 :slot',
            'currency' => '货币槽位 :slot',
            'bag_slot' => '背包位 :slot',
            'bag_inner' => '背包 :bag 第 :slot 格',
            'unknown' => '背包（未知槽位）',
        ],
        'bank' => [
            'bag_slot' => '银行包位 :slot',
            'bag_inner' => '银行包 :bag 第 :slot 格',
        ],
    ],
    'js' => [
        'modules' => [
            'item_owner' => [
                'search' => [
                    'validation' => [
                        'empty' => '请输入关键词',
                    ],
                    'error' => [
                        'failed' => '搜索失败',
                    ],
                    'status' => [
                        'loading' => '加载中…',
                    ],
                    'results' => [
                        'empty' => '未找到物品',
                        'view' => '查看归属',
                    ],
                ],
                'results' => [
                    'title_empty' => '选择一个物品',
                    'subtitle_empty' => '搜索物品以查看归属详情。',
                    'title_loading' => '正在加载归属…',
                    'title_error' => '归属加载失败',
                    'subtitle_totals' => ':characters 个角色 · :instances 个堆叠 · 共计 :count 件',
                    'status' => [
                        'loading' => '加载中…',
                    ],
                    'error' => [
                        'load_failed' => '归属加载失败',
                    ],
                    'characters' => [
                        'placeholder' => '没有角色拥有该物品',
                    ],
                    'instances' => [
                        'placeholder' => '没有可显示的实例',
                    ],
                ],
                'actions' => [
                    'confirm_delete' => '确定删除选中的物品实例？',
                    'delete_success' => '已删除选中的物品实例',
                    'delete_failed' => '删除物品实例失败',
                    'replace_success' => '已替换选中的物品实例',
                    'replace_failed' => '替换物品实例失败',
                ],
                'modal' => [
                    'replace' => [
                        'validation' => [
                            'entry' => '请输入有效的物品ID',
                        ],
                    ],
                ],
                'quality' => [
                    'unknown' => '未知',
                    0 => '粗糙',
                    1 => '普通',
                    2 => '优秀',
                    3 => '精良',
                    4 => '史诗',
                    5 => '传说',
                    6 => '神器',
                    7 => '传家宝',
                ],
            ],
        ],
    ],
];
