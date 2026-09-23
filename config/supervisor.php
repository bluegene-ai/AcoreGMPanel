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
    // master switch for the "Supervisor" page and its APIs
    'enabled' => true,

    // Directory that contains acore_supervisor.exe + supervisor.ini.
    // Leave empty to auto-detect: <web root>/../release/supervisor, then a few neighbours.
    'dir' => '',

    // Explicit overrides (absolute paths). Empty = derived from 'dir'.
    'exe' => '',
    'config_file' => '',
    'status_file' => '',
    'control_file' => '',
    'log_file' => '',

    // A status file that has not been rewritten for this long means the supervisor is not
    // running any more (it rewrites the file every tick, ~2x per second).
    'status_stale_seconds' => 20,

    // How many lines of logs/supervisor.log the page shows.
    'log_tail_lines' => 200,

    // How often the page refreshes its state (seconds, 0 = manual only).
    'poll_seconds' => 5,

    // Optional: let the panel launch the supervisor when it is not running.
    // This runs "schtasks /run /tn <start_task_name>", so the supervisor must be registered as a
    // Task Scheduler task (trigger "At log on", "Run only when user is logged on") - that is the
    // only way a web request can start a process inside the visible desktop session.
    'allow_start' => false,
    'start_task_name' => '',

    // Optional: path to schtasks.exe when it is not on PATH.
    'schtasks_path' => '',

    // ---------------------------------------------------------------------------------------------
    // MORE THAN ONE SUPERVISOR (one per realm)
    // ---------------------------------------------------------------------------------------------
    // acore_supervisor.exe supervises exactly one worldserver + one authserver, so a multi-realm
    // machine runs one supervisor process per realm. List them here; every key above acts as the
    // DEFAULT for the entries below, and an entry only overrides what differs.
    //
    //   'instances' => [
    //       // id => overrides. A plain string is shorthand for ['dir' => <string>]
    //       'realm-a' => [
    //           'label' => 'Realm A',
    //           'dir' => 'D:\AzerothCore\release\supervisor',
    //       ],
    //       'realm-b' => [
    //           'label' => 'Realm B',
    //           'dir' => 'D:\AzerothCore\release\supervisor-b',
    //           // allow_start/start_task_name are per instance too: one scheduled task per supervisor
    //           'allow_start' => true,
    //           'start_task_name' => 'AcoreSupervisorB',
    //       ],
    //       // the instance described by the keys above (auto-detected release/supervisor):
    //       'default' => [],
    //   ],
    //
    // Without 'instances' (or with an empty list) the page keeps working exactly as before: a single
    // instance named "default". An id that is not listed is refused by the APIs instead of being
    // silently mapped to another realm.
    'instances' => [],
];
