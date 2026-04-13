<?php
return [
    'page_title' => '背包 / 物品查询',
    'form' => [
        'type_label' => '查询类型',
        'type_character_name' => '角色名称（模糊）',
        'type_username' => '账号用户名',
        'value_label' => '查询值',
        'value_placeholder' => '输入角色或账号',
        'submit' => '搜索',
    ],
    'chars' => [
        'title' => '角色列表',
        'subtitle' => '选择一个角色查看背包详情',
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
    'items' => [
        'title' => '物品列表',
        'subtitle_empty' => '未选择角色',
        'filter_placeholder' => '过滤物品名称',
        'table' => [
            'instance_guid' => '实例 GUID',
            'item_id' => '物品 ID',
            'name' => '名称',
            'count' => '数量',
            'slot' => '包 / 槽位',
            'actions' => '操作',
            'empty' => '未选择角色',
        ],
    ],
    'modal' => [
        'title' => '删除 / 减少物品',
        'quantity_label' => '数量',
        'quantity_hint' => '若数量 ≥ 当前堆叠，将删除该实例。',
        'cancel' => '取消',
        'confirm' => '确认执行',
    ],
    'js' => [
        'modules' => [
            'bag_query' => [
                'quality' => [
                    0 => '粗糙',
                    1 => '普通',
                    2 => '优秀',
                    3 => '精良',
                    4 => '史诗',
                    5 => '传说',
                    6 => '神器',
                    7 => '传家宝',
                ],
                'classes' => [
                    'warrior' => '战士',
                    'paladin' => '圣骑士',
                    'hunter' => '猎人',
                    'rogue' => '盗贼',
                    'priest' => '牧师',
                    'death-knight' => '死亡骑士',
                    'shaman' => '萨满祭司',
                    'mage' => '法师',
                    'warlock' => '术士',
                    'monk' => '武僧',
                    'druid' => '德鲁伊',
                    'demon-hunter' => '恶魔猎手',
                ],
                'errors' => [
                    'parse_failed' => '解析响应失败',
                    'network' => '网络异常',
                ],
                'status' => [
                    'loading' => '加载中…',
                ],
                'search' => [
                    'validation' => [
                        'empty' => '请输入查询值',
                    ],
                    'error' => [
                        'failed' => '查询失败',
                    ],
                    'empty' => '暂无结果',
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
                    'quality' => [
                        'unknown' => '未知',
                    ],
                    'error' => [
                        'load_failed' => '物品加载失败',
                    ],
                ],
                'actions' => [
                    'view' => '查看',
                    'delete' => '删除',
                    'processing' => '执行中…',
                ],
                'delete' => [
                    'info' => '物品 <strong>#:entry :name</strong> 当前数量 <strong>:count</strong><br>实例 GUID：:inst',
                    'validation' => [
                        'quantity' => '数量必须大于 0 且不超过当前堆叠',
                    ],
                    'success' => '物品已删除',
                    'error' => '操作失败',
                ],
            ],
        ],
    ],
];
