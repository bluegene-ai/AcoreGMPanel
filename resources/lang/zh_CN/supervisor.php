<?php
/**
 * File: resources/lang/zh_CN/supervisor.php
 * Purpose: 守护管理页（acore_supervisor.exe）文案。
 */

return [
    'page_title' => '守护管理',
    'intro' => '查看并控制 acore_supervisor.exe：worldserver / authserver 的运行状态、世界循环心跳、登录服探活与重启次数，并可直接启停或重启。',

    'supervisor' => [
        'running' => '守护程序运行中',
        'not_running' => '守护程序未运行',
        'status_age' => '状态更新于 :seconds 秒前',
    ],

    'reason' => [
        'ok' => '状态文件持续更新',
        'disabled' => '面板中未启用守护管理',
        'not_configured' => '未找到守护程序目录',
        'no_status_file' => '还没有状态文件（守护程序可能尚未启动）',
        'stale' => '状态文件已过期',
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

    'notices' => [
        'disabled' => '守护管理已在 config/supervisor.php 中关闭。',
        'not_running' => '守护程序当前未运行，因此只能查看历史状态，无法下发启停指令。',
        'not_running_hint' => '请在服务器桌面会话中运行 release\\supervisor\\start_supervisor.bat（或通过登录时的计划任务启动），再回到本页刷新。',
        'no_services' => '状态文件中没有任何服务。',
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
        'instance' => '实例名',
        'version' => '版本',
        'pid' => '进程号',
        'uptime' => '运行时长',
        'control_file' => '指令文件',
        'status_file' => '状态文件',
        'last_command' => '最近指令',
        'no_command' => '尚未收到任何指令',
    ],

    'messages' => [
        'command_sent' => '已下发指令：:action（:target）',
        'supervisor_starting' => '已请求启动守护程序（计划任务 :task），请稍后刷新。',
    ],

    'errors' => [
        'disabled' => '守护管理未启用。',
        'unknown_action' => '未知指令：:action',
        'unknown_target' => '未知目标：:target',
        'not_running' => '守护程序未运行，无法下发指令。',
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
                ],
                'errors' => [
                    'refresh_failed' => '读取守护状态失败',
                    'command_failed' => '指令下发失败',
                ],
            ],
        ],
    ],
];
