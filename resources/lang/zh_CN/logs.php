<?php
return array (
  'page_title' => '统一日志管理',
  'intro' => '集中查看面板各模块日志，支持快速筛选、自动刷新和原始输出。',
  'fields' => 
  array (
    'module' => '模块',
    'type' => '类型',
    'limit' => '行数',
  ),
  'actions' => 
  array (
    'load' => '加载',
    'auto_refresh' => '开启自动刷新',
  ),
  'table' => 
  array (
    'headers' => 
    array (
      'time' => '时间',
      'server' => '服务器',
      'actor' => '操作人',
      'summary' => '摘要',
    ),
    'loading' => '加载中…',
  ),
  'raw' => 
  array (
    'title' => '原始日志',
    'empty' => '-- 等待加载 --',
  ),
  'index' => 
  array (
    'page_title' => '统一日志管理',
    'errors' => 
    array (
      'invalid_module' => '模块或类型无效',
      'read_failed' => '读取日志失败：:message',
      'unauthorized' => '未授权',
    ),
  ),
  'manager' => 
  array (
    'pipe_sql' => 
    array (
      'summary' => ':type :status（影响：:affected）',
      'sql_suffix' => ' | :sql',
      'error_suffix' => ' | 错误：:error',
    ),
  ),
  'config' => 
  array (
    'modules' => 
    array (
      'account' => 
      array (
        'label' => '账号',
        'description' => '账号管理相关操作记录。',
        'types' => 
        array (
          'actions' => 
          array (
            'label' => '操作记录',
          ),
        ),
      ),
      'bag_query' => 
      array (
        'label' => '背包查询',
        'description' => '背包/物品查询模块操作记录。',
        'types' => 
        array (
          'actions' => 
          array (
            'label' => '操作记录',
          ),
        ),
      ),
      'item' => 
      array (
        'label' => '物品',
        'description' => '物品编辑与操作相关日志。',
        'types' => 
        array (
          'sql' => 
          array (
            'label' => 'SQL 执行',
          ),
          'actions' => 
          array (
            'label' => '操作记录',
          ),
          'deleted' => 
          array (
            'label' => '删除记录',
          ),
        ),
      ),
      'item_owner' => 
      array (
        'label' => '物品归属',
        'description' => '批量删除与替换历史。',
        'types' => 
        array (
          'actions' => 
          array (
            'label' => '操作记录',
          ),
        ),
      ),
      'creature' => 
      array (
        'label' => '生物',
        'description' => '生物编辑相关 SQL 日志。',
        'types' => 
        array (
          'sql' => 
          array (
            'label' => 'SQL 执行',
          ),
        ),
      ),
      'quest' => 
      array (
        'label' => '任务',
        'description' => '任务编辑相关日志。',
        'types' => 
        array (
          'sql' => 
          array (
            'label' => 'SQL 执行',
          ),
          'deleted' => 
          array (
            'label' => '删除记录',
          ),
        ),
      ),
      'mail' => 
      array (
        'label' => '邮件',
        'description' => '邮件模块 SQL 与删除日志。',
        'types' => 
        array (
          'sql' => 
          array (
            'label' => 'SQL 执行',
          ),
          'deleted' => 
          array (
            'label' => '删除记录',
          ),
        ),
      ),
      'massmail' => 
      array (
        'label' => '群发',
        'description' => '群发模块执行记录。',
        'types' => 
        array (
          'actions' => 
          array (
            'label' => '操作记录',
          ),
        ),
      ),
      'server' => 
      array (
        'label' => '服务器',
        'description' => '服务器切换与调试输出。',
        'types' => 
        array (
          'debug' => 
          array (
            'label' => '调试日志',
          ),
        ),
      ),
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'logs' => 
      array (
        'summary' => 
        array (
          'module' => '模块：',
          'type' => '类型：',
          'source' => '来源文件：',
          'display' => '当前显示：',
          'separator' => ' ｜ ',
        ),
        'status' => 
        array (
          'no_entries' => '暂无日志',
          'panel_not_ready' => 'Panel API 未就绪，请检查 panel.js 是否加载成功。',
          'panel_waiting' => 'Panel API 初始化中，请稍候…',
          'load_failed' => '加载失败',
          'no_raw' => '-- 无日志 --',
          'request_error' => '请求异常',
          'exception_prefix' => '[异常] ',
          'error_prefix' => '[错误] ',
          'info_prefix' => '[信息] ',
        ),
        'actions' => 
        array (
          'auto_on' => '开启自动刷新',
          'auto_off' => '关闭自动刷新',
        ),
      ),
    ),
  ),
);
