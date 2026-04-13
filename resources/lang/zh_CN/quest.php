<?php
return [
    'common' => [
        'na' => '—',
    ],
    'index' => [
        'page_title' => '任务管理',
        'filters' => [
            'id_placeholder' => '任务 ID',
            'title_placeholder' => '标题包含',
            'min_level_placeholder' => '最小等级',
            'level_placeholder' => '等级',
            'type_all' => '全部类型',
            'actions' => [
                'search' => '搜索',
                'reset' => '清空',
                'create' => '新增',
                'log' => '查看日志',
            ],
        ],
        'table' => [
            'headers' => [
                'id' => 'ID',
                'title' => '标题',
                'min_level' => '最小等级',
                'level' => '等级',
                'type' => '类型',
                'reward_xp' => '奖励经验',
                'reward_money' => '奖励金币',
                'reward_items' => '奖励物品',
                'actions' => '操作',
            ],
            'empty' => '无结果',
            'quest_info_unknown' => 'Info :id',
            'quest_info_default' => '普通',
            'reward_xp_difficulty' => '难度 :value',
            'reward_money_difficulty' => '难度 :value',
            'reward_items_fixed' => '固定奖励',
            'reward_items_choice' => '自选奖励',
            'reward_item_title' => 'ID :id',
            'actions' => [
                'edit' => '编辑',
                'delete' => '删除',
            ],
        ],
        'modals' => [
            'new' => [
                'title' => '新建任务',
                'id_label' => '新建 ID*',
                'copy_label' => '复制自（可选）',
                'copy_hint' => '留空将创建空白任务模板。',
                'cancel' => '取消',
                'confirm' => '创建',
            ],
        ],
    ],
    'messages' => [
        'not_found_title' => '任务不存在',
        'not_found' => '未找到任务。',
    ],
    'api' => [
        'errors' => [
            'invalid_id' => '无效的任务 ID。',
        ],
    ],
    'log_modal' => [
        'title' => '任务日志',
        'type_label' => '日志类型',
        'types' => [
            'sql' => 'SQL 执行',
            'deleted' => '删除快照',
            'actions' => '行为记录',
        ],
        'refresh' => '刷新',
        'empty' => '-- 暂无日志 --',
        'close' => '关闭',
    ],
    'aggregate' => [
        'warnings' => [
            'no_changes' => '无差异可生成。',
        ],
        'errors' => [
            'invalid_id' => '无效的任务 ID。',
            'template_id_mismatch' => '模板 ID 与请求不符。',
            'hash_mismatch' => '任务已被其他会话修改。',
            'validation_failed' => '验证失败。',
            'field_required' => '字段 :field 不能为空。',
            'field_numeric' => ':field 必须为数字。',
            'index_missing' => '缺少 Index。',
            'index_range' => 'Index 必须在 :min-:max 之间。',
            'index_duplicate' => 'Index :value 重复。',
            'choice_limit' => '可选奖励最多 :limit 条。',
            'array_required' => '需要数组。',
            'positive_integer' => '必须为正整数。',
            'duplicate_id' => '存在重复 ID :id。',
            'no_changes_payload' => '没有可保存的改动。',
            'save_failed' => '保存失败：:reason',
        ],
    ],
    'repository' => [
        'info_labels' => [
            0 => '普通',
            1 => '小队',
            21 => '生活',
            41 => 'PVP',
            62 => '团队',
            81 => '地下城',
            82 => '事件',
            83 => '传奇',
            84 => '护送',
            85 => '英雄',
            88 => '团队（10人）',
            89 => '团队（25人）',
        ],
        'money' => [
            'separator' => ' ',
            'gold' => ':value 金',
            'silver' => ':value 银',
            'copper' => ':value 铜',
        ],
        'defaults' => [
            'log_title' => '新任务 :id',
        ],
        'messages' => [
            'copy_created' => '已复制创建任务。',
            'created' => '任务已创建。',
            'delete_success' => '已删除任务 #:id。',
            'delete_none' => '未删除任何行。',
            'no_changes' => '没有可应用的修改。',
            'no_valid_fields' => '未提供可更新的有效字段。',
            'no_values_changed' => '字段值未发生变化。',
            'update_done' => '更新完成。',
        ],
        'errors' => [
            'invalid_new_id' => '无效的新 ID。',
            'id_exists' => 'ID 已存在。',
            'copy_source_missing' => '复制源不存在。',
            'copy_failed' => '复制创建失败。',
            'create_failed' => '创建失败。',
            'invalid_id' => '无效的任务 ID。',
            'update_failed' => '更新失败。',
            'sql_empty' => 'SQL 不能为空。',
            'sql_multiple' => '不允许一次执行多条语句。',
            'sql_parse_column' => '无法解析列赋值：:column',
            'sql_invalid_column' => '列 :column 不允许更新。',
            'sql_update_where' => 'UPDATE 语句必须以 WHERE ID = <数字> [LIMIT 1] 结束。',
            'sql_only_update_insert' => '仅允许对 quest_template 执行 UPDATE 或 INSERT。',
            'sql_exec_error' => '执行错误：:error',
            'log_unknown_type' => '未知的日志类型。',
            'log_open_failed' => '无法打开日志文件。',
        ],
        'sql' => [
            'insert_label' => '插入',
            'update_label' => '更新',
            'affected' => ':operation 影响行数：:count',
        ],
    ],
    'edit' => [
        'page_title' => '编辑任务 #:id',
        'toolbar' => [
            'back' => '返回列表',
            'log' => '查看日志',
            'execute_sql' => '执行 SQL',
            'copy_sql' => '复制 SQL',
        ],
        'diff' => [
            'title' => '差异 SQL 预览',
            'hint' => '自动生成 UPDATE，仅包含改动字段，并附带 LIMIT 1 保护。',
            'empty' => '-- 无改动 --',
        ],
        'tabs' => [
            'general' => '基础',
            'objectives' => '目标',
            'requirements' => '条件',
            'rewards' => '奖励',
            'internal' => '内部',
        ],
        'nav' => [
            'title' => '分组导航',
        ],
        'mini_diff' => [
            'title' => '改动明细',
            'empty' => '暂无改动',
            'table' => [
                'field' => '字段',
                'value' => '旧值 → 新值',
            ],
            'collapse' => '折叠',
            'reset' => '重置',
            'reset_title' => '重置为当前数据库值',
        ],
        'fields' => [
            'bitmask' => [
                'undo_tooltip' => '还原原始值',
            ],
        ],
    ],
    'config' => [
        'fields' => [
            'enums' => [
                'quest_type' => [
                    0 => '普通',
                    1 => '小队',
                    2 => 'PvP',
                    3 => '精英',
                    4 => '地下城',
                    5 => '团队',
                    6 => '传奇',
                    7 => '事件',
                ],
            ],
            'bitmasks' => [
                'races' => [
                    0 => '人类',
                    1 => '兽人',
                    2 => '亡灵',
                    3 => '暗夜精灵',
                    4 => '牛头人',
                    5 => '侏儒',
                    6 => '巨魔',
                    7 => '血精灵',
                    8 => '德莱尼',
                ],
                'classes' => [
                    0 => '战士',
                    1 => '圣骑士',
                    2 => '猎人',
                    3 => '潜行者',
                    4 => '牧师',
                    5 => '死亡骑士',
                    6 => '萨满祭司',
                    7 => '法师',
                    8 => '术士',
                    9 => '德鲁伊',
                ],
                'quest_flags' => [
                    0 => '日常任务',
                    1 => '可共享',
                    2 => '限时',
                    3 => '可重复',
                    4 => '护送',
                    5 => '团队（旧）',
                    6 => '月常',
                    7 => '节日季节',
                    8 => '公会/事件预留',
                    9 => '仅英雄地下城',
                    10 => '地下城查找器',
                    11 => '团队查找器',
                    12 => '账号共享',
                    13 => '周常',
                    14 => '场景战役',
                    15 => '账号周常',
                    16 => '世界任务（示例）',
                    17 => '隐藏追踪',
                    18 => '自动接取',
                    19 => '自动完成',
                    20 => '不可放弃',
                    21 => '显示进度区域',
                    22 => '任务日志中隐藏',
                    23 => '仅事件期间',
                    24 => '职业限定',
                    25 => '种族限定',
                    26 => '账号仅一次',
                    27 => '保留任务物品',
                    28 => '团队可接',
                    29 => '场景挑战',
                    30 => '史诗标记',
                    31 => '内部保留',
                ],
                'quest_special_flags' => [
                    0 => '脚本处理',
                    1 => '数据库内部使用',
                    2 => '内部可重复',
                    3 => '需要 GM',
                    4 => '副本内不可放弃',
                    5 => '忽略阵营检查',
                    6 => '起始物品校验',
                    7 => '延迟奖励',
                    8 => '队伍共享进度',
                    9 => '仅追踪（无奖励）',
                    10 => '普通玩家隐藏',
                    11 => '账号唯一',
                    12 => '角色唯一',
                    13 => '额外脚本触发',
                    14 => '服务器事件触发',
                    15 => '调试/测试',
                ],
            ],
            'groups' => [
                'basic' => [
                    'label' => '基础信息',
                ],
                'objectives' => [
                    'label' => '目标文本',
                ],
                'requirements' => [
                    'label' => '条件限制',
                ],
                'flags' => [
                    'label' => '标志位',
                ],
                'rewards' => [
                    'label' => '奖励',
                ],
                'internal' => [
                    'label' => '内部/脚本',
                ],
            ],
            'form' => [
                'LogTitle' => [
                    'label' => '日志标题',
                ],
                'QuestDescription' => [
                    'label' => '任务描述',
                ],
                'QuestLevel' => [
                    'label' => '任务等级',
                ],
                'MinLevel' => [
                    'label' => '最低等级',
                ],
                'QuestType' => [
                    'label' => '任务类型',
                ],
                'ObjectiveText1' => [
                    'label' => '目标文本 1',
                ],
                'ObjectiveText2' => [
                    'label' => '目标文本 2',
                ],
                'ObjectiveText3' => [
                    'label' => '目标文本 3',
                ],
                'ObjectiveText4' => [
                    'label' => '目标文本 4',
                ],
                'AllowableRaces' => [
                    'label' => '允许种族（位掩码）',
                ],
                'RequiredClasses' => [
                    'label' => '要求职业（位掩码）',
                ],
                'TimeAllowed' => [
                    'label' => '限时（秒）',
                    'help' => '0 表示不限时。',
                ],
                'Flags' => [
                    'label' => '主标志位',
                ],
                'SpecialFlags' => [
                    'label' => '特殊标志位',
                    'help' => '主标志位之外的附加行为位。',
                ],
                'RewardMoney' => [
                    'label' => '奖励金钱',
                ],
                'RewardSpell' => [
                    'label' => '奖励法术',
                ],
                'RewardItem1' => [
                    'label' => '奖励物品 1',
                ],
                'RewardAmount1' => [
                    'label' => '数量 1',
                ],
                'RewardItem2' => [
                    'label' => '奖励物品 2',
                ],
                'RewardAmount2' => [
                    'label' => '数量 2',
                ],
                'RewardItem3' => [
                    'label' => '奖励物品 3',
                ],
                'RewardAmount3' => [
                    'label' => '数量 3',
                ],
                'RewardItem4' => [
                    'label' => '奖励物品 4',
                ],
                'RewardAmount4' => [
                    'label' => '数量 4',
                ],
                'SrcSpell' => [
                    'label' => '来源法术',
                ],
                'StartItem' => [
                    'label' => '起始物品',
                ],
                'RewardMailTemplateId' => [
                    'label' => '奖励邮件模板',
                ],
            ],
        ],
        'metadata' => [
            'template_groups' => [
                'identity' => [
                    'label' => '身份与文本',
                ],
                'progression' => [
                    'label' => '进度与等级',
                ],
                'requirements' => [
                    'label' => '阵营与条件',
                ],
                'objectives' => [
                    'label' => '目标',
                ],
                'rewards' => [
                    'label' => '奖励配置',
                ],
                'flags' => [
                    'label' => '标志与引导',
                ],
            ],
            'narrative_tables' => [
                'details' => [
                    'label' => '详情文本',
                ],
                'request' => [
                    'label' => '请求物品文本',
                ],
                'offer' => [
                    'label' => '奖励文本',
                ],
            ],
            'reward_tables' => [
                'choice_items' => [
                    'label' => '自选奖励',
                ],
                'items' => [
                    'label' => '固定奖励',
                ],
                'currencies' => [
                    'label' => '货币奖励',
                ],
                'factions' => [
                    'label' => '声望奖励',
                ],
            ],
        ],
    ],
    'js' => [
        'modules' => [
            'quest' => [
                'api' => [
                    'not_ready' => 'Panel.api 未就绪',
                ],
                'logs' => [
                    'loading_placeholder' => '-- 加载中... --',
                    'empty_placeholder' => '-- 暂无日志 --',
                    'error_placeholder' => '-- 加载失败 --',
                    'load_failed' => '日志加载失败',
                    'load_failed_with_reason' => '日志加载失败: :reason',
                ],
                'create' => [
                    'enter_new_id' => '请输入新任务 ID',
                    'success_redirect' => '任务创建成功，正在跳转...',
                    'failed' => '创建任务失败',
                    'failed_with_reason' => '创建任务失败: :reason',
                ],
                'list' => [
                    'confirm_delete' => '确认删除任务 :id?',
                    'delete_success' => '任务已删除',
                    'delete_failed' => '删除任务失败',
                    'delete_failed_with_reason' => '删除任务失败: :reason',
                ],
                'editor' => [
                    'no_changes_comment' => '-- 无改动 --',
                    'no_sql_available' => '当前没有可执行的 SQL',
                    'confirm_execute' => '执行当前 UPDATE?',
                    'exec_success' => 'SQL 已执行',
                    'exec_failed' => '执行失败',
                    'exec_failed_with_reason' => '执行失败: :reason',
                    'copy_sql_success' => '已复制 SQL',
                    'copy_sql_failed_with_reason' => '复制失败: :reason',
                    'diff_count' => ':count 处改动',
                    'rows_label' => '行:',
                    'reset_prompt' => '重置所有改动并重新读取当前数据库行?',
                    'reset_success' => '改动已重置',
                    'reset_failed' => '重置失败',
                    'reset_failed_with_reason' => '重置失败: :reason',
                    'restore_field' => '已还原 :field',
                    'refresh_failed_console' => '刷新任务数据失败',
                ],
                'mini' => [
                    'revert_tooltip' => '还原该字段',
                    'collapse' => '折叠',
                    'expand' => '展开',
                ],
                'core' => [
                    'no_changes_sql_comment' => '-- 无改动 --',
                ],
            ],
        ],
    ],
];