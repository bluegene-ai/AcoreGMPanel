<?php
/**
 * File: resources/lang/zh_CN/app.php
 * Purpose: Provides functionality for the resources/lang/zh_CN module.
 */

declare(strict_types=1);

return [
    'app' => [
    'name' => 'Acore GM 面板',
    'title_suffix' => 'Acore GM 面板',
        'footer_copyright' => '© :year Acore 游戏管理面板',
        'metrics_text' => '耗时 :time · 内存 +:memory',
        'metrics_title' => '页面渲染约 :ms 毫秒，峰值内存 :mb MB',
    ],
    'nav' => [
        'home' => '首页',
        'account' => '账号',
        'item' => '物品',
        'creature' => '生物',
        'quest' => '任务',
        'mail' => '邮件',
        'mass_mail' => '群发',
        'bag' => '背包查询',
        'item_owner' => '物品归属',
        'soap' => 'SOAP',
        'smart_ai' => 'SmartAI',
        'logs' => '日志',
    ],
    'common' => [
        'performance' => '性能',
        'loading' => '加载中…',
        'online_total_label' => '在线 / 总数',
        'online_total_title' => '当前在线玩家 / 该服务器的总角色数',
        'language' => '语言',
        'languages' => [
            'zh_CN' => '简体中文',
            'en' => 'English',
        ],
        'validation' => [
            'missing_id' => '缺少 id',
            'missing_ip' => '缺少 IP',
            'missing_player' => '缺少 player',
            'missing_params' => '缺少必要参数',
            'required' => '此字段为必填项',
            'number' => '请输入数字',
            'min' => '不得小于 :min',
        'config' => [
            'modules' => [
                'item_owner' => [
                    'label' => '物品归属',
                    'description' => '批量删除和替换历史。',
                    'types' => [
                        'actions' => [
                            'label' => '操作记录',
                        ],
                    ],
                ],
            ],
        ],
            'max' => '不得大于 :max',
            'length_max' => '长度不得超过 :max 个字符',
            'id_required' => '缺少ID',
            'invalid_id' => '无效ID',
            'no_valid_id' => '无有效ID',
        ],
        'errors' => [
            'query_failed' => '查询失败：:message',
            'database' => '数据库错误：:message',
            'not_found' => '不存在',
        ],
        'api' => [
            'errors' => [
                'request_failed' => '请求失败',
                'request_failed_retry' => '请求失败，请稍后再试',
                'request_failed_message' => '请求失败：:message',
                'request_failed_reason' => '请求失败：:reason',
                'unknown' => '未知错误',
                'unauthorized' => '未授权',
            ],
            'success' => [
                'generic' => '操作成功',
                'queued' => '任务已加入队列',
            ],
        ],
    ],
    'pagination' => [
        'previous' => '上一页',
        'next' => '下一页',
    ],
    'server' => [
        'label' => '服务器',
        'auto_detect_base' => '已自动检测 base_path = :base，将于安装完成后自动写入配置文件。',
        'base_mismatch' => '当前访问前缀 ":detected" 与配置 base_path ":configured" 不一致。请检查部署或更新配置。',
        'normalized_warning' => '已规范化访问路径，建议直接使用 :base 作为面板入口。',
        'default_option' => '服#:id',
    ],
    'support' => [
        'ip_location' => [
            'labels' => [
                'private' => '内网IP',
                'unknown' => '未知归属地',
            ],
            'errors' => [
                'empty' => 'IP不能为空',
                'invalid' => 'IP格式不正确',
                'provider_unreachable' => '无法连接 IP 数据服务',
                'response_invalid' => '返回格式异常',
                'failed' => '查询失败',
                'failed_reason' => '查询失败：:message',
            ],
        ],
        'server_list' => [
            'default' => '默认服',
        ],
        'multi_server' => [
            'errors' => [
                'auth_config_missing' => '服务器 #:server 缺少 auth 配置。',
            ],
        ],
        'srp' => [
            'errors' => [
                'gmp_missing' => 'GMP 扩展未启用，无法生成 SRP verifier（请在 php.ini 启用 extension=gmp）。',
                'gmp_missing_binary' => 'GMP 扩展未启用，无法生成 SRP verifier（binary32）。',
            ],
        ],
        'soap_executor' => [
            'errors' => [
                'empty_command' => '命令不能为空。',
                'not_whitelisted' => '命令不在白名单中。',
                'request_failed' => 'SOAP 请求失败。',
                'unknown' => '未知错误。',
            ],
        ],
    ],
    'game_meta' => [
        'classes' => [
            1 => '战士',
            2 => '圣骑士',
            3 => '猎人',
            4 => '盗贼',
            5 => '牧师',
            6 => '死亡骑士',
            7 => '萨满',
            8 => '法师',
            9 => '术士',
            10 => '武僧',
            11 => '德鲁伊',
            12 => '恶魔猎手',
        ],
        'qualities' => [
            0 => '粗糙',
            1 => '普通',
            2 => '优秀',
            3 => '精良',
            4 => '史诗',
            5 => '传说',
            6 => '神器',
            7 => '传家宝',
        ],
        'fallbacks' => [
            'class' => '未知#:id',
            'quality' => '品质#:id',
        ],
    ],
    'database' => [
        'errors' => [
            'config_missing' => '数据库配置不存在：:name',
            'connection_failed' => '数据库连接失败：:database @ :host:: :port (:error)',
            'server_config_missing' => '服务器配置不存在：:server (角色 :role)',
        ],
    ],
    'home' => [
        'page_title' => '欢迎使用新版面板',
        'intro' => '这是初始骨架。版本: :version',
        'features' => [
            'unified_mvc' => '统一 MVC 路由',
            'migration' => '后续将迁移各功能模块(Account/Item/Creature/...)',
        ],
        'readme_heading' => '项目说明文档',
        'readme_missing' => '未找到 README 文件。',
        'readme_source' => '当前内容来源：:file',
    ],
    'auth' => [
        'page_title' => '管理员登录',
        'login_title' => '登录',
        'username' => '用户名',
        'password' => '密码',
        'submit' => '登录',
        'error_invalid' => '用户名或密码错误',
        'errors' => [
            'not_logged_in' => '请先登录',
        ],
    ],
    'alerts' => [
        'not_installed_redirect' => '系统尚未完成安装，正在跳转到安装向导……',
        'bootstrap' => [
            'auto_detect_base_path' => '已自动检测 base_path = :base，将在安装完成后写入配置。',
            'base_path_mismatch' => '当前访问前缀“:detected”与配置 base_path“:configured”不一致，请检查部署或更新配置。',
            'normalized_path' => '已规范化访问路径，请直接使用 :target 作为面板入口。',
            'auto_write_base_path' => '已自动写入 base_path = :base 到 config/generated/app.php。',
        ],
    ],
    'cli' => [
        'normalize_config' => [
            'missing_dir' => 'config 目录不存在：:path',
            'fixed' => '修复: :file',
            'skipped_failed' => '跳过（替换失败）: :file',
            'summary' => '完成。修复文件: :fixed，未需处理: :skipped',
        ],
    ],
    'errors' => [
        'internal_server_error_title' => '服务器内部错误',
    ],
    'soap' => [
        'page_title' => 'SOAP 命令向导',
        'intro' => '基于 AzerothCore GM 指令整理，选择命令后按向导填写参数并执行。',
        'search_label' => '搜索命令',
        'search_placeholder' => '输入关键字或命令片段',
        'summary' => [
            'title' => '请选择命令',
            'hint' => '在左侧选择分类与命令，可使用搜索快速定位。支持服务器切换，通过顶部下拉选择目标 Realm。',
        ],
        'target_hint' => '需要在游戏中选中目标后再执行。',
        'steps' => [
            'fill' => '步骤 1：填写参数',
            'confirm' => '步骤 2：确认命令',
        ],
        'api' => [
            'errors' => [
                'unauthorized' => '未授权',
                'invalid_arguments' => '参数无效',
            ],
        ],
            'soap' => [
                'meta' => [
                    'updated_at' => '指令表更新于 :date',
                    'source_link' => 'GM Commands',
                    'source_label' => '参考：:link',
                    'separator' => ' · ',
                ],
                'categories' => [
                    'all' => [
                        'label' => '全部命令',
                        'summary' => '显示所有收录的命令',
                    ],
                ],
                'list' => [
                    'empty' => '未找到匹配的命令',
                ],
                'risk' => [
                    'badge' => [
                        'low' => '低风险',
                        'medium' => '中风险',
                        'high' => '高风险',
                        'unknown' => '未知风险',
                    ],
                    'short' => [
                        'low' => '低',
                        'medium' => '中',
                        'high' => '高',
                        'unknown' => '？',
                    ],
                ],
                'fields' => [
                    'empty' => '此命令无需额外参数。',
                ],
                'errors' => [
                    'missing_required' => '存在未填写的必填参数。',
                    'unknown_response' => '未知响应',
                ],
                'form' => [
                    'error_joiner' => '、',
                ],
                'feedback' => [
                    'execute_success' => '执行成功',
                    'execute_failed' => '执行失败',
                ],
                'output' => [
                    'unknown_time' => '未知耗时',
                    'meta' => '状态：:code · 耗时：:time',
                    'empty' => '(无输出)',
                ],
                'copy' => [
                    'empty' => '暂无命令可复制',
                    'success' => '已复制到剪贴板',
                    'failure' => '复制失败',
                ],
            ],
            ],
            'flags' => [
                'regular' => [
                    'not_lootable' => '不可拾取',
                    'conjured' => '魔法制造',
                    'openable' => '可打开',
                    'indestructible' => '不可摧毁',
                    'no_equip_cooldown' => '无装备冷却',
                    'wrapper_container' => '包装容器',
                    'party_loot_shared' => '队伍共享拾取',
                    'refundable' => '可退还',
                    'unique_equipped' => '唯一装备',
                    'arena_usable' => '竞技场可用',
                    'throwable' => '可投掷',
                    'shapeshift_usable' => '变形可用',
                    'profession_recipe' => '专业配方',
                    'account_bound' => '账号绑定',
                    'ignore_reagent' => '忽略材料',
                    'millable' => '可研磨',
                ],
                'extra' => [
                    'horde_only' => '部落专属',
                    'alliance_only' => '联盟专属',
                    'extended_cost_requires_gold' => '扩展需金币',
                    'disable_need_roll' => '禁用需求掷骰',
                    'disable_need_roll_alt' => '禁用需求掷骰2',
                    'standard_pricing' => '标准定价',
                    'battle_net_bound' => '战网绑定',
                ],
                'custom' => [
                    'real_time_duration' => '真实时间计时',
                    'ignore_quest_status' => '忽略任务状态',
                    'party_loot_rules' => '队伍拾取规则',
                ],
                'separator' => '，',
                'empty' => '（无）',
        'preview_label' => '即将执行',
        'actions' => [
            'copy' => '复制命令',
            'execute' => '执行命令',
        ],
        'output_title' => '执行结果',
        'legacy' => [
            'errors' => [
                'curl_failed' => '无法连接到 SOAP 端点。',
                'curl_error_unknown' => '未知的 cURL 错误。',
                'http_error' => 'SOAP 请求返回 HTTP 状态 :code。',
            ],
        ],
        'wizard' => [
            'errors' => [
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
            ],
            'catalog' => [
                'categories' => [
                    'general' => [
                        'label' => '常规',
                        'summary' => '服务器状态、公告与 GM 自身工具',
                    ],
                    'account' => [
                        'label' => '账号管理',
                        'summary' => '账号 GM 等级、锁定与封禁操作',
                    ],
                    'character' => [
                        'label' => '角色管理',
                        'summary' => '角色等级、外观及状态控制',
                    ],
                    'teleport' => [
                        'label' => '传送/位置',
                        'summary' => '角色或 GM 的传送、定位类命令',
                    ],
                    'item' => [
                        'label' => '物品/装备',
                        'summary' => '为目标添加或移除物品',
                    ],
                    'spell' => [
                        'label' => '法术/技能',
                        'summary' => '授予或移除技能、天赋等',
                    ],
                    'quest' => [
                        'label' => '任务',
                        'summary' => '赋予、完成或移除任务',
                    ],
                    'misc' => [
                        'label' => '外观/状态',
                        'summary' => '改变模型、添加金钱等杂项命令',
                    ],
                ],
                'commands' => [
                    'server-info' => [
                        'description' => '显示服务器核心、构建时间、在线人数与正常运行时间等信息。',
                    ],
                    'server-motd' => [
                        'description' => '查看或修改服务器 MOTD（登录公告）。',
                        'arguments' => [
                            'message' => [
                                'label' => '公告内容',
                                'placeholder' => '留空则显示当前 MOTD',
                            ],
                        ],
                    ],
                    'announce-global' => [
                        'description' => '向全服玩家广播一条系统公告。',
                        'arguments' => [
                            'message' => [
                                'label' => '公告内容',
                                'placeholder' => '请输入要广播的内容',
                            ],
                        ],
                    ],
                    'announce-name' => [
                        'description' => '以 GM 名义发送公告（会展示 GM 名称）。',
                        'arguments' => [
                            'message' => [
                                'label' => '公告内容',
                            ],
                        ],
                    ],
                    'notify' => [
                        'description' => '向全服弹出屏幕中部提示信息。',
                        'arguments' => [
                            'message' => [
                                'label' => '提示内容',
                            ],
                        ],
                    ],
                    'gm-visible' => [
                        'description' => '切换 GM 自身在世界中的可见性。',
                        'arguments' => [
                            'state' => [
                                'label' => '状态',
                                'options' => [
                                    'on' => 'on - 进入 GM 隐身',
                                    'off' => 'off - 退出 GM 隐身',
                                ],
                            ],
                        ],
                            'executor' => [
                                'errors' => [
                                    'empty' => '命令不能为空',
                                    'not_whitelisted' => '命令未被列入白名单',
                                    'request_failed' => '请求失败',
                                    'unknown' => '未知错误',
                                ],
                            ],
                    ],
                    'account-set-gmlevel' => [
                        'description' => '设置账号的 GM 等级。',
                        'arguments' => [
                            'account' => [
                                'label' => '账号用户名',
                            ],
                            'level' => [
                                'label' => 'GM 等级',
                                'options' => [
                                    '0' => '0 - 普通玩家',
                                    '1' => '1 - 见习 GM',
                                    '2' => '2 - 完整 GM',
                                    '3' => '3 - 管理员',
                                ],
                            ],
                            'realm' => [
                                'label' => 'Realm ID (可选)',
                                'placeholder' => '留空则对所有 Realm 生效',
                            ],
                        ],
                    ],
                    'account-set-password' => [
                        'description' => '重置账号密码。',
                        'arguments' => [
                            'account' => [
                                'label' => '账号用户名',
                            ],
                            'password' => [
                                'label' => '新密码',
                            ],
                        ],
                    ],
                    'account-lock' => [
                        'description' => '启用或取消账号锁定。',
                        'arguments' => [
                            'account' => [
                                'label' => '账号用户名',
                            ],
                            'state' => [
                                'label' => '锁定状态',
                                'options' => [
                                    'on' => 'on - 锁定登陆',
                                    'off' => 'off - 解除锁定',
                                ],
                            ],
                        ],
                    ],
                    'ban-account' => [
                        'description' => '封禁账号指定时长并记录原因。',
                        'arguments' => [
                            'account' => [
                                'label' => '账号用户名',
                            ],
                            'duration' => [
                                'label' => '封禁时长',
                                'placeholder' => '例如 3d 或 12h 或 permanent',
                            ],
                            'reason' => [
                                'label' => '原因 (可选)',
                            ],
                        ],
                    ],
                    'unban-account' => [
                        'description' => '解除账号封禁。',
                        'arguments' => [
                            'account' => [
                                'label' => '账号用户名',
                            ],
                        ],
                    ],
                    'character-level' => [
                        'description' => '设置指定角色等级。',
                        'arguments' => [
                            'name' => [
                                'label' => '角色名',
                            ],
                            'level' => [
                                'label' => '等级',
                            ],
                        ],
                    ],
                    'character-rename' => [
                        'description' => '强制角色下次登录时改名。',
                        'arguments' => [
                            'name' => [
                                'label' => '角色名',
                            ],
                        ],
                    ],
                    'character-customize' => [
                        'description' => '强制角色登录时进行外观自定义。',
                        'arguments' => [
                            'name' => [
                                'label' => '角色名',
                            ],
                        ],
                    ],
                    'character-revive' => [
                        'description' => '复活已死亡角色。',
                        'arguments' => [
                            'name' => [
                                'label' => '角色名',
                            ],
                        ],
                    ],
                    'character-lookup' => [
                        'description' => '按名称搜索角色。',
                        'arguments' => [
                            'pattern' => [
                                'label' => '角色名关键字',
                            ],
                        ],
                    ],
                    'tele-name' => [
                        'description' => '传送到预设地点（需在数据库中存在）。',
                        'arguments' => [
                            'location' => [
                                'label' => '地点名称',
                            ],
                        ],
                    ],
                    'tele-worldport' => [
                        'description' => '传送到指定地图坐标。使用前确认坐标有效。',
                        'arguments' => [
                            'map' => [
                                'label' => '地图ID',
                            ],
                            'x' => [
                                'label' => 'X 坐标',
                            ],
                            'y' => [
                                'label' => 'Y 坐标',
                            ],
                            'z' => [
                                'label' => 'Z 坐标',
                            ],
                            'o' => [
                                'label' => '朝向 (可选)',
                            ],
                        ],
                        'notes' => [
                            'ensure_valid' => '确保坐标合法，否则可能掉线或卡死。',
                        ],
                    ],
                    'go-creature' => [
                        'description' => '传送到指定生物 GUID 所在位置。',
                        'arguments' => [
                            'guid' => [
                                'label' => '生物 GUID',
                            ],
                        ],
                    ],
                    'go-object' => [
                        'description' => '传送到指定游戏对象 GUID 所在位置。',
                        'arguments' => [
                            'guid' => [
                                'label' => '对象 GUID',
                            ],
                        ],
                    ],
                    'summon-player' => [
                        'description' => '将指定玩家传送到 GM 身边。',
                        'arguments' => [
                            'player' => [
                                'label' => '玩家名称',
                            ],
                        ],
                        'notes' => [
                            'require_online' => '需要玩家在线。',
                        ],
                    ],
                    'additem' => [
                        'description' => '为当前选中目标添加物品。目标需为玩家角色。',
                        'arguments' => [
                            'item' => [
                                'label' => '物品ID',
                            ],
                            'count' => [
                                'label' => '数量 (可选)',
                            ],
                        ],
                    ],
                    'additemset' => [
                        'description' => '为当前选中角色添加整套物品套装。',
                        'arguments' => [
                            'itemset' => [
                                'label' => '套装ID',
                            ],
                        ],
                    ],
                    'removeitem' => [
                        'description' => '从当前目标背包中移除指定物品。',
                        'arguments' => [
                            'item' => [
                                'label' => '物品ID',
                            ],
                            'count' => [
                                'label' => '数量 (可选)',
                            ],
                        ],
                    ],
                    'learn-spell' => [
                        'description' => '令当前选中角色学习某个法术或技能。',
                        'arguments' => [
                            'spell' => [
                                'label' => '法术ID',
                            ],
                        ],
                    ],
                    'unlearn-spell' => [
                        'description' => '移除当前目标的某个法术或技能。',
                        'arguments' => [
                            'spell' => [
                                'label' => '法术ID',
                            ],
                        ],
                    ],
                    'talent-reset' => [
                        'description' => '重置当前目标的天赋。',
                    ],
                    'quest-add' => [
                        'description' => '给予当前目标一个任务。',
                        'arguments' => [
                            'quest' => [
                                'label' => '任务ID',
                            ],
                        ],
                    ],
                    'quest-complete' => [
                        'description' => '直接完成当前目标的任务。',
                        'arguments' => [
                            'quest' => [
                                'label' => '任务ID',
                            ],
                        ],
                    ],
                    'quest-remove' => [
                        'description' => '从当前目标移除指定任务。',
                        'arguments' => [
                            'quest' => [
                                'label' => '任务ID',
                            ],
                        ],
                    ],
                    'morph' => [
                        'description' => '将当前目标变形为指定模型。',
                        'arguments' => [
                            'display' => [
                                'label' => '模型显示ID',
                            ],
                        ],
                    ],
                    'demorph' => [
                        'description' => '恢复当前目标的原始模型。',
                    ],
                    'modify-money' => [
                        'description' => '为当前目标增加或减少铜币数，正数为增加，负数为移除。',
                        'arguments' => [
                            'amount' => [
                                'label' => '铜币数量（可为负）',
                                'placeholder' => '例如 100000 (10金) 或 -5000',
                            ],
                        ],
                    ],
                    'modify-speed' => [
                        'description' => '调整当前目标的移动速度。',
                        'arguments' => [
                            'multiplier' => [
                                'label' => '速度倍率',
                                'placeholder' => '1 为正常，2 为双倍',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'smartai' => [
        'page_title' => 'SmartAI 向导',
        'intro' => '根据 AzerothCore Smart Scripts 规范构建事件、动作与目标，快速生成可直接执行的 SQL。',
        'sidebar' => [
            'nav_title' => '步骤导航',
            'steps' => [
                'base' => '基础信息',
                'event' => '事件选择',
                'action' => '动作配置',
                'target' => '目标与预览',
            ],
            'quick_view' => '速览',
            'view_wiki' => '查看官方 Wiki',
            'updated_at' => '数据更新：:date',
        ],
        'base' => [
            'title' => '基础信息',
            'description' => '设置脚本作用对象与通用字段，例如 entry、概率、阶段等。',
        ],
        'segment' => [
            'add' => '添加事件段',
            'hint' => '每个事件段包含独立的事件、动作与目标，可按顺序依次执行。',
        ],
        'event' => [
            'title' => '选择事件 (Event)',
            'description' => '事件定义何时触发脚本。选择类型后填写参数，所有参数含义基于 Wiki 说明。',
        ],
        'action' => [
            'title' => '配置动作 (Action)',
            'description' => '动作会在事件触发时执行，可组合施法、对话、召唤等行为。',
        ],
        'target' => [
            'title' => '目标与预览',
            'description' => '确定动作的目标并生成 SQL。可直接复制或下载到脚本工具中执行。',
        ],
        'preview' => [
            'title' => 'SQL 预览',
            'generate' => '生成 SQL',
            'copy' => '复制',
            'placeholder' => '-- 请先完成前面步骤并点击生成',
        ],
        'footer' => [
            'prev' => '上一步',
            'next' => '下一步',
            'step_indicator' => '第 :current 步 / :total',
        ],
        'builder' => [
            'messages' => [
                'validation_failed' => '参数校验失败',
            ],
            'errors' => [
                'base' => [
                    'entryorguid' => '请输入有效的 entry 或 GUID。',
                    'source_type' => '未支持的 source_type，请在下拉列表中选择。',
                    'event_chance' => '概率必须在 0 - 100 之间。',
                    'event_flags' => '事件标志不可为负数。',
                    'id_negative' => '脚本 ID 不可为负数。',
                    'link_negative' => 'Link 不可为负数。',
                    'phase_negative' => '阶段掩码不可为负数。',
                ],
                'segment' => [
                    'event_required' => '至少需要一个事件。',
                ],
                'event' => [
                    'type' => '请选择事件类型。',
                ],
                'action' => [
                    'type' => '请选择动作类型。',
                ],
                'target' => [
                    'type' => '请选择目标类型。',
                ],
            ],
        ],
    ],
    'logs' => [
        'index' => [
            'page_title' => '日志查看',
            'errors' => [
                'invalid_module' => '无效的模块或类型',
                'read_failed' => '读取日志失败：:message',
                'unauthorized' => '未授权',
            ],
        ],
        'manager' => [
            'summary' => [
                'impact' => '影响：:count',
                'impact_paren' => '（影响：:count）',
                'error_prefix' => ' | 错误：:message',
            ],
            'pipe_sql' => [
                'summary' => ':type :status（影响：:affected）',
                'sql_suffix' => ' | SQL：:sql',
                'error_suffix' => ' | 错误：:error',
            ],
        ],
    ],
    'account' => [
        'page_title' => '账号管理',
        'search' => [
            'type_username' => '按用户名',
            'type_id' => '按ID',
            'placeholder' => '搜索…',
            'submit' => '查询',
            'create' => '新增账号',
        ],
        'feedback' => [
            'found' => '找到 :total 条记录，当前第 :page / :pages 页。',
            'empty' => '无结果',
            'enter_search' => '请输入搜索条件',
            'private_ip_disabled' => '内网IP不可查询',
        ],
        'table' => [
            'id' => 'ID',
            'username' => '用户名',
            'gm' => 'GM',
            'online' => '在线',
            'last_login' => '最后登录',
            'last_ip' => '最后IP',
            'ip_location' => 'IP归属地',
            'actions' => '操作',
        ],
        'status' => [
            'online' => '在线',
            'offline' => '离线',
        ],
        'actions' => [
            'chars' => '角色',
            'gm' => 'GM',
            'ban' => '封禁',
            'unban' => '解封',
            'password' => '改密',
            'same_ip' => '同IP账号',
            'kick' => '踢',
        ],
        'ban' => [
            'badge' => '已封禁 (:duration)',
            'tooltip' => "封禁原因: :reason\n开始: :start\n结束: :end",
            'no_end' => '永久',
            'permanent' => '永久',
            'soon' => '即将解除',
            'under_minute' => '不足1分钟',
            'separator' => '',
            'duration' => [
                'day' => ':value天',
                'hour' => ':value小时',
                'minute' => ':value分钟',
            ],
            'prompt_hours' => '封禁时长（小时，0 = 永久）：',
            'error_hours' => '封禁时长无效',
            'prompt_reason' => '封禁理由：',
            'default_reason' => 'Panel 封禁',
            'success' => '封禁成功',
            'failure' => '封禁失败',
            'confirm_unban' => '确认解封该账号？',
            'unban_success' => '解封完成',
            'unban_failure' => '解封失败',
        ],
        'ip_lookup' => [
            'private' => '内网IP',
            'failed' => '查询失败',
            'unknown' => '未知归属地',
            'loading' => '查询中…',
        ],
        'characters' => [
            'title' => '角色列表 - :name',
            'loading' => '加载中…',
            'fetch_error' => '拉取角色失败',
            'table' => [
                'guid' => 'GUID',
                'name' => '名称',
                'level' => '等级',
                'status' => '状态',
            ],
            'kick_button' => '踢下线',
            'offline_tooltip' => '角色已离线，无法踢下线',
            'empty' => '无角色',
            'ban_badge' => '已封禁',
            'confirm_kick' => '确认踢出角色 :name？',
            'kick_success' => '已发送踢出命令：:name',
            'kick_failed' => '踢出失败：:message',
            'fetch_failed' => '角色拉取失败：:message',
        ],
        'gm' => [
            'prompt_level' => '设置 GM 级别 (0-6)：',
            'error_level' => 'GM 级别无效',
            'success' => 'GM 等级已更新',
            'failure' => 'GM 等级更新失败',
        ],
        'password' => [
            'prompt_new' => '输入新密码（至少 8 位）：',
            'error_empty' => '密码不能为空',
            'error_length' => '密码长度至少 8 位',
            'prompt_confirm' => '再次输入新密码：',
            'error_mismatch' => '两次输入不一致',
            'success' => '密码修改成功（旧会话已失效）',
            'failure' => '改密失败：:message',
            'failure_generic' => '未知错误',
        ],
        'create' => [
            'title' => '新增账号',
            'labels' => [
                'username' => '用户名',
                'password' => '登录密码',
                'password_confirm' => '确认密码',
                'email' => '邮箱（可选）',
                'gmlevel' => 'GM 等级',
            ],
            'placeholders' => [
                'username' => '不区分大小写',
                'password' => '至少 8 位',
                'password_confirm' => '再次输入',
                'email' => 'example@domain.com',
            ],
            'gm_options' => [
                'player' => '0 - 玩家',
                'one' => '1',
                'two' => '2',
                'three' => '3',
            ],
            'actions' => [
                'cancel' => '取消',
                'submit' => '创建',
            ],
            'status' => [
                'submitting' => '创建中…',
            ],
            'errors' => [
                'username_required' => '请输入用户名',
                'username_length' => '用户名长度超出限制',
                'password_length' => '密码至少需要 8 位',
                'password_mismatch' => '两次输入的密码不一致',
                'email_length' => '邮箱长度超出限制',
                'email_invalid' => '邮箱格式不正确',
                'request_generic' => '创建失败',
            ],
            'success' => '账号创建成功：:name',
        ],
        'same_ip' => [
            'missing_ip' => '该账号暂无最后登录 IP',
            'title' => '同 IP 账号 - :ip',
            'loading' => '查询中…',
            'empty' => '没有其他账号使用该 IP',
            'table' => [
                'id' => 'ID',
                'username' => '用户名',
                'gm' => 'GM',
                'status' => '状态',
                'last_login' => '最后登录',
                'ip_location' => 'IP 归属地',
            ],
            'status' => [
                'banned' => '封禁',
                'remaining' => '剩余：:value',
            ],
            'error_generic' => '查询失败',
            'error' => '查询失败：:message',
        ],
        'api' => [
            'validation' => [
                'username_min' => '用户名至少需要 3 个字符',
                'username_max' => '用户名长度超出限制',
                'password_min' => '密码至少需要 8 位',
                'gm_range' => 'GM 级别必须在 0-6 之间',
            ],
            'defaults' => [
                'no_reason' => '无理由',
            ],
            'errors' => [
                'missing_username_column' => 'account 表缺少 username 列',
                'username_exists' => '用户名已存在',
                'build_columns_failed' => '无法构建账号插入列集合',
                'missing_account_id' => '无法获取账号ID',
                'password_set_failed' => '设置密码失败',
                'create_failed' => '创建账号失败: :message',
                'query_characters_failed' => '查询角色失败: :message',
                'password_schema_unsupported' => '修改失败：账号结构不支持当前方式 (缺少 v/s 或 sha_pass_hash)',
            ],
        ],
    ],
    'creature' => [
        'index' => [
            'page_title' => '生物管理',
            'filters' => [
                'search_type' => [
                    'name' => '按名称搜索',
                    'id' => '按 ID 搜索',
                ],
                'placeholders' => [
                    'search_value' => '关键字或 ID',
                    'min_level' => '最低等级',
                    'max_level' => '最高等级',
                ],
                'buttons' => [
                    'search' => '搜索',
                    'reset' => '重置',
                    'create' => '创建',
                    'log' => '查看日志',
                ],
            ],
            'npcflag' => [
                'summary' => 'NPC 标志筛选',
                'apply' => '应用',
                'clear' => '清除',
                'mode_hint' => '模式：所有选定位必须同时存在（AND）',
            ],
            'table' => [
                'headers' => [
                    'id' => 'ID',
                    'name' => '名称',
                    'subname' => '副标题',
                    'min_level' => '最低等级',
                    'max_level' => '最高等级',
                    'faction' => '阵营',
                    'npcflag' => 'NPC 标志',
                    'actions' => '操作',
                    'verify' => '校验',
                ],
                'actions' => [
                    'edit' => '编辑',
                    'delete' => '删除',
                ],
                'verify_button' => '校验',
                'empty' => '暂无结果',
            ],
            'modals' => [
                'new' => [
                    'title' => '创建生物',
                    'id_label' => '新建 ID*',
                    'copy_label' => '复制来源（可选）',
                    'copy_hint' => '复制 ID 留空将创建空白模板。',
                    'cancel' => '取消',
                    'confirm' => '创建',
                ],
                'log' => [
                    'title' => '生物日志',
                    'type_label' => '日志类型',
                    'types' => [
                        'sql' => 'SQL 执行',
                        'deleted' => '删除快照',
                        'actions' => '操作记录',
                    ],
                    'refresh' => '刷新',
                    'empty' => '-- 暂无日志 --',
                    'close' => '关闭',
                ],
                'verify' => [
                    'title' => '行数据校验',
                    'headers' => [
                        'field' => '字段',
                        'rendered' => '渲染值',
                        'database' => '数据库值',
                        'status' => '状态',
                    ],
                    'close' => '关闭',
                    'copy_sql' => '复制 UPDATE 语句',
                ],
            ],
        ],
        'edit' => [
            'title' => '编辑生物 #:id',
            'actions' => [
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
            ],
            'labels' => [
                'only_changes' => '仅显示修改',
            ],
            'toolbar' => [
                'changed_fields' => '修改字段：',
            ],
            'diff' => [
                'title' => '差异 SQL 预览',
                'hint' => '点击“差异 SQL”生成；仅列出已修改的列。空字符串 -> NULL，并会自动附加 LIMIT 1。',
                'placeholder' => '-- 尚未生成 --',
            ],
            'models' => [
                'heading' => '模型列表（creature_template_model）',
                'table' => [
                    'index' => '索引',
                    'display_id' => '显示 ID',
                    'scale' => '缩放',
                    'probability' => '概率',
                    'verified_build' => '验证版本',
                    'actions' => '操作',
                ],
                'empty' => '暂无模型',
            ],
            'modal' => [
                'title' => '模型',
                'display_id' => '显示 ID',
                'scale' => '缩放',
                'probability' => '概率（0-1）',
                'verified_build' => '验证版本',
            ],
            'rank_enum' => [
                0 => '普通',
                1 => '精英',
                2 => '稀有精英',
                3 => '首领',
                4 => '稀有',
            ],
            'type_enum' => [
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
            ],
        ],
        'config' => [
            'groups' => [
                'base' => [
                    'label' => '基础信息',
                    'fields' => [
                        'name' => [
                            'label' => '名称',
                        ],
                        'subname' => [
                            'label' => '子名称',
                        ],
                        'minlevel' => [
                            'label' => '最低等级',
                        ],
                        'maxlevel' => [
                            'label' => '最高等级',
                        ],
                        'exp' => [
                            'label' => '经验类型 (exp)',
                            'help' => '0 = 无，1 = 普通，2 = 精英',
                        ],
                        'faction' => [
                            'label' => '阵营 ID (faction)',
                        ],
                        'scale' => [
                            'label' => '模型缩放 (scale)',
                        ],
                        'speed_walk' => [
                            'label' => '行走速度 (speed_walk)',
                        ],
                        'speed_run' => [
                            'label' => '奔跑速度 (speed_run)',
                        ],
                        'rank' => [
                            'label' => '等级类型 (rank)',
                        ],
                        'type' => [
                            'label' => '生物类型 (type)',
                        ],
                    ],
                ],
                'combat' => [
                    'label' => '战斗参数',
                    'fields' => [
                        'dmgschool' => [
                            'label' => '伤害系别 (dmgschool)',
                        ],
                        'baseattacktime' => [
                            'label' => '近战攻击间隔 (ms)',
                        ],
                        'rangeattacktime' => [
                            'label' => '远程攻击间隔 (ms)',
                        ],
                        'mindmg' => [
                            'label' => '近战最小伤害 (mindmg)',
                        ],
                        'maxdmg' => [
                            'label' => '近战最大伤害 (maxdmg)',
                        ],
                        'dmg_multiplier' => [
                            'label' => '伤害倍率 (dmg_multiplier)',
                        ],
                        'basevariance' => [
                            'label' => '伤害波动 (basevariance)',
                        ],
                        'rangevariance' => [
                            'label' => '远程伤害波动 (rangevariance)',
                        ],
                        'attackpower' => [
                            'label' => '近战攻击强度 (attackpower)',
                        ],
                        'rangedattackpower' => [
                            'label' => '远程攻击强度 (rangedattackpower)',
                        ],
                    ],
                ],
                'vitals' => [
                    'label' => '生命 / 法力 / 抗性',
                    'fields' => [
                        'healthmodifier' => [
                            'label' => '生命倍率 (healthmodifier)',
                        ],
                        'manamodifier' => [
                            'label' => '法力倍率 (manamodifier)',
                        ],
                        'armormodifier' => [
                            'label' => '护甲倍率 (armormodifier)',
                        ],
                        'resistance1' => [
                            'label' => '神圣抗性 (resistance1)',
                        ],
                        'resistance2' => [
                            'label' => '火焰抗性 (resistance2)',
                        ],
                        'resistance3' => [
                            'label' => '自然抗性 (resistance3)',
                        ],
                        'resistance4' => [
                            'label' => '冰霜抗性 (resistance4)',
                        ],
                        'resistance5' => [
                            'label' => '暗影抗性 (resistance5)',
                        ],
                        'resistance6' => [
                            'label' => '奥术抗性 (resistance6)',
                        ],
                    ],
                ],
                'drops' => [
                    'label' => '掉落 / 经济',
                    'fields' => [
                        'lootid' => [
                            'label' => '标准掉落 ID (lootid)',
                        ],
                        'pickpocketloot' => [
                            'label' => '偷窃掉落 ID (pickpocketloot)',
                        ],
                        'skinloot' => [
                            'label' => '剥皮掉落 ID (skinloot)',
                        ],
                        'mingold' => [
                            'label' => '金币最小值 (mingold)',
                        ],
                        'maxgold' => [
                            'label' => '金币最大值 (maxgold)',
                        ],
                    ],
                ],
                'ai' => [
                    'label' => 'AI / 脚本',
                    'fields' => [
                        'ainame' => [
                            'label' => 'AI 名称 (ainame)',
                        ],
                        'scriptname' => [
                            'label' => '脚本名称 (scriptname)',
                        ],
                        'gossip_menu_id' => [
                            'label' => '闲聊菜单 ID (gossip_menu_id)',
                        ],
                        'movementtype' => [
                            'label' => '移动类型 (movementtype)',
                        ],
                    ],
                ],
                'flags' => [
                    'label' => '标志 / 位字段',
                    'fields' => [
                        'npcflag' => [
                            'label' => 'NPC 标志 (npcflag)',
                        ],
                        'unit_flags' => [
                            'label' => '单位标志 (unit_flags)',
                        ],
                        'unit_flags2' => [
                            'label' => '单位标志 2 (unit_flags2)',
                        ],
                        'type_flags' => [
                            'label' => '类型标志 (type_flags)',
                        ],
                        'flags_extra' => [
                            'label' => '额外标志 (flags_extra)',
                        ],
                        'dynamicflags' => [
                            'label' => '动态标志 (dynamicflags)',
                        ],
                    ],
                ],
            ],
            'flags' => [
                'npcflag' => [
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
                ],
                'unit_flags' => [
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
                ],
                'unit_flags2' => [
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
                ],
                'type_flags' => [
                    0 => '可驯服',
                    1 => '幽灵可见',
                    2 => '首领',
                    3 => '不播放受伤/招架动画',
                    4 => '无战利品',
                    5 => '无经验',
                    6 => '触发器',
                    7 => '守卫',
                ],
                'flags_extra' => [
                    0 => '副本绑定',
                    1 => '平民',
                    2 => '不产生仇恨',
                    3 => '不可交互',
                    4 => '可驯服宠物',
                    5 => '死亡可交互',
                    6 => '强制对话',
                ],
                'dynamicflags' => [
                    0 => '发光',
                    1 => '可拾取',
                    2 => '追踪目标',
                    3 => '已被占用',
                    4 => '被玩家占用',
                    5 => '特殊信息',
                ],
            ],
            'factions' => [
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
            ],
        ],
        'repository' => [
            'errors' => [
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
            ],
            'success' => [
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
            ],
            'info_labels' => [
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
            ],
        ],
    ],
    'item' => [
        'page_title' => '物品管理',
        'quality' => [
            'unknown' => '未知',
        ],
        'meta' => [
            'qualities' => [
                0 => '粗糙',
                1 => '普通',
                2 => '优秀',
                3 => '精良',
                4 => '史诗',
                5 => '传说',
                6 => '神器',
                7 => '传家宝',
            ],
            'classes' => [
                0 => '消耗品',
                1 => '容器',
                2 => '武器',
                3 => '宝石',
                4 => '护甲',
                5 => '材料',
                6 => '弹药',
                7 => '商品',
                8 => '通用（废弃）',
                9 => '配方',
                10 => '货币（废弃）',
                11 => '箭袋',
                12 => '任务',
                13 => '钥匙',
                14 => '永久（废弃）',
                15 => '杂项',
                16 => '雕文',
            ],
            'subclasses' => [
                0 => [
                    0 => '消耗品',
                    1 => '药水',
                    2 => '药剂',
                    3 => '合剂',
                    4 => '卷轴',
                    5 => '食物和饮料',
                    6 => '物品强化',
                    7 => '绷带',
                    8 => '其他',
                ],
                1 => [
                    0 => '背包',
                    1 => '灵魂袋',
                    2 => '草药袋',
                    3 => '附魔袋',
                    4 => '工程袋',
                    5 => '宝石袋',
                    6 => '矿石袋',
                    7 => '制皮袋',
                    8 => '铭文袋',
                ],
                2 => [
                    0 => '单手斧',
                    1 => '双手斧',
                    2 => '弓',
                    3 => '枪械',
                    4 => '单手锤',
                    5 => '双手锤',
                    6 => '长柄武器',
                    7 => '单手剑',
                    8 => '双手剑',
                    9 => '已废弃',
                    10 => '法杖',
                    13 => '拳套',
                    14 => '杂项武器',
                    15 => '匕首',
                    16 => '投掷武器',
                    17 => '矛',
                    18 => '弩',
                    19 => '魔杖',
                    20 => '鱼竿',
                ],
                3 => [
                    0 => '红色',
                    1 => '蓝色',
                    2 => '黄色',
                    3 => '紫色',
                    4 => '绿色',
                    5 => '橙色',
                    6 => '多彩',
                    7 => '简易',
            'edit' => [
                'page_title' => '编辑物品 #:id',
            ],
            'api' => [
                'errors' => [
                    'invalid_id' => '无效的物品ID。',
                    'not_found' => '未找到物品。',
                    'log_type_unknown' => '未知日志类型。',
                ],
            ],
                    8 => '棱彩',
                ],
                4 => [
                    0 => '杂项',
                    1 => '布甲',
                    2 => '皮甲',
                    3 => '锁甲',
                    4 => '板甲',
                    5 => '小圆盾（废弃）',
                    6 => '盾牌',
                    7 => '圣契',
                    8 => '神像',
                    9 => '图腾',
                    10 => '印记',
                ],
                5 => [
                    0 => '材料',
                ],
                6 => [
                    0 => '魔杖（废弃）',
                    1 => '弩箭（废弃）',
                    2 => '箭',
                    3 => '子弹',
                    4 => '投掷物（废弃）',
                ],
                7 => [
                    0 => '商品',
                    1 => '零件',
                    2 => '爆炸物',
                    3 => '装置',
                    4 => '珠宝加工',
                    5 => '布料',
                    6 => '皮革',
                    7 => '金属与矿石',
                    8 => '肉类',
                    9 => '草药',
                    10 => '元素',
                    11 => '其他',
                    12 => '附魔',
                    13 => '材料',
                    14 => '护甲附魔',
                    15 => '武器附魔',
                ],
                8 => [
                    0 => '通用（废弃）',
                ],
                9 => [
                    0 => '书籍',
                    1 => '制皮',
                    2 => '裁缝',
                    3 => '工程',
                    4 => '锻造',
                    5 => '烹饪',
                    6 => '炼金',
                    7 => '急救',
                    8 => '附魔',
                    9 => '钓鱼',
                    10 => '珠宝加工',
                    11 => '铭文',
                ],
                10 => [
                    0 => '货币（废弃）',
                ],
                11 => [
                    0 => '箭袋（废弃）',
                    1 => '箭袋（废弃）',
                    2 => '箭袋',
                    3 => '弹药袋',
                ],
                12 => [
                    0 => '任务',
                ],
                13 => [
                    0 => '钥匙',
                    1 => '开锁器',
                ],
                14 => [
                    0 => '永久（废弃）',
                ],
                15 => [
                    0 => '垃圾',
                    1 => '施法材料',
                    2 => '宠物',
                    3 => '节日',
                    4 => '其他',
                    5 => '坐骑',
                ],
                16 => [
                    1 => '战士雕文',
                    2 => '圣骑士雕文',
                    3 => '猎人雕文',
                    4 => '潜行者雕文',
                    5 => '牧师雕文',
                    6 => '死亡骑士雕文',
                    7 => '萨满雕文',
                    8 => '法师雕文',
                    9 => '术士雕文',
                    11 => '德鲁伊雕文',
                ],
            ],
        ],
        'filter' => [
            'type_name' => '按名称',
            'type_id' => '按ID',
            'keyword_placeholder' => '关键字或ID',
            'quality_all' => '所有品质',
            'class_all' => '全部类别',
            'subclass_all' => '全部子类',
            'submit' => '搜索',
            'reset' => '清空',
            'reset_title' => '清空筛选',
            'create' => '新增',
            'sql_log' => '执行SQL日志',
        ],
        'table' => [
            'id' => 'ID',
            'name' => '名称',
            'quality' => '品质',
            'class' => '类别',
            'subclass' => '子类',
            'level' => '等级',
            'actions' => '操作',
            'empty' => '无结果',
        ],
        'tooltip' => [
            'quality' => '品质: :quality (:value)',
        ],
        'actions' => [
            'edit' => '编辑',
            'delete' => '删除',
        ],
        'modal' => [
            'common' => [
                'cancel' => '取消',
                'close' => '关闭',
            ],
            'new' => [
                'title' => '新增物品',
                'id_label' => '新建ID*',
                'copy_label' => '复制自(可选)',
                'copy_hint' => '留空复制ID则创建空白模板。',
                'class' => '类别',
                'subclass' => '子类',
                'submit' => '创建',
            ],
            'log' => [
                'title' => 'Item 日志查看',
                'type_label' => '日志类型',
                'type_sql' => 'SQL 执行',
                'type_deleted' => '删除快照',
                'type_actions' => '操作轨迹',
                'refresh' => '刷新',
                'placeholder' => '-- 暂无日志 --',
            ],
        ],
        'edit' => [
            'title' => '编辑物品 #:id',
            'page_title' => '编辑物品 #:id',
            'back_to_list' => '返回列表',
            'compact' => [
                'normal' => '标准模式',
                'compact' => '紧凑模式',
            ],
            'delete' => '删除',
            'save' => '保存',
            'diff_sql' => '差异 SQL',
            'flags' => [
                'title' => '标志 Flags',
                'choose' => '选择',
                'loading' => '（加载中…）',
            ],
            'description' => '描述',
            'group_fallback' => '分组 :index',
            'diff' => [
                'title' => '实时差异 SQL 预览',
                'full_mode' => '输出全部列',
                'hint' => '修改表单字段后会自动生成 UPDATE 语句。差异模式仅列出变更列，全量模式输出全部列；超过 200 字的文本会截断并标注。',
                'placeholder' => '-- 暂无变更 --',
                'exec_title' => '执行结果',
                'sample_title' => '示例行 / 变更预览',
            ],
            'actions' => [
                'copy' => '复制',
                'execute' => '执行',
                'clear' => '清空',
                'hide' => '隐藏',
                'copy_json' => '复制 JSON',
            ],
        ],
    ],
    'mail' => [
        'page_title' => '邮件管理',
        'filters' => [
            'sender' => '发送者',
            'receiver' => '接收者',
            'subject' => '主题包含',
            'unread_all' => '全部状态',
            'unread_only' => '仅未读',
            'attachments_all' => '附件（全部）',
            'attachments_only' => '仅有附件',
            'expiring' => '过期（天）',
            'actions' => [
                'search' => '搜索',
                'reset' => '清空',
                'refresh' => '刷新',
                'log' => '查看日志',
            ],
        ],
        'toolbar' => [
            'bulk_read' => '批量标记已读',
            'bulk_delete' => '批量删除（系统/GM）',
            'total' => '合计：',
        ],
        'table' => [
            'headers' => [
                'id' => 'ID',
                'sender' => '发送者',
                'receiver' => '接收者',
                'subject' => '主题',
                'money' => '金币',
                'attachments' => '附件',
                'expire' => '到期',
                'status' => '状态',
                'actions' => '操作',
            ],
            'attachments' => [
                'has' => '有',
            ],
            'status' => [
                'unread' => '未读',
                'read' => '已读',
            ],
            'actions' => [
                'view' => '查看',
                'mark_read' => '标记已读',
                'delete' => '删除',
            ],
            'expire' => [
                'none' => '—',
                'expired' => '已过期',
                'in_days' => ':days 天后',
            ],
            'loading' => '加载中…',
            'empty' => '无记录',
        ],
        'log_modal' => [
            'title' => '邮件操作日志',
            'type_label' => '日志类型',
            'types' => [
                'sql' => 'SQL 执行',
                'deleted' => '删除记录',
            ],
            'limit_label' => '数量',
            'limits' => [
                'recent' => '最近 :count 条',
            ],
            'refresh' => '刷新',
            'empty' => '-- 暂无日志 --',
            'close' => '关闭',
        ],
        'detail' => [
            'title' => '邮件详情',
            'loading' => '加载中…',
            'labels' => [
                'sender' => '发送者',
                'receiver' => '接收者',
                'money' => '金币',
                'expire' => '到期',
                'status' => '状态',
                'attachment_count' => '附件数',
                'subject' => '主题：',
                'attachments' => '附件：',
            ],
            'status' => [
                'unread' => '未读',
                'read' => '已读',
            ],
            'expire' => [
                'expired' => '已过期',
                'today' => '今天到期',
                'day_singular' => ':days 天后',
                'day_plural' => ':days 天后',
            ],
            'no_subject' => '（无主题）',
            'no_body' => '（无正文）',
            'attachments_yes' => '有',
            'attachments_no' => '无',
            'attachments_none' => '无附件',
            'close' => '关闭',
        ],
        'actions' => [
            'view' => '查看',
            'mark_read' => '标记已读',
            'delete' => '删除',
        ],
        'status' => [
            'load_failed' => '列表加载失败',
            'mark_read_done' => '邮件已标记为已读',
            'mark_failed' => '标记已读失败',
            'bulk_mark_done' => '已批量标记 :count 封为已读',
            'bulk_mark_failed' => '批量标记失败',
            'delete_done' => '邮件已删除',
            'delete_failed' => '删除失败',
            'bulk_delete_done' => '已批量删除 :count 封邮件',
            'bulk_delete_failed' => '批量删除失败',
            'logs_failed' => '日志加载失败',
            'detail_failed' => '邮件详情加载失败',
            'action_failed' => '操作失败',
        ],
        'confirm' => [
            'delete_one' => '确认删除此邮件（系统/GM）？',
            'delete_selected' => '确认删除所选邮件？',
        ],
        'logs' => [
            'loading' => '-- 加载中 --',
            'empty' => '-- 暂无日志 --',
            'failed' => '-- 加载失败 --',
            'error' => '日志加载失败',
            'meta' => ':file ｜ 条数: :count',
            'meta_with_server' => ':file ｜ 条数: :count ｜ 服务器: :server',
        ],
        'tail_log' => [
            'unknown_type' => '未知日志类型',
            'sql_entry' => '[:time][:server] :user :operation :status (影响: :affected)',
            'sql_suffix' => ' | :sql',
            'sql_error_suffix' => ' | 错误：:error',
            'action_entry' => '[:time][:server] :user :action ID: :id',
            'action_snapshot_suffix' => ' SQL：:snapshot',
        ],
        'stats' => [
            'summary' => '未读估算: :unread ｜ 7天内到期: :expiring',
        ],
        'errors' => [
            'init_failed' => '邮件模块初始化失败',
            'exception' => '邮件模块异常',
        ],
        'api' => [
            'errors' => [
                'unauthorized' => '未授权',
                'invalid_id' => '无效ID',
                'missing_id' => '缺少ID',
                'no_valid_id' => '无有效ID',
                'not_found' => '不存在',
                'delete_restricted' => '删除失败: 仅系统或GM邮件',
                'repository_not_ready' => '仓库未初始化',
            ],
            'success' => [
                'marked_read' => '已标记已读',
                'no_changes' => '未更改',
                'bulk_marked' => '批量已读: :count 封',
                'deleted_single' => '已删除(系统/GM邮件)',
                'bulk_deleted' => '批量删除: :count 封',
                'bulk_deleted_blocked_suffix' => '，阻止 :count 封',
            ],
        ],
    ],
    'mass_mail' => [
        'index' => [
            'page_title' => '群发系统',
            'sections' => [
                'announce' => [
                    'title' => '发布公告',
                    'message_label' => '公告内容',
                    'message_placeholder' => '请输入公告内容',
                    'submit' => '发送公告',
                    'hint' => '将同时执行 .announce 与 .notify',
                ],
                'boost' => [
                    'title' => '角色直升',
                    'character_label' => '角色名称',
                    'character_placeholder' => '请输入角色名',
                    'level_label' => '目标等级',
                    'level_options' => [
                        '60' => '60级',
                        '70' => '70级',
                        '80' => '80级',
                    ],
                    'summary_label' => '礼包内容',
                    'summary_prefill' => "500金 (5,000,000铜)\n灵纹布包 ×3 (#21841)\n乌龟坐骑 ×1 (#23720)\n角色职业对应 T2 套装 (自动识别)",
                    'submit' => '执行直升',
                ],
                'send' => [
                    'title' => '群发邮件 / 物品 / 金币',
                    'action_label' => '发送类型',
                    'action_placeholder' => '--请选择--',
                    'action_options' => [
                        'send_mail' => '邮件(纯文本)',
                        'send_item' => '物品(邮件附带)',
                        'send_gold' => '金币(邮件附带)',
                    ],
                    'target_label' => '目标类型',
                    'target_options' => [
                        'online' => '在线角色',
                        'custom' => '自定义列表',
                    ],
                    'subject_label' => '标题',
                    'subject_default' => 'PureLand',
                    'body_label' => '正文',
                    'body_default' => '这是来自管理团队的邮件，祝您游戏愉快。',
                    'item_id_label' => '物品ID',
                    'quantity_label' => '数量',
                    'gold_label' => '金币(铜为单位)',
                    'gold_preview_placeholder' => '—',
                    'custom_list_label' => '自定义角色列表 (每行一个)',
                    'submit' => '执行群发',
                    'hint' => '角色上限 2000，失败角色在日志 recipients 中使用 ! 标记。',
                ],
                'logs' => [
                    'title' => '最近操作日志',
                    'limit_label' => '显示数量',
                    'limit_options' => [
                        '30' => '最近30',
                        '50' => '最近50',
                        '100' => '最近100',
                    ],
                    'refresh' => '刷新',
                    'table' => [
                        'headers' => [
                            'time' => '时间',
                            'type' => '类型',
                            'details' => '详情',
                            'targets' => '目标',
                            'success_fail' => '成功/失败',
                            'status' => 'OK',
                            'recipients' => '接收者',
                        ],
                        'item_prefix' => '物品：#:id',
                        'item_name_separator' => ' - ',
                        'item_quantity_prefix' => ' ×',
                        'gold_units' => [
                            'gold' => ':value金',
                            'silver' => ':value银',
                            'copper' => ':value铜',
                            'separator' => '',
                        ],
                        'gold_label' => '金币：:value',
                        'error_prefix' => '错误：',
                        'recipients_placeholder' => '—',
                        'empty' => '无日志',
                    ],
                ],
            ],
            'confirm' => [
                'title' => '请确认批量发送',
                'hint_html' => '输入 <code>CONFIRM</code> 以继续',
                'input_placeholder' => 'CONFIRM',
                'cancel' => '取消',
                'submit' => '继续执行',
            ],
        ],
        'announce' => [
            'validation' => [
                'empty' => '请输入公告内容',
            ],
        ],
        'feedback' => [
            'done' => '完成',
        ],
        'errors' => [
            'network' => '网络异常',
            'parse_failed' => '响应解析失败'
        ],
        'status' => [
            'sending' => '发送中…',
        ],
        'confirm' => [
            'heading' => '即将执行 <strong>:action</strong>',
            'subject' => '标题：:value',
            'item' => '物品ID：:id × :count',
            'gold' => '金币（铜）：:amount',
            'target_type' => '目标类型：:value',
            'custom_count' => '自定义角色数：:count',
            'online' => '在线角色：数量将在发送时实时获取',
            'footer' => '系统已启用分批发送（批次=200）。请确认无误后继续。',
        ],
        'logs' => [
            'empty' => '无日志',
            'item_label' => '物品：#:id',
            'item_name_separator' => ' - ',
            'item_quantity_prefix' => ' ×',
            'gold_label' => '金币：:value',
            'error_prefix' => '错误：',
        ],
        'boost' => [
            'summary' => [
                'gold' => '500 金（:copper 铜）',
                'bag' => '灵纹布包 ×:count (#21841)',
                'mount' => '乌龟坐骑 ×:count (#23720)',
                'set' => '角色职业对应 T2 套装（自动识别）',
            ],
            'validation' => [
                'name' => '请输入角色名称',
                'level' => '请选择目标等级',
            ],
            'status' => [
                'executing' => '执行中…',
            ],
        ],
        'gold' => [
            'units' => [
                'gold' => '金',
                'silver' => '银',
                'copper' => '铜',
            ],
        ],
        'service' => [
            'announce' => [
                'message_required' => '公告内容不能为空',
                'success' => '公告已发送',
                'partial' => '公告已发送但存在错误',
            ],
            'bulk' => [
                'subject_required' => '邮件主题不能为空',
                'no_targets' => '未选择任何收件人',
                'target_limit' => '收件人数量超过上限（最多 :max 个）',
                'item_invalid' => '物品 ID 或数量无效',
                'item_missing' => '物品不存在（ID :id）',
                'gold_invalid' => '金额必须大于 0',
                'unknown_action' => '未知操作类型',
                'action_labels' => [
                    'send_mail' => '发送邮件',
                    'send_item' => '发送物品',
                    'send_gold' => '发送金币',
                ],
                'summary' => '操作：:action · 批次：:batches · 目标：:targets · 成功：:success · 失败：:fail',
                'sample_errors' => '错误示例：:errors',
            ],
            'boost' => [
                'name_required' => '角色名称不能为空',
                'level_unsupported' => '不受支持的目标等级',
                'character_missing' => '未找到角色或职业不支持',
                'config_empty' => '直升礼包配置为空',
                'unknown_error' => '未知错误',
                'command_failed' => ':label 执行失败：:reason',
                'success' => '角色 :name 已直升至 :level 级并发送礼包。',
                'partial' => '直升执行完成但存在错误：:errors',
                'mail' => [
                    'subject' => '直升礼包',
                    'body_items' => '直升礼包物品，请查收。',
                    'body_gold' => '直升礼包金币，请查收。',
                ],
                'commands' => [
                    'items' => '发送物品',
                    'gold' => '发送金币',
                    'level' => '设置等级',
                ],
                'class_labels' => [
                    'warrior' => '战士',
                    'paladin' => '圣骑士',
                    'hunter' => '猎人',
                    'rogue' => '潜行者',
                    'priest' => '牧师',
                    'shaman' => '萨满祭司',
                    'mage' => '法师',
                    'warlock' => '术士',
                    'druid' => '德鲁伊',
                ],
                'log_subject' => '直升礼包发送给 :name',
                'log_item_label' => ':class 直升物品',
            ],
        ],
    ],
    'item_owner' => [
        'page_title' => '物品归属',
        'search' => [
            'title' => '物品查找',
            'subtitle' => '通过名称或物品ID查询哪些角色持有该物品。',
            'keyword_label' => '关键词',
            'keyword_placeholder' => '名称或物品ID',
            'submit' => '搜索',
            'status' => [
                'loading' => '加载中…',
            ],
            'results' => [
                'entry' => '物品ID',
                'name' => '名称',
                'quality' => '品质',
                'stackable' => '堆叠上限',
                'actions' => '操作',
                'placeholder' => '搜索结果将显示在此处。',
                'view' => '查看归属',
                'empty' => '未找到物品',
            ],
            'validation' => [
                'empty' => '请输入关键词',
            ],
            'error' => [
                'failed' => '搜索失败',
            ],
        ],
        'results' => [
            'title_empty' => '选择一个物品',
            'subtitle_empty' => '搜索物品以查看归属详情。',
            'title_loading' => '正在加载归属…',
            'title_error' => '归属加载失败',
            'subtitle_totals' => ':characters 个角色 · :instances 个堆叠 · 共计 :count 件',
            'status' => [
                'loading' => '加载中…',
            ],
            'error' => [
                'load_failed' => '归属加载失败',
            ],
            'characters' => [
                'title' => '角色',
                'name' => '角色名',
                'level' => '等级',
                'class' => '职业',
                'total' => '总数量',
                'placeholder' => '没有角色拥有该物品',
            ],
            'instances' => [
                'title' => '物品实例',
                'instance' => '实例GUID',
                'character' => '角色',
                'count' => '数量',
                'location' => '位置',
                'container' => '容器',
                'placeholder' => '没有可显示的实例',
            ],
        ],
        'actions' => [
            'delete_selected' => '删除选中',
            'replace_selected' => '替换选中',
            'confirm_delete' => '确定删除选中的物品实例？',
            'delete_success' => '已删除选中的物品实例',
            'delete_failed' => '删除物品实例失败',
            'replace_success' => '已替换选中的物品实例',
            'replace_failed' => '替换物品实例失败',
        ],
        'modal' => [
            'replace' => [
                'title' => '替换物品',
                'entry_label' => '新的物品ID',
                'entry_placeholder' => '输入物品ID',
                'entry_hint' => '所有选中的物品实例将替换为此物品。',
                'cancel' => '取消',
                'confirm' => '应用',
                'validation' => [
                    'entry' => '请输入有效的物品ID',
                ],
            ],
        ],
        'quality' => [
            'unknown' => '未知',
            '0' => '粗糙',
            '1' => '普通',
            '2' => '优秀',
            '3' => '精良',
            '4' => '史诗',
            '5' => '传说',
            '6' => '神器',
            '7' => '传家宝',
        ],
        'api' => [
            'errors' => [
                'invalid_entry' => '无效的物品ID。',
                'entry_not_found' => '未找到物品。',
                'empty_selection' => '请先选择至少一个物品实例。',
                'instances_not_found' => '所选物品实例不存在或已被移除。',
                'invalid_instance' => '无效的物品实例。',
                'unknown_action' => '未知操作。',
                'invalid_new_entry' => '请输入有效的替换物品ID。',
                'new_entry_not_found' => '替换的物品ID不存在。',
                'stack_too_large' => '堆叠数量 :stack 超过上限 :limit。',
                'delete_partial' => '已删除 :success 个实例，:failed 个失败。',
                'replace_partial' => '已替换 :success 个实例，:failed 个失败。',
                'replace_failed' => '更新物品实例失败。',
            ],
            'success' => [
                'delete_done' => '已删除 :count 个物品实例。',
                'replace_done' => '已替换 :count 个物品实例。',
            ],
        ],
        'locations' => [
            'equipment' => [
                'head' => '头部',
                'neck' => '颈部',
                'shoulders' => '肩部',
                'body' => '衬衣',
                'chest' => '胸甲',
                'waist' => '腰部',
                'legs' => '腿部',
                'feet' => '脚部',
                'wrist' => '护腕',
                'hands' => '手部',
                'finger1' => '戒指1',
                'finger2' => '戒指2',
                'trinket1' => '饰品1',
                'trinket2' => '饰品2',
                'back' => '背部',
                'main_hand' => '主手',
                'off_hand' => '副手',
                'ranged' => '远程/圣物',
                'tabard' => '战袍',
            ],
            'inventory' => [
                'backpack' => '背包槽位 :slot',
                'bank_main' => '银行槽位 :slot',
                'keyring' => '钥匙链槽位 :slot',
                'currency' => '货币槽位 :slot',
                'bag_slot' => '背包位 :slot',
                'bag_inner' => '背包 :bag 第 :slot 格',
                'unknown' => '背包（未知槽位）',
            ],
            'bank' => [
                'bag_slot' => '银行包位 :slot',
                'bag_inner' => '银行包 :bag 第 :slot 格',
            ],
        ],
    ],
    'audit' => [
        'api' => [
            'errors' => [
                'read_failed' => '读取失败',
            ],
        ],
    ],
    'logs' => [
        'page_title' => '统一日志管理',
        'intro' => '集中查看面板各模块日志，支持快速筛选、自动刷新和原始输出。',
        'fields' => [
            'module' => '模块',
            'type' => '类型',
            'limit' => '行数',
        ],
        'actions' => [
            'load' => '加载',
            'auto_refresh' => '开启自动刷新',
        ],
        'table' => [
            'headers' => [
                'time' => '时间',
                'server' => '服务器',
                'actor' => '操作人',
                'summary' => '摘要',
            ],
            'loading' => '加载中…',
        ],
        'raw' => [
            'title' => '原始日志',
            'empty' => '-- 等待加载 --',
        ],
    ],
    'bag_query' => [
        'page_title' => '背包 / 物品查询',
        'form' => [
            'type_label' => '查询类型',
            'type_character_name' => '角色名称（模糊）',
            'type_username' => '账号用户名',
            'value_label' => '查询值',
            'value_placeholder' => '输入角色或账号',
            'submit' => '搜索',
        ],
        'chars' => [
            'title' => '角色列表',
            'subtitle' => '选择一个角色查看背包详情',
            'table' => [
                'guid' => 'GUID',
                'name' => '名称',
                'level' => '等级',
                'race' => '种族',
                'class' => '职业',
                'actions' => '操作',
                'empty' => '等待搜索…',
            ],
        ],
        'items' => [
            'title' => '物品列表',
            'subtitle_empty' => '未选择角色',
            'filter_placeholder' => '过滤物品名称',
            'table' => [
                'instance_guid' => '实例 GUID',
                'item_id' => '物品 ID',
                'name' => '名称',
                'quality' => '品质',
                'count' => '数量',
                'slot' => '包 / 槽位',
                'actions' => '操作',
                'empty' => '未选择角色',
            ],
        ],
        'modal' => [
            'title' => '删除 / 减少物品',
            'quantity_label' => '数量',
            'quantity_hint' => '若数量 ≥ 当前堆叠，将删除该实例。',
            'cancel' => '取消',
            'confirm' => '确认执行',
        ],
    ],
    'js' => [
        'common' => [
            'loading' => '加载中…',
            'no_data' => '暂无数据',
            'search_placeholder' => '搜索…',
            'errors' => [
                'network' => '网络异常',
                'timeout' => '请求超时',
                'invalid_json' => '响应格式无效',
                'unknown' => '未知错误',
            ],
            'actions' => [
                'close' => '关闭',
                'confirm' => '确定',
                'cancel' => '取消',
                'retry' => '重试',
            ],
            'yes' => '是',
            'no' => '否',
        ],
        'modules' => [
            'logs' => [
                'summary' => [
                    'module' => '模块：',
                    'type' => '类型：',
                    'source' => '来源文件：',
                    'display' => '当前显示：',
                    'separator' => ' ｜ ',
                ],
                'status' => [
                    'no_entries' => '暂无日志',
                    'panel_not_ready' => 'Panel API 未就绪，请检查 panel.js 是否加载成功。',
                    'panel_waiting' => 'Panel API 初始化中，请稍候…',
                    'load_failed' => '加载失败',
                    'no_raw' => '-- 无日志 --',
                    'request_error' => '请求异常',
                    'exception_prefix' => '[异常] ',
                    'error_prefix' => '[错误] ',
                    'info_prefix' => '[信息] ',
                ],
                'actions' => [
                    'auto_on' => '开启自动刷新',
                    'auto_off' => '关闭自动刷新',
                ],
            ],
            'bag_query' => [
                'quality' => [
                    '0' => '粗糙',
                    '1' => '普通',
                    '2' => '优秀',
                    '3' => '精良',
                    '4' => '史诗',
                    '5' => '传说',
                    '6' => '神器',
                    '7' => '传家宝',
                ],
                'classes' => [
                    'warrior' => '战士',
                    'paladin' => '圣骑士',
                    'hunter' => '猎人',
                    'rogue' => '盗贼',
                    'priest' => '牧师',
                    'death-knight' => '死亡骑士',
                    'shaman' => '萨满祭司',
                    'mage' => '法师',
                    'warlock' => '术士',
                    'monk' => '武僧',
                    'druid' => '德鲁伊',
                    'demon-hunter' => '恶魔猎手',
                ],
                'errors' => [
                    'parse_failed' => '解析响应失败',
                    'network' => '网络异常',
                ],
                'status' => [
                    'loading' => '加载中…',
                ],
                'search' => [
                    'validation' => [
                        'empty' => '请输入查询值',
                    ],
                    'error' => [
                        'failed' => '查询失败',
                    ],
                    'empty' => '暂无结果',
                ],
                'items' => [
                    'subtitle' => [
                        'none' => '未选择角色',
                        'current_name' => '当前角色：:name',
                        'current_guid' => '当前角色 GUID :guid',
                        'with_status' => ':base（:status）',
                    ],
                    'placeholder' => [
                        'none' => '未选择角色',
                    ],
                    'filter' => [
                        'placeholder' => '按名称过滤物品',
                    ],
                    'empty' => '未找到物品或背包为空',
                    'quality' => [
                        'unknown' => '未知',
                    ],
                    'error' => [
                        'load_failed' => '物品加载失败',
                    ],
                ],
                'actions' => [
                    'view' => '查看',
                    'delete' => '删除',
                    'processing' => '执行中…',
                ],
                'delete' => [
                    'info' => '物品 <strong>#:entry :name</strong> 当前数量 <strong>:count</strong><br>实例 GUID：:inst',
                    'validation' => [
                        'quantity' => '数量必须大于 0 且不超过当前堆叠',
                    ],
                    'success' => '物品已删除',
                    'error' => '操作失败',
                ],
            ],
            'item_owner' => [
                'search' => [
                    'validation' => [
                        'empty' => '请输入关键词',
                    ],
                    'error' => [
                        'failed' => '搜索失败',
                    ],
                    'status' => [
                        'loading' => '加载中…',
                    ],
                    'results' => [
                        'empty' => '未找到物品',
                        'view' => '查看归属',
                    ],
                ],
                'results' => [
                    'title_empty' => '选择一个物品',
                    'subtitle_empty' => '搜索物品以查看归属详情。',
                    'title_loading' => '正在加载归属…',
                    'title_error' => '归属加载失败',
                    'subtitle_totals' => ':characters 个角色 · :instances 个堆叠 · 共计 :count 件',
                    'status' => [
                        'loading' => '加载中…',
                    ],
                    'error' => [
                        'load_failed' => '归属加载失败',
                    ],
                    'characters' => [
                        'placeholder' => '没有角色拥有该物品',
                    ],
                    'instances' => [
                        'placeholder' => '没有可显示的实例',
                    ],
                ],
                'actions' => [
                    'confirm_delete' => '确定删除选中的物品实例？',
                    'delete_success' => '已删除选中的物品实例',
                    'delete_failed' => '删除物品实例失败',
                    'replace_success' => '已替换选中的物品实例',
                    'replace_failed' => '替换物品实例失败',
                ],
                'modal' => [
                    'replace' => [
                        'validation' => [
                            'entry' => '请输入有效的物品ID',
                        ],
                    ],
                ],
                'quality' => [
                    'unknown' => '未知',
                    '0' => '粗糙',
                    '1' => '普通',
                    '2' => '优秀',
                    '3' => '精良',
                    '4' => '史诗',
                    '5' => '传说',
                    '6' => '神器',
                    '7' => '传家宝',
                ],
            ],
            'creature' => [
                'create' => [
                    'enter_new_id' => '请输入新ID',
                    'success_redirect' => '创建成功，正在跳转',
                    'failure' => '创建失败',
                    'failure_with_reason' => '创建失败: :reason',
                ],
                'logs' => [
                    'loading_placeholder' => '-- 加载中... --',
                    'empty_placeholder' => '-- 暂无日志 --',
                    'load_failed_placeholder' => '-- 加载失败 --',
                    'load_failed' => '日志加载失败',
                    'load_failed_with_reason' => '日志加载失败: :reason',
                ],
                'list' => [
                    'confirm_delete' => '确认删除 :id ?',
                    'delete_success' => '删除完成',
                    'delete_failed' => '删除失败',
                    'delete_failed_with_reason' => '删除失败: :reason',
                ],
                'diff' => [
                    'group_change_count' => '(:count 项修改)',
                    'no_changes_placeholder' => '-- 暂无变更 --',
                    'copy_sql_success' => '已复制 SQL',
                ],
                'common' => [
                    'copy_failed' => '复制失败',
                ],
                'errors' => [
                    'panel_api_not_ready' => 'Panel API 未就绪',
                ],
                'exec' => [
                    'actions' => [
                        'clear' => '清空',
                        'hide' => '隐藏',
                        'copy_json' => '复制 JSON',
                        'copy_sql' => '复制 SQL',
                    ],
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
                    'confirm_run_diff' => "确认执行以下 SQL？\n:sql",
                    'no_diff_sql' => '没有可执行的差异 SQL',
                    'diff_sql_success' => '差异 SQL 执行成功',
                    'prompt_sql' => '输入要执行的 UPDATE/INSERT SQL (单条)',
                    'only_update_insert' => '仅允许 UPDATE 或 INSERT',
                    'status' => [
                        'save_success' => '保存成功',
                        'run_success' => '执行成功',
                        'save_failed' => '保存失败',
                        'run_failed' => '执行失败',
                    ],
                ],
                'models' => [
                    'confirm_delete' => '删除该模型?',
                    'save_success' => '模型保存成功',
                    'save_failed' => '模型保存失败',
                    'save_failed_with_reason' => '模型保存失败: :reason',
                    'delete_success' => '模型已删除',
                    'delete_failed' => '删除失败',
                'api' => [
                    'errors' => [
                        'invalid_guid' => '无效的 GUID',
                        'invalid_parameters' => '参数无效',
                        'quantity_positive' => '数量必须大于 0',
                        'instance_not_found' => '实例不存在或不属于该角色',
                        'quantity_exceeds_stack' => '数量超过当前堆叠',
                        'inventory_mismatch' => 'inventory 未匹配',
                        'reduce_failed' => '操作失败：:message',
                    ],
                    'success' => [
                        'item_deleted' => '物品已删除',
                        'quantity_reduced' => '数量已减少',
                    ],
                ],
                    'delete_failed_with_reason' => '删除失败: :reason',
                ],
                'save' => [
                    'no_changes' => '没有修改需要保存',
                    'success' => '保存成功',
                    'failed' => '保存失败',
                    'failed_with_reason' => '保存失败: :reason',
                    'confirm_delete_creature' => '确认删除生物 :id ?',
                    'delete_success' => '已删除',
                    'delete_failed' => '删除失败',
                    'delete_failed_with_reason' => '删除失败: :reason',
                ],
                'verify' => [
                    'failure' => '校验失败',
                    'failure_with_reason' => '校验失败: :reason',
                    'diff_bad' => '不同',
                    'diff_ok' => '一致',
                    'diff_summary' => '检测到 :count 处不一致',
                    'copy_update' => '复制UPDATE语句',
                    'copied' => '已复制',
                    'row_match' => '该行一致',
                ],
                'nav' => [
                    'auto_group_title' => '分组 :index',
                ],
                'compact' => [
                    'mode' => [
                        'normal' => '正常',
                        'compact' => '紧凑',
                    ],
                ],
                'bitmask' => [
                    'modal_title' => '位标志选择',
                    'search_placeholder' => '搜索...',
                    'select_all' => '全选',
                    'clear' => '清空',
                    'tips' => '提示：勾选即时更新值。搜索可过滤描述。',
                    'close' => '关闭',
                    'field_title' => ':field (:value)',
                    'trigger' => '位',
                ],
            ],
            'item' => [
                'common' => [
                    'copy_success' => '已复制',
                ],
                'create' => [
                    'enter_new_id' => '请输入新ID',
                    'success_redirect' => '创建成功，正在跳转',
                    'failure' => '创建失败',
                    'failure_with_reason' => '创建失败: :reason',
                    'subclass' => [
                        'loading_option' => '加载中...',
                    ],
                ],
                'list' => [
                    'confirm_delete' => '确认删除物品 #:id?',
                    'delete_success' => '物品已删除',
                    'delete_failed' => '删除失败',
                    'delete_failed_with_reason' => '删除失败: :reason',
                    'subclass' => [
                        'all_option' => '(全部子类)',
                        'loading_option' => '加载中...',
                    ],
                ],
                'diff' => [
                    'no_changes_comment' => '-- 无变化 (修改表单后再试)',
                    'no_changes_placeholder' => '-- 暂无变更 --',
                    'no_changes_to_execute' => '没有可执行的变更',
                    'comment' => [
                        'class_fallback_name' => '类别 :id',
                        'class_label' => '类别',
                        'subclass_fallback_name' => '子类 :id',
                        'subclass_label' => '子类',
                    ],
                    'modal' => [
                        'title' => 'UPDATE 预览',
                        'copy_button' => '复制',
                        'close_button' => '关闭',
                    ],
                ],
                'exec' => [
                    'only_item_template_update' => '只允许执行 item_template 的 UPDATE',
                    'confirm_run_diff' => '确认执行当前 SQL?',
                    'status' => [
                        'success' => '成功',
                        'failed' => '失败',
                    ],
                    'timing' => '耗时 :duration',
                    'summary' => [
                        'rows_label' => '影响行数:',
                    ],
                    'default_error' => '执行失败',
                    'warning_prefix' => '警告:',
                    'error_prefix' => '错误:',
                    'messages' => [
                        'none' => '-- 无警告',
                        'check_above' => '-- 查看上方错误',
                    ],
                    'run_success' => '执行成功',
                    'run_failed_with_reason' => '执行失败: :reason',
                    'copy_json_success' => '已复制 JSON',
                    'request_exception' => '请求异常: :reason',
                ],
                'logs' => [
                    'loading_placeholder' => '-- 加载中... --',
                    'empty_placeholder' => '-- 暂无日志 --',
                    'load_failed_placeholder' => '-- 加载失败 --',
                    'load_failed' => '日志加载失败',
                    'load_failed_with_reason' => '日志加载失败: :reason',
                ],
                'save' => [
                    'no_changes' => '没有修改需要保存',
                    'success' => '保存成功',
                    'failed' => '保存失败',
                    'failed_with_reason' => '保存失败: :reason',
                    'confirm_delete_item' => '确认删除物品 #:id?',
                    'delete_success' => '物品已删除',
                    'delete_failed' => '删除失败',
                ],
            ],
            'quest' => [
                'api' => [
                    'not_ready' => 'Panel.api 未就绪',
                ],
                'logs' => [
                    'loading_placeholder' => '-- 加载中...',
                    'empty_placeholder' => '-- 暂无日志 --',
                    'error_placeholder' => '-- 加载失败 --',
                    'load_failed' => '日志加载失败',
                    'load_failed_with_reason' => '日志加载失败: :reason',
                ],
                'create' => [
                    'enter_new_id' => '请输入新任务 ID',
                    'success_redirect' => '任务创建成功，正在跳转',
                    'failed' => '创建失败',
                    'failed_with_reason' => '创建失败: :reason',
                ],
                'list' => [
                    'core' => [
                        'no_changes_sql_comment' => '-- 无改动 --',
                    ],
                    'delete_failed_with_reason' => '删除失败: :reason',
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
                    'diff_count' => ':count 改动',
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
            'smartai' => [
                'segments' => [
                    'move_up_title' => '上移',
                    'move_down_title' => '下移',
                    'delete_segment_title' => '删除分段',
                    'default_label' => '分段 :number',
                    'empty_prompt' => '请添加一个分段。',
                ],
                'search' => [
                    'placeholder' => '搜索关键字或 ID',
                ],
                'list' => [
                    'empty' => '未找到匹配项',
                ],
                'selector' => [
                    'select_type' => '请选择类型。',
                    'no_params' => '该类型无额外参数。',
                ],
                'validation' => [
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
                ],
                'api' => [
                    'no_response' => '服务器无响应',
                ],
                'preview' => [
                    'placeholder' => '-- 暂无生成的 SQL --',
                    'error_placeholder' => '-- 生成失败，请检查表单错误 --',
                ],
                'summary' => [
                    'segments' => '分段数: :count',
                    'event' => '事件: :name',
                    'action' => '动作: :name',
                    'target' => '目标: :name',
                ],
                'feedback' => [
                    'generate_success' => 'SQL 生成成功',
                    'generate_failed' => '生成失败',
                    'copy_success' => '已复制到剪贴板',
                    'copy_failed' => '复制失败，请手动复制',
                ],
            ],
            'bitmask' => [
                'popup' => [
                    'title' => '位标志：:name',
                ],
                'help' => [
                    'toggle_tip' => '点击切换；按住 Shift 拖动可多选。',
                ],
                'actions' => [
                    'close' => '关闭',
                    'clear' => '清空',
                    'apply' => '应用',
                ],
                'labels' => [
                    'joiner' => '、',
                    'none' => '（无）',
                ],
                'status' => [
                    'current_value' => '当前值：:value',
                ],
                'filter' => [
                    'placeholder' => '输入关键字过滤',
                ],
                'controls' => [
                    'select_all' => '全选',
                    'select_none' => '全不选',
                    'select_invert' => '反选',
                ],
                'option' => [
                    'label' => '(:bit) :name',
                ],
                'modal' => [
                    'title' => '编辑 :target',
                ],
            ],
        ],
    ],
    'quest' => [
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
                    'copy_hint' => '留空复制 ID 将创建空白任务模板。',
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
                'invalid_id' => '无效的任务ID。',
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
                '0' => '普通',
                '1' => '小队',
                '21' => '生活',
                '41' => 'PVP',
                '62' => '团队',
                '81' => '地下城',
                '82' => '事件',
                '83' => '传奇',
                '84' => '护送',
                '85' => '英雄',
                '88' => '团队（10人）',
                '89' => '团队（25人）',
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
    ],
    'realm' => [
        'errors' => [
            'not_logged_in' => '请先登录。',
            'not_found' => '服务器不存在。',
        ],
    ],
    'setup' => [
        'layout' => [
            'page_title' => '安装向导 - Acore GM Panel',
            'intro' => '按照向导完成环境检测、数据库配置和管理员创建，即可开始使用面板。',
            'step_titles' => [
                1 => '环境检测',
                2 => '模式与数据库',
                3 => '连接测试',
                4 => '管理员账号',
                5 => '完成',
            ],
            'stepper_label' => '安装步骤',
        ],
        'flash' => [
            'already_installed' => '系统已安装。如需重新安装，请删除 config/generated/install.lock。',
            'install_success_debug' => '安装成功。若需调试，请编辑 config/generated/app.php 将 debug 改为 true。',
        ],
        'env' => [
            'title' => '步骤 1 · 环境检测',
            'hint' => '请确认服务器满足运行要求，全部通过后即可选择向导语言。',
            'pill' => '环境',
            'checks' => [
                'php_version' => 'PHP 版本',
                'pdo_mysql' => 'PDO MySQL 扩展',
                'soap' => 'SOAP 扩展',
                'mbstring' => 'mbstring 扩展',
                'config_writable' => 'config 目录可写',
            ],
            'requirements' => [
                'writable' => '可写',
            ],
            'messages' => [
                'write_failed' => '无法写入（请检查目录权限）',
                'create_failed' => '创建失败（检查父目录权限）',
                'created' => '（已创建）',
            ],
            'check_passed' => '检测已全部通过，请选择界面语言后继续。',
            'check_failed' => '存在未通过的检测项，请根据提示完成安装后重试。',
            'retry' => '重新检测',
            'language_title' => '语言选择',
            'language_intro' => '选择向导和面板后续使用的界面语言。',
            'language_hint' => '界面语言 · :code',
            'language_submit' => '下一步：配置模式',
            'language_submit_fail' => '保存语言选择失败，请稍后再试。',
            'invalid_locale' => '语言选择无效。',
        ],
        'mode' => [
            'step_title' => '步骤 :current / :total · 模式与数据库',
            'section' => [
                'mode' => [
                    'title' => '选择部署模式',
                    'hint' => '根据计划托管的 Realm 数量选择对应模式。',
                    'pill' => '模式',
                    'aria_group' => '部署模式列表',
                ],
                'auth' => [
                    'title' => '主 Auth 数据库',
                    'hint' => '始终需要配置的核心连接，其他模块默认继承此处设置。',
                    'pill' => 'Auth',
                ],
                'shared_db' => [
                    'title' => '共享 Realm 默认值',
                    'hint' => '设置可复用的端口与账号，供各 Realm 继承使用。',
                    'pill' => '共享数据库',
                    'toggle_aria' => '共享数据库模式',
                    'toggle_shared' => '使用共享默认值',
                    'toggle_custom' => '仅逐个 Realm 配置',
                    'characters' => [
                        'title' => '角色库默认值',
                    ],
                    'world' => [
                        'title' => '世界库默认值',
                    ],
                    'summary_shared' => 'Realm 将默认继承这些连接设置，必要时可再覆盖。',
                    'summary_custom' => '共享默认已关闭，每个 Realm 必须填写完整连接信息。',
                ],
                'single_realm' => [
                    'title' => '单服数据库',
                    'hint' => '配置主 Realm 的角色库与世界库连接。',
                    'pill' => '单 Realm',
                    'advanced_auth' => '高级凭据',
                    'characters' => [
                        'title' => '角色数据库',
                    ],
                    'world' => [
                        'title' => '世界数据库',
                    ],
                ],
                'realms' => [
                    'title' => 'Realm 列表',
                    'hint' => '运行 worldserver 后可自动发现，亦可手动添加。',
                    'pill' => 'Realm',
                ],
                'shared_realm' => [
                    'note_shared' => 'Realm 将以共享数据库或 SOAP 默认值为起点，可按需覆盖字段。',
                    'note_custom' => '已禁用共享默认值，每个 Realm 需填写完整的数据库与 SOAP 信息。',
                ],
                'soap' => [
                    'title' => '全局 SOAP 配置',
                    'hint' => '为面板和定时任务提供默认 SOAP 入口。',
                    'pill' => 'SOAP',
                ],
                'shared_soap' => [
                    'toggle_aria' => '共享 SOAP 模式',
                    'toggle_shared' => '使用共享 SOAP 默认值',
                    'toggle_custom' => '按 Realm 配置 SOAP',
                    'summary_shared' => 'Realm 将默认继承此 SOAP 连接。',
                    'summary_custom' => '每个 Realm 需提供独立的 SOAP 连接。',
                ],
            ],
            'cards' => [
                'single' => [
                    'title' => '单 Realm',
                    'badge' => '快速开始',
                    'desc' => '单个 Realm，共享主 Auth 数据库。',
                    'aria' => '选择单 Realm 部署',
                    'tags' => [
                        'shared_account' => '共享 Auth 库',
                        'single_realm' => '仅 1 个 Realm',
                        'low_maintenance' => '维护成本低',
                    ],
                ],
                'multi' => [
                    'title' => '多 Realm（共享 Auth）',
                    'badge' => '常用',
                    'desc' => '共用 Auth，角色库与世界库按 Realm 拆分。',
                    'aria' => '选择多 Realm 共享 Auth 部署',
                    'tags' => [
                        'shared_auth' => '共享 Auth 库',
                        'split_characters' => '角色库按 Realm',
                        'port_reuse' => '端口可复用',
                    ],
                ],
                'multi_full' => [
                    'title' => '多 Realm（完全隔离）',
                    'badge' => '高级',
                    'desc' => '每个 Realm 拥有独立的 Auth、角色与世界数据库。',
                    'aria' => '选择多 Realm 完全隔离部署',
                    'tags' => [
                        'full_isolation' => '数据库完全隔离',
                        'security' => '安全性更高',
                        'high_complexity' => '维护最复杂',
                    ],
                ],
            ],
            'matrix' => [
                'aria' => '模式对比表',
                'head' => [
                    'type' => '部署模式',
                    'auth_db' => 'Auth 数据库',
                    'auth_port' => 'Auth 端口',
                    'auth_credentials' => 'Auth 凭据',
                    'characters_db' => '角色数据库',
                    'characters_port' => '角色端口',
                    'characters_credentials' => '角色凭据',
                    'world_db' => '世界数据库',
                    'world_port' => '世界端口',
                    'world_credentials' => '世界凭据',
                    'soap_credentials' => 'SOAP 凭据',
                    'soap_port' => 'SOAP 端口',
                ],
                'rows' => [
                    'single' => [
                        'type' => '单 Realm',
                        'auth_db' => '共用主 Auth',
                        'auth_port' => '沿用主端口',
                        'auth_credentials' => '沿用主凭据',
                        'characters_db' => '单数据库',
                        'characters_port' => '自定义端口',
                        'characters_credentials' => '可选自定义',
                        'world_db' => '单数据库',
                        'world_port' => '自定义端口',
                        'world_credentials' => '可选自定义',
                        'soap_credentials' => '手动填写',
                        'soap_port' => '手动填写',
                    ],
                    'multi' => [
                        'type' => '多 Realm（共享 Auth）',
                        'auth_db' => '共用主 Auth',
                        'auth_port' => '沿用主端口',
                        'auth_credentials' => '沿用主凭据',
                        'characters_db' => '按 Realm 拆分',
                        'characters_port' => '按 Realm 配置',
                        'characters_credentials' => '按 Realm 可选覆盖',
                        'world_db' => '按 Realm 拆分',
                        'world_port' => '按 Realm 配置',
                        'world_credentials' => '按 Realm 可选覆盖',
                        'soap_credentials' => '按 Realm 配置',
                        'soap_port' => '按 Realm 配置',
                    ],
                    'multi_full' => [
                        'type' => '多 Realm（完全隔离）',
                        'auth_db' => '每个 Realm 独立',
                        'auth_port' => '每个 Realm 独立端口',
                        'auth_credentials' => '每个 Realm 独立凭据',
                        'characters_db' => '每个 Realm 独立',
                        'characters_port' => '每个 Realm 独立端口',
                        'characters_credentials' => '每个 Realm 独立凭据',
                        'world_db' => '每个 Realm 独立',
                        'world_port' => '每个 Realm 独立端口',
                        'world_credentials' => '每个 Realm 独立凭据',
                        'soap_credentials' => '每个 Realm 独立',
                        'soap_port' => '每个 Realm 独立端口',
                    ],
                ],
                'hint' => '可先参考对比表了解维护成本，再决定最终模式。',
            ],
            'fields' => [
                'host' => '主机',
                'port' => '端口',
                'database' => '数据库',
                'user' => '用户名',
                'password' => '密码',
                'uri' => 'URI',
            ],
            'placeholders' => [
                'inherit_auth' => '继承 Auth 连接',
            ],
            'actions' => [
                'refresh' => '自动发现 Realm',
                'manual' => '手动添加 Realm',
                'tip' => '提示：先运行 worldserver，再点击自动发现可直接导入。',
                'refresh_fail' => '加载 Realm 列表失败。',
                'request_fail' => '请求失败，请重试。',
                'save_fail' => '保存失败，请检查表单后重试。',
                'unknown_error' => '发生未知错误。',
                'manual_disabled' => '已自动获取 Realm，删除后可重新手动添加。',
            ],
            'realm' => [
                'title_prefix' => 'Realm :index',
                'remove' => '移除',
                'name_label' => 'Realm 名称',
                'name_placeholder' => '示例：Azeroth',
                'inherit' => '继承主连接凭据',
                'auth' => 'Auth 覆盖配置',
                'auth_placeholders' => [
                    'inherit_main' => '留空则继承主 Auth 设置',
                ],
                'characters' => [
                    'title' => '角色数据库',
                ],
                'world' => [
                    'title' => '世界数据库',
                ],
                'soap' => [
                    'title' => 'SOAP 凭据',
                    'host' => '主机',
                    'port' => '端口',
                    'user' => '用户名',
                    'password' => '密码',
                    'uri' => 'URI',
                ],
                'soap_placeholder' => '留空则继承全局 SOAP 配置',
                'empty' => '尚未配置 Realm。',
                'summary' => '已配置 :count 个 Realm。',
                'summary_ids' => 'ID：:ids',
                'meta' => [
                    'id' => 'ID :value',
                    'port' => '端口 :value',
                ],
                'refresh_fail' => '加载 Realm 列表失败。',
                'request_fail' => '请求失败，请重试。',
                'save_fail' => '保存失败，请检查表单后重试。',
                'unknown_error' => '发生未知错误。',
            ],
            'footer' => [
                'hint' => '这些设置可在安装完成后于面板内随时调整。',
                'submit' => '保存并继续',
                'back' => '返回环境检测',
            ],
        ],
        'test' => [
            'title' => '步骤 :current / :total 连接测试',
            'success' => '全部连接成功。',
            'next_admin' => '下一步：管理员',
            'failure' => '存在失败，请返回修改。',
            'back' => '返回修改',
        ],
        'status' => [
            'ok' => '通过',
            'fail' => '失败',
        ],
        'admin' => [
            'step_title' => '步骤 :current / :total · 管理员账号',
            'fields' => [
                'username' => '用户名',
                'password' => '密码',
                'password_confirm' => '确认密码',
            ],
            'submit' => '保存并生成配置',
            'back' => '返回连接测试',
            'save_failed' => '保存管理员配置失败，请稍后再试。',
            'errors' => [
                'username_required' => '用户名不能为空。',
                'password_required' => '密码不能为空。',
                'password_mismatch' => '两次密码不一致。',
            ],
        ],
        'finish' => [
            'step_title' => '步骤 :current / :total · 完成',
            'success' => '配置文件生成成功。请删除 /setup 入口（或保持 install.lock）以防重复安装。',
            'enter_panel' => '进入面板',
            'failure' => '生成失败：:errors',
            'back' => '返回管理员步骤',
            'errors' => [
                'create_config_dir' => '无法创建配置目录：:path',
                'write_failed' => '写入文件失败：:file',
            ],
        ],
        'api' => [
            'realms' => [
                'missing_auth_db' => '缺少 auth 数据库名。',
                'connection_failed' => '连接或查询失败：:error',
            ],
        ],
    ],
];

