<?php
return array (
  'page_title' => 'SmartAI 向导',
  'intro' => '根据 AzerothCore Smart Scripts 规范构建事件、动作与目标，快速生成可直接执行的 SQL。',
  'sidebar' => 
  array (
    'nav_title' => '步骤导航',
    'steps' => 
    array (
      'base' => '基础信息',
      'event' => '事件选择',
      'action' => '动作配置',
      'target' => '目标与预览',
    ),
    'quick_view' => '速览',
    'view_wiki' => '查看官方 Wiki',
    'updated_at' => '数据更新：:date',
  ),
  'base' => 
  array (
    'title' => '基础信息',
    'description' => '设置脚本作用对象与通用字段，例如 entry、概率、阶段等。',
  ),
  'segment' => 
  array (
    'add' => '添加事件段',
    'hint' => '每个事件段包含独立的事件、动作与目标，可按顺序依次执行。',
  ),
  'event' => 
  array (
    'title' => '选择事件 (Event)',
    'description' => '事件定义何时触发脚本。选择类型后填写参数，所有参数含义基于 Wiki 说明。',
  ),
  'action' => 
  array (
    'title' => '配置动作 (Action)',
    'description' => '动作会在事件触发时执行，可组合施法、对话、召唤等行为。',
  ),
  'target' => 
  array (
    'title' => '目标与预览',
    'description' => '确定动作的目标并生成 SQL。可直接复制或下载到脚本工具中执行。',
  ),
  'preview' => 
  array (
    'title' => 'SQL 预览',
    'generate' => '生成 SQL',
    'copy' => '复制',
    'placeholder' => '-- 请先完成前面步骤并点击生成',
  ),
  'footer' => 
  array (
    'prev' => '上一步',
    'next' => '下一步',
    'step_indicator' => '第 :current 步 / :total',
  ),
  'catalog' => 
  array (
    'metadata' => 
    array (
      'notes' => 
      array (
        0 => '字段与参数含义以 AzerothCore Wiki 为准。',
        1 => '生成的 SQL 可直接写入 smart_scripts 表。',
      ),
    ),
    'source_types' => 
    array (
      0 => 
      array (
        'label' => '生物 (Creature)',
      ),
      1 => 
      array (
        'label' => '游戏对象 (GameObject)',
      ),
      2 => 
      array (
        'label' => '区域触发器 (AreaTrigger)',
      ),
      3 => 
      array (
        'label' => '事件 (Event)',
      ),
      9 => 
      array (
        'label' => '定时动作列表 (Timed ActionList)',
      ),
    ),
    'base' => 
    array (
      'entryorguid' => 
      array (
        'label' => 'Entry / GUID',
        'hint' => '根据 Source Type 填写对应的 entry 或 guid。',
      ),
      'source_type' => 
      array (
        'label' => 'Source Type',
        'hint' => '脚本类型（生物/游戏对象/定时动作列表等）。',
      ),
      'id' => 
      array (
        'label' => 'ID',
        'hint' => '同一 entry/source_type 下的脚本序号。',
      ),
      'link' => 
      array (
        'label' => 'Link',
        'hint' => '链接到上一条脚本的 ID（0 为不链接）。',
      ),
      'event_phase_mask' => 
      array (
        'label' => 'Phase Mask',
        'hint' => '事件阶段掩码（bitmask）。',
      ),
      'event_chance' => 
      array (
        'label' => 'Chance',
        'hint' => '触发概率（0-100）。',
      ),
      'event_flags' => 
      array (
        'label' => 'Event Flags',
        'hint' => '事件标志位（bitmask）。',
      ),
      'comment' => 
      array (
        'label' => 'Comment',
        'hint' => '备注（可选）。',
      ),
      'include_delete' => 
      array (
        'label' => '包含 DELETE',
        'hint' => '生成 SQL 时包含删除旧脚本的语句。',
      ),
    ),
  ),
  'builder' => 
  array (
    'messages' => 
    array (
      'validation_failed' => '参数校验失败',
    ),
    'errors' => 
    array (
      'base' => 
      array (
        'entryorguid' => '请输入有效的 entry 或 GUID。',
        'source_type' => '未支持的 source_type，请在下拉列表中选择。',
        'event_chance' => '概率必须在 0 - 100 之间。',
        'event_flags' => '事件标志不可为负数。',
        'id_negative' => '脚本 ID 不可为负数。',
        'link_negative' => 'Link 不可为负数。',
        'phase_negative' => '阶段掩码不可为负数。',
      ),
      'segment' => 
      array (
        'event_required' => '至少需要一个事件。',
      ),
      'event' => 
      array (
        'type' => '请选择事件类型。',
      ),
      'action' => 
      array (
        'type' => '请选择动作类型。',
      ),
      'target' => 
      array (
        'type' => '请选择目标类型。',
      ),
    ),
  ),
  'js' => 
  array (
    'modules' => 
    array (
      'smartai' => 
      array (
        'segments' => 
        array (
          'move_up_title' => '上移',
          'move_down_title' => '下移',
          'delete_segment_title' => '删除分段',
          'default_label' => '分段 :number',
          'empty_prompt' => '请添加一个分段。',
        ),
        'search' => 
        array (
          'placeholder' => '搜索关键字或 ID',
        ),
        'list' => 
        array (
          'empty' => '未找到匹配项',
        ),
        'selector' => 
        array (
          'select_type' => '请选择类型。',
          'no_params' => '该类型无额外参数。',
        ),
        'validation' => 
        array (
          'entry_required' => '请输入有效的 entry。',
          'entry_invalid' => '需要有效的 entry。',
          'segment_required' => '请至少添加一个分段。',
          'event_required_next' => '继续前请先选择事件类型。',
          'event_required' => '请选择事件类型。',
          'event_required_all' => '请为每个分段选择事件类型。',
          'action_required_next' => '继续前请先选择动作类型。',
          'action_required' => '请选择动作类型。',
          'action_required_all' => '请为每个分段选择动作类型。',
          'target_required_next' => '继续前请先选择目标类型。',
          'target_required' => '请选择目标类型。',
          'target_required_all' => '请为每个分段选择目标类型。',
        ),
        'api' => 
        array (
          'no_response' => '服务器无响应',
        ),
        'preview' => 
        array (
          'placeholder' => '-- 暂无生成的 SQL --',
          'error_placeholder' => '-- 生成失败，请检查表单错误 --',
        ),
        'summary' => 
        array (
          'segments' => '分段数: :count',
          'event' => '事件: :name',
          'action' => '动作: :name',
          'target' => '目标: :name',
        ),
        'feedback' => 
        array (
          'generate_success' => 'SQL 生成成功',
          'generate_failed' => '生成失败',
          'copy_success' => '已复制到剪贴板',
          'copy_failed' => '复制失败，请手动复制',
        ),
      ),
    ),
  ),
);
