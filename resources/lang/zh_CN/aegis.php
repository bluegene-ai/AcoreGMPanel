<?php
return array (
  'page_title' => 'Aegis 反作弊',
  'intro' => '按服务器查看作弊轨迹、事件明细，并通过 SOAP 执行 Aegis 手工处置命令。',
  'actions' => 
  array (
    'refresh_all' => '刷新全部',
    'search' => '查询',
    'clear' => '清除',
    'delete' => '删除',
    'success' => 'Aegis 命令执行成功。',
    'failure' => 'Aegis 命令执行失败。',
  ),
  'errors' => 
  array (
    'player_required' => '请输入玩家名或 GUID。',
    'target_required' => '需要目标玩家。',
    'invalid_action' => '无效的 Aegis 操作。',
  ),
  'overview' => 
  array (
    'days' => '时间窗口',
    'days_value' => ':days 天',
    'stage_distribution' => '处罚阶段分布',
    'cheat_distribution' => '作弊类型分布',
    'top_offenders' => '高频对象',
  ),
  'player' => 
  array (
    'title' => '玩家快照',
    'lookup_label' => '玩家名或 GUID',
    'lookup_placeholder' => '例如：PlayerName 或 12345',
    'lookup_submit' => '加载玩家',
    'empty' => '输入玩家名或 GUID 以查看当前处罚状态和最近事件。',
  ),
  'manual' => 
  array (
    'title' => '手工处理',
    'action_label' => '动作',
    'target_label' => '目标玩家',
    'target_placeholder' => 'clear / delete 时必填',
    'submit' => '执行命令',
    'actions' => 
    array (
      'clear' => '清除玩家记录',
      'delete' => '删除玩家数据',
      'reload' => '重载配置',
      'purge' => '清空全部数据',
    ),
    'help' => 
    array (
      'clear' => 'Clear 会清除玩家的 Aegis 跟踪状态，但不会删除角色本身。',
      'delete' => 'Delete 会删除该玩家已持久化的 Aegis 数据。',
      'reload' => 'Reload 会重新读取 Aegis 配置，无需重启服务器。',
      'purge' => 'Purge 会清空当前服务器全部 offense/event 持久化数据。',
    ),
  ),
  'filters' => 
  array (
    'all' => '全部',
    'query' => '搜索',
    'query_placeholder' => '按玩家、账号、GUID 或原因搜索',
    'stage' => '处罚阶段',
    'cheat_type' => '作弊类型',
    'status_label' => '处罚状态',
    'evidence_level' => '证据等级',
    'days' => '天数',
    'status' => 
    array (
      'all' => '全部跟踪对象',
      'tracked' => '仅跟踪中',
      'debuffed' => '已减益',
      'jailed' => '已关监狱',
      'banned' => '临时封禁',
      'permanent' => '永久封禁',
    ),
  ),
  'offense' => 
  array (
    'title' => '处罚记录',
    'columns' => 
    array (
      'player' => '玩家',
      'account' => '账号',
      'cheat' => '最近作弊',
      'stage' => '阶段',
      'offense_count' => '次数',
      'tier' => '层级',
      'last_reason' => '最近原因',
      'last_offense_at' => '最近触发时间',
      'actions' => '操作',
    ),
  ),
  'event' => 
  array (
    'title' => '可疑事件',
    'columns' => 
    array (
      'time' => '时间',
      'player' => '玩家',
      'account' => '账号',
      'cheat' => '作弊类型',
      'level' => '证据等级',
      'tag' => '标签',
      'risk' => '风险',
      'position' => '地图 / 坐标',
      'detail' => '详情',
    ),
  ),
  'log' => 
  array (
    'title' => '原始 aegis.log',
    'refresh' => '刷新日志',
    'meta_empty' => '正在加载日志信息…',
    'empty' => '-- 无日志行 --',
  ),
  'enums' => 
  array (
    'cheat_type' => 
    array (
      0 => '全部类型',
      1 => '速度',
      2 => '飞行',
      3 => '传送',
      4 => '跳跃',
      5 => '水上行走',
      6 => '穿墙',
      7 => '破根',
      8 => '受控移动',
      9 => '其他',
    ),
    'evidence_level' => 
    array (
      0 => '信息',
      1 => '低',
      2 => '中',
      3 => '高',
    ),
    'punish_stage' => 
    array (
      0 => '跟踪',
      1 => '警告',
      2 => '减益',
      3 => '监狱',
      4 => '封禁',
      5 => '永久',
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'aegis' => 
      array (
        'status' => 
        array (
          'loading' => '加载中…',
          'empty' => '暂无数据',
        ),
        'pagination' => 
        array (
          'prev' => '上一页',
          'next' => '下一页',
          'label' => '第 :page / :pages 页',
        ),
        'cards' => 
        array (
          'tracked' => '跟踪玩家',
          'debuffed' => '已减益',
          'jailed' => '已监禁',
          'banned' => '已封禁',
          'last_day' => '24小时事件',
          'window_total' => '窗口总事件',
        ),
        'top' => 
        array (
          'offense_count' => '触发次数：:count',
        ),
        'player' => 
        array (
          'online' => '在线',
          'offline' => '离线',
          'guid' => 'GUID：:value',
          'account' => '账号：:value',
          'level' => '等级：:value',
          'stage' => '阶段：:value',
          'cheat' => '作弊：:value',
          'offenses' => '次数：:value',
          'tier' => '层级：:value',
          'no_reason' => '无原因',
          'no_offense' => '暂无处罚记录',
          'recent_events' => '最近事件',
        ),
        'actions' => 
        array (
          'clear' => '清除',
          'delete' => '删除',
          'success' => '操作成功',
          'failure' => '操作失败',
        ),
        'manual' => 
        array (
          'confirm' => '确认执行该 Aegis 操作吗？',
        ),
        'log' => 
        array (
          'meta_missing' => '未找到日志文件',
          'empty' => '-- 无日志行 --',
        ),
        'errors' => 
        array (
          'generic' => '请求失败',
          'player_required' => '请输入玩家名或 GUID',
          'target_required' => '需要目标玩家',
          'load_overview' => '加载概览失败',
          'load_player' => '加载玩家失败',
          'load_offenses' => '加载处罚记录失败',
          'load_events' => '加载事件失败',
          'load_log' => '加载日志失败',
        ),
      ),
    ),
  ),
);
