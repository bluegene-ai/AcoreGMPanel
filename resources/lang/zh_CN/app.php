<?php
return array (
  'app' => 
  array (
    'name' => 'Acore GM 面板',
    'title_suffix' => 'Acore GM 面板',
    'footer_copyright' => '© :year Acore 游戏管理面板',
    'metrics_text' => '耗时 :time · 内存 +:memory',
    'metrics_title' => '页面渲染约 :ms 毫秒，峰值内存 :mb MB',
  ),
  'nav' => 
  array (
    'home' => '首页',
    'account' => '账号',
    'character' => '角色',
    'character_boost' => '直升',
    'item' => '物品',
    'creature' => '生物',
    'quest' => '任务',
    'mail' => '邮件',
    'mass_mail' => '群发',
    'bag' => '背包查询',
    'item_owner' => '物品归属',
    'soap' => 'SOAP',
    'smart_ai' => 'SmartAI',
    'aegis' => 'Aegis 反作弊',
    'logs' => '日志',
  ),
  'common' => 
  array (
    'performance' => '性能',
    'loading' => '加载中…',
    'online_total_label' => '在线 / 总数',
    'online_total_title' => '当前在线玩家 / 该服务器的总角色数',
    'language' => '语言',
    'languages' => 
    array (
      'zh_CN' => '简体中文',
      'en' => 'English',
    ),
    'validation' => 
    array (
      'missing_id' => '缺少 id',
      'missing_ip' => '缺少 IP',
      'missing_player' => '缺少 player',
      'missing_params' => '缺少必要参数',
      'required' => '此字段为必填项',
      'number' => '请输入数字',
      'min' => '不得小于 :min',
      'max' => '不得大于 :max',
      'length_max' => '长度不得超过 :max 个字符',
      'id_required' => '缺少ID',
      'invalid_id' => '无效ID',
      'no_valid_id' => '无有效ID',
    ),
    'errors' => 
    array (
      'query_failed' => '查询失败：:message',
      'database' => '数据库错误：:message',
      'not_found' => '不存在',
    ),
    'api' => 
    array (
      'errors' => 
      array (
        'request_failed' => '请求失败',
        'request_failed_retry' => '请求失败，请稍后再试',
        'request_failed_message' => '请求失败：:message',
        'request_failed_reason' => '请求失败：:reason',
        'unknown' => '未知错误',
        'unauthorized' => '未授权',
        'forbidden' => '无权限执行该操作',
      ),
      'success' => 
      array (
        'generic' => '操作成功',
        'queued' => '任务已加入队列',
      ),
    ),
      'capabilities' => 
      array (
        'page_limited' => '当前账号缺少部分能力，相关操作已隐藏。',
        'section_hidden' => ':section 因缺少所需能力而被隐藏。',
        'read_only' => '当前页面以只读模式提供。',
        'no_actions' => '当前账号没有可用操作。',
      ),
  ),
  'pagination' => 
  array (
    'previous' => '上一页',
    'next' => '下一页',
  ),
  'server' => 
  array (
    'label' => '服务器',
    'default_option' => '服#:id',
  ),
  'database' => 
  array (
    'errors' => 
    array (
      'config_missing' => '数据库配置不存在：:name',
      'connection_failed' => '数据库连接失败：:database @ :host:: :port (:error)',
      'server_config_missing' => '服务器配置不存在：:server (角色 :role)',
    ),
  ),
  'cli' => 
  array (
    'normalize_config' => 
    array (
      'title' => '配置规范化',
      'done' => '配置规范化完成。',
      'missing_dir' => 'config 目录不存在：:path',
      'fixed' => '修复: :file',
      'skipped_failed' => '跳过（替换失败）: :file',
      'summary' => '完成。修复文件: :fixed，未需处理: :skipped',
    ),
  ),
  'errors' => 
  array (
    403 => '禁止访问',
    404 => '页面不存在',
    'internal_server_error_title' => '服务器内部错误',
  ),
  'flags' => 
  array (
    'genders' => 
    array (
      0 => '男',
      1 => '女',
    ),
    'online' => 
    array (
      0 => '离线',
      1 => '在线',
    ),
  ),
  'js' => 
  array (
    'common' => 
    array (
      'loading' => '加载中…',
      'no_data' => '暂无数据',
      'search_placeholder' => '搜索…',
      'errors' => 
      array (
        'network' => '网络异常',
        'timeout' => '请求超时',
        'invalid_json' => '响应格式无效',
        'unknown' => '未知错误',
      ),
      'actions' => 
      array (
        'close' => '关闭',
        'confirm' => '确定',
        'cancel' => '取消',
        'retry' => '重试',
      ),
      'yes' => '是',
      'no' => '否',
    ),
  ),
);
