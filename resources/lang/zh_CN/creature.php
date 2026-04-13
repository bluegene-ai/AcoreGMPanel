<?php
return array (
  'index' => 
  array (
    'page_title' => '生物管理',
    'filters' => 
    array (
      'search_type' => 
      array (
        'name' => '按名称搜索',
        'id' => '按 ID 搜索',
      ),
      'placeholders' => 
      array (
        'search_value' => '关键字或 ID',
        'min_level' => '最低等级',
        'max_level' => '最高等级',
      ),
      'buttons' => 
      array (
        'search' => '搜索',
        'reset' => '重置',
        'create' => '创建',
        'log' => '查看日志',
      ),
    ),
    'npcflag' => 
    array (
      'summary' => 'NPC 标志筛选',
      'apply' => '应用',
      'clear' => '清除',
      'mode_hint' => '模式：所有选定位必须同时存在（AND）',
    ),
    'table' => 
    array (
      'headers' => 
      array (
        'id' => 'ID',
        'name' => '名称',
        'subname' => '副标题',
        'min_level' => '最低等级',
        'max_level' => '最高等级',
        'faction' => '阵营',
        'npcflag' => 'NPC 标志',
        'actions' => '操作',
        'verify' => '校验',
      ),
      'actions' => 
      array (
        'edit' => '编辑',
        'delete' => '删除',
      ),
      'verify_button' => '校验',
      'empty' => '暂无结果',
    ),
    'modals' => 
    array (
      'new' => 
      array (
        'title' => '创建生物',
        'id_label' => '新建 ID*',
        'copy_label' => '复制来源（可选）',
        'copy_hint' => '复制 ID 留空将创建空白模板。',
        'cancel' => '取消',
        'confirm' => '创建',
      ),
      'log' => 
      array (
        'title' => '生物日志',
        'type_label' => '日志类型',
        'types' => 
        array (
          'sql' => 'SQL 执行',
          'deleted' => '删除快照',
          'actions' => '操作记录',
        ),
        'refresh' => '刷新',
        'empty' => '-- 暂无日志 --',
        'close' => '关闭',
      ),
      'verify' => 
      array (
        'title' => '行数据校验',
        'headers' => 
        array (
          'field' => '字段',
          'rendered' => '渲染值',
          'database' => '数据库值',
          'status' => '状态',
        ),
        'close' => '关闭',
        'copy_sql' => '复制 UPDATE 语句',
      ),
    ),
  ),
  'edit' => 
  array (
    'title' => '编辑生物 #:id',
    'actions' => 
    array (
      'back' => '返回列表',
      'compact' => '切换紧凑视图',
      'delete' => '删除',
      'save' => '保存',
      'diff_sql' => '差异 SQL',
      'exec_sql' => '执行 SQL',
      'copy' => '复制',
      'execute' => '执行',
      'add_model' => '添加模型',
      'edit_model' => '编辑',
      'delete_model' => '删除',
      'cancel' => '取消',
    ),
    'labels' => 
    array (
      'only_changes' => '仅显示修改',
    ),
    'toolbar' => 
    array (
      'changed_fields' => '修改字段：',
    ),
    'diff' => 
    array (
      'title' => '差异 SQL 预览',
      'hint' => '点击“差异 SQL”生成；仅列出已修改的列。空字符串会转为 NULL，并自动附加 LIMIT 1。',
      'placeholder' => '-- 尚未生成 --',
    ),
    'models' => 
    array (
      'heading' => '模型列表（creature_template_model）',
      'table' => 
      array (
        'index' => '索引',
        'display_id' => '显示 ID',
        'scale' => '缩放',
        'probability' => '概率',
        'verified_build' => '验证版本',
        'actions' => '操作',
      ),
      'empty' => '暂无模型',
    ),
    'modal' => 
    array (
      'title' => '模型',
      'display_id' => '显示 ID',
      'scale' => '缩放',
      'probability' => '概率（0-1）',
      'verified_build' => '验证版本',
    ),
    'rank_enum' => 
    array (
      0 => '普通',
      1 => '精英',
      2 => '稀有精英',
      3 => '首领',
      4 => '稀有',
    ),
    'type_enum' => 
    array (
      0 => '无',
      1 => '野兽',
      2 => '龙类',
      3 => '恶魔',
      4 => '元素',
      5 => '巨人',
      6 => '亡灵',
      7 => '人型',
      8 => '小动物',
      9 => '机械',
      10 => '未指定',
      11 => '图腾',
      12 => '伴生物',
      13 => '气体形态',
    ),
  ),
  'config' => 
  array (
    'groups' => 
    array (
      'base' => 
      array (
        'label' => '基础信息',
        'fields' => 
        array (
          'name' => 
          array (
            'label' => '名称',
          ),
          'subname' => 
          array (
            'label' => '副标题',
          ),
          'minlevel' => 
          array (
            'label' => '最低等级',
          ),
          'maxlevel' => 
          array (
            'label' => '最高等级',
          ),
          'exp' => 
          array (
            'label' => '经验类型 (exp)',
            'help' => '0=无，1=普通，2=精英',
          ),
          'faction' => 
          array (
            'label' => '阵营 ID (faction)',
          ),
          'scale' => 
          array (
            'label' => '模型缩放 (scale)',
          ),
          'speed_walk' => 
          array (
            'label' => '步行速度 (speed_walk)',
          ),
          'speed_run' => 
          array (
            'label' => '跑步速度 (speed_run)',
          ),
          'rank' => 
          array (
            'label' => '等级类型 (rank)',
          ),
          'type' => 
          array (
            'label' => '生物类型 (type)',
          ),
        ),
      ),
      'combat' => 
      array (
        'label' => '战斗参数',
        'fields' => 
        array (
          'dmgschool' => 
          array (
            'label' => '伤害系别 (dmgschool)',
          ),
          'baseattacktime' => 
          array (
            'label' => '近战攻击间隔 (ms)',
          ),
          'rangeattacktime' => 
          array (
            'label' => '远程攻击间隔 (ms)',
          ),
          'mindmg' => 
          array (
            'label' => '近战最小伤害 (mindmg)',
          ),
          'maxdmg' => 
          array (
            'label' => '近战最大伤害 (maxdmg)',
          ),
          'dmg_multiplier' => 
          array (
            'label' => '伤害倍率 (dmg_multiplier)',
          ),
          'basevariance' => 
          array (
            'label' => '伤害浮动 (basevariance)',
          ),
          'rangevariance' => 
          array (
            'label' => '远程伤害浮动 (rangevariance)',
          ),
          'attackpower' => 
          array (
            'label' => '近战攻击强度 (attackpower)',
          ),
          'rangedattackpower' => 
          array (
            'label' => '远程攻击强度 (rangedattackpower)',
          ),
        ),
      ),
      'vitals' => 
      array (
        'label' => '生命 / 法力 / 抗性',
        'fields' => 
        array (
          'healthmodifier' => 
          array (
            'label' => '生命倍率 (healthmodifier)',
          ),
          'manamodifier' => 
          array (
            'label' => '法力倍率 (manamodifier)',
          ),
          'armormodifier' => 
          array (
            'label' => '护甲倍率 (armormodifier)',
          ),
          'resistance1' => 
          array (
            'label' => '神圣抗性 (resistance1)',
          ),
          'resistance2' => 
          array (
            'label' => '火焰抗性 (resistance2)',
          ),
          'resistance3' => 
          array (
            'label' => '自然抗性 (resistance3)',
          ),
          'resistance4' => 
          array (
            'label' => '冰霜抗性 (resistance4)',
          ),
          'resistance5' => 
          array (
            'label' => '暗影抗性 (resistance5)',
          ),
          'resistance6' => 
          array (
            'label' => '奥术抗性 (resistance6)',
          ),
        ),
      ),
      'drops' => 
      array (
        'label' => '掉落 / 经济',
        'fields' => 
        array (
          'lootid' => 
          array (
            'label' => '常规掉落 ID (lootid)',
          ),
          'pickpocketloot' => 
          array (
            'label' => '偷窃掉落 ID (pickpocketloot)',
          ),
          'skinloot' => 
          array (
            'label' => '剥皮掉落 ID (skinloot)',
          ),
          'mingold' => 
          array (
            'label' => '最小金币 (mingold)',
          ),
          'maxgold' => 
          array (
            'label' => '最大金币 (maxgold)',
          ),
        ),
      ),
      'ai' => 
      array (
        'label' => 'AI / 脚本',
        'fields' => 
        array (
          'ainame' => 
          array (
            'label' => 'AI 名称 (ainame)',
          ),
          'scriptname' => 
          array (
            'label' => '脚本名 (scriptname)',
          ),
          'gossip_menu_id' => 
          array (
            'label' => '闲聊菜单 ID (gossip_menu_id)',
          ),
          'movementtype' => 
          array (
            'label' => '移动类型 (movementtype)',
          ),
        ),
      ),
    ),
    'flags' => 
    array (
      'label' => '标志 / 位字段',
      'fields' => 
      array (
        'npcflag' => 
        array (
          'label' => 'NPC 标志 (npcflag)',
        ),
        'unit_flags' => 
        array (
          'label' => '单位标志 (unit_flags)',
        ),
        'unit_flags2' => 
        array (
          'label' => '单位标志 2 (unit_flags2)',
        ),
        'type_flags' => 
        array (
          'label' => '类型标志 (type_flags)',
        ),
        'flags_extra' => 
        array (
          'label' => '额外标志 (flags_extra)',
        ),
        'dynamicflags' => 
        array (
          'label' => '动态标志 (dynamicflags)',
        ),
      ),
    ),
  ),
  'flags' => 
  array (
    'npcflag' => 
    array (
      0 => '闲聊',
      1 => '任务发布',
      2 => '训练师',
      3 => '职业训练师',
      4 => '专业训练师',
      5 => '商人',
      6 => '弹药商',
      7 => '食物商',
      8 => '毒药商',
      9 => '材料商',
      10 => '修理',
      11 => '飞行管理员',
      12 => '灵魂医者',
      13 => '灵魂向导',
      14 => '旅店老板',
      15 => '银行',
      16 => '公会申请',
      17 => '战袍设计师',
      18 => '战场军官',
      19 => '拍卖师',
      20 => '兽栏管理员',
      21 => '公会银行',
      22 => '法术点击',
      23 => '邮箱',
    ),
    'unit_flags' => 
    array (
      0 => '服务器控制',
      1 => '不可被攻击',
      2 => '移除客户端控制',
      3 => '玩家控制',
      4 => '可重命名',
      5 => '准备状态',
      6 => '未知 6',
      7 => '不可攻击 1',
      8 => '对玩家免疫',
      9 => '对 NPC 免疫',
      10 => '拾取中',
      11 => '宠物战斗中',
      12 => 'PvP',
      13 => '沉默',
      14 => '不能游泳',
      15 => '可以游泳',
      16 => '不可攻击 2',
      17 => '被安抚',
      18 => '眩晕',
      19 => '战斗中',
      20 => '飞行/运输中',
      21 => '被缴械',
    ),
    'unit_flags2' => 
    array (
      0 => '假死',
      1 => '隐藏尸体',
      2 => '未知 2',
      3 => '不可选取',
      4 => '死亡可交互',
      5 => '强制移动',
      6 => '副手缴械',
      7 => '未知 7',
      8 => '可更换天赋',
      9 => '不可嘲讽',
    ),
    'type_flags' => 
    array (
      0 => '可驯服',
      1 => '幽灵可见',
      2 => '首领',
      3 => '不播放受伤/招架动画',
      4 => '无战利品',
      5 => '无经验',
      6 => '触发器',
      7 => '守卫',
    ),
    'flags_extra' => 
    array (
      0 => '副本绑定',
      1 => '平民',
      2 => '不产生仇恨',
      3 => '不可交互',
      4 => '可驯服宠物',
      5 => '死亡可交互',
      6 => '强制对话',
    ),
    'dynamicflags' => 
    array (
      0 => '发光',
      1 => '可拾取',
      2 => '追踪目标',
      3 => '已被占用',
      4 => '被玩家占用',
      5 => '特殊信息',
    ),
  ),
  'factions' => 
  array (
    14 => '怪物',
    35 => '友方守卫',
    68 => '通用友方',
    69 => '玩家：联盟',
    70 => '玩家：部落',
    74 => '暴风城卫兵',
    84 => '铁炉堡卫兵',
    120 => '雷霆崖卫兵',
    121 => '奥格瑞玛卫兵',
    122 => '达纳苏斯卫兵',
    123 => '诺莫瑞根卫兵',
    124 => '幽暗城守卫',
    169 => '热砂港',
    469 => '联盟',
    529 => '银色黎明',
    530 => '部落',
    609 => '塞纳里奥议会',
    910 => '暗月马戏团',
    934 => '铜须氏族',
    935 => '呼啸峡湾远征军',
  ),
  'repository' => 
  array (
    'errors' => 
    array (
      'invalid_new_id' => '无效的新 ID',
      'id_exists' => 'ID 已存在',
      'copy_source_missing' => '源条目不存在',
      'copy_failed' => '复制生物模板失败',
      'create_failed' => '创建生物模板失败',
      'invalid_id' => '无效的 ID',
      'no_rows_deleted' => '未删除任何行',
      'no_changes' => '没有可应用的修改',
      'no_valid_fields' => '未提供有效列',
      'no_value_changes' => '值未发生变化',
      'update_failed' => '更新失败',
      'model_invalid' => '模型数据无效',
      'model_index_limit' => '模型索引已达上限',
      'model_add_failed' => '添加模型失败',
      'model_update_failed' => '更新模型失败',
      'model_delete_failed' => '未删除任何模型',
      'sql_empty' => 'SQL 语句不能为空',
      'sql_multi' => '不允许多条语句',
      'sql_parse_column' => '无法解析列：:column',
      'sql_invalid_column' => '列 :column 不允许使用',
      'sql_update_where' => 'UPDATE 必须以 WHERE entry = <数字> 结尾（可选 LIMIT 1）',
      'sql_only_update_insert' => '仅允许 UPDATE 或 INSERT creature_template 语句',
      'sql_exec_error' => '执行错误：:error',
    ),
    'success' => 
    array (
      'copied' => '已复制生物模板（来源 #:source）',
      'created' => '生物模板已创建',
      'deleted' => '删除成功（ID #:id）',
      'updated' => '更新完成',
      'model_added' => '模型添加成功',
      'model_updated' => '模型更新成功',
      'model_deleted' => '模型删除成功',
      'sql_action_inserted' => '插入',
      'sql_action_affected' => '影响',
      'sql_rows' => ':action 行数：:count',
    ),
    'info_labels' => 
    array (
      0 => '普通',
      1 => '小队',
      21 => '专业',
      41 => 'PvP',
      62 => '团队副本',
      81 => '地下城',
      82 => '事件',
      83 => '传奇',
      84 => '护送',
      85 => '英雄副本',
      88 => '团队（10人）',
      89 => '团队（25人）',
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'creature' => 
      array (
        'create' => 
        array (
          'enter_new_id' => '请输入新ID',
          'success_redirect' => '创建成功，正在跳转',
          'failure' => '创建失败',
          'failure_with_reason' => '创建失败: :reason',
        ),
        'logs' => 
        array (
          'loading_placeholder' => '-- 加载中... --',
          'empty_placeholder' => '-- 暂无日志 --',
          'load_failed_placeholder' => '-- 加载失败 --',
          'load_failed' => '日志加载失败',
          'load_failed_with_reason' => '日志加载失败: :reason',
        ),
        'list' => 
        array (
          'confirm_delete' => '确认删除 :id ?',
          'delete_success' => '删除完成',
          'delete_failed' => '删除失败',
          'delete_failed_with_reason' => '删除失败: :reason',
        ),
        'diff' => 
        array (
          'group_change_count' => '(:count 项修改)',
          'no_changes_placeholder' => '-- 暂无变更 --',
          'copy_sql_success' => '已复制 SQL',
        ),
        'common' => 
        array (
          'copy_failed' => '复制失败',
        ),
        'errors' => 
        array (
          'panel_api_not_ready' => 'Panel API 未就绪',
        ),
        'exec' => 
        array (
          'actions' => 
          array (
            'clear' => '清空',
            'hide' => '隐藏',
            'copy_json' => '复制 JSON',
            'copy_sql' => '复制 SQL',
          ),
          'result_heading' => '执行结果',
          'rows_affected' => '行: :count',
          'sample_row_heading' => '示例行',
          'sql_prefix' => 'SQL: :sql',
          'copy_json_success' => '已复制 JSON',
          'copy_sql_success' => '已复制 SQL',
          'default_success' => '执行成功',
          'default_error' => '执行失败',
          'failure_with_reason' => ':prefix: :reason',
          'sql_empty_notify' => 'SQL 为空，无法执行',
          'sql_empty_response' => 'SQL为空',
          'confirm_run_diff' => '确认执行以下 SQL？
:sql',
          'no_diff_sql' => '没有可执行的差异 SQL',
          'diff_sql_success' => '差异 SQL 执行成功',
          'prompt_sql' => '输入要执行的 UPDATE/INSERT SQL (单条)',
          'only_update_insert' => '仅允许 UPDATE 或 INSERT',
          'status' => 
          array (
            'save_success' => '保存成功',
            'run_success' => '执行成功',
            'save_failed' => '保存失败',
            'run_failed' => '执行失败',
          ),
        ),
        'models' => 
        array (
          'confirm_delete' => '删除该模型?',
          'save_success' => '模型保存成功',
          'save_failed' => '模型保存失败',
          'save_failed_with_reason' => '模型保存失败: :reason',
          'delete_success' => '模型已删除',
          'delete_failed' => '删除失败',
          'delete_failed_with_reason' => '删除失败: :reason',
        ),
        'save' => 
        array (
          'no_changes' => '没有修改需要保存',
          'success' => '保存成功',
          'failed' => '保存失败',
          'failed_with_reason' => '保存失败: :reason',
          'confirm_delete_creature' => '确认删除生物 :id ?',
          'delete_success' => '已删除',
          'delete_failed' => '删除失败',
          'delete_failed_with_reason' => '删除失败: :reason',
        ),
        'verify' => 
        array (
          'failure' => '校验失败',
          'failure_with_reason' => '校验失败: :reason',
          'diff_bad' => '不同',
          'diff_ok' => '一致',
          'diff_summary' => '检测到 :count 处不一致',
          'copy_update' => '复制UPDATE语句',
          'copied' => '已复制',
          'row_match' => '该行一致',
        ),
        'nav' => 
        array (
          'auto_group_title' => '分组 :index',
        ),
        'compact' => 
        array (
          'mode' => 
          array (
            'normal' => '正常',
            'compact' => '紧凑',
          ),
        ),
        'bitmask' => 
        array (
          'modal_title' => '位标志选择',
          'search_placeholder' => '搜索...',
          'select_all' => '全选',
          'clear' => '清空',
          'tips' => '提示：勾选即时更新值。搜索可过滤描述。',
          'close' => '关闭',
          'field_title' => ':field (:value)',
          'trigger' => '位',
        ),
      ),
    ),
  ),
);
