<?php
return [
    'page_title' => '物品 / 库存管理',

    'tabs' => [
        'character' => '按角色查询',
        'item' => '按物品查询',
    ],

    'character' => [
        'form' => [
            'type_label' => '查询类型',
            'type_character_name' => '角色名称（模糊）',
            'type_username' => '账号用户名',
            'value_label' => '查询值',
            'value_placeholder' => '输入角色名或账号名',
            'submit' => '搜索',
        ],
        'chars' => [
            'title' => '角色列表',
            'subtitle' => '选择一个角色查看其背包、银行与装备',
            'table' => [
                'guid' => 'GUID',
                'name' => '名称',
                'level' => '等级',
                'race' => '种族',
                'account' => '账号',
                'actions' => '操作',
                'empty' => '等待搜索…',
            ],
        ],
    ],

    'item' => [
        'form' => [
            'keyword_label' => '关键词',
            'keyword_placeholder' => '物品名称或物品 ID',
            'submit' => '搜索',
        ],
        'search' => [
            'title' => '物品查找',
            'subtitle' => '通过名称或物品 ID 查询哪些角色持有该物品。',
            'view' => '查看归属',
            'empty' => '未找到物品',
            'table' => [
                'entry' => '物品 ID',
                'name' => '名称',
                'quality' => '品质',
                'stackable' => '堆叠上限',
                'actions' => '操作',
                'placeholder' => '搜索结果将显示在此处。',
            ],
        ],
    ],

    'items' => [
        'title' => '物品列表',
        'subtitle_empty' => '未选择角色',
        'filter_placeholder' => '按名称过滤物品',
        'contains' => '该背包内有物品',
        'contains_count' => '内含 :count 件',
        'open_in_editor' => '在物品管理中打开',
        'table' => [
            'instance_guid' => '实例 GUID',
            'item_id' => '物品 ID',
            'name' => '名称',
            'count' => '数量',
            'location' => '位置',
            'owners' => '归属',
            'actions' => '操作',
            'empty' => '未选择角色',
        ],
    ],

    'owners' => [
        'title_empty' => '选择一个物品',
        'title_loading' => '正在加载归属…',
        'title_error' => '归属加载失败',
        'subtitle_empty' => '先搜索物品，再查看其归属。',
        'subtitle_totals' => ':characters 个角色 · :instances 个堆叠 · 共计 :count 件',
        'table' => [
            'instance' => '实例 GUID',
            'character' => '角色',
            'count' => '数量',
            'location' => '位置',
            'container' => '容器',
            'placeholder' => '尚未加载归属数据。',
            'empty' => '没有可显示的实例',
        ],
        'actions' => [
            'delete_selected' => '删除选中',
            'replace_selected' => '替换选中',
        ],
        'pager' => [
            'prev' => '上一页',
            'next' => '下一页',
            'info' => '第 :page / :pages 页',
        ],
    ],

    'actions' => [
        'view' => '查看物品',
        'delete' => '删除',
        'processing' => '执行中…',
    ],

    'modal' => [
        'delete' => [
            'title' => '删除 / 减少物品',
            'quantity_label' => '数量',
            'quantity_hint' => '数量等于当前堆叠时会删除该实例；小于则只减少数量。',
            'destroy_contents' => '该背包内仍有物品，确认一并摧毁',
            'container_requires_confirm' => '该背包内还有 :count 件物品。勾选上方选项以一并摧毁，或将数量调小。',
            'cancel' => '取消',
            'confirm' => '确认执行',
            'success' => '物品已处理',
            'error' => '操作失败',
            'validation' => [
                'quantity' => '数量必须大于 0 且不超过当前堆叠。',
            ],
        ],
        'bulk_delete' => [
            'title' => '批量删除确认',
            'info' => '即将删除选中的 :count 个物品实例，此操作不可撤销。',
            'destroy_contents' => '选中项中包含背包，确认一并摧毁其中的物品',
            'failed_title' => '以下实例未能删除：',
            'cancel' => '取消',
            'confirm' => '确认删除',
            'success' => '删除完成',
            'error' => '删除失败',
        ],
        'replace' => [
            'title' => '替换物品',
            'entry_label' => '新的物品 ID',
            'entry_placeholder' => '输入物品 ID',
            'entry_hint' => '选中的实例将替换为该物品；若原堆叠超过新物品的堆叠上限，超出的部分会拆分到同容器的空闲格。',
            'entry_hint_short' => '超出新物品堆叠上限的部分会拆分到同容器空位。',
            'cancel' => '取消',
            'confirm' => '应用',
            'success' => '替换完成',
            'error' => '替换失败',
            'validation' => [
                'entry' => '请输入有效的物品 ID。',
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
            'unknown' => '背包槽位 :slot（未识别）',
        ],
        'bank' => [
            'bag_slot' => '银行包位 :slot',
            'bag_inner' => '银行包 :bag 第 :slot 格',
        ],
    ],

    'api' => [
        'errors' => [
            'invalid_guid' => '无效的角色 GUID。',
            'invalid_entry' => '无效的物品 ID。',
            'entry_not_found' => '未找到该物品。',
            'invalid_parameters' => '参数无效。',
            'instance_not_found' => '未找到该物品实例，或它不属于指定角色。',
            'invalid_instance' => '无效的物品实例。',
            'instances_not_found' => '所选物品实例不存在或已不属于任何角色。',
            'quantity_positive' => '数量必须大于 0。',
            'quantity_exceeds_stack' => '数量超过当前堆叠（现有 :count）。',
            'inventory_mismatch' => '该物品实例与角色的背包记录不一致。',
            'container_not_empty' => '该物品是容器，内部还有 :count 件物品。请确认一并摧毁后再试。',
            'container_not_empty_bulk' => '选中项中包含非空容器，请确认一并摧毁后再试。',
            'not_enough_slots' => '目标容器空闲格不足（需要 :needed 格，剩余 :available 格），未做任何修改。',
            'instance_create_failed' => '创建物品实例失败：:message',
            'reduce_failed' => '操作失败：:message',
            'delete_failed' => '删除失败。',
            'delete_partial' => '已删除 :success 个实例，:failed 个失败，本次已回滚。',
            'empty_selection' => '请先选择至少一个物品实例。',
            'invalid_new_entry' => '请输入有效的替换物品 ID。',
            'new_entry_not_found' => '替换的目标物品 ID 不存在。',
            'replace_failed' => '替换物品实例失败。',
            'batch_contains_missing' => '选中项中包含已不存在或已不属于任何角色的实例，本次未做任何修改。',
            'unknown_action' => '未知操作。',
            'lookup_failed' => '物品数据查询失败。',
        ],
        'success' => [
            'quantity_reduced' => '数量已减少。',
            'item_deleted' => '物品实例已删除。',
            'item_deleted_with_contents' => '物品实例已删除，同时摧毁其内部 :count 件物品。',
            'delete_done' => '已删除 :count 个物品实例。',
            'replace_done' => '已替换 :count 个物品实例。',
            'replace_done_split' => '已替换 :count 个物品实例，并因堆叠上限拆分为 :created 个新实例。',
        ],
    ],

    'js' => [
        'modules' => [
            'item_inventory' => [
                'errors' => [
                    'parse_failed' => '解析响应失败',
                    'network' => '网络异常',
                ],
                'status' => [
                    'loading' => '加载中…',
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
                'character' => [
                    'validation' => [
                        'empty' => '请输入查询值',
                    ],
                    'empty' => '暂无结果',
                    'error' => [
                        'failed' => '查询失败',
                    ],
                ],
                'item' => [
                    'validation' => [
                        'empty' => '请输入关键词',
                    ],
                    'search' => [
                        'empty' => '未找到物品',
                        'view' => '查看归属',
                        'open_in_editor' => '在物品管理中打开',
                    ],
                    'error' => [
                        'failed' => '搜索失败',
                    ],
                ],
                'items' => [
                    'subtitle' => [
                        'none' => '未选择角色',
                        'current_name' => '当前角色：:name',
                        'current_guid' => '当前角色 GUID :guid',
                        'with_status' => ':base（:status）',
                    ],
                    'placeholder' => [
                        'none' => '未选择角色',
                    ],
                    'filter' => [
                        'placeholder' => '按名称过滤物品',
                    ],
                    'empty' => '未找到物品或背包为空',
                    'contains' => '该背包内有物品',
                    'contains_count' => '内含 :count 件',
                    'open_in_editor' => '在物品管理中打开',
                    'error' => [
                        'load_failed' => '物品加载失败',
                    ],
                ],
                'owners' => [
                    'title_empty' => '选择一个物品',
                    'title_loading' => '正在加载归属…',
                    'title_error' => '归属加载失败',
                    'subtitle_empty' => '先搜索物品，再查看其归属。',
                    'subtitle_totals' => ':characters 个角色 · :instances 个堆叠 · 共计 :count 件',
                    'table' => [
                        'empty' => '没有可显示的实例',
                    ],
                    'pager' => [
                        'info' => '第 :page / :pages 页',
                    ],
                    'error' => [
                        'load_failed' => '归属加载失败',
                    ],
                ],
                'actions' => [
                    'view_items' => '查看物品',
                    'view_owners' => '查看归属',
                    'delete' => '删除',
                    'processing' => '执行中…',
                ],
                'modal' => [
                    'delete' => [
                        'info' => '物品 #:entry :name —— 当前堆叠 :count —— 实例 GUID :inst',
                        'quantity_hint' => '数量等于当前堆叠时会删除该实例；小于则只减少数量。',
                        'destroy_contents' => '该背包内仍有物品，确认一并摧毁',
                        'container_requires_confirm' => '该背包内还有 :count 件物品。勾选上方选项以一并摧毁，或将数量调小。',
                        'success' => '物品已处理',
                        'error' => '操作失败',
                        'validation' => [
                            'quantity' => '数量必须大于 0 且不超过当前堆叠。',
                        ],
                    ],
                    'bulk_delete' => [
                        'info' => '即将删除选中的 :count 个物品实例，此操作不可撤销。',
                        'destroy_contents' => '选中项中包含背包，确认一并摧毁其中的物品',
                        'failed_title' => '以下实例未能删除：',
                        'success' => '删除完成',
                        'error' => '删除失败',
                    ],
                    'replace' => [
                        'success' => '替换完成',
                        'error' => '替换失败',
                        'validation' => [
                            'entry' => '请输入有效的物品 ID。',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
