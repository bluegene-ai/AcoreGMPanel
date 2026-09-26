<?php
/**
 * File: config/supervisor.php
 * Purpose: Connects the panel to acore_supervisor.exe (the native watchdog that owns
 *          worldserver.exe / authserver.exe).
 *
 * The panel never starts a game server itself: the supervisor runs inside the interactive
 * desktop session (that is what keeps the visible GM console alive), so the panel talks to it
 * through files written next to the supervisor:
 *
 *   logs/supervisor_status.json   supervisor -> panel (state, heartbeat age, probe, restarts)
 *   logs/supervisor_control.txt   panel -> supervisor (id/action/target, one command per file)
 *   logs/supervisor.log           supervisor's own log, shown as a tail on the page
 *
 * Copy the overrides you need into config/generated/supervisor.php (that file is not tracked).
 */

declare(strict_types=1);

return [
    
    'enabled' => true,

    
    
    'dir' => '',

    
    'exe' => '',
    'config_file' => '',
    'status_file' => '',
    'control_file' => '',
    'log_file' => '',

    
    
    'status_stale_seconds' => 20,

    
    'log_tail_lines' => 200,

    
    'poll_seconds' => 5,

    
    
    
    
    'allow_start' => false,
    'start_task_name' => '',

    
    'schtasks_path' => '',

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    'instances' => [],
];
