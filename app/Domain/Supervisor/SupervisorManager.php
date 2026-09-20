<?php
/**
 * File: app/Domain/Supervisor/SupervisorManager.php
 * Purpose: Read/write bridge between the panel and acore_supervisor.exe.
 * Classes:
 *   - SupervisorManager
 * Functions:
 *   - __construct()
 *   - paths()
 *   - status()
 *   - logTail()
 *   - dispatch()
 *   - startSupervisor()
 *   - isControlAllowed()
 *   - resolveDirectory()
 *   - decodeStatus()
 *   - normalizeService()
 *   - healthFor()
 *   - readStatusFile()
 *   - statusAge()
 *   - tailFile()
 *   - atomicWrite()
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

    private const SERVICE_ACTIONS = [
        self::ACTION_START,
        self::ACTION_STOP,
        self::ACTION_RESTART,
        self::ACTION_PING,
        self::ACTION_SHUTDOWN,
    ];

    private const TARGETS = ['all', 'worldserver', 'authserver'];

    /** @var array<string,mixed> */
    private array $config;

    private ?string $resolvedDir = null;

    public function __construct()
    {
        $config = Config::get('supervisor', []);
        $this->config = is_array($config) ? $config : [];
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * @return array{dir:string,exe:string,config_file:string,status_file:string,control_file:string,log_file:string}
     */
    public function paths(): array
    {
        $dir = $this->resolveDirectory();

        $pick = static function (string $configured, string $dir, string $relative): string {
            $configured = trim($configured);
            if ($configured !== '') {
                return $configured;
            }

            return $dir === '' ? '' : $dir . DIRECTORY_SEPARATOR . $relative;
        };

        return [
            'dir' => $dir,
            'exe' => $pick((string) ($this->config['exe'] ?? ''), $dir, 'acore_supervisor.exe'),
            'config_file' => $pick((string) ($this->config['config_file'] ?? ''), $dir, 'supervisor.ini'),
            'status_file' => $pick((string) ($this->config['status_file'] ?? ''), $dir, 'logs' . DIRECTORY_SEPARATOR . 'supervisor_status.json'),
            'control_file' => $pick((string) ($this->config['control_file'] ?? ''), $dir, 'logs' . DIRECTORY_SEPARATOR . 'supervisor_control.txt'),
            'log_file' => $pick((string) ($this->config['log_file'] ?? ''), $dir, 'logs' . DIRECTORY_SEPARATOR . 'supervisor.log'),
        ];
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

        $reason = 'ok';
        if (!$enabled) {
            $reason = 'disabled';
        } elseif (!$available) {
            $reason = $paths['dir'] === '' ? 'not_configured' : 'no_status_file';
        } elseif (!$running) {
            $reason = 'stale';
        }

        $services = [];
        foreach ((array) ($decoded['services'] ?? []) as $service) {
            if (!is_array($service)) {
                continue;
            }

            $services[] = $this->normalizeService($service);
        }

        return [
            'enabled' => $enabled,
            'available' => $available,
            'running' => $running,
            'reason' => $reason,
            'reason_label' => Lang::get('app.supervisor.reason.' . $reason),
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
                'last_command' => $this->normalizeLastCommand($decoded['lastCommand'] ?? null),
            ],
            'services' => $services,
        ];
    }

    /**
     * @return array<int,string>
     */
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
     * Launch the supervisor through its Task Scheduler task (the only way to reach the
     * interactive session from a web request).
     *
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
     * Write through a temporary file + rename so the supervisor never reads a half-written
     * command (it polls the file several times per second).
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

    /**
     * @return array<int,string>
     */
    private function candidateDirectories(): array
    {
        // .../AGMP/app/Domain/Supervisor -> AGMP (3) -> .../Server (6)
        $panelRoot = dirname(__DIR__, 3);
        $serverRoot = dirname(__DIR__, 6);

        $candidates = [
            $serverRoot . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . 'supervisor',
            dirname($panelRoot, 2) . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . 'supervisor',
            $panelRoot . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . 'supervisor',
        ];

        $resolved = [];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false) {
                continue;
            }

            $resolved[] = rtrim($real, "\\/");
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return array<string,mixed>|null
     */
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

    /**
     * @param array<string,mixed> $service
     * @return array<string,mixed>
     */
    private function normalizeService(array $service): array
    {
        $state = (string) ($service['state'] ?? 'unknown');
        $heartbeatSeen = (bool) ($service['heartbeatSeen'] ?? false);
        $heartbeatAge = isset($service['heartbeatAgeSec']) ? (int) $service['heartbeatAgeSec'] : -1;
        $heartbeatTimeout = (int) ($service['heartbeatTimeoutSec'] ?? 0);
        $probeOk = (bool) ($service['probeOk'] ?? true);
        $probePort = (int) ($service['probePort'] ?? 0);

        [$health, $tone] = $this->healthFor($state, $heartbeatSeen, $heartbeatAge, $heartbeatTimeout, $probeOk, (bool) ($service['stoppedByUser'] ?? false));

        return [
            'name' => (string) ($service['name'] ?? ''),
            'role' => (string) ($service['role'] ?? ''),
            'enabled' => (bool) ($service['enabled'] ?? true),
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
        bool $stoppedByUser
    ): array {
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
     * @param mixed $command
     * @return array<string,mixed>|null
     */
    private function normalizeLastCommand(mixed $command): ?array
    {
        if (!is_array($command)) {
            return null;
        }

        $doneTick = (int) ($command['doneAtTickMs'] ?? 0);
        $ageSeconds = null;
        if ($doneTick > 0) {
            // Tick() is milliseconds since boot; convert to "how long ago" using the status age
            $ageSeconds = null;
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

    /**
     * @return array<int,string>
     */
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
