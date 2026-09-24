<?php
/**
 * File: resources/lang/en/supervisor.php
 * Purpose: Copy for the supervisor (watchdog) management page.
 */

return [
    'page_title' => 'Supervisor',
    'intro' => 'Inspect and control acore_supervisor.exe: worldserver / authserver state, world-loop heartbeat, auth probe and restart counters, with start / stop / restart actions.',

    'supervisor' => [
        'running' => 'supervisor running',
        'not_running' => 'supervisor is not running',
        'status_age' => 'state written :seconds s ago',
    ],

    'reason' => [
        'ok' => 'status file is being updated',
        'disabled' => 'supervisor integration is disabled in the panel',
        'not_configured' => 'supervisor directory not found',
        'dir_missing' => 'the configured supervisor directory does not exist',
        'no_status_file' => 'no status file yet (the supervisor may not have run)',
        'stale' => 'status file is stale',
    ],

    'diagnostics' => [
        'title' => 'Why the supervisor was not found',
        'intro' => 'The panel looks for acore_supervisor.exe / supervisor.ini in the directories below. Running the supervisor somewhere else is fine - the panel just has to be told where it is.',
        'configured' => 'Configured directory',
        'configured_empty' => '(not configured, auto-detection)',
        'env' => 'Environment variable :var',
        'env_empty' => '(not set)',
        'resolved' => 'Resolved to',
        'resolved_empty' => '(no directory matched)',
        'status_file' => 'Status file',
        'status_file_missing' => 'does not exist',
        'status_file_age' => 'updated :seconds s ago',
        'process_user' => 'PHP process user',
        'open_basedir' => 'open_basedir',
        'open_basedir_empty' => '(not restricted)',
        'open_basedir_warning' => 'PHP runs with an open_basedir restriction: a supervisor outside that list is invisible to the panel no matter what path you configure.',
        'candidate_path' => 'Directories tried',
        'candidate_state' => 'exists / exe / ini / status file',
        'exists_yes' => 'yes',
        'exists_no' => 'no',
        'fix_title' => 'How to fix it',
        'fix_configured' => 'The "configured directory" above is wrong or the folder was renamed: correct dir in config/generated/supervisor.php, or drop that key to go back to auto-detection.',
        'fix_hint' => 'Pick either one, then refresh this page:',
        'fix_option_config' => 'Pin it in the panel config (recommended): create :file',
        'fix_option_env' => 'Set the :var environment variable for the PHP process (Apache SetEnv, or .env)',
        'fix_option_note' => 'Forward slashes or doubled backslashes both work; point at the FOLDER, not at the exe itself.',
        'placeholder_dir' => 'C:/replace/with/the/supervisor/folder',
    ],

    'actions' => [
        'start' => 'Start',
        'stop' => 'Stop',
        'restart' => 'Restart',
        'start_all' => 'Start all',
        'stop_all' => 'Stop all',
        'restart_all' => 'Restart all',
        'start_supervisor' => 'Start supervisor',
        'refresh' => 'Refresh',
        'auto_refresh' => 'Auto refresh',
    ],

    'confirm' => [
        'stop_all' => 'Stop worldserver and authserver? They will stay down until you start them again.',
        'stop_service' => 'Stop :service? It will stay down until you start it again.',
    ],

    'notices' => [
        'disabled' => 'Supervisor management is disabled in config/supervisor.php.',
        'not_running' => 'The supervisor is not running, so only the last known state can be shown and no commands can be sent.',
        'not_running_hint' => 'Start release\\supervisor\\start_supervisor.bat inside the desktop session (or via the logon scheduled task), then refresh this page.',
        'dir_missing' => 'The supervisor directory in the panel config does not exist - fix the path using the diagnostics below.',
        'dir_collision' => 'Instances :instances point at the same supervisor directory (:dir). One supervisor owns one worldserver + one authserver, so give every realm its own dir in config/generated/supervisor.php - otherwise both entries show and control the same realm.',
        'no_services' => 'The status file contains no services.',
    ],

    'services' => [
        'worldserver' => 'WorldServer',
        'authserver' => 'AuthServer',
    ],

    'fields' => [
        'state' => 'State',
        'pid' => 'PID',
        'uptime' => 'Uptime',
        'heartbeat' => 'World-loop heartbeat',
        'heartbeat_ago' => ':seconds s ago',
        'heartbeat_raised' => 'configured :configured s, effective :effective s (raised by the server cadence)',
        'heartbeat_interval' => 'server writes one line every :seconds s',
        'heartbeat_cadence' => 'measured cadence :seconds s',
        'not_available' => 'n/a',
        'probe' => 'Port probe',
        'probe_ok' => 'reachable',
        'probe_failed' => 'no answer',
        'restarts' => 'Restarts',
        'memory' => 'Working set',
        'last_event' => 'Last event',
    ],

    'states' => [
        'starting' => 'starting',
        'running' => 'running',
        'waiting-restart' => 'waiting to restart',
        'stopped' => 'stopped',
        'unknown' => 'unknown',
    ],

    'health' => [
        'healthy' => 'healthy',
        'starting' => 'starting',
        'lagging' => 'heartbeat lagging',
        'hung' => 'suspected hang',
        'restarting' => 'restarting',
        'stopped' => 'stopped on request',
        'down' => 'not running',
        'probe_failed' => 'probe failing',
    ],

    'targets' => [
        'all' => 'all services',
        'worldserver' => 'WorldServer',
        'authserver' => 'AuthServer',
    ],

    'log' => [
        'title' => 'Supervisor log',
        'empty' => '-- no log output yet --',
    ],

    'meta' => [
        'title' => 'Supervisor details',
        'panel_instance' => 'Panel instance',
        'instance' => 'Instance',
        'version' => 'Version',
        'pid' => 'PID',
        'uptime' => 'Uptime',
        'control_file' => 'Command file',
        'status_file' => 'Status file',
        'last_command' => 'Last command',
        'no_command' => 'no command received yet',
        'command_age' => ':seconds s ago',
    ],

    'messages' => [
        'command_sent' => 'Command sent: :action (:target)',
        'supervisor_starting' => 'Requested supervisor start through the scheduled task :task - refresh in a moment.',
    ],

    'instances' => [
        'title' => 'Supervisor instances (one per realm)',
    ],

    'errors' => [
        'disabled' => 'Supervisor management is disabled.',
        'unknown_action' => 'Unknown action: :action',
        'unknown_target' => 'Unknown target: :target',
        'unknown_instance' => 'Unknown supervisor instance: :instance (not listed under "instances" in config/supervisor.php)',
        'not_running' => 'The supervisor is not running, the command cannot be delivered.',
        'control_unavailable' => 'The command directory is not writable, the command cannot be delivered.',
        'write_failed' => 'Cannot write the command file: :message',
        'start_not_configured' => 'Starting is not configured (set allow_start and start_task_name in config/supervisor.php).',
        'exec_disabled' => 'exec() is disabled, the supervisor cannot be started from the panel.',
        'start_failed' => 'Starting the scheduled task :task failed: :message',
    ],

    'js' => [
        'modules' => [
            'supervisor' => [
                'fields' => [
                    'heartbeat_ago' => ':seconds s ago',
                    'not_available' => 'n/a',
                    'probe_ok' => 'reachable',
                    'probe_failed' => 'no answer',
                ],
                'actions' => [
                    'start' => 'Start',
                    'stop' => 'Stop',
                ],
                'confirm' => [
                    'stop_service' => 'Stop :service?',
                ],
                'supervisor' => [
                    'running' => 'supervisor running',
                    'not_running' => 'supervisor is not running',
                    'status_age' => 'state written :seconds s ago',
                ],
                'meta' => [
                    'no_command' => 'no command received yet',
                ],
                'log' => [
                    'empty' => '-- no log output yet --',
                ],
                'messages' => [
                    'sending' => 'sending command…',
                    'switching' => 'loading :instance …',
                    'command_sent' => 'command sent',
                    'timeout' => 'no confirmation from the supervisor yet - refresh in a moment.',
                ],
                'errors' => [
                    'refresh_failed' => 'cannot read the supervisor state',
                    'command_failed' => 'command failed',
                ],
            ],
        ],
    ],
];
