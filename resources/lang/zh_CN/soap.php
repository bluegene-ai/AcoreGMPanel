<?php
return array (
  'page_title' => 'SOAP 命令向导',
  'intro' => '基于 AzerothCore GM 指令整理，选择命令后按向导填写参数并执行。',
  'search_label' => '搜索命令',
  'search_placeholder' => '输入关键字或命令片段',
  'summary' => 
  array (
    'title' => '请选择命令',
    'hint' => '在左侧选择分类与命令，可使用搜索快速定位。支持服务器切换，通过顶部下拉选择目标 Realm。',
  ),
  'target_hint' => '需要在游戏中选中目标后再执行。',
  'steps' => 
  array (
    'fill' => '步骤 1：填写参数',
    'confirm' => '步骤 2：确认命令',
  ),
  'preview_label' => '即将执行',
  'actions' => 
  array (
    'copy' => '复制命令',
    'execute' => '执行命令',
  ),
  'output_title' => '执行结果',
  'legacy' => 
  array (
    'errors' => 
    array (
      'curl_failed' => '无法连接到 SOAP 端点。',
      'curl_error_unknown' => '未知的 cURL 错误。',
      'http_error' => 'SOAP 请求返回 HTTP 状态 :code。',
    ),
  ),
  'api' => 
  array (
    'errors' => 
    array (
      'unauthorized' => '未授权',
      'invalid_arguments' => '参数无效',
    ),
  ),
  'modules' => 
  array (
    'soap' => 
    array (
      'feedback' => 
      array (
        'execute_success' => '执行成功',
        'execute_failed' => '执行失败',
      ),
    ),
  ),
  'wizard' => 
  array (
    'errors' => 
    array (
      'command_not_found' => '未找到命令定义',
      'command_missing' => '命令未收录或已下线',
      'argument_required' => '必填项',
      'validation_failed' => '参数校验失败',
      'template_missing_list' => '模板缺少参数：:fields',
      'template_incomplete' => '命令模板未完成',
      'number_required' => '请输入数值',
      'number_invalid' => '必须是数字',
      'number_too_small' => '不可小于 :min',
      'number_too_large' => '不可大于 :max',
      'invalid_option' => '选项无效',
    ),
    'catalog' => 
    array (
      'categories' => 
      array (
        'general' => 
        array (
          'label' => '常规',
          'summary' => '服务器状态、公告与 GM 自身工具',
        ),
        'account' => 
        array (
          'label' => '账号管理',
          'summary' => '账号 GM 等级、锁定与封禁操作',
        ),
        'character' => 
        array (
          'label' => '角色管理',
          'summary' => '角色等级、外观及状态控制',
        ),
        'teleport' => 
        array (
          'label' => '传送/位置',
          'summary' => '角色或 GM 的传送、定位类命令',
        ),
        'item' => 
        array (
          'label' => '物品/装备',
          'summary' => '为目标添加或移除物品',
        ),
        'spell' => 
        array (
          'label' => '法术/技能',
          'summary' => '授予或移除技能、天赋等',
        ),
        'quest' => 
        array (
          'label' => '任务',
          'summary' => '赋予、完成或移除任务',
        ),
        'misc' => 
        array (
          'label' => '外观/状态',
          'summary' => '改变模型、添加金钱等杂项命令',
        ),
      ),
      'commands' => 
      array (
        'server-info' => 
        array (
          'description' => '显示服务器核心、构建时间、在线人数与正常运行时间等信息。',
        ),
        'server-motd' => 
        array (
          'description' => '查看或修改服务器 MOTD（登录公告）。',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => '公告内容',
              'placeholder' => '留空则显示当前 MOTD',
            ),
          ),
        ),
        'announce-global' => 
        array (
          'description' => '向全服玩家广播一条系统公告。',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => '公告内容',
              'placeholder' => '请输入要广播的内容',
            ),
          ),
        ),
        'announce-name' => 
        array (
          'description' => '以 GM 名义发送公告（会展示 GM 名称）。',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => '公告内容',
            ),
          ),
        ),
        'notify' => 
        array (
          'description' => '向全服弹出屏幕中部提示信息。',
          'arguments' => 
          array (
            'message' => 
            array (
              'label' => '提示内容',
            ),
          ),
        ),
        'gm-visible' => 
        array (
          'description' => '切换 GM 自身在世界中的可见性。',
          'arguments' => 
          array (
            'state' => 
            array (
              'label' => '状态',
              'options' => 
              array (
                'on' => 'on - 进入 GM 隐身',
                'off' => 'off - 退出 GM 隐身',
              ),
            ),
          ),
          'executor' => 
          array (
            'errors' => 
            array (
              'empty' => '命令不能为空',
              'not_whitelisted' => '命令未被列入白名单',
              'request_failed' => '请求失败',
              'unknown' => '未知错误',
            ),
          ),
        ),
        'account-set-gmlevel' => 
        array (
          'description' => '设置账号的 GM 等级。',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => '账号用户名',
            ),
            'level' => 
            array (
              'label' => 'GM 等级',
              'options' => 
              array (
                0 => '0 - 普通玩家',
                1 => '1 - 见习 GM',
                2 => '2 - 完整 GM',
                3 => '3 - 管理员',
              ),
            ),
            'realm' => 
            array (
              'label' => 'Realm ID (可选)',
              'placeholder' => '留空则对所有 Realm 生效',
            ),
          ),
        ),
        'account-set-password' => 
        array (
          'description' => '重置账号密码。',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => '账号用户名',
            ),
            'password' => 
            array (
              'label' => '新密码',
            ),
          ),
        ),
        'account-lock' => 
        array (
          'description' => '启用或取消账号锁定。',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => '账号用户名',
            ),
            'state' => 
            array (
              'label' => '锁定状态',
              'options' => 
              array (
                'on' => 'on - 锁定登陆',
                'off' => 'off - 解除锁定',
              ),
            ),
          ),
        ),
        'ban-account' => 
        array (
          'description' => '封禁账号指定时长并记录原因。',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => '账号用户名',
            ),
            'duration' => 
            array (
              'label' => '封禁时长',
              'placeholder' => '例如 3d 或 12h 或 permanent',
            ),
            'reason' => 
            array (
              'label' => '原因 (可选)',
            ),
          ),
        ),
        'unban-account' => 
        array (
          'description' => '解除账号封禁。',
          'arguments' => 
          array (
            'account' => 
            array (
              'label' => '账号用户名',
            ),
          ),
        ),
        'character-level' => 
        array (
          'description' => '设置指定角色等级。',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => '角色名',
            ),
            'level' => 
            array (
              'label' => '等级',
            ),
          ),
        ),
        'character-rename' => 
        array (
          'description' => '强制角色下次登录时改名。',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => '角色名',
            ),
          ),
        ),
        'character-customize' => 
        array (
          'description' => '强制角色登录时进行外观自定义。',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => '角色名',
            ),
          ),
        ),
        'character-revive' => 
        array (
          'description' => '复活已死亡角色。',
          'arguments' => 
          array (
            'name' => 
            array (
              'label' => '角色名',
            ),
          ),
        ),
        'character-lookup' => 
        array (
          'description' => '按名称搜索角色。',
          'arguments' => 
          array (
            'pattern' => 
            array (
              'label' => '角色名关键字',
            ),
          ),
        ),
        'tele-name' => 
        array (
          'description' => '传送到预设地点（需在数据库中存在）。',
          'arguments' => 
          array (
            'location' => 
            array (
              'label' => '地点名称',
            ),
          ),
        ),
        'tele-worldport' => 
        array (
          'description' => '传送到指定地图坐标。使用前确认坐标有效。',
          'arguments' => 
          array (
            'map' => 
            array (
              'label' => '地图ID',
            ),
            'x' => 
            array (
              'label' => 'X 坐标',
            ),
            'y' => 
            array (
              'label' => 'Y 坐标',
            ),
            'z' => 
            array (
              'label' => 'Z 坐标',
            ),
            'o' => 
            array (
              'label' => '朝向 (可选)',
            ),
          ),
          'notes' => 
          array (
            'ensure_valid' => '确保坐标合法，否则可能掉线或卡死。',
          ),
        ),
        'go-creature' => 
        array (
          'description' => '传送到指定生物 GUID 所在位置。',
          'arguments' => 
          array (
            'guid' => 
            array (
              'label' => '生物 GUID',
            ),
          ),
        ),
        'go-object' => 
        array (
          'description' => '传送到指定游戏对象 GUID 所在位置。',
          'arguments' => 
          array (
            'guid' => 
            array (
              'label' => '对象 GUID',
            ),
          ),
        ),
        'summon-player' => 
        array (
          'description' => '将指定玩家传送到 GM 身边。',
          'arguments' => 
          array (
            'player' => 
            array (
              'label' => '玩家名称',
            ),
          ),
          'notes' => 
          array (
            'require_online' => '需要玩家在线。',
          ),
        ),
        'additem' => 
        array (
          'description' => '为当前选中目标添加物品。目标需为玩家角色。',
          'arguments' => 
          array (
            'item' => 
            array (
              'label' => '物品ID',
            ),
            'count' => 
            array (
              'label' => '数量 (可选)',
            ),
          ),
        ),
        'additemset' => 
        array (
          'description' => '为当前选中角色添加整套物品套装。',
          'arguments' => 
          array (
            'itemset' => 
            array (
              'label' => '套装ID',
            ),
          ),
        ),
        'removeitem' => 
        array (
          'description' => '从当前目标背包中移除指定物品。',
          'arguments' => 
          array (
            'item' => 
            array (
              'label' => '物品ID',
            ),
            'count' => 
            array (
              'label' => '数量 (可选)',
            ),
          ),
        ),
        'learn-spell' => 
        array (
          'description' => '令当前选中角色学习某个法术或技能。',
          'arguments' => 
          array (
            'spell' => 
            array (
              'label' => '法术ID',
            ),
          ),
        ),
        'unlearn-spell' => 
        array (
          'description' => '移除当前目标的某个法术或技能。',
          'arguments' => 
          array (
            'spell' => 
            array (
              'label' => '法术ID',
            ),
          ),
        ),
        'talent-reset' => 
        array (
          'description' => '重置当前目标的天赋。',
        ),
        'quest-add' => 
        array (
          'description' => '给予当前目标一个任务。',
          'arguments' => 
          array (
            'quest' => 
            array (
              'label' => '任务ID',
            ),
          ),
        ),
        'quest-complete' => 
        array (
          'description' => '直接完成当前目标的任务。',
          'arguments' => 
          array (
            'quest' => 
            array (
              'label' => '任务ID',
            ),
          ),
        ),
        'quest-remove' => 
        array (
          'description' => '从当前目标移除指定任务。',
          'arguments' => 
          array (
            'quest' => 
            array (
              'label' => '任务ID',
            ),
          ),
        ),
        'morph' => 
        array (
          'description' => '将当前目标变形为指定模型。',
          'arguments' => 
          array (
            'display' => 
            array (
              'label' => '模型显示ID',
            ),
          ),
        ),
        'demorph' => 
        array (
          'description' => '恢复当前目标的原始模型。',
        ),
        'modify-money' => 
        array (
          'description' => '为当前目标增加或减少铜币数，正数为增加，负数为移除。',
          'arguments' => 
          array (
            'amount' => 
            array (
              'label' => '铜币数量（可为负）',
              'placeholder' => '例如 100000 (10金) 或 -5000',
            ),
          ),
        ),
        'modify-speed' => 
        array (
          'description' => '调整当前目标的移动速度。',
          'arguments' => 
          array (
            'multiplier' => 
            array (
              'label' => '速度倍率',
              'placeholder' => '1 为正常，2 为双倍',
            ),
          ),
        ),
      ),
    ),
  ),
  'soap' => 
  array (
    'meta' => 
    array (
      'updated_at' => '指令表更新于 :date',
      'source_link' => 'GM Commands',
      'source_label' => '参考：:link',
      'separator' => ' · ',
    ),
    'categories' => 
    array (
      'all' => 
      array (
        'label' => '全部命令',
        'summary' => '显示所有收录的命令',
      ),
    ),
    'list' => 
    array (
      'empty' => '未找到匹配的命令',
    ),
    'risk' => 
    array (
      'badge' => 
      array (
        'low' => '低风险',
        'medium' => '中风险',
        'high' => '高风险',
        'unknown' => '未知风险',
      ),
      'short' => 
      array (
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'unknown' => '？',
      ),
    ),
    'fields' => 
    array (
      'empty' => '此命令无需额外参数。',
    ),
    'errors' => 
    array (
      'missing_required' => '存在未填写的必填参数。',
      'unknown_response' => '未知响应',
    ),
    'form' => 
    array (
      'error_joiner' => '、',
    ),
    'feedback' => 
    array (
      'execute_success' => '执行成功',
      'execute_failed' => '执行失败',
    ),
    'output' => 
    array (
      'unknown_time' => '未知耗时',
      'meta' => '状态：:code · 耗时：:time',
      'empty' => '(无输出)',
    ),
    'copy' => 
    array (
      'empty' => '暂无命令可复制',
      'success' => '已复制到剪贴板',
      'failure' => '复制失败',
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'soap' => 
      array (
        'meta' => 
        array (
          'updated_at' => '指令表更新于 :date',
          'source_link' => 'GM Commands',
          'source_label' => '参考：:link',
          'separator' => ' · ',
        ),
        'categories' => 
        array (
          'all' => 
          array (
            'label' => '全部命令',
            'summary' => '显示所有收录的命令',
          ),
        ),
        'list' => 
        array (
          'empty' => '未找到匹配的命令',
        ),
        'risk' => 
        array (
          'badge' => 
          array (
            'low' => '低风险',
            'medium' => '中风险',
            'high' => '高风险',
            'unknown' => '未知风险',
          ),
          'short' => 
          array (
            'low' => '低',
            'medium' => '中',
            'high' => '高',
            'unknown' => '？',
          ),
        ),
        'fields' => 
        array (
          'empty' => '此命令无需额外参数。',
        ),
        'errors' => 
        array (
          'missing_required' => '存在未填写的必填参数。',
          'unknown_response' => '未知响应',
        ),
        'form' => 
        array (
          'error_joiner' => '、',
        ),
        'feedback' => 
        array (
          'execute_success' => '执行成功',
          'execute_failed' => '执行失败',
        ),
        'output' => 
        array (
          'unknown_time' => '未知耗时',
          'meta' => '状态：:code · 耗时：:time',
          'empty' => '(无输出)',
        ),
        'copy' => 
        array (
          'empty' => '暂无命令可复制',
          'success' => '已复制到剪贴板',
          'failure' => '复制失败',
        ),
      ),
    ),
  ),
);
