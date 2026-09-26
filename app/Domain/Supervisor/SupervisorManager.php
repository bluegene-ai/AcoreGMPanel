<?php
/**
 * File: app/Domain/Supervisor/SupervisorManager.php
 * Purpose: Read/write bridge between the panel and acore_supervisor.exe.
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Supervisor;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use RuntimeException;
use Throwable;

final class SupervisorManager
{
    public const ACTION_START = 'start';
    public const ACTION_STOP = 'stop';
    public const ACTION_RESTART = 'restart';
    public const ACTION_PING = 'ping';
    public const ACTION_SHUTDOWN = 'shutdown';

    /** Id of the single instance described by the flat config keys. */
    public const DEFAULT_INSTANCE = 'default';

    private const SERVICE_ACTIONS = [
        self::ACTION_START,
        self::ACTION_STOP,
        self::ACTION_RESTART,
        self::ACTION_PING,
        self::ACTION_SHUTDOWN,
    ];

    private const TARGETS = ['all', 'worldserver', 'authserver'];

    /**
     * Keys that may be overridden per instance; every other key in the file is global.
     */
    private const INSTANCE_KEYS = [
        'enabled',
        'label',
        'dir',
        'exe',
        'config_file',
        'status_file',
        'control_file',
        'log_file',
        'status_stale_seconds',
        'log_tail_lines',
        'poll_seconds',
        'allow_start',
        'start_task_name',
        'schtasks_path',
    ];

    /** @var array<string,mixed> whole config file: flat keys = defaults, "instances" = extra instances */
    private array $root;

    private string $instanceId;

    /** @var array<string,mixed> effective config of the selected instance */
    private array $config;

    private ?string $resolvedDir = null;

    /** @var array{file:string,found:bool,sections:array<string,array<string,string>>}|null parsed supervisor.ini */
    private ?array $iniCache = null;

    /** @var array<string,string> where each resolved path came from (configured|ini|discovered|default|none) */
    private array $pathSources = [];

    /**
     * One manager instance represents ONE supervisor instance (one acore_supervisor.exe).
     *
     * @param string|null $instance instance id; null/'' = first configured instance, unknown ids stay invalid (isValidInstance()) so no command reaches the wrong realm
     * @param array<string,mixed>|null $config inject a config array instead of Config::get('supervisor')
     */
    public function __construct(?string $instance = null, ?array $config = null)
    {
        $root = $config ?? Config::get('supervisor', []);
        $this->root = is_array($root) ? $root : [];

        $definitions = $this->instanceDefinitions();
        $requested = trim((string) $instance);
        $this->instanceId = $requested !== ''
            ? $requested
            : (string) (array_key_first($definitions) ?? self::DEFAULT_INSTANCE);
        $this->config = $this->effectiveConfig($this->instanceId);
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /** False when the requested instance id is not configured: the caller must refuse the request. */
    public function isValidInstance(): bool
    {
        return array_key_exists($this->instanceId, $this->instanceDefinitions());
    }

    public function instanceId(): string
    {
        return $this->instanceId;
    }

    public function instanceLabel(): string
    {
        return $this->labelFor($this->instanceId, $this->config);
    }

    /**
     * Configured instances, in config order - enough for the page switcher.
     * @return array<int,array{id:string,label:string,enabled:bool}>
     */
    public function instances(): array
    {
        $list = [];
        foreach (array_keys($this->instanceDefinitions()) as $id) {
            $id = (string) $id;
            $config = $this->effectiveConfig($id);
            $list[] = [
                'id' => $id,
                'label' => $this->labelFor($id, $config),
                'enabled' => (bool) ($config['enabled'] ?? true),
            ];
        }

        return $list;
    }

    /**
     * Compact state of every configured instance (switcher badges); each entry reads its own status file.
     * @return array<int,array{id:string,label:string,enabled:bool,running:bool,available:bool,reason:string,tone:string,services:array<string,string>}>
     */
    public function summaries(): array
    {
        $out = [];
        foreach ($this->instances() as $entry) {
            $other = new self($entry['id'], $this->root);
            $state = $other->status();

            $services = [];
            foreach ((array) ($state['services'] ?? []) as $service) {
                if (!is_array($service)) {
                    continue;
                }
                $services[(string) ($service['name'] ?? '')] = (string) ($service['tone'] ?? 'muted');
            }

            $out[] = [
                'id' => $entry['id'],
                'label' => $entry['label'],
                'enabled' => $entry['enabled'],
                'running' => (bool) ($state['running'] ?? false),
                'available' => (bool) ($state['available'] ?? false),
                'reason' => (string) ($state['reason'] ?? ''),
                'tone' => $this->summaryTone($state, $services),
                'services' => $services,
                // two instances on one directory would show and control the SAME supervisor (see dirCollisions())
                'dir' => (string) ($other->paths()['dir'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Instances that resolve to the same supervisor directory, keyed by directory.
     * One exe supervises one world+auth pair, so a shared directory means the same realm is listed twice and a restart hits the other realm. Empty in a correct configuration.
     * @return array<string,array<int,string>> directory => instance labels
     */
    public function dirCollisions(): array
    {
        $byDir = [];
        foreach ($this->summaries() as $summary) {
            $dir = trim((string) ($summary['dir'] ?? ''));
            if ($dir === '') {
                continue;
            }
            $byDir[$dir][] = (string) ($summary['label'] ?? $summary['id'] ?? '');
        }

        return array_filter($byDir, static fn (array $labels): bool => count($labels) > 1);
    }

    /**
     * @return array<string,mixed>|null overrides of the instance, or null when it is not configured
     */
    private function instanceOverrides(string $id): ?array
    {
        $definitions = $this->instanceDefinitions();

        return array_key_exists($id, $definitions) ? $definitions[$id] : null;
    }

    /**
     * Instance table from the config file. Without "instances" the whole file describes ONE instance
     * (id "default"); with it, every entry is an instance, the flat keys are defaults for all of them,
     * and a plain string entry is the supervisor directory shorthand.
     * @return array<string,array<string,mixed>>
     */
    private function instanceDefinitions(): array
    {
        $declared = $this->root['instances'] ?? null;
        if (!is_array($declared) || $declared === []) {
            return [self::DEFAULT_INSTANCE => []];
        }

        $definitions = [];
        foreach ($declared as $id => $overrides) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            if (is_string($overrides)) {
                $overrides = ['dir' => $overrides];
            }
            $definitions[$id] = is_array($overrides) ? $overrides : [];
        }

        return $definitions === [] ? [self::DEFAULT_INSTANCE => []] : $definitions;
    }

    private function effectiveConfig(string $id): array
    {
        $defaults = [];
        foreach (self::INSTANCE_KEYS as $key) {
            if (array_key_exists($key, $this->root)) {
                $defaults[$key] = $this->root[$key];
            }
        }

        $overrides = $this->instanceOverrides($id) ?? [];

        // A NAMED instance must not inherit the flat dir/file keys: it would silently bind to the
        // default realm's supervisor. A path written inside the instance entry itself always wins.
        if ($id !== self::DEFAULT_INSTANCE) {
            $ownDir = trim((string) ($overrides['dir'] ?? ''));
            $inherited = ['exe', 'config_file', 'status_file', 'control_file', 'log_file'];
            if ($ownDir === '') {
                $inherited[] = 'dir';
            }

            foreach ($inherited as $key) {
                if (!array_key_exists($key, $overrides)) {
                    unset($defaults[$key]);
                }
            }
        }

        $config = array_replace($defaults, $overrides);
        $config['id'] = $id;

        return $config;
    }

    private function labelFor(string $id, array $config): string
    {
        $label = trim((string) ($config['label'] ?? ''));

        return $label !== '' ? $label : $id;
    }

    private function summaryTone(array $state, array $services): string
    {
        if (!($state['enabled'] ?? true)) {
            return 'muted';
        }
        if (!($state['running'] ?? false)) {
            return 'error';
        }

        $worst = 'ok';
        $rank = ['ok' => 0, 'muted' => 1, 'warn' => 2, 'error' => 3];
        foreach ($services as $tone) {
            if (($rank[$tone] ?? 0) > ($rank[$worst] ?? 0)) {
                $worst = $tone;
            }
        }

        return $worst;
    }

    /**
     * @return array{dir:string,exe:string,config_file:string,status_file:string,control_file:string,log_file:string}
     */
    public function paths(): array
    {
        // an id that is not configured must not resolve to another instance's files
        if (!$this->isValidInstance()) {
            return [
                'dir' => '',
                'exe' => '',
                'config_file' => '',
                'status_file' => '',
                'control_file' => '',
                'log_file' => '',
            ];
        }

        $dir = $this->resolveDirectory();
        $ini = $this->iniFilePaths();

        // Path priority: panel config, then the names supervisor.ini declares, then what is actually in
        // the directory, then the defaults. A wrong control-file guess would send commands into a file
        // the supervisor never reads.
        $pick = function (string $configured, string $fromIni, string $discovered, string $relative, string $sourceKey) use ($dir): string {
            $sources = [
                ['configured', $configured],
                ['ini', $fromIni],
                ['discovered', $discovered],
            ];
            foreach ($sources as [$source, $candidate]) {
                if (trim((string) $candidate) !== '') {
                    $this->pathSources[$sourceKey] = $source;

                    return trim((string) $candidate);
                }
            }

            $this->pathSources[$sourceKey] = $dir === '' ? 'none' : 'default';

            return $dir === '' ? '' : $dir . DIRECTORY_SEPARATOR . $relative;
        };

        $exe = trim((string) ($this->config['exe'] ?? ''));
        $configFile = trim((string) ($this->config['config_file'] ?? ''));
        if ($exe === '' && $dir !== '') {
            // the ini never names the executable, so probe the directory: default name first, then *supervisor*.exe
            $exe = $this->newestInDirectory($dir, ['acore_supervisor.exe', '*supervisor*.exe', '*.exe']);
            if ($exe === '') {
                $exe = $dir . DIRECTORY_SEPARATOR . 'acore_supervisor.exe';
            }
        }
        if ($configFile === '' && $dir !== '') {
            $configFile = $dir . DIRECTORY_SEPARATOR . 'supervisor.ini';
        }

        return [
            'dir' => $dir,
            'exe' => $exe,
            'config_file' => $configFile,
            'status_file' => $pick((string) ($this->config['status_file'] ?? ''), $ini['status_file'], $this->newestInDirectory($dir, ['logs' . DIRECTORY_SEPARATOR . '*status*.json', '*status*.json']), 'logs' . DIRECTORY_SEPARATOR . 'supervisor_status.json', 'status_file'),
            // the control file is usually ABSENT (the supervisor consumes it): never guess it from disk,
            // a wrong path sends commands into the void
            'control_file' => $pick((string) ($this->config['control_file'] ?? ''), $ini['control_file'], '', 'logs' . DIRECTORY_SEPARATOR . 'supervisor_control.txt', 'control_file'),
            'log_file' => $pick((string) ($this->config['log_file'] ?? ''), $ini['log_file'], $this->newestInDirectory($dir, ['logs' . DIRECTORY_SEPARATOR . '*supervisor*.log', '*supervisor*.log']), 'logs' . DIRECTORY_SEPARATOR . 'supervisor.log', 'log_file'),
        ];
    }

    /**
     * The supervisor's own supervisor.ini, parsed section by section: it is the authoritative
     * description of the deployment (services, exe/log paths, file names, instance name, tick) and the
     * panel config only overrides it. Relative paths resolve like the supervisor resolves them: file
     * paths and WorkDir against the ini folder, Exe/ServerConf against WorkDir.
     * @return array{file:string,found:bool,sections:array<string,array<string,string>>}
     */
    private function iniConfig(): array
    {
        if ($this->iniCache !== null) {
            return $this->iniCache;
        }

        $dir = $this->resolveDirectory();
        $configured = trim((string) ($this->config['config_file'] ?? ''));
        $file = $configured !== ''
            ? $configured
            : ($dir === '' ? '' : $dir . DIRECTORY_SEPARATOR . 'supervisor.ini');

        $this->iniCache = ['file' => $file, 'found' => false, 'sections' => []];

        $raw = $file === '' ? false : @file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return $this->iniCache;
        }

        // scan by hand instead of parse_ini_file(): a value containing ':' / '%' / '|' would make the
        // whole file unreadable
        $sections = [];
        $current = '';
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }

            if ($line[0] === '[' && str_ends_with($line, ']')) {
                $current = strtolower(trim(substr($line, 1, -1)));

                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $sections[$current][strtolower(trim(substr($line, 0, $eq)))] = trim(substr($line, $eq + 1));
        }

        $this->iniCache = ['file' => $file, 'found' => true, 'sections' => $sections];

        return $this->iniCache;
    }

    /**
     * One value out of supervisor.ini ('' when the file or the key is missing).
     */
    private function iniValue(string $section, string $key, string $default = ''): string
    {
        $value = $this->iniConfig()['sections'][strtolower($section)][strtolower($key)] ?? '';

        return $value === '' ? $default : $value;
    }

    /**
     * GuardLog / StatusFile / ControlFile out of the supervisor's own ini.
     *
     * @return array{ini_file:string,ini_found:bool,status_file:string,control_file:string,log_file:string}
     */
    private function iniFilePaths(): array
    {
        $ini = $this->iniConfig();
        $iniDir = $ini['file'] === '' ? '' : dirname($ini['file']);

        return [
            'ini_file' => $ini['file'],
            'ini_found' => $ini['found'],
            'status_file' => $iniDir === '' ? '' : $this->resolveIniPath($this->iniValue('general', 'statusfile'), $iniDir),
            'control_file' => $iniDir === '' ? '' : $this->resolveIniPath($this->iniValue('general', 'controlfile'), $iniDir),
            'log_file' => $iniDir === '' ? '' : $this->resolveIniPath($this->iniValue('general', 'guardlog'), $iniDir),
        ];
    }

    /** What supervisor.ini says about itself and the services it runs, with relative paths resolved. */
    private function iniSummary(): array
    {
        $ini = $this->iniConfig();
        if (!$ini['found']) {
            return [
                'file' => $ini['file'],
                'found' => false,
                'instance_name' => '',
                'status_enabled' => true,
                'tick_ms' => 0,
                'exit_when_all_stopped' => false,
                'services' => [],
            ];
        }

        $iniDir = dirname($ini['file']);
        $services = [];
        foreach (['worldserver', 'authserver'] as $name) {
            if (!isset($ini['sections'][$name])) {
                continue;
            }

            $workDir = $this->resolveIniPath($this->iniValue($name, 'workdir'), $iniDir);
            $exe = $this->iniValue($name, 'exe');
            if ($exe !== '' && !preg_match('#^[A-Za-z]:[\\\\/]#', $exe) && !str_starts_with($exe, '\\\\')) {
                $exe = $workDir === '' ? $exe : $workDir . DIRECTORY_SEPARATOR . $exe;
            }

            $services[] = [
                'name' => $name,
                'enabled' => $this->iniValue($name, 'enabled', 'true') !== 'false',
                'role' => $this->iniValue($name, 'role'),
                'exe' => $exe,
                'work_dir' => $workDir,
                'server_conf' => $this->resolveIniPath($this->iniValue($name, 'serverconf'), $workDir),
                'log_file' => $this->iniValue($name, 'logfile'),
                'console' => $this->iniValue($name, 'console'),
                'probe_port' => (int) $this->iniValue($name, 'probeport', '0'),
                'probe_mode' => $this->iniValue($name, 'probemode'),
            ];
        }

        return [
            'file' => $ini['file'],
            'found' => true,
            'instance_name' => $this->iniValue('general', 'instancename'),
            'status_enabled' => strtolower($this->iniValue('general', 'statusenabled', 'true')) !== 'false',
            'tick_ms' => (int) $this->iniValue('general', 'tickms', '0'),
            'exit_when_all_stopped' => strtolower($this->iniValue('general', 'exitwhenallstopped', 'false')) === 'true',
            'services' => $services,
        ];
    }

    /** Absolute path stays; a relative one is resolved against the ini folder, exactly like the supervisor does. */
    private function resolveIniPath(string $value, string $iniDir): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $isAbsolute = preg_match('#^[A-Za-z]:[\\\\/]#', $value) === 1 || str_starts_with($value, '\\\\');
        if ($isAbsolute) {
            return rtrim($value, '\\/');
        }

        return $iniDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value), DIRECTORY_SEPARATOR);
    }

    /**
     * Newest file matching one of the globs, relative to the supervisor directory ('' when none).
     * @param array<int,string> $globs
     */
    private function newestInDirectory(string $dir, array $globs): string
    {
        if ($dir === '') {
            return '';
        }

        $newest = '';
        $newestTime = -1;
        foreach ($globs as $glob) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . $glob) ?: [] as $match) {
                if (!is_file($match)) {
                    continue;
                }
                $time = (int) @filemtime($match);
                if ($time > $newestTime) {
                    $newestTime = $time;
                    $newest = $match;
                }
            }
            if ($newest !== '') {
                break;
            }
        }

        return $newest;
    }

    /**
     * Full state used by the page and the status API.
     */
    public function status(): array
    {
        $paths = $this->paths();
        $enabled = $this->enabled();

        $decoded = null;
        $age = null;
        if ($enabled && $paths['status_file'] !== '' && is_file($paths['status_file'])) {
            $decoded = $this->decodeStatus($paths['status_file']);
            $age = $this->statusAge($paths['status_file']);
        }

        $staleAfter = max(5, (int) ($this->config['status_stale_seconds'] ?? 20));
        $available = $decoded !== null;
        $running = $available && $age !== null && $age <= $staleAfter;

        // "the panel cannot find the supervisor at all" must stay distinct from "not running": a
        // production deployment often keeps the supervisor outside the web root.
        $configuredDir = trim((string) ($this->config['dir'] ?? ''));
        $iniSummary = $this->iniSummary();
        $reason = 'ok';
        if (!$enabled) {
            $reason = 'disabled';
        } elseif (!$available) {
            if ($configuredDir !== '' && !is_dir($configuredDir)) {
                $reason = 'dir_missing';
            } elseif ($paths['dir'] === '' && trim((string) ($this->config['status_file'] ?? '')) === '') {
                $reason = 'not_configured';
            } elseif (($iniSummary['found'] ?? false) && ($iniSummary['status_enabled'] ?? true) === false) {
                // StatusEnabled = false in the ini: no status file is written on purpose
                $reason = 'status_disabled';
            } else {
                $reason = 'no_status_file';
            }
        } elseif (!$running) {
            $reason = 'stale';
        }

        // A status file whose InstanceName differs from the ini's was written by ANOTHER realm's
        // supervisor (copied folder, wrong dir) and would show the wrong realm as running.
        $iniInstance = (string) ($iniSummary['instance_name'] ?? '');
        $statusInstance = (string) ($decoded['instance'] ?? '');
        $instanceCheck = [
            'ini' => $iniInstance,
            'status' => $statusInstance,
            'mismatch' => $iniInstance !== '' && $statusInstance !== '' && strcasecmp($iniInstance, $statusInstance) !== 0,
        ];

        $services = [];
        foreach ((array) ($decoded['services'] ?? []) as $service) {
            if (!is_array($service)) {
                continue;
            }

            $services[] = $this->normalizeService($service);
        }

        return [
            'enabled' => $enabled,
            'instance' => [
                'id' => $this->instanceId,
                'label' => $this->instanceLabel(),
            ],
            'available' => $available,
            'running' => $running,
            'reason' => $reason,
            'reason_label' => Lang::get('app.supervisor.reason.' . $reason),
            // what the supervisor's own ini says (authoritative for names, services, tick); panel config only overrides
            'ini' => $iniSummary,
            'instance_check' => $instanceCheck,
            // only when something is wrong: tells the operator which directories were tried
            'diagnostics' => $available ? null : $this->diagnostics(),
            'status_age_seconds' => $age,
            'stale_after_seconds' => $staleAfter,
            'poll_seconds' => max(0, (int) ($this->config['poll_seconds'] ?? 5)),
            'control_allowed' => $this->isControlAllowed($running, $paths),
            'can_start' => $this->canStartSupervisor(),
            'start_task_name' => (string) ($this->config['start_task_name'] ?? ''),
            'exe_exists' => $paths['exe'] !== '' && is_file($paths['exe']),
            'paths' => $paths,
            'supervisor' => [
                'version' => (string) ($decoded['version'] ?? ''),
                'pid' => (int) ($decoded['pid'] ?? 0),
                'instance' => (string) ($decoded['instance'] ?? ''),
                'uptime_seconds' => (int) ($decoded['uptimeSec'] ?? 0),
                'updated_at_ms' => (int) ($decoded['updatedAtTickMs'] ?? 0),
                'last_command' => $this->normalizeLastCommand($decoded['lastCommand'] ?? null, (int) ($decoded['updatedAtTickMs'] ?? 0)),
            ],
            'services' => $services,
        ];
    }

    public function logTail(?int $lines = null): array
    {
        $paths = $this->paths();
        $lines ??= max(20, min(2000, (int) ($this->config['log_tail_lines'] ?? 200)));

        if ($paths['log_file'] === '' || !is_file($paths['log_file'])) {
            return [];
        }

        return $this->tailFile($paths['log_file'], $lines);
    }

    /**
     * Hand a command to the supervisor through the control file.
     *
     * @return array{success:bool,message:string,id?:string,action?:string,target?:string}
     */
    public function dispatch(string $action, string $target = 'all'): array
    {
        $action = strtolower(trim($action));
        $target = strtolower(trim($target));

        if ($action === 'start_supervisor') {
            return $this->startSupervisor();
        }

        if (!$this->enabled()) {
            return $this->failure(Lang::get('app.supervisor.errors.disabled'));
        }

        if (!in_array($action, self::SERVICE_ACTIONS, true)) {
            return $this->failure(Lang::get('app.supervisor.errors.unknown_action', ['action' => $action]));
        }

        if (!in_array($target, self::TARGETS, true)) {
            return $this->failure(Lang::get('app.supervisor.errors.unknown_target', ['target' => $target]));
        }

        $paths = $this->paths();
        $state = $this->status();

        if (!$state['running']) {
            return $this->failure(Lang::get('app.supervisor.errors.not_running'));
        }

        // Never command a service this supervisor does not run (Enabled = false in its ini): on a
        // shared-authserver machine that would start a SECOND authserver, so only the owning realm's
        // entry may touch it.
        if ($target !== 'all') {
            foreach ((array) ($state['services'] ?? []) as $service) {
                if (!is_array($service) || (string) ($service['name'] ?? '') !== $target) {
                    continue;
                }
                if (($service['enabled'] ?? true) === false) {
                    return $this->failure(Lang::get('app.supervisor.errors.service_disabled', ['service' => $target]));
                }
                break;
            }
        }

        if (!$state['control_allowed']) {
            return $this->failure(Lang::get('app.supervisor.errors.control_unavailable'));
        }

        $id = $this->newCommandId();
        $payload = "id={$id}\naction={$action}\ntarget={$target}\n";

        try {
            $this->atomicWrite($paths['control_file'], $payload);
        } catch (Throwable $exception) {
            return $this->failure(Lang::get('app.supervisor.errors.write_failed', ['message' => $exception->getMessage()]));
        }

        return [
            'success' => true,
            'id' => $id,
            'action' => $action,
            'target' => $target,
            'message' => Lang::get('app.supervisor.messages.command_sent', [
                'action' => Lang::get('app.supervisor.actions.' . $action),
                'target' => Lang::get('app.supervisor.targets.' . $target),
            ]),
        ];
    }

    /**
     * Launch the supervisor through its Task Scheduler task (the only way to reach the interactive
     * session from a web request).
     * @return array{success:bool,message:string}
     */
    public function startSupervisor(): array
    {
        if (!$this->canStartSupervisor()) {
            return $this->failure(Lang::get('app.supervisor.errors.start_not_configured'));
        }

        $task = (string) $this->config['start_task_name'];
        $schtasks = trim((string) ($this->config['schtasks_path'] ?? ''));
        if ($schtasks === '') {
            $schtasks = 'schtasks.exe';
        }

        if (!function_exists('exec')) {
            return $this->failure(Lang::get('app.supervisor.errors.exec_disabled'));
        }

        $command = escapeshellarg($schtasks) . ' /run /tn ' . escapeshellarg($task) . ' 2>&1';
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return $this->failure(Lang::get('app.supervisor.errors.start_failed', [
                'task' => $task,
                'message' => trim(implode(' ', array_slice($output, 0, 3))),
            ]));
        }

        return [
            'success' => true,
            'message' => Lang::get('app.supervisor.messages.supervisor_starting', ['task' => $task]),
        ];
    }

    public function canStartSupervisor(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        if (!(bool) ($this->config['allow_start'] ?? false)) {
            return false;
        }

        return trim((string) ($this->config['start_task_name'] ?? '')) !== '';
    }

    private function isControlAllowed(bool $running, array $paths): bool
    {
        if (!$running || $paths['control_file'] === '') {
            return false;
        }

        $dir = dirname($paths['control_file']);

        return is_dir($dir) && is_writable($dir);
    }

    private function newCommandId(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (Throwable) {
            return dechex(time()) . dechex(mt_rand(0, 0xFFFF));
        }
    }

    /**
     * @return array{success:false,message:string}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    /**
     * Write through a temporary file + rename so the supervisor never reads a half-written command
     * (it polls the file several times per second).
     */
    private function atomicWrite(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            throw new RuntimeException('directory not found: ' . $dir);
        }

        $tmp = $path . '.panel-tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            throw new RuntimeException('cannot write ' . $tmp);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('cannot replace ' . $path);
        }
    }

    private function resolveDirectory(): string
    {
        if ($this->resolvedDir !== null) {
            return $this->resolvedDir;
        }

        $configured = trim((string) ($this->config['dir'] ?? ''));
        if ($configured !== '') {
            return $this->resolvedDir = rtrim($configured, "\\/");
        }

        foreach ($this->candidateDirectories() as $candidate) {
            if (is_file($candidate . DIRECTORY_SEPARATOR . 'acore_supervisor.exe')
                || is_file($candidate . DIRECTORY_SEPARATOR . 'supervisor.ini')) {
                return $this->resolvedDir = $candidate;
            }
        }

        return $this->resolvedDir = '';
    }

    private function candidateDirectories(): array
    {
        $resolved = [];
        foreach ($this->rawCandidateDirectories() as $candidate) {
            $real = realpath($candidate);
            if ($real === false) {
                continue;
            }

            $resolved[] = rtrim($real, "\\/");
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Every directory the panel would look in, including the ones that do not exist - the page
     * needs those to explain itself (see diagnostics()).
     *
     * @return array<int,string>
     */
    private function rawCandidateDirectories(): array
    {
        // .../AGMP/app/Domain/Supervisor -> AGMP (3) -> .../Server (6)
        $panelRoot = rtrim(dirname(__DIR__, 3), "\\/");
        $serverRoot = rtrim(dirname(__DIR__, 6), "\\/");
        $named = $this->instanceId !== self::DEFAULT_INSTANCE;

        $candidates = [];

        // 1) explicit override, highest priority: an env var is the only knob that does not need a
        //    file change on a production host (httpd SetEnv / .env / scheduled task). It is a
        //    single-instance knob on purpose: applying it to a named instance would point every
        //    named instance at the same folder.
        if (!$named) {
            $envDir = $this->envDirectory();
            if ($envDir !== '') {
                $candidates[] = $envDir;
            }
        }

        // 2) the roots to probe: the server root, the panel's parents and every ancestor of the
        //    panel. Walking instead of trusting one fixed depth keeps a panel deployed deeper (an
        //    extra folder) or shallower (web root = <server>/web/WWW) working.
        $roots = [$serverRoot, rtrim(dirname($panelRoot, 2), "\\/"), $panelRoot];
        $ancestor = $panelRoot;
        for ($depth = 0; $depth < 8; $depth++) {
            $parent = rtrim(dirname($ancestor), "\\/");
            if ($parent === $ancestor || $parent === '' || $parent === '.') {
                break;
            }

            $roots[] = $parent;
            $ancestor = $parent;
        }

        // 3) which folder names count as "a supervisor" under those roots.
        //    A NAMED instance only gets its own conventions: on a multi-realm machine the generic
        //    release/supervisor folder belongs to ANOTHER realm, and binding this instance to it
        //    would show - and control - the wrong realm's supervisor. That is the same reason an
        //    unknown instance id is refused instead of being mapped to another realm; a forgotten
        //    dir must end in "directory not found" plus the diagnostics block, never in a silently
        //    wrong realm.
        //    The documented per-realm layouts are release/supervisor-<id> and release/<id>/supervisor
        //    (see supervisor.ini "several realms on one machine"); the same names directly under a
        //    root are accepted too, so a realm folder that sits next to the panel is found as well.
        $conventions = $named
            ? [
                'release' . DIRECTORY_SEPARATOR . 'supervisor-' . $this->instanceId,
                'release' . DIRECTORY_SEPARATOR . $this->instanceId . DIRECTORY_SEPARATOR . 'supervisor',
                'supervisor-' . $this->instanceId,
                $this->instanceId . DIRECTORY_SEPARATOR . 'supervisor',
            ]
            : ['release' . DIRECTORY_SEPARATOR . 'supervisor', 'supervisor'];

        foreach (array_unique($roots) as $root) {
            if ($root === '' || $root === '.') {
                continue;
            }
            foreach ($conventions as $convention) {
                $candidates[] = $root . DIRECTORY_SEPARATOR . $convention;
            }
        }

        // the configured override is checked last but is never dropped from the report
        $configured = trim((string) ($this->config['dir'] ?? ''));
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * ACORE_SUPERVISOR_DIR override from the process environment or the request environment
     * (Apache SetEnv shows up in $_SERVER, not always in getenv()).
     */
    private function envDirectory(): string
    {
        foreach ([getenv('ACORE_SUPERVISOR_DIR'), $_SERVER['ACORE_SUPERVISOR_DIR'] ?? null, $_ENV['ACORE_SUPERVISOR_DIR'] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return rtrim(trim($value), "\\/");
            }
        }

        return '';
    }

    /**
     * Why the supervisor was (not) found, for the page and for support: the directories that were
     * tried, what is in them, which path won and what the panel process may not be able to see.
     */
    public function diagnostics(): array
    {
        $configuredDir = trim((string) ($this->config['dir'] ?? ''));
        $panelRoot = rtrim(dirname(__DIR__, 3), "\\/");

        $candidates = [];
        foreach ($this->rawCandidateDirectories() as $candidate) {
            $exists = is_dir($candidate);
            $candidates[] = [
                'path' => $candidate,
                'exists' => $exists,
                'has_exe' => $exists && is_file($candidate . DIRECTORY_SEPARATOR . 'acore_supervisor.exe'),
                'has_ini' => $exists && is_file($candidate . DIRECTORY_SEPARATOR . 'supervisor.ini'),
                'has_status' => $exists && is_file($candidate . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'supervisor_status.json'),
            ];
        }

        $paths = $this->paths();
        $statusExists = $paths['status_file'] !== '' && is_file($paths['status_file']);
        $ini = $this->iniFilePaths();
        $iniSummary = $this->iniSummary();

        // where the panel config and the ini disagree about a file name: the config wins, so a wrong
        // entry sends commands into a file the supervisor never reads
        $conflicts = [];
        foreach (['status_file', 'control_file', 'log_file'] as $key) {
            $configured = trim((string) ($this->config[$key] ?? ''));
            $fromIni = (string) ($ini[$key] ?? '');
            if ($configured !== '' && $fromIni !== '' && strcasecmp($configured, $fromIni) !== 0) {
                $conflicts[$key] = ['configured' => $configured, 'ini' => $fromIni];
            }
        }

        return [
            'instance' => $this->instanceId,
            'panel_root' => $panelRoot,
            'configured_dir' => $configuredDir,
            'env_dir' => $this->envDirectory(),
            'env_var' => 'ACORE_SUPERVISOR_DIR',
            'resolved_dir' => $paths['dir'],
            'status_file' => $paths['status_file'],
            'status_file_exists' => $statusExists,
            'status_file_age_seconds' => $statusExists ? $this->statusAge($paths['status_file']) : null,
            'control_file' => $paths['control_file'],
            'log_file' => $paths['log_file'],
            // which supervisor.ini was read and where every path came from: the usual reason a running
            // supervisor looks "not running" is a renamed status/control file
            'ini_file' => $ini['ini_file'],
            'ini_found' => $ini['ini_found'],
            'ini' => $iniSummary,
            'ini_conflicts' => $conflicts,
            'file_sources' => $this->pathSources,
            'exe' => $paths['exe'],
            'exe_exists' => $paths['exe'] !== '' && is_file($paths['exe']),
            'config_file' => $paths['config_file'],
            'candidates' => $candidates,
            // the two things that make a file invisible to PHP on a production host
            'open_basedir' => (string) ini_get('open_basedir'),
            'process_user' => (string) (getenv('USERNAME') ?: (getenv('USER') ?: '')),
            'override_file' => 'config/generated/supervisor.php',
        ];
    }

    private function decodeStatus(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function statusAge(string $path): ?int
    {
        $mtime = @filemtime($path);
        if ($mtime === false) {
            return null;
        }

        return max(0, time() - $mtime);
    }

    private function normalizeService(array $service): array
    {
        $state = (string) ($service['state'] ?? 'unknown');
        $heartbeatSeen = (bool) ($service['heartbeatSeen'] ?? false);
        $heartbeatAge = isset($service['heartbeatAgeSec']) ? (int) $service['heartbeatAgeSec'] : -1;
        // heartbeatTimeoutSec is the EFFECTIVE limit: the supervisor raises it to
        // max(configured, RecordUpdateTimeDiffInterval + 120s) so a slow record interval does not look
        // like a stall. The panel must judge and display the effective one.
        $heartbeatTimeout = (int) ($service['heartbeatTimeoutSec'] ?? 0);
        $heartbeatTimeoutConfigured = (int) ($service['heartbeatTimeoutConfiguredSec'] ?? $heartbeatTimeout);
        $probeOk = (bool) ($service['probeOk'] ?? true);
        // A service this supervisor does not run (Enabled = false, e.g. a shared authserver owned by
        // another realm) is not "down": it is out of scope here.
        $enabled = (bool) ($service['enabled'] ?? true);

        [$health, $tone] = $this->healthFor($state, $heartbeatSeen, $heartbeatAge, $heartbeatTimeout, $probeOk, (bool) ($service['stoppedByUser'] ?? false), $enabled);

        return [
            'name' => (string) ($service['name'] ?? ''),
            'role' => (string) ($service['role'] ?? ''),
            'enabled' => $enabled,
            'state' => $state,
            'state_label' => Lang::get('app.supervisor.states.' . $state, [], $state),
            'stopped_by_user' => (bool) ($service['stoppedByUser'] ?? false),
            'health' => $health,
            'health_label' => Lang::get('app.supervisor.health.' . $health),
            'tone' => $tone,
            'pid' => (int) ($service['pid'] ?? 0),
            'adopted' => (bool) ($service['adopted'] ?? false),
            'uptime_seconds' => (int) ($service['uptimeSec'] ?? 0),
            'log_file' => (string) ($service['logFile'] ?? ''),
            'log_stale_seconds' => isset($service['logStaleSec']) ? (int) $service['logStaleSec'] : -1,
            'heartbeat_seen' => $heartbeatSeen,
            'heartbeat_age_seconds' => $heartbeatAge,
            'heartbeat_timeout_seconds' => $heartbeatTimeout,
            'heartbeat_timeout_configured_seconds' => $heartbeatTimeoutConfigured,
            'heartbeat_timeout_raised' => $heartbeatTimeoutConfigured > 0 && $heartbeatTimeout > $heartbeatTimeoutConfigured,
            // declared vs observed heartbeat cadence: the two numbers that separate a real stall from a
            // normal slow record interval
            'heartbeat_interval_seconds' => (int) ($service['heartbeatIntervalSec'] ?? 0),
            'heartbeat_min_record_ms' => (int) ($service['heartbeatMinRecordMs'] ?? 0),
            'heartbeat_cadence_seconds' => (int) ($service['heartbeatCadenceSec'] ?? 0),
            'probe_ok' => $probeOk,
            'probe_failures' => (int) ($service['probeFailures'] ?? 0),
            'probe_detail' => (string) ($service['probeDetail'] ?? ''),
            'restarts' => (int) ($service['restarts'] ?? 0),
            'consecutive_failures' => (int) ($service['consecutiveFailures'] ?? 0),
            'last_exit_code' => isset($service['lastExitCode']) ? (int) $service['lastExitCode'] : null,
            'last_event' => (string) ($service['lastEvent'] ?? ''),
            'last_stop' => (string) ($service['lastStop'] ?? ''),
            'working_set_mb' => (float) ($service['workingSetMB'] ?? 0),
            'cpu_total_ms' => (int) ($service['cpuTotalMs'] ?? 0),
        ];
    }

    /**
     * @return array{0:string,1:string} health key + tone
     */
    private function healthFor(
        string $state,
        bool $heartbeatSeen,
        int $heartbeatAge,
        int $heartbeatTimeout,
        bool $probeOk,
        bool $stoppedByUser,
        bool $enabled = true
    ): array {
        // not supervised by this instance at all (Enabled = false): never report it as down/hung and
        // never offer a control that could start a second copy of a shared service
        if (!$enabled) {
            return ['disabled', 'muted'];
        }

        if ($state === 'stopped') {
            return $stoppedByUser ? ['stopped', 'muted'] : ['down', 'error'];
        }

        if ($state === 'waiting-restart') {
            return ['restarting', 'warn'];
        }

        if ($state === 'starting') {
            return ['starting', 'warn'];
        }

        if ($heartbeatTimeout > 0 && $heartbeatSeen && $heartbeatAge >= 0) {
            if ($heartbeatAge > $heartbeatTimeout) {
                return ['hung', 'error'];
            }
            if ($heartbeatAge > (int) max(1, intdiv($heartbeatTimeout, 2))) {
                return ['lagging', 'warn'];
            }
        }

        if (!$probeOk) {
            return ['probe_failed', 'warn'];
        }

        return ['healthy', 'ok'];
    }

    /**
     * @param int $statusTickMs the supervisor's own updatedAtTickMs for this snapshot
     */
    private function normalizeLastCommand(mixed $command, int $statusTickMs = 0): ?array
    {
        if (!is_array($command)) {
            return null;
        }

        $doneTick = (int) ($command['doneAtTickMs'] ?? 0);
        $ageSeconds = null;
        if ($doneTick > 0 && $statusTickMs >= $doneTick) {
            // Tick() is ms since boot: snapshot tick - completion tick = how long ago the command ran
            $ageSeconds = intdiv($statusTickMs - $doneTick, 1000);
        }

        return [
            'id' => (string) ($command['id'] ?? ''),
            'action' => (string) ($command['action'] ?? ''),
            'target' => (string) ($command['target'] ?? ''),
            'result' => (string) ($command['result'] ?? ''),
            'message' => (string) ($command['message'] ?? ''),
            'age_seconds' => $ageSeconds,
        ];
    }

    private function tailFile(string $path, int $lines): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunkSize = 8192;
        $collected = [];

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);

        while ($position > 0 && count($collected) <= $lines) {
            $readSize = (int) min($chunkSize, $position);
            $position -= $readSize;
            fseek($handle, $position, SEEK_SET);
            $chunk = (string) fread($handle, $readSize);
            $buffer = $chunk . $buffer;

            $collected = explode("\n", $buffer);
        }

        fclose($handle);

        $collected = array_values(array_filter(array_map('rtrim', $collected), static fn (string $line): bool => $line !== ''));

        if (count($collected) > $lines) {
            $collected = array_slice($collected, -$lines);
        }

        return $collected;
    }
}
