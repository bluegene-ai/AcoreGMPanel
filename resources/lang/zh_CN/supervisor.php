<?php
/**
 * File: resources/lang/zh_CN/supervisor.php
 * Purpose: 守护管理页（acore_supervisor.exe）文案。
 */

return [
    'page_title' => '守护管理',
    'intro' => '查看并控制 acore_supervisor.exe：worldserver / authserver 的运行状态、世界循环心跳、登录服探活与重启次数，并可直接启停或重启。',
    'intro_short' => '查看并控制 worldserver / authserver 的状态与心跳，可直接启停或重启。',
    'intro_short' => '查看并控制 worldserver / authserver 守护进程。',

    'supervisor' => [
        'running' => '守护程序运行中',
        'not_running' => '守护程序未运行',
        'status_age' => '状态更新于 :seconds 秒前',
    ],

    'reason' => [
        'ok' => '状态文件持续更新',
        'disabled' => '面板中未启用守护管理',
        'not_configured' => '未找到守护程序目录',
        'dir_missing' => '配置的守护程序目录不存在',
        'status_disabled' => '守护配置里 StatusEnabled = false，不写状态文件',
        'no_status_file' => '还没有状态文件（守护程序可能尚未启动）',
        'stale' => '状态文件已过期',
    ],

    'diagnostics' => [
        'title' => '为什么找不到守护程序',
        'intro' => '面板按下面的顺序查找 acore_supervisor.exe / supervisor.ini，全部落空就会显示"未找到守护程序目录"。守护在别的目录跑完全没问题，只要让面板知道它在哪。',
        'intro_short' => '面板按下面的路径查找守护程序。',
        'configured' => '配置的目录',
        'configured_empty' => '（未配置，使用自动探测）',
        'env' => '环境变量 :var',
        'env_empty' => '（未设置）',
        'resolved' => '实际解析到',
        'resolved_empty' => '（没有命中任何目录）',
        'status_file' => '状态文件',
        'status_file_missing' => '不存在',
        'status_file_age' => '更新于 :seconds 秒前',
        'control_file' => '指令文件',
        'control_file_absent' => '尚未创建（下发一次指令后才会出现）',
        'log_file' => '守护日志',
        'ini_file' => '守护配置',
        'ini_read' => '已读取（文件名按它解析）',
        'ini_missing' => '未找到，只能按默认文件名猜',
        'source_configured' => '来自面板配置',
        'source_ini' => '取自 supervisor.ini',
        'source_discovered' => '目录中自动发现',
        'source_default' => '使用默认文件名',
        'source_none' => '未能解析',
        'conflict_title' => '面板配置与 supervisor.ini 不一致（面板配置优先，可能让指令写进守护不读的文件）：',
        'conflict_title_short' => '面板配置与 supervisor.ini 不一致：',
        'conflict_line' => '面板写的是 :configured，ini 里是 :ini',
        'ini_key_status' => 'StatusFile',
        'ini_key_control' => 'ControlFile',
        'ini_key_log' => 'GuardLog',
        'process_user' => 'PHP 运行账号',
        'open_basedir' => 'open_basedir',
        'open_basedir_empty' => '（未限制）',
        'open_basedir_warning' => 'PHP 设置了 open_basedir，守护目录在该列表之外时面板永远看不到它，需要把该目录加进去。',
        'open_basedir_warning_short' => 'PHP 的 open_basedir 未包含守护目录。',
        'candidate_path' => '查找过的目录',
        'candidate_state' => '存在 / exe / ini / 状态文件',
        'exists_yes' => '有',
        'exists_no' => '无',
        'fix_title' => '怎么修',
        'fix_configured' => '上面的"配置的目录"写错了或者盘符/目录已改名：改 config/generated/supervisor.php 里的 dir，或删掉这一项改回自动探测。',
        'fix_configured_short' => '「配置的目录」写错了或目录已改名。',
        'fix_hint' => '任选一种，然后刷新本页：',
        'fix_hint_short' => '任选一种配置方式，然后刷新本页。',
        'fix_option_config' => '写死在面板配置里（推荐，最稳）：新建 :file',
        'fix_option_env' => '给 PHP 进程设置环境变量 :var（Apache 可写 SetEnv，或写进 .env）',
        'fix_option_note' => '路径用正斜杠或双反斜杠都行；不要指向 exe 本身，指向它所在的文件夹。',
        'fix_option_note_short' => '指向守护程序所在的文件夹（不要指向 exe）。',
        'placeholder_dir' => 'C:/请改成守护程序所在的文件夹',
    ],

    'notices' => [
        'disabled' => '守护管理已在 config/supervisor.php 中关闭。',
        'not_running' => '守护程序当前未运行，因此只能查看历史状态，无法下发启停指令。',
        'not_running_hint' => '请在服务器桌面会话中运行 release\\supervisor\\start_supervisor.bat（或通过登录时的计划任务启动），再回到本页刷新。',
        'dir_missing' => '面板配置里的守护程序目录不存在，请照下面的诊断信息改正路径。',
        'dir_collision' => '实例 :instances 指向同一个守护目录 :dir。一个守护只管一个 worldserver + 一个 authserver，请在 config/generated/supervisor.php 里给每个区各自的 dir，否则两个入口显示和操作的是同一个区。',
        'instance_mismatch' => '状态文件里的守护实例名是「:status」，但 :file 的 InstanceName 写的是「:ini」——这个目录（或面板配置的 status_file）很可能属于另一个区，页面显示的可能是别的区的状态。',
        'service_disabled' => '本守护未启用该服务（ini 里 Enabled = false），通常由另一个区的守护负责，因此这里不提供控制按钮。',
        'no_services' => '状态文件中没有任何服务。',
    ],

    'actions' => [
        'start' => '启动',
        'stop' => '停止',
        'restart' => '重启',
        'start_all' => '全部启动',
        'stop_all' => '全部停止',
        'restart_all' => '全部重启',
        'start_supervisor' => '启动守护程序',
        'refresh' => '刷新',
        'auto_refresh' => '自动刷新',
    ],

    'confirm' => [
        'stop_all' => '确定要停止 worldserver 与 authserver 吗？停止后不会自动拉起，需要手动再启动。',
        'stop_service' => '确定要停止 :service 吗？停止后不会自动拉起。',
    ],

    'services' => [
        'worldserver' => 'WorldServer（世界服）',
        'authserver' => 'AuthServer（登录服）',
    ],

    'fields' => [
        'state' => '状态',
        'pid' => '进程号',
        'uptime' => '运行时长',
        'heartbeat' => '世界循环心跳',
        'heartbeat_ago' => ':seconds 秒前',
        'heartbeat_raised' => '配置 :configured 秒 → 实际 :effective 秒（按服务器节奏自动放宽）',
        'heartbeat_interval' => '服务器每 :seconds 秒写一行',
        'heartbeat_cadence' => '实测间隔 :seconds 秒',
        'not_available' => '不适用',
        'probe' => '端口探活',
        'probe_ok' => '正常',
        'probe_failed' => '无响应',
        'restarts' => '重启次数',
        'memory' => '内存占用',
        'last_event' => '最近事件',
    ],

    'states' => [
        'starting' => '启动中',
        'running' => '运行中',
        'waiting-restart' => '等待重启',
        'stopped' => '已停止',
        'unknown' => '未知',
    ],

    'health' => [
        'healthy' => '健康',
        'starting' => '启动中',
        'lagging' => '心跳偏慢',
        'hung' => '疑似卡死',
        'restarting' => '重启中',
        'stopped' => '已按指令停止',
        'down' => '未运行',
        'disabled' => '本守护未启用',
        'probe_failed' => '探活失败',
    ],

    'targets' => [
        'all' => '全部服务',
        'worldserver' => 'WorldServer',
        'authserver' => 'AuthServer',
    ],

    'log' => [
        'title' => '守护程序日志',
        'empty' => '-- 暂无日志 --',
    ],

    'meta' => [
        'title' => '守护程序信息',
        'panel_instance' => '面板实例',
        'instance' => '实例名（状态文件）',
        'ini' => '守护配置',
        'ini_read' => '已读取',
        'ini_missing' => '未找到（用默认文件名/自动探测）',
        'ini_instance' => '实例名（ini）',
        'ini_status_enabled' => '状态文件开关',
        'ini_tick' => '主循环间隔',
        'ini_services' => 'ini 配置的服务',
        'ini_service_on' => '启用',
        'ini_service_off' => '未启用',
        'ini_probe' => '探活端口 :port',
        'yes' => '开启',
        'no_status' => '关闭（StatusEnabled = false）',
        'version' => '版本',
        'pid' => '进程号',
        'uptime' => '运行时长',
        'control_file' => '指令文件',
        'status_file' => '状态文件',
        'last_command' => '最近指令',
        'no_command' => '尚未收到任何指令',
        'command_age' => ':seconds 秒前',
    ],

    'messages' => [
        'command_sent' => '已下发指令：:action（:target）',
        'supervisor_starting' => '已请求启动守护程序（计划任务 :task），请稍后刷新。',
    ],

    'instances' => [
        'title' => '守护实例（每个实例对应一个区）',
    ],

    'errors' => [
        'disabled' => '守护管理未启用。',
        'unknown_action' => '未知指令：:action',
        'unknown_target' => '未知目标：:target',
        'unknown_instance' => '未知守护实例：:instance（未在 config/supervisor.php 的 instances 里配置）',
        'not_running' => '守护程序未运行，无法下发指令。',
        'service_disabled' => '本守护未运行 :service（ini 里 Enabled = false），请到负责它的那个区下发指令。',
        'control_unavailable' => '指令文件目录不可写，无法下发指令。',
        'write_failed' => '写入指令失败：:message',
        'start_not_configured' => '未配置启动方式（需要在 config/supervisor.php 里设置 allow_start 与 start_task_name）。',
        'exec_disabled' => 'PHP 的 exec() 被禁用，无法启动守护程序。',
        'start_failed' => '启动计划任务 :task 失败：:message',
    ],

    'js' => [
        'modules' => [
            'supervisor' => [
                'fields' => [
                    'heartbeat_ago' => ':seconds 秒前',
                    'not_available' => '不适用',
                    'probe_ok' => '正常',
                    'probe_failed' => '无响应',
                ],
                'actions' => [
                    'start' => '启动',
                    'stop' => '停止',
                ],
                'confirm' => [
                    'stop_service' => '确定要停止 :service 吗？',
                ],
                'supervisor' => [
                    'running' => '守护程序运行中',
                    'not_running' => '守护程序未运行',
                    'status_age' => '状态更新于 :seconds 秒前',
                ],
                'meta' => [
                    'no_command' => '尚未收到任何指令',
                ],
                'log' => [
                    'empty' => '-- 暂无日志 --',
                ],
                'messages' => [
                    'sending' => '正在下发指令…',
                    'command_sent' => '指令已下发',
                    'timeout' => '暂未收到守护程序确认，稍后刷新查看。',
                    'switching' => '正在加载 :instance …',
                    'unknown_instance' => '未知守护实例：:instance',
                ],
                'errors' => [
                    'refresh_failed' => '读取守护状态失败',
                    'command_failed' => '指令下发失败',
                ],
            ],
        ],
    ],
];
