<?php
return array (
  'page_title' => '账号管理',
  'search' => 
  array (
    'type_username' => '按用户名',
    'type_id' => '按ID',
    'placeholder' => '搜索…',
    'submit' => '查询',
    'load_all' => '加载全部账号',
    'create' => '新增账号',
  ),
  'filters' => 
  array (
    'online' => '在线状态',
    'online_any' => '全部账号',
    'online_only' => '仅在线',
    'online_offline' => '仅离线',
    'ban' => '封禁状态',
    'ban_any' => '全部账号',
    'ban_only' => '仅封禁',
    'ban_unbanned' => '未封禁',
    'exclude_username' => '排除字符',
    'exclude_username_placeholder' => '如：test',
  ),
  'feedback' => 
  array (
    'found' => '找到 :total 条记录，当前第 :page / :pages 页。',
    'empty' => '无结果',
    'enter_search' => '请输入搜索条件',
    'private_ip_disabled' => '内网IP不可查询',
  ),
  'table' => 
  array (
    'id' => 'ID',
    'username' => '用户名',
    'gm' => 'GM',
    'online' => '在线',
    'last_login' => '最后登录',
    'last_ip' => '最后IP',
    'ip_location' => 'IP归属地',
    'actions' => '操作',
  ),
  'status' => 
  array (
    'online' => '在线',
    'offline' => '离线',
  ),
  'actions' => 
  array (
    'chars' => '角色',
    'gm' => 'GM',
    'ban' => '封禁',
    'unban' => '解封',
    'password' => '改密',
    'email' => '改邮箱',
    'rename' => '改用户名',
    'same_ip' => '同IP账号',
    'kick' => '踢',
    'delete' => '删除',
  ),
  'bulk' => 
  array (
    'select_all' => '全选',
    'delete' => '批量删除',
    'ban' => '批量封禁',
    'unban' => '批量解封',
    'no_selection' => '请先选择至少一项',
  ),
  'delete' => 
  array (
    'confirm' => '确认删除该账号？此操作将同时删除该账号下的全部角色，且不可恢复。',
    'success' => '删除成功',
    'blocked_online' => '账号下存在在线角色（:name），请先踢下线。',
    'characters_failed' => '删除角色失败：:message',
    'account_failed' => '删除账号失败：:message',
  ),
  'email' => 
  array (
    'title' => '修改邮箱 - :name',
    'labels' => 
    array (
      'email' => '邮箱',
    ),
    'placeholders' => 
    array (
      'email' => 'example@domain.com',
    ),
    'actions' => 
    array (
      'cancel' => '取消',
      'submit' => '保存',
    ),
    'invalid' => '邮箱格式不正确',
    'not_supported' => '当前数据库不支持邮箱字段',
    'blocked_online' => '账号在线时不允许修改邮箱',
    'success' => '邮箱已更新',
  ),
  'rename' => 
  array (
    'title' => '修改用户名 - :name',
    'labels' => 
    array (
      'username' => '新用户名',
      'password' => '新密码',
      'password_confirm' => '确认密码',
    ),
    'actions' => 
    array (
      'cancel' => '取消',
      'submit' => '保存',
    ),
    'invalid_username' => '用户名无效（1-20字符）',
    'invalid_password' => '密码长度至少 8 位',
    'password_mismatch' => '两次输入的密码不一致',
    'password_reset_failed' => '重置密码失败（无法生成 verifier）',
    'blocked_online' => '账号在线时不允许修改用户名',
    'taken' => '该用户名已被占用',
    'success' => '用户名已更新（:old → :new）',
  ),
  'ban' => 
  array (
    'badge' => '已封禁 (:duration)',
    'tooltip' => '封禁原因: :reason
开始: :start
结束: :end',
    'no_end' => '永久',
    'permanent' => '永久',
    'soon' => '即将解除',
    'under_minute' => '不足1分钟',
    'separator' => '',
    'duration' => 
    array (
      'day' => ':value天',
      'hour' => ':value小时',
      'minute' => ':value分钟',
    ),
    'prompt_hours' => '封禁时长（小时，0 = 永久）：',
    'error_hours' => '封禁时长无效',
    'prompt_reason' => '封禁理由：',
    'default_reason' => 'Panel 封禁',
    'success' => '封禁成功',
    'failure' => '封禁失败',
    'confirm_unban' => '确认解封该账号？',
    'unban_success' => '解封完成',
    'unban_failure' => '解封失败',
  ),
  'ip_lookup' => 
  array (
    'private' => '内网IP',
    'failed' => '查询失败',
    'unknown' => '未知归属地',
    'loading' => '查询中…',
  ),
  'characters' => 
  array (
    'title' => '角色列表 - :name',
    'loading' => '加载中…',
    'fetch_error' => '拉取角色失败',
    'table' => 
    array (
      'guid' => 'GUID',
      'name' => '名称',
      'level' => '等级',
      'status' => '状态',
    ),
    'kick_button' => '踢下线',
    'offline_tooltip' => '角色已离线，无法踢下线',
    'empty' => '无角色',
    'ban_badge' => '已封禁',
    'confirm_kick' => '确认踢出角色 :name？',
    'kick_success' => '已发送踢出命令：:name',
    'kick_failed' => '踢出失败：:message',
    'fetch_failed' => '角色拉取失败：:message',
  ),
  'gm' => 
  array (
    'prompt_level' => '设置 GM 级别 (0-6)：',
    'error_level' => 'GM 级别无效',
    'success' => 'GM 等级已更新',
    'failure' => 'GM 等级更新失败',
  ),
  'password' => 
  array (
    'prompt_new' => '输入新密码（至少 8 位）：',
    'error_empty' => '密码不能为空',
    'error_length' => '密码长度至少 8 位',
    'prompt_confirm' => '再次输入新密码：',
    'error_mismatch' => '两次输入不一致',
    'success' => '密码修改成功（旧会话已失效）',
    'failure' => '改密失败：:message',
    'failure_generic' => '未知错误',
  ),
  'create' => 
  array (
    'title' => '新增账号',
    'labels' => 
    array (
      'username' => '用户名',
      'password' => '登录密码',
      'password_confirm' => '确认密码',
      'email' => '邮箱（可选）',
      'gmlevel' => 'GM 等级',
    ),
    'placeholders' => 
    array (
      'username' => '不区分大小写',
      'password' => '至少 8 位',
      'password_confirm' => '再次输入',
      'email' => 'example@domain.com',
    ),
    'gm_options' => 
    array (
      'player' => '0 - 玩家',
      'one' => '1',
      'two' => '2',
      'three' => '3',
    ),
    'actions' => 
    array (
      'cancel' => '取消',
      'submit' => '创建',
    ),
    'status' => 
    array (
      'submitting' => '创建中…',
    ),
    'errors' => 
    array (
      'username_required' => '请输入用户名',
      'username_length' => '用户名长度超出限制',
      'password_length' => '密码至少需要 8 位',
      'password_mismatch' => '两次输入的密码不一致',
      'email_length' => '邮箱长度超出限制',
      'email_invalid' => '邮箱格式不正确',
      'request_generic' => '创建失败',
    ),
    'success' => '账号创建成功：:name',
  ),
  'same_ip' => 
  array (
    'missing_ip' => '该账号暂无最后登录 IP',
    'title' => '同 IP 账号 - :ip',
    'loading' => '查询中…',
    'empty' => '没有其他账号使用该 IP',
    'table' => 
    array (
      'id' => 'ID',
      'username' => '用户名',
      'gm' => 'GM',
      'status' => '状态',
      'last_login' => '最后登录',
      'ip_location' => 'IP 归属地',
    ),
    'status' => 
    array (
      'banned' => '封禁',
      'remaining' => '剩余：:value',
    ),
    'error_generic' => '查询失败',
    'error' => '查询失败：:message',
  ),
  'api' => 
  array (
    'validation' => 
    array (
      'username_min' => '用户名至少需要 3 个字符',
      'username_max' => '用户名长度超出限制',
      'password_min' => '密码至少需要 8 位',
      'gm_range' => 'GM 级别必须在 0-6 之间',
    ),
    'defaults' => 
    array (
      'no_reason' => '无理由',
    ),
    'errors' => 
    array (
      'missing_username_column' => 'account 表缺少 username 列',
      'username_exists' => '用户名已存在',
      'build_columns_failed' => '无法构建账号插入列集合',
      'missing_account_id' => '无法获取账号ID',
      'password_set_failed' => '设置密码失败',
      'create_failed' => '创建账号失败: :message',
      'query_characters_failed' => '查询角色失败: :message',
      'password_schema_unsupported' => '修改失败：账号结构不支持当前方式 (缺少 v/s 或 sha_pass_hash)',
    ),
  ),
);
