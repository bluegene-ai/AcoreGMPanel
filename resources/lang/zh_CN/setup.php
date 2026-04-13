<?php
return array (
  'layout' => 
  array (
    'page_title' => '安装向导 - Acore GM Panel',
    'intro' => '按照向导完成环境检测、数据库配置和管理员创建，即可开始使用面板。',
    'step_titles' => 
    array (
      1 => '环境检测',
      2 => '模式与数据库',
      3 => '连接测试',
      4 => '管理员账号',
      5 => '完成',
    ),
    'stepper_label' => '安装步骤',
  ),
  'flash' => 
  array (
    'already_installed' => '系统已安装。如需重新安装，请删除 config/generated/install.lock。',
    'install_success_debug' => '安装成功。若需调试，请编辑 config/generated/app.php 将 debug 改为 true。',
  ),
  'env' => 
  array (
    'title' => '步骤 1 · 环境检测',
    'hint' => '请确认服务器满足运行要求，全部通过后即可选择向导语言。',
    'pill' => '环境',
    'checks' => 
    array (
      'php_version' => 'PHP 版本',
      'pdo_mysql' => 'PDO MySQL 扩展',
      'soap' => 'SOAP 扩展',
      'mbstring' => 'mbstring 扩展',
      'config_writable' => 'config 目录可写',
    ),
    'requirements' => 
    array (
      'writable' => '可写',
    ),
    'messages' => 
    array (
      'write_failed' => '无法写入（请检查目录权限）',
      'create_failed' => '创建失败（检查父目录权限）',
      'created' => '（已创建）',
    ),
    'check_passed' => '检测已全部通过，请选择界面语言后继续。',
    'check_failed' => '存在未通过的检测项，请根据提示完成安装后重试。',
    'retry' => '重新检测',
    'language_title' => '语言选择',
    'language_intro' => '选择向导和面板后续使用的界面语言。',
    'language_hint' => '界面语言 · :code',
    'language_submit' => '下一步：配置模式',
    'language_submit_fail' => '保存语言选择失败，请稍后再试。',
    'invalid_locale' => '语言选择无效。',
  ),
  'mode' => 
  array (
    'step_title' => '步骤 :current / :total · 模式与数据库',
    'section' => 
    array (
      'mode' => 
      array (
        'title' => '选择部署模式',
        'hint' => '根据你的部署形态选择服务器分组方式。',
        'pill' => '模式',
        'aria_group' => '部署模式列表',
      ),
      'server_groups' => 
      array (
        'title' => '服务器分组',
        'hint' => '单服务器和多服多区模式都在这里维护完整的 Auth、Characters、World 与 SOAP 配置。',
        'pill' => '服务器',
        'summary' => '可以继续追加服务器分组，也可以删除多余分组。',
      ),
      'auth' => 
      array (
        'title' => '共享 Auth 数据库',
        'hint' => '一服多区模式下先验证 Auth 库，系统会读取 realmlist 自动生成后续配置组。',
        'pill' => 'Auth',
      ),
      'realm_groups' => 
      array (
        'title' => '自动生成的 Realm 配置',
        'hint' => '验证通过后，将按 realmlist 中的每个区服生成 Characters、World 和 SOAP 配置。',
        'pill' => 'Realm',
      ),
    ),
    'cards' => 
    array (
      'single' => 
      array (
        'title' => '单服务器',
        'badge' => '默认',
        'desc' => '维护一组完整服务器配置，适合单服部署。',
      ),
      'multi' => 
      array (
        'title' => '一服多区',
        'badge' => '共享 Auth',
        'desc' => '先连接共享 Auth 数据库，再根据 realmlist 自动生成每个区服的数据库与 SOAP 配置。',
      ),
      'multi_full' => 
      array (
        'title' => '多服多区',
        'badge' => '完全独立',
        'desc' => '每个服务器分组都维护独立的 Auth、Characters、World 与 SOAP 配置。',
      ),
    ),
    'fields' => 
    array (
      'host' => '主机',
      'port' => '端口',
      'database' => '数据库',
      'user' => '用户名',
      'password' => '密码',
      'uri' => 'URI',
    ),
    'actions' => 
    array (
      'add_server' => '增加服务器',
      'verify' => '验证 Auth 并读取 realmlist',
      'verifying' => '验证中...',
      'request_fail' => '请求失败，请重试。',
      'save_fail' => '保存失败，请检查表单后重试。',
      'unknown_error' => '发生未知错误。',
    ),
    'server' => 
    array (
      'title_prefix' => '服务器 :index',
      'remove' => '移除',
      'name_label' => '服务器名称',
      'name_placeholder' => '示例：主服 / PVP-01',
      'auth_title' => 'Auth 数据库',
      'characters_title' => 'Characters 数据库',
      'world_title' => 'World 数据库',
      'soap_title' => 'SOAP 配置',
    ),
    'realm' => 
    array (
      'title_prefix' => 'Realm :index',
      'empty' => '尚未读取到 Realm。请先完成 Auth 验证。',
      'remove' => '移除',
      'meta' => 
      array (
        'id' => 'ID :value',
        'port' => '端口 :value',
      ),
      'characters' => 
      array (
        'title' => 'Characters 数据库',
      ),
      'world' => 
      array (
        'title' => 'World 数据库',
      ),
      'soap' => 
      array (
        'title' => 'SOAP 配置',
      ),
    ),
    'messages' => 
    array (
      'verify_success' => '验证成功，已生成 :count 个 Realm 配置。',
      'verify_empty' => 'Auth 验证成功，但 realmlist 为空。',
      'verify_fail' => 'Auth 验证失败，请检查数据库连接与权限。',
    ),
    'footer' => 
    array (
      'hint' => '这些设置会直接用于下一步连接测试，安装完成后仍可继续调整。',
      'submit' => '保存并继续',
      'back' => '返回环境检测',
    ),
  ),
  'test' => 
  array (
    'title' => '步骤 :current / :total 连接测试',
    'success' => '全部连接成功。',
    'next_admin' => '下一步：管理员',
    'failure' => '存在失败，请返回修改。',
    'group_ok' => '数据库通过',
    'group_fail' => '数据库失败',
    'soap_warning' => 'soap验证不通过，无法使用部分功能！',
    'back' => '返回修改',
  ),
  'status' => 
  array (
    'ok' => '通过',
    'fail' => '失败',
  ),
  'admin' => 
  array (
    'step_title' => '步骤 :current / :total · 管理员账号',
    'fields' => 
    array (
      'username' => '用户名',
      'password' => '密码',
      'password_confirm' => '确认密码',
    ),
    'submit' => '保存并生成配置',
    'back' => '返回连接测试',
    'save_failed' => '保存管理员配置失败，请稍后再试。',
    'errors' => 
    array (
      'username_required' => '用户名不能为空。',
      'password_required' => '密码不能为空。',
      'password_mismatch' => '两次密码不一致。',
    ),
  ),
  'finish' => 
  array (
    'step_title' => '步骤 :current / :total · 完成',
    'success' => '配置文件生成成功。请删除 /setup 入口（或保持 install.lock）以防重复安装。',
    'enter_panel' => '进入面板',
    'failure' => '生成失败：:errors',
    'back' => '返回管理员步骤',
    'errors' => 
    array (
      'create_config_dir' => '无法创建配置目录：:path',
      'write_failed' => '写入文件失败：:file',
    ),
  ),
  'api' => 
  array (
    'realms' => 
    array (
      'missing_auth_db' => '缺少 Auth 数据库名。',
      'connection_failed' => '连接或查询失败：:error',
    ),
  ),
);
