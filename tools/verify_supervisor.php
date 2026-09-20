<?php
/**
 * File: tools/verify_supervisor.php
 * Purpose: CLI verification for the supervisor (watchdog) panel integration.
 *
 *   php tools/verify_supervisor.php
 *       unit checks: path auto-detection, status parsing, staleness, health mapping,
 *       command validation + atomic control-file writing, log tail, page rendering.
 *
 *   php tools/verify_supervisor.php --e2e --status-file=<p> --control-file=<p> \
 *        --action=restart --target=worldserver [--log-file=<p>]
 *       real dispatch against a running supervisor (prints one JSON line) - used by
 *       tests/run_scenario.ps1 -Scenario control -UsePhp.
 */

declare(strict_types=1);

namespace {
    define('PANEL_CLI_AUTH_BYPASS', true);

    require dirname(__DIR__) . '/bootstrap/autoload.php';
    require_once dirname(__DIR__) . '/bootstrap/helpers.php';

    use Acme\Panel\Core\Config;
    use Acme\Panel\Core\Lang;
    use Acme\Panel\Core\Request;
    use Acme\Panel\Domain\Supervisor\SupervisorManager;

    error_reporting(E_ALL & ~E_DEPRECATED);

    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--')) {
            $parts = explode('=', substr($argument, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }

    Config::init(dirname(__DIR__) . '/config');
    Lang::init();

    $_SESSION = [
        'panel_logged_in' => true,
        'panel_user' => 'cli-verifier',
        'panel_capabilities' => ['*'],
    ];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/supervisor';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    // ------------------------------------------------------------------ end-to-end dispatch mode
    if (isset($options['e2e'])) {
        $statusFile = (string) ($options['status-file'] ?? '');
        $controlFile = (string) ($options['control-file'] ?? '');
        if ($statusFile === '' || $controlFile === '') {
            fwrite(STDERR, "status-file and control-file are required\n");
            exit(2);
        }

        Config::set('supervisor.dir', '');
        Config::set('supervisor.status_file', $statusFile);
        Config::set('supervisor.control_file', $controlFile);
        if (!empty($options['log-file'])) {
            Config::set('supervisor.log_file', (string) $options['log-file']);
        }

        $manager = new SupervisorManager();
        $result = $manager->dispatch((string) ($options['action'] ?? 'ping'), (string) ($options['target'] ?? 'all'));
        $result['state_running'] = $manager->status()['running'];

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        exit(($result['success'] ?? false) ? 0 : 1);
    }

    // ------------------------------------------------------------------ unit checks
    $failures = [];
    $checks = 0;

    function check(string $label, bool $condition, string $detail = ''): void
    {
        global $failures, $checks;
        $checks++;
        if ($condition) {
            echo "  PASS  {$label}\n";
            return;
        }

        $failures[] = $label;
        echo "  FAIL  {$label}" . ($detail !== '' ? " :: {$detail}" : '') . "\n";
    }

    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'agmp-supervisor-verify-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0777, true);
    @mkdir($tmp . DIRECTORY_SEPARATOR . 'logs', 0777, true);

    $statusFile = $tmp . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'supervisor_status.json';
    $controlFile = $tmp . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'supervisor_control.txt';
    $logFile = $tmp . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'supervisor.log';

    echo "1) path resolution\n";
    $auto = new SupervisorManager();
    $autoPaths = $auto->paths();
    check('auto-detects release/supervisor', str_contains(str_replace('/', '\\', $autoPaths['dir']), 'release\\supervisor'), $autoPaths['dir']);
    check('auto status file points at logs/supervisor_status.json', str_ends_with(str_replace('/', '\\', $autoPaths['status_file']), 'logs\\supervisor_status.json'), $autoPaths['status_file']);
    check('auto control file points at logs/supervisor_control.txt', str_ends_with(str_replace('/', '\\', $autoPaths['control_file']), 'logs\\supervisor_control.txt'), $autoPaths['control_file']);

    echo "2) status parsing, health mapping and staleness\n";
    Config::set('supervisor.status_file', $statusFile);
    Config::set('supervisor.control_file', $controlFile);
    Config::set('supervisor.log_file', $logFile);
    Config::set('supervisor.status_stale_seconds', 20);
    Config::set('supervisor.dir', $tmp);

    $manager = new SupervisorManager();

    $missing = $manager->status();
    check('missing status file -> running=false', $missing['running'] === false, (string) $missing['reason']);
    check('missing status file -> reason=no_status_file', $missing['reason'] === 'no_status_file', (string) $missing['reason']);
    check('control is refused while the supervisor is not running', $manager->dispatch('restart', 'worldserver')['success'] === false);

    $payload = [
        'version' => '1.1.0',
        'pid' => 4242,
        'updatedAtTickMs' => 1000,
        'startedAtTickMs' => 10,
        'uptimeSec' => 990,
        'instance' => 'Acore80',
        'controlFile' => $controlFile,
        'statusFile' => $statusFile,
        'lastCommand' => [
            'id' => 'abc123',
            'action' => 'restart',
            'target' => 'worldserver',
            'result' => 'ok',
            'message' => 'ok: worldserver: restarted (graceful)',
        ],
        'services' => [
            [
                'name' => 'worldserver', 'role' => 'world', 'enabled' => true, 'state' => 'running',
                'stoppedByUser' => false, 'pid' => 100, 'uptimeSec' => 900,
                'logFile' => 'logs/Server.log', 'logStaleSec' => 3,
                'heartbeatSeen' => true, 'heartbeatAgeSec' => 4, 'heartbeatTimeoutSec' => 180,
                'probeOk' => true, 'probeFailures' => 0, 'probeDetail' => 'probe disabled',
                'restarts' => 2, 'consecutiveFailures' => 0, 'lastExitCode' => 0,
                'lastEvent' => 'startup-complete', 'lastStop' => '', 'workingSetMB' => 850.5, 'cpuTotalMs' => 12345,
            ],
            [
                'name' => 'authserver', 'role' => 'auth', 'enabled' => true, 'state' => 'running',
                'stoppedByUser' => false, 'pid' => 200, 'uptimeSec' => 900,
                'logFile' => 'logs/Auth.log', 'logStaleSec' => 900,
                'heartbeatSeen' => false, 'heartbeatAgeSec' => -1, 'heartbeatTimeoutSec' => 180,
                'probeOk' => false, 'probeFailures' => 2, 'probeDetail' => 'no response to AUTH_LOGON_CHALLENGE',
                'restarts' => 0, 'consecutiveFailures' => 2, 'lastExitCode' => 0,
                'lastEvent' => 'probe failed', 'lastStop' => '', 'workingSetMB' => 120.0, 'cpuTotalMs' => 999,
            ],
        ],
    ];
    file_put_contents($statusFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

    $state = $manager->status();
    check('fresh status file -> running=true', $state['running'] === true, (string) $state['reason']);
    check('control allowed while running', $state['control_allowed'] === true);
    check('supervisor metadata parsed', $state['supervisor']['pid'] === 4242 && $state['supervisor']['instance'] === 'Acore80');
    check('last command parsed', $state['supervisor']['last_command']['id'] === 'abc123');
    check('two services parsed', count($state['services']) === 2);

    $world = $state['services'][0];
    $auth = $state['services'][1];
    check('worldserver health=healthy/ok', $world['health'] === 'healthy' && $world['tone'] === 'ok', $world['health'] . '/' . $world['tone']);
    check('worldserver state label localised', $world['state_label'] !== '' && $world['state_label'] !== 'running', $world['state_label']);
    check('authserver probe failure -> health=probe_failed', $auth['health'] === 'probe_failed' && $auth['tone'] === 'warn', $auth['health'] . '/' . $auth['tone']);
    check('restart counter parsed', $world['restarts'] === 2);
    check('working set parsed', abs($world['working_set_mb'] - 850.5) < 0.01);

    // a stale status file means the supervisor died
    touch($statusFile, time() - 60);
    clearstatcache(true, $statusFile);
    $stale = $manager->status();
    check('stale status file -> running=false', $stale['running'] === false);
    check('stale status file -> reason=stale', $stale['reason'] === 'stale', (string) $stale['reason']);
    check('control refused while stale', $manager->dispatch('restart', 'worldserver')['success'] === false);
    file_put_contents($statusFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

    echo "3) command validation and atomic control file\n";
    check('unknown action rejected', $manager->dispatch('explode', 'worldserver')['success'] === false);
    check('unknown target rejected', $manager->dispatch('restart', 'database')['success'] === false);

    $dispatch = $manager->dispatch('restart', 'worldserver');
    check('valid command accepted', $dispatch['success'] === true, (string) ($dispatch['message'] ?? ''));
    check('control file written', is_file($controlFile));
    $written = is_file($controlFile) ? (string) file_get_contents($controlFile) : '';
    check('control file has an id', (bool) preg_match('/^id=[0-9a-f]+$/m', $written), $written);
    check('control file carries the action', str_contains($written, 'action=restart'), $written);
    check('control file carries the target', str_contains($written, 'target=worldserver'), $written);
    check('no leftover temp file', !is_file($controlFile . '.panel-tmp'));
    check('dispatch returns the id for polling', ($dispatch['id'] ?? '') !== '');

    $shutdown = $manager->dispatch('shutdown', 'all');
    check('shutdown on all accepted', $shutdown['success'] === true);
    @unlink($controlFile);

    echo "4) log tail\n";
    file_put_contents($logFile, implode("\n", array_map(static fn (int $i): string => "[line {$i}] supervisor event", range(1, 300))) . "\n");
    $tail = $manager->logTail(50);
    check('log tail returns the requested amount', count($tail) === 50, (string) count($tail));
    check('log tail ends with the newest line', end($tail) === '[line 300] supervisor event', (string) end($tail));

    echo "5) page rendering through the controller\n";
    // Request properties are filled by capture(); build them explicitly here.
    $makeRequest = static function (string $method = 'GET', array $post = []): Request {
        $request = new Request();
        $request->method = $method;
        $request->uri = '/supervisor';
        $request->get = [];
        $request->post = $post;
        $request->headers = [];
        $request->server = $_SERVER;
        return $request;
    };

    $controller = new Acme\Panel\Http\Controllers\Supervisor\SupervisorController();
    $request = $makeRequest();
    $response = $controller->index($request);
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('content');
    $property->setAccessible(true);
    $html = (string) $property->getValue($response);

    check('page renders html', str_contains($html, 'sv-grid'), substr($html, 0, 120));
    check('page contains both service cards', str_contains($html, 'data-sv-service="worldserver"') && str_contains($html, 'data-sv-service="authserver"'));
    check('page contains control buttons', str_contains($html, 'data-sv-action="restart"') && str_contains($html, 'data-sv-action="stop"'));
    check('page exposes the JS payload', str_contains($html, 'SUPERVISOR_DATA'));
    check('page shows the localised nav title', str_contains($html, Lang::get('app.supervisor.page_title')), Lang::get('app.supervisor.page_title'));
    check('page includes the log box content', str_contains($html, '[line 300] supervisor event'));

    echo "6) registration and base-path handling\n";
    check('navigation entry rendered', str_contains($html, '/supervisor') && str_contains($html, Lang::get('app.nav.supervisor')));
    check('module stylesheet linked', str_contains($html, 'css/modules/supervisor.css'));
    check('module script exposed to the panel loader', str_contains($html, 'js/modules/supervisor.js'));
    check('body tagged with the page module', str_contains($html, 'data-module="supervisor"'));

    if (preg_match('#data-global="SUPERVISOR_DATA">(.*?)</script>#s', $html, $match) === 1) {
        $payload = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $payloadOk = is_array($payload);
        check('SUPERVISOR_DATA is valid json', $payloadOk);
        if ($payloadOk) {
            // Panel.api adds the base path itself -> the payload must stay root-relative
            check('api urls are root-relative (no duplicated base path)', str_starts_with((string) $payload['statusUrl'], '/supervisor/') && !str_contains((string) $payload['statusUrl'], '//'), (string) $payload['statusUrl']);
            check('command url is root-relative', str_starts_with((string) $payload['commandUrl'], '/supervisor/'), (string) $payload['commandUrl']);
            check('payload exposes the control capability', array_key_exists('canControl', $payload));
            check('payload carries the poll interval', array_key_exists('pollSeconds', $payload));
        }
    } else {
        check('SUPERVISOR_DATA payload found', false);
    }

    echo "7) api answers\n";
    $statusResponse = $controller->apiStatus($request);
    $statusReflection = new ReflectionClass($statusResponse);
    $statusProperty = $statusReflection->getProperty('content');
    $statusProperty->setAccessible(true);
    $statusJson = json_decode((string) $statusProperty->getValue($statusResponse), true);
    check('status api returns success', ($statusJson['success'] ?? false) === true);
    check('status api returns services', count($statusJson['state']['services'] ?? []) === 2);

    $commandResponse = $controller->apiCommand($makeRequest('POST', ['action' => 'ping', 'target' => 'all']));
    $commandProperty = (new ReflectionClass($commandResponse))->getProperty('content');
    $commandProperty->setAccessible(true);
    $commandJson = json_decode((string) $commandProperty->getValue($commandResponse), true);
    check('command api dispatches through the manager', ($commandJson['success'] ?? false) === true, (string) ($commandJson['message'] ?? ''));

    $badResponse = $controller->apiCommand($makeRequest('POST', ['action' => 'nope', 'target' => 'all']));
    $badProperty = (new ReflectionClass($badResponse))->getProperty('content');
    $badProperty->setAccessible(true);
    $badJson = json_decode((string) $badProperty->getValue($badResponse), true);
    check('command api rejects unknown actions', ($badJson['success'] ?? true) === false);

    echo "\n";
    if ($failures === []) {
        echo "RESULT: supervisor verification ALL PASS ({$checks} checks)\n";
    } else {
        echo 'RESULT: ' . count($failures) . " FAILURE(S)\n";
        foreach ($failures as $failure) {
            echo "  - {$failure}\n";
        }
    }

    // cleanup
    foreach ([$statusFile, $controlFile, $logFile] as $file) {
        @unlink($file);
    }
    @rmdir($tmp . DIRECTORY_SEPARATOR . 'logs');
    @rmdir($tmp);

    exit($failures === [] ? 0 : 1);
}
