<?php
/**
 * File: resources/lang/zh_CN/logs.php
 * Purpose: 审计日志台文案与服务端共用的模块/动作目录标签。
 */

return [
  'page_title' => '审计日志',
  'intro' => '面板所有写操作、异常与系统事件的统一记录，支持按区服、模块、操作人、时间与关键字筛选。',
  'storage_unavailable' => '审计日志库当前不可访问，页面只能读取历史缓存；期间产生的日志会落到 storage/logs 下的兜底文件。',

  'fields' => [
    'keyword' => '关键字',
    'keyword_placeholder' => '摘要 / 目标 / 操作人 / URI / 明细',
    'channel' => '渠道',
    'module' => '模块',
    'action' => '动作',
    'actor' => '操作人',
    'status' => '状态',
    'realm' => '区服',
    'range' => '时间范围',
    'from' => '起始时间',
    'to' => '结束时间',
    'per_page' => '每页',
  ],

  'filters' => [
    'all' => '全部',
    'all_realms' => '全部区服',
  ],

  'actions' => [
    'search' => '查询',
    'reset' => '重置',
    'auto_refresh' => '开启自动刷新',
    'export' => '导出 CSV',
    'purge' => '清理',
  ],

  'ranges' => [
    '1h' => '最近 1 小时',
    '24h' => '最近 24 小时',
    '7d' => '最近 7 天',
    '30d' => '最近 30 天',
    '90d' => '最近 90 天',
    'all' => '全部时间',
    'custom' => '自定义区间',
  ],

  'purge' => [
    'hint' => '按时间清理历史记录，操作不可撤销。',
    'days' => '保留天数',
    'confirm' => '执行清理',
  ],

  'table' => [
    'headers' => [
      'time' => '时间',
      'realm' => '区服',
      'actor' => '操作人',
      'channel' => '渠道',
      'module' => '模块 · 动作',
      'status' => '状态',
      'target' => '目标',
      'summary' => '摘要',
    ],
    'loading' => '加载中…',
  ],

  'detail' => [
    'title' => '记录明细',
    'close' => '关闭',
  ],

  'storage' => [
    'table' => '日志表',
  ],

  'api' => [
    'purge_summary' => '清理 :days 天前的日志，删除 :deleted 条',
    'purge_done' => '已删除 :days 天前的 :deleted 条记录',
    'export_summary' => '导出审计日志 :count 条',
    'errors' => [
      'read_failed' => '读取日志失败',
      'purge_failed' => '清理日志失败',
    ],
  ],

  'channels' => [
    'audit' => '操作',
    'error' => '异常',
    'system' => '系统',
  ],

  'statuses' => [
    'ok' => '成功',
    'fail' => '失败',
    'denied' => '已拒绝',
  ],

  'catalog' => [
    'auth' => [
      'label' => '登录',
      'description' => '面板登录与登出。',
      'actions' => [
        'login' => '登录',
        'login_failed' => '登录失败',
        'logout' => '登出',
      ],
    ],
    'account' => [
      'label' => '账号',
      'description' => '账号的创建、封禁、改名与密码操作。',
      'actions' => [
        'create' => '创建账号',
        'delete' => '删除账号',
        'ban' => '封禁账号',
        'unban' => '解封账号',
        'bulk_ban' => '批量封禁',
        'bulk_unban' => '批量解封',
        'bulk_delete' => '批量删除',
        'set_gm' => '设置 GM 等级',
        'update_email' => '修改邮箱',
        'rename' => '修改账号名',
        'change_password' => '修改密码',
        'kick' => '踢下线',
      ],
    ],
    'character' => [
      'label' => '角色',
      'description' => '角色等级、金币、传送、封禁与重置操作。',
      'actions' => [
        'boost' => '角色直升',
        'boost_failed' => '直升失败',
        'ban' => '封禁角色',
        'unban' => '解封角色',
        'bulk_ban' => '批量封禁',
        'bulk_unban' => '批量解封',
        'bulk_delete' => '批量删除',
        'delete' => '删除角色',
        'set_level' => '设置等级',
        'set_gold' => '设置金币',
        'kick' => '踢下线',
        'kick_before_teleport' => '传送前踢下线',
        'teleport' => '传送',
        'unstuck' => '脱困',
        'reset_talents' => '重置天赋',
        'reset_spells' => '重置技能',
        'reset_cooldowns' => '重置冷却',
        'rename_flag' => '标记改名',
      ],
    ],
    'character_boost' => [
      'label' => '直升',
      'description' => '直升模板、兑换码与兑换记录。',
      'actions' => [
        'generate_redeem_codes' => '生成兑换码',
        'delete_unused_redeem_code' => '删除未用兑换码',
        'purge_unused_redeem_codes' => '清理未用兑换码',
        'save_template' => '保存模板',
        'delete_template' => '删除模板',
        'public_redeem' => '玩家兑换',
        'public_redeem_failed' => '玩家兑换失败',
      ],
    ],
    'item' => [
      'label' => '物品',
      'description' => '物品模板的新建、修改、删除与 SQL 执行。',
      'actions' => [
        'create' => '新建物品',
        'update' => '修改物品',
        'delete' => '删除物品',
        'exec_sql' => '执行 SQL',
        'snapshot' => '整行快照',
      ],
    ],
    'creature' => [
      'label' => '生物',
      'description' => '生物模板的新建、修改、删除与 SQL 执行。',
      'actions' => [
        'create' => '新建生物',
        'update' => '修改生物',
        'delete' => '删除生物',
        'exec_sql' => '执行 SQL',
        'snapshot' => '整行快照',
        'repository_warning' => '仓储告警',
      ],
    ],
    'creature_model' => [
      'label' => '生物模型',
      'description' => '生物模型行的增删改。',
      'actions' => [
        'add' => '新增模型',
        'edit' => '修改模型',
        'delete' => '删除模型',
      ],
    ],
    'quest' => [
      'label' => '任务',
      'description' => '任务模板与聚合数据的保存、删除与 SQL 执行。',
      'actions' => [
        'create' => '新建任务',
        'update' => '修改任务',
        'delete' => '删除任务',
        'exec_sql' => '执行 SQL',
        'snapshot' => '整行快照',
        'aggregate_save' => '保存聚合数据',
      ],
    ],
    'item_inventory' => [
      'label' => '物品/库存',
      'description' => '背包查询、物品归属与批量增删。',
      'actions' => [
        'search_characters' => '查询角色',
        'view_character_items' => '查看角色物品',
        'view_ownership' => '查看物品归属',
        'reduce_instance' => '扣减物品',
        'bulk_delete' => '批量删除',
        'bulk_replace' => '批量替换',
        'read_degraded' => '查询降级',
        'mutation_failed' => '变更失败',
        'instance_create_failed' => '实例创建失败',
      ],
    ],
    'mail' => [
      'label' => '邮件',
      'description' => '邮件查看、标记已读与删除。',
      'actions' => [
        'view' => '查看邮件',
        'mark_read' => '标记已读',
        'mark_read_bulk' => '批量标记已读',
        'delete' => '删除邮件',
        'delete_bulk' => '批量删除邮件',
        'exec_sql' => '执行 SQL',
        'snapshot' => '删除快照',
      ],
    ],
    'massmail' => [
      'label' => '群发',
      'description' => '全服公告与批量发放邮件、物品、金币。',
      'actions' => [
        'announce' => '全服公告',
        'send_mail' => '群发邮件',
        'send_item' => '群发物品',
        'send_gold' => '群发金币',
        'send_item_gold' => '群发物品与金币',
      ],
    ],
    'boss' => [
      'label' => '活动 Boss',
      'description' => 'Boss 配置、扩展配置、奖池与控制命令。',
      'actions' => [
        'command' => '控制命令',
        'save_config' => '保存主配置',
        'save_ext_config' => '保存扩展配置',
        'copy_ext_config' => '复制扩展配置',
        'save_reward_pools' => '保存奖池',
      ],
    ],
    'trivia' => [
      'label' => '聊天答题',
      'description' => '答题开关、设置、题库与预设。',
      'actions' => [
        'action' => '控制命令',
        'save_settings' => '保存设置',
        'question_create' => '新增题目',
        'question_update' => '修改题目',
        'question_delete' => '删除题目',
        'question_enable' => '启用题目',
        'question_disable' => '停用题目',
        'question_import' => '导入题库',
        'question_export' => '导出题库',
        'preset_save' => '保存奖励预设',
        'preset_delete' => '删除奖励预设',
        'winners_clear' => '清空获奖记录',
      ],
    ],
    'auctionator' => [
      'label' => '拍卖行',
      'description' => '拍卖行配置、商品策略与控制命令。',
      'actions' => [
        'config_save' => '保存配置',
        'item_policy' => '商品策略',
        'command' => '控制命令',
        'listing' => '上架',
        'power' => '能量',
        'buyout' => '一口价',
        'max_item_level' => '物品等级上限',
        'repository_warning' => '仓储告警',
      ],
    ],
    'raf' => [
      'label' => '招募',
      'description' => '招募关系绑定、解绑与备注。',
      'actions' => [
        'bind' => '绑定招募',
        'force_bind' => '强制绑定',
        'unbind' => '解绑招募',
        'comment' => '备注',
      ],
    ],
    'aegis' => [
      'label' => '反作弊',
      'description' => '反作弊面板下发的处理命令。',
      'actions' => [
        'command' => '处理命令',
      ],
    ],
    'supervisor' => [
      'label' => '进程守护',
      'description' => '服务进程的启停与心跳探测。',
      'actions' => [
        'ping' => '心跳',
        'start' => '启动',
        'stop' => '停止',
        'restart' => '重启',
        'command' => '控制命令',
      ],
    ],
    'soap_exec' => [
      'label' => 'SOAP',
      'description' => '通过 SOAP 下发的服务器命令。',
      'actions' => [
        'command' => 'SOAP 命令',
      ],
    ],
    'realm' => [
      'label' => '区服',
      'description' => '面板区服切换。',
      'actions' => [
        'switch' => '切换区服',
      ],
    ],
    'setup' => [
      'label' => '安装',
      'description' => '面板安装与重配置。',
      'actions' => [
        'install' => '安装',
        'reconfigure' => '重新配置',
      ],
    ],
    'logs' => [
      'label' => '日志审计',
      'description' => '审计日志自身的导出与清理。',
      'actions' => [
        'export' => '导出日志',
        'purge' => '清理日志',
      ],
    ],
    'system' => [
      'label' => '系统',
      'description' => '框架级异常与告警。',
      'actions' => [
        'exception' => '未捕获异常',
        'warning' => '运行告警',
        'notice' => '运行提示',
        'prune' => '日志清理',
        'storage_fallback' => '兜底落盘',
      ],
    ],
  ],

  'js' => [
    'modules' => [
      'logs' => [
        'filters' => [
          'all' => '全部',
        ],
        'actions' => [
          'auto_on' => '开启自动刷新',
          'auto_off' => '关闭自动刷新',
        ],
        'summary' => [
          'total' => '命中',
          'range' => '时间跨度',
        ],
        'pager' => [
          'prev' => '上一页',
          'next' => '下一页',
          'range' => ':from-:to / :total',
        ],
        'status' => [
          'no_entries' => '暂无日志',
          'load_failed' => '加载失败',
          'request_error' => '请求异常',
          'panel_waiting' => 'Panel API 初始化中，请稍候…',
        ],
        'detail' => [
          'detail_json' => '原始明细',
          'fields' => [
            'time' => '时间',
            'channel' => '渠道',
            'module' => '模块 · 动作',
            'status' => '状态',
            'severity' => '级别',
            'actor' => '操作人',
            'realm' => '区服',
            'target' => '目标',
            'summary' => '摘要',
            'ip' => '来源 IP',
            'request' => '请求',
            'duration' => '耗时',
            'user_agent' => 'User-Agent',
            'record_id' => '记录号',
          ],
        ],
        'purge' => [
          'confirm_text' => '确定删除 :days 天前的审计记录？此操作不可撤销。',
          'invalid_days' => '请填写大于 0 的天数。',
          'done' => '已执行清理。',
        ],
      ],
    ],
  ],
];
