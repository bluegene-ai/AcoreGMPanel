<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Setup;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Support\Csrf;
use PDO;
use SoapClient;
use SoapFault;
use SoapParam;

class SetupController
{
    private const STEP_ENV = 1;
    private const STEP_MODE = 2;
    private const STEP_TEST = 3;
    private const STEP_ADMIN = 4;
    private const STEP_FINISH = 5;

    public function index(Request $req): Response
    {
        $installed = is_file($this->generatedInstallLockPath()) && Config::get('auth.admin.username');
        if ($installed) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['flash'] = ['info' => Lang::get('app.setup.flash.already_installed')];

            return Response::redirect('/account/login');
        }

        $step = (int) ($req->get['step'] ?? 1);
        if ($step < 1 || $step > self::STEP_FINISH) {
            $step = 1;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['setup'] = $_SESSION['setup'] ?? [];

        return match ($step) {
            self::STEP_ENV => $this->stepEnv(),
            self::STEP_MODE => $this->stepMode(),
            self::STEP_TEST => $this->stepTest(),
            self::STEP_ADMIN => $this->stepAdmin(),
            self::STEP_FINISH => $this->stepFinish(),
            default => $this->stepEnv(),
        };
    }

    private function stepEnv(): Response
    {
        $checks = [
            'php_version' => [
                'ok' => version_compare(PHP_VERSION, '8.0', '>='),
                'current' => PHP_VERSION,
                'require' => '>=8.0',
            ],
            'pdo_mysql' => ['ok' => extension_loaded('pdo_mysql')],
            'soap' => ['ok' => extension_loaded('soap')],
            'mbstring' => ['ok' => extension_loaded('mbstring')],
        ];

        $cfgDir = $this->generatedConfigDir();
        $cfgLabel = $this->generatedConfigRelativePath();
        $writable = false;
        $wMsg = '';
        if (is_dir($cfgDir)) {
            $testFile = $cfgDir . '/.perm_test_' . bin2hex(random_bytes(3));
            $bytes = @file_put_contents($testFile, 'test');
            if ($bytes !== false) {
                $writable = true;
                @unlink($testFile);
            } else {
                $wMsg = Lang::get('app.setup.env.messages.write_failed');
            }
        } else {
            if (@mkdir($cfgDir, 0775, true) || is_dir($cfgDir)) {
                $writable = true;
                $wMsg = Lang::get('app.setup.env.messages.created');
            } else {
                $wMsg = Lang::get('app.setup.env.messages.create_failed');
            }
        }

        $checks['config_writable'] = [
            'ok' => $writable,
            'require' => Lang::get('app.setup.env.requirements.writable') . ' (' . $cfgLabel . ')',
            'msg' => $wMsg ? $wMsg . ' [' . $cfgLabel . ']' : '',
        ];

        $allOk = array_reduce($checks, static fn ($carry, $item) => $carry && $item['ok'], true);

        $locales = Lang::available();
        if (empty($locales)) {
            $locales = [Lang::locale()];
        }

        return $this->view('setup/env', [
            'checks' => $checks,
            'allOk' => (bool) $allOk,
            'locales' => $locales,
            'currentLocale' => Lang::locale(),
        ]);
    }

    private function stepMode(): Response
    {
        $state = &$_SESSION['setup'];

        if (!empty($state['mode']) && $state['mode'] !== 'single' && !empty($state['auth']['database'])) {
            $needSync = false;
            if (empty($state['realms'])) {
                $needSync = true;
            } else {
                $first = $state['realms'][0] ?? [];
                if (empty($first['realm_id']) || empty($first['name'])) {
                    $needSync = true;
                }
            }
            if ($needSync) {
                $this->syncRealmNames();
            }
        }

        return $this->view('setup/mode', ['state' => $state]);
    }

    private function stepTest(): Response
    {
        $this->syncRealmNames();
        $state = $_SESSION['setup'] ?? [];
        if (empty($state['mode'])) {
            return Response::redirect('/setup?step=2');
        }

        $results = [];
        $allOk = true;

        if (isset($state['auth'])) {
            [$ok, $msg] = $this->testPdo($state['auth']);
            $results[] = ['name' => 'Auth DB', 'ok' => $ok, 'msg' => $msg];
            $allOk = $allOk && $ok;
        }

        $mode = $state['mode'] ?? 'single';
        if ($mode === 'single') {
            foreach (['characters' => 'Characters DB', 'world' => 'World DB'] as $k => $label) {
                if (isset($state[$k])) {
                    [$ok, $msg] = $this->testPdo($state[$k]);
                    $results[] = ['name' => $label, 'ok' => $ok, 'msg' => $msg];
                    $allOk = $allOk && $ok;
                }
            }
        } else {
            if (!empty($state['realms']) && is_array($state['realms'])) {
                foreach ($state['realms'] as $idx => $realm) {
                    foreach (['characters' => 'Characters', 'world' => 'World'] as $rk => $rLabel) {
                        if (!empty($realm[$rk])) {
                            [$ok, $msg] = $this->testPdo($realm[$rk]);
                            $results[] = [
                                'name' => 'Realm#' . ($idx + 1) . ' ' . $rLabel,
                                'ok' => $ok,
                                'msg' => $msg,
                            ];
                            $allOk = $allOk && $ok;
                        }
                    }
                    if (!empty($realm['soap'])) {
                        [$ok, $msg] = $this->testSoap($realm['soap']);
                        $results[] = ['name' => 'Realm#' . ($idx + 1) . ' SOAP', 'ok' => $ok, 'msg' => $msg];
                        $allOk = $allOk && $ok;
                    }
                }
            }
        }

        $hasRealmSoap = !empty(array_filter($state['realms'] ?? [], static fn ($r) => !empty($r['soap'])));
        if (!$hasRealmSoap && isset($state['soap'])) {
            [$ok, $msg] = $this->testSoap($state['soap']);
            $results[] = ['name' => 'Global SOAP', 'ok' => $ok, 'msg' => $msg];
            $allOk = $allOk && $ok;
        }

        return $this->view('setup/test', ['results' => $results, 'allOk' => $allOk]);
    }

    private function stepAdmin(): Response
    {
        return $this->view('setup/admin', ['admin' => $_SESSION['setup']['admin'] ?? []]);
    }

    private function stepFinish(): Response
    {
        $this->syncRealmNames();
        $state = $_SESSION['setup'] ?? [];
        if (empty($state['admin']['username']) || empty($state['admin']['password_hash'])) {
            return Response::redirect('/setup?step=4');
        }

        $cfgDir = $this->generatedConfigDir();
        $cfgLabel = $this->generatedConfigRelativePath();
        $errors = [];

        if (!is_dir($cfgDir)) {
            if (!@mkdir($cfgDir, 0775, true) && !is_dir($cfgDir)) {
                $errors[] = Lang::get('app.setup.finish.errors.create_config_dir', ['path' => $cfgLabel]);
            }
        }

        $atomicWrite = function (string $file, string $content) use (&$errors, $cfgDir): void {
            if (!empty($errors)) {
                return;
            }
            $target = $cfgDir . '/' . $file;
            $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
            $bytes = @file_put_contents($tmp, $content, LOCK_EX);
            if ($bytes === false) {
                $errors[] = Lang::get('app.setup.finish.errors.write_failed', ['file' => $file]);
                @unlink($tmp);
                return;
            }

            if (!@rename($tmp, $target)) {
                $bytes2 = @file_put_contents($target, $content, LOCK_EX);
                if ($bytes2 === false) {
                    $errors[] = Lang::get('app.setup.finish.errors.write_failed', ['file' => $file]);
                }
                @unlink($tmp);
            }
        };

        $dbArr = [
            'default' => 'auth',
            'connections' => [
                'auth' => $this->dbExport($state['auth'] ?? []),
            ],
        ];

        $mode = $state['mode'] ?? 'single';
        if ($mode === 'single') {
            foreach (['world', 'characters'] as $r) {
                if (isset($state[$r])) {
                    $dbArr['connections'][$r] = $this->dbExport($state[$r]);
                }
            }
        }

        $written = [];
        $record = static function (string $file) use (&$written): void {
            $written[] = $file;
        };

        $atomicWrite('database.php', "<?php\nreturn " . var_export($dbArr, true) . ";\n");
        if (empty($errors)) {
            $record('database.php');
        }

        if ($mode !== 'single') {
            $servers = [];
            foreach (($state['realms'] ?? []) as $idx => $realm) {
                $servers[$idx] = [
                    'realm_id' => $realm['realm_id'] ?? ($idx + 1),
                    'name' => $realm['name'] ?? Lang::get('app.server.default_option', ['id' => $idx + 1]),
                    'port' => $realm['port'] ?? 0,
                    'auth' => $this->dbExport($realm['auth'] ?? $state['auth']),
                    'characters' => $this->dbExport($realm['characters'] ?? []),
                    'world' => $this->dbExport($realm['world'] ?? []),
                ];
                if (!empty($realm['soap'])) {
                    $servers[$idx]['soap'] = $realm['soap'];
                }
            }
            $atomicWrite('servers.php', "<?php\nreturn " . var_export([
                'servers' => $servers,
                'default' => 0,
            ], true) . ";\n");
            if (empty($errors)) {
                $record('servers.php');
            }
        }

        $shouldWriteSoap = isset($state['soap'])
            || ($mode !== 'single' && $this->hasRealmSoapConfigs($state['realms'] ?? []));
        if ($shouldWriteSoap) {
            $soapConfig = $this->buildSoapConfig($state, $mode);
            $atomicWrite('soap.php', "<?php\nreturn " . var_export($soapConfig, true) . ";\n");
            if (empty($errors)) {
                $record('soap.php');
            }
        }

        $atomicWrite('auth.php', "<?php\nreturn " . var_export(['admin' => $state['admin']], true) . ";\n");
        if (empty($errors)) {
            $record('auth.php');
        }

        $appFile = $cfgDir . '/app.php';
        if (!is_file($appFile)) {
            $basePath = rtrim($_SERVER['BASE_PATH'] ?? ($_SERVER['APP_BASE_PATH'] ?? ''), '/');
            $appArr = [
                'debug' => false,
                'base_path' => $basePath ? '/' . $basePath : (defined('APP_BASE_PATH') ? APP_BASE_PATH : ''),
            ];
            $atomicWrite('app.php', "<?php\nreturn " . var_export($appArr, true) . ";\n");
            if (empty($errors)) {
                $record('app.php');
            }
        }

        $atomicWrite('install.lock', date('c'));
        if (empty($errors)) {
            $record('install.lock');
        }

        if (!empty($errors)) {
            foreach ($written as $f) {
                @unlink($cfgDir . '/' . $f);
            }

            return $this->view('setup/finish', ['success' => false, 'errors' => $errors]);
        }

        unset($_SESSION['setup']);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['flash'] = ['info' => Lang::get('app.setup.flash.install_success_debug')];

        return Response::redirect('/account/login');
    }

    public function post(Request $req): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $action = $req->post['action'] ?? '';
        if (!Csrf::verify($req->post['_csrf'] ?? null)) {
            return Response::json(['success' => false, 'message' => 'CSRF invalid'], 419);
        }

        return match ($action) {
            'mode_save' => $this->handleModeSave($req),
            'lang_save' => $this->handleLangSave($req),
            'admin_save' => $this->handleAdminSave($req),
            default => Response::json(['success' => false, 'message' => 'Unknown action']),
        };
    }

    private function handleModeSave(Request $req): Response
    {
        $mode = $req->post['mode'] ?? 'single';
        $_SESSION['setup']['mode'] = $mode;

        $_SESSION['setup']['auth'] = $this->readDbFromPost($req, 'auth_');
        $authDefaults = $_SESSION['setup']['auth'];

        $previousShared = $_SESSION['setup']['shared'] ?? [];
        $sharedDbModeInput = (string) ($req->post['shared_db_mode'] ?? ($previousShared['db']['mode'] ?? 'shared'));
        $sharedDbMode = $sharedDbModeInput === 'custom' ? 'custom' : 'shared';

        $sharedDbCharacters = [
            'port' => (int) ($req->post['shared_char_port'] ?? ($previousShared['db']['characters']['port'] ?? ($authDefaults['port'] ?? 3306))),
            'username' => trim((string) ($req->post['shared_char_user'] ?? ($previousShared['db']['characters']['username'] ?? ($authDefaults['username'] ?? '')))),
            'password' => (string) ($req->post['shared_char_pass'] ?? ($previousShared['db']['characters']['password'] ?? ($authDefaults['password'] ?? ''))),
        ];
        $sharedDbWorld = [
            'port' => (int) ($req->post['shared_world_port'] ?? ($previousShared['db']['world']['port'] ?? $sharedDbCharacters['port'])),
            'username' => trim((string) ($req->post['shared_world_user'] ?? ($previousShared['db']['world']['username'] ?? $sharedDbCharacters['username']))),
            'password' => (string) ($req->post['shared_world_pass'] ?? ($previousShared['db']['world']['password'] ?? $sharedDbCharacters['password'])),
        ];

        $sharedSoapModeInput = (string) ($req->post['shared_soap_mode'] ?? ($previousShared['soap']['mode'] ?? 'shared'));
        $sharedSoapMode = $sharedSoapModeInput === 'custom' ? 'custom' : 'shared';

        $globalSoap = [
            'host' => trim((string) ($req->post['soap_host'] ?? ($previousShared['soap']['host'] ?? '127.0.0.1'))),
            'port' => (int) ($req->post['soap_port'] ?? ($previousShared['soap']['port'] ?? 7878)),
            'username' => trim((string) ($req->post['soap_user'] ?? ($previousShared['soap']['username'] ?? 'soap_user'))),
            'password' => (string) ($req->post['soap_pass'] ?? ($previousShared['soap']['password'] ?? 'soap_pass')),
            'uri' => trim((string) ($req->post['soap_uri'] ?? ($previousShared['soap']['uri'] ?? 'urn:AC'))),
        ];

        $_SESSION['setup']['shared'] = [
            'db' => [
                'mode' => $sharedDbMode,
                'characters' => $sharedDbCharacters,
                'world' => $sharedDbWorld,
            ],
            'soap' => [
                'mode' => $sharedSoapMode,
                'host' => $globalSoap['host'],
                'port' => $globalSoap['port'],
                'username' => $globalSoap['username'],
                'password' => $globalSoap['password'],
                'uri' => $globalSoap['uri'],
            ],
        ];

        if ($mode === 'single') {
            $_SESSION['setup']['characters'] = $this->readDbFromPost($req, 'char_', 'auth');
            $_SESSION['setup']['world'] = $this->readDbFromPost($req, 'world_', 'auth');
        } else {
            $realms = $req->post['realms'] ?? [];
            $norm = [];

            foreach ($realms as $r) {
                $realm = [];

                if ($mode === 'multi-full') {
                    $realmAuth = $this->normalizeDbArray($r['auth'] ?? []);
                    if ($realmAuth['database'] !== '') {
                        if ($realmAuth['username'] === '') {
                            $realmAuth['username'] = $authDefaults['username'];
                        }
                        if ($realmAuth['password'] === '') {
                            $realmAuth['password'] = $authDefaults['password'];
                        }
                        $realm['auth'] = $realmAuth;
                    }
                }

                $charactersCfg = $r['characters'] ?? [];
                if ($mode === 'multi' && $sharedDbMode !== 'custom') {
                    $charactersCfg['port'] = $sharedDbCharacters['port'];
                    $charactersCfg['username'] = $sharedDbCharacters['username'];
                    $charactersCfg['password'] = $sharedDbCharacters['password'];
                }
                $realm['characters'] = $this->inheritIfEmpty(
                    $this->normalizeDbArray($charactersCfg),
                    $authDefaults
                );

                $worldCfg = $r['world'] ?? [];
                if ($mode === 'multi' && $sharedDbMode !== 'custom') {
                    $worldCfg['port'] = $sharedDbWorld['port'];
                    $worldCfg['username'] = $sharedDbWorld['username'];
                    $worldCfg['password'] = $sharedDbWorld['password'];
                }
                $realm['world'] = $this->inheritIfEmpty(
                    $this->normalizeDbArray($worldCfg),
                    $authDefaults
                );

                $soapCfg = $r['soap'] ?? [];
                if ($mode === 'multi' && $sharedSoapMode !== 'custom') {
                    $soapCfg['host'] = $globalSoap['host'];
                    $soapCfg['port'] = $globalSoap['port'];
                    $soapCfg['username'] = $globalSoap['username'];
                    $soapCfg['password'] = $globalSoap['password'];
                    $soapCfg['uri'] = $globalSoap['uri'];
                }

                if (!empty(array_filter($soapCfg, static fn ($v) => $v !== '' && $v !== null))) {
                    $realm['soap'] = [
                        'host' => trim((string) ($soapCfg['host'] ?? '')),
                        'port' => (int) ($soapCfg['port'] ?? $globalSoap['port']),
                        'username' => trim((string) ($soapCfg['username'] ?? '')),
                        'password' => (string) ($soapCfg['password'] ?? ''),
                        'uri' => trim((string) ($soapCfg['uri'] ?? 'urn:AC')),
                    ];
                }

                $norm[] = $realm;
            }

            $_SESSION['setup']['realms'] = $norm;
        }

        $_SESSION['setup']['soap'] = $globalSoap;

        return Response::json([
            'success' => true,
            'redirect' => \Acme\Panel\Core\Url::to('/setup?step=3'),
        ]);
    }

    private function handleAdminSave(Request $req): Response
    {
        $user = trim($req->post['admin_user'] ?? '');
        $pass = $req->post['admin_pass'] ?? '';
        $pass2 = $req->post['admin_pass2'] ?? '';

        if ($user === '') {
            return Response::json([
                'success' => false,
                'message' => Lang::get('app.setup.admin.errors.username_required'),
            ]);
        }
        if ($pass === '') {
            return Response::json([
                'success' => false,
                'message' => Lang::get('app.setup.admin.errors.password_required'),
            ]);
        }
        if ($pass !== $pass2) {
            return Response::json([
                'success' => false,
                'message' => Lang::get('app.setup.admin.errors.password_mismatch'),
            ]);
        }

        $_SESSION['setup']['admin'] = [
            'username' => $user,
            'password_hash' => password_hash($pass, PASSWORD_BCRYPT),
        ];

        return Response::json([
            'success' => true,
            'redirect' => \Acme\Panel\Core\Url::to('/setup?step=5'),
        ]);
    }

    private function handleLangSave(Request $req): Response
    {
        $requested = (string) ($req->post['locale'] ?? '');
        $available = Lang::available();
        if ($requested === '') {
            $requested = Lang::locale();
        }
        if (!in_array($requested, $available, true)) {
            return Response::json([
                'success' => false,
                'message' => Lang::get('app.setup.env.invalid_locale', [], 'Invalid locale selection'),
            ], 422);
        }

        Lang::setLocale($requested);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['panel_locale'] = Lang::locale();

        return Response::json([
            'success' => true,
            'redirect' => \Acme\Panel\Core\Url::to('/setup?step=2'),
        ]);
    }

    private function readDbFromPost(Request $req, string $prefix, string $inheritFrom = ''): array
    {
        $arr = [
            'host' => $req->post[$prefix . 'host'] ?? '127.0.0.1',
            'port' => (int) ($req->post[$prefix . 'port'] ?? 3306),
            'database' => $req->post[$prefix . 'db'] ?? ($req->post[$prefix . 'database'] ?? ''),
            'username' => $req->post[$prefix . 'user'] ?? ($req->post[$prefix . 'username'] ?? ''),
            'password' => $req->post[$prefix . 'pass'] ?? ($req->post[$prefix . 'password'] ?? ''),
            'charset' => 'utf8mb4',
        ];

        $base = $this->normalizeDbArray($arr);
        if ($inheritFrom === 'auth' && isset($_SESSION['setup']['auth'])) {
            $auth = $_SESSION['setup']['auth'];
            if ($base['username'] === '') {
                $base['username'] = $auth['username'];
            }
            if ($base['password'] === '') {
                $base['password'] = $auth['password'];
            }
        }

        return $base;
    }

    private function normalizeDbArray(array $a): array
    {
        return [
            'host' => trim($a['host'] ?? '127.0.0.1'),
            'port' => (int) ($a['port'] ?? 3306),
            'database' => trim($a['database'] ?? ($a['db'] ?? '')),
            'username' => trim($a['username'] ?? ($a['user'] ?? '')),
            'password' => $a['password'] ?? ($a['pass'] ?? ''),
            'charset' => trim($a['charset'] ?? 'utf8mb4'),
        ];
    }

    private function inheritIfEmpty(array $child, array $parent): array
    {
        if ($child['username'] === '') {
            $child['username'] = $parent['username'];
        }
        if ($child['password'] === '') {
            $child['password'] = $parent['password'];
        }
        if (empty($child['port']) && !empty($parent['port'])) {
            $child['port'] = $parent['port'];
        }

        return $child;
    }

    private function testPdo(array $cfg): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            unset($pdo);

            return [true, 'OK'];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    private function testSoap(array $cfg): array
    {
        $options = [
            'location' => 'http://' . $cfg['host'] . ':' . $cfg['port'] . '/',
            'uri' => $cfg['uri'],
            'login' => $cfg['username'],
            'password' => $cfg['password'],
            'connection_timeout' => 5,
            'exceptions' => true,
        ];

        try {
            $client = new SoapClient(null, $options);
        } catch (SoapFault $e) {
            return [false, $this->sanitizeErrorMessage('SOAP', $e->getMessage())];
        } catch (\Exception $e) {
            return [false, $this->sanitizeErrorMessage('SOAP', $e->getMessage())];
        }

        $warning = null;
        $previousHandler = set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            $warning = $message;

            return true;
        });

        try {
            $client->executeCommand(new SoapParam('.server info', 'command'));
        } catch (SoapFault $e) {
            return [false, $this->sanitizeErrorMessage('SOAP', $e->getMessage())];
        } catch (\Exception $e) {
            return [false, $this->sanitizeErrorMessage('SOAP', $e->getMessage())];
        } finally {
            if ($previousHandler !== null) {
                set_error_handler($previousHandler);
            } else {
                restore_error_handler();
            }
        }

        if ($warning !== null) {
            return [false, $this->sanitizeErrorMessage('SOAP', $warning)];
        }

        return [true, 'OK'];
    }

    private function sanitizeErrorMessage(string $prefix, string $msg): string
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 4));
        $m = str_replace(['\\', "\r"], ['/', ''], $msg);

        if ($root && str_contains($m, $root)) {
            $m = str_replace($root, '[root]', $m);
        }

        $m = preg_replace('#[A-Z]:/[^\s:]+#i', '[path]', $m);
        $m = preg_replace('#/(?:[\w.-]+/){2,}[\w.-]+#', '[path]', $m);
        $m = preg_replace('/\s+/', ' ', trim($m));

        if (strlen($m) > 160) {
            $m = substr($m, 0, 157) . '...';
        }

        return $prefix . ' Error: ' . $m;
    }

    private function dbExport(array $cfg): array
    {
        return [
            'host' => $cfg['host'] ?? '',
            'port' => $cfg['port'] ?? 3306,
            'database' => $cfg['database'] ?? '',
            'username' => $cfg['username'] ?? '',
            'password' => $cfg['password'] ?? '',
            'charset' => $cfg['charset'] ?? 'utf8mb4',
        ];
    }

    private function buildSoapConfig(array $state, string $mode): array
    {
        $global = $this->normalizeSoapConfig($state['soap'] ?? []);
        $config = $global;

        if ($mode !== 'single') {
            $realms = [];
            foreach (($state['realms'] ?? []) as $idx => $realm) {
                $soap = $realm['soap'] ?? null;
                if (!is_array($soap) || $soap === []) {
                    continue;
                }

                $entry = $this->normalizeSoapConfig($soap);
                $entry['realm_id'] = $realm['realm_id'] ?? ($idx + 1);
                $entry['server_index'] = $idx;
                if (!empty($realm['name'])) {
                    $entry['name'] = $realm['name'];
                }
                $realms[$idx] = $entry;
            }

            if ($realms) {
                $config['realms'] = $realms;
            }
        }

        return $config;
    }

    private function normalizeSoapConfig(array $cfg): array
    {
        $hasHost = array_key_exists('host', $cfg);
        $hasPort = array_key_exists('port', $cfg);
        $hasUser = array_key_exists('username', $cfg);
        $hasPass = array_key_exists('password', $cfg);
        $hasUri = array_key_exists('uri', $cfg);

        return [
            'host' => $hasHost ? trim((string) $cfg['host']) : '127.0.0.1',
            'port' => $hasPort ? (int) $cfg['port'] : 7878,
            'username' => $hasUser ? trim((string) $cfg['username']) : '',
            'password' => $hasPass ? (string) $cfg['password'] : '',
            'uri' => $hasUri ? trim((string) $cfg['uri']) : 'urn:AC',
        ];
    }

    private function hasRealmSoapConfigs(array $realms): bool
    {
        foreach ($realms as $realm) {
            if (!empty($realm['soap']) && is_array($realm['soap'])) {
                return true;
            }
        }

        return false;
    }

    private function configBaseDir(): string
    {
        return dirname(__DIR__, 4) . '/config';
    }

    private function generatedConfigDir(): string
    {
        return $this->configBaseDir() . '/generated';
    }

    private function generatedConfigRelativePath(): string
    {
        return 'config/generated';
    }

    private function generatedInstallLockPath(): string
    {
        return $this->generatedConfigDir() . '/install.lock';
    }

    private function view(string $template, array $vars = []): Response
    {
        if (!isset($vars['currentLocale'])) {
            $vars['currentLocale'] = Lang::locale();
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include dirname(__DIR__, 4) . '/resources/views/' . $template . '.php';
        $html = ob_get_clean();

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function syncRealmNames(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $state = &$_SESSION['setup'];
        if (empty($state['mode']) || $state['mode'] === 'single') {
            return;
        }
        if (empty($state['auth']['database'])) {
            return;
        }

        try {
            $auth = $state['auth'];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $auth['host'],
                $auth['port'],
                $auth['database'],
                $auth['charset']
            );
            $pdo = new PDO($dsn, $auth['username'], $auth['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $rows = $pdo->query('SELECT id,name,port FROM realmlist ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return;
            }

            if (empty($state['realms']) || !is_array($state['realms'])) {
                $state['realms'] = [];
            }
            foreach ($rows as $idx => $row) {
                if (!isset($state['realms'][$idx])) {
                    $state['realms'][$idx] = [];
                }
                $state['realms'][$idx]['name'] = $row['name'];
                $state['realms'][$idx]['realm_id'] = (int) $row['id'];
                $state['realms'][$idx]['port'] = (int) $row['port'];
            }
        } catch (\Throwable $e) {
            // ignore sync failures during setup
        }
    }

    public function apiRealms(Request $req): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $source = $req->method === 'POST' ? $req->post : $req->get;

        if (empty($_SESSION['setup']['mode']) && !empty($source['mode']) && $source['mode'] !== 'single') {
            $_SESSION['setup']['mode'] = $source['mode'];
        }

        $authKeys = ['host', 'port', 'db', 'database', 'user', 'username', 'pass', 'password'];
        $hasTemp = false;
        foreach ($authKeys as $k) {
            if (isset($source['auth_' . $k])) {
                $hasTemp = true;
                break;
            }
        }
        if ($hasTemp) {
            $_SESSION['setup']['auth'] = [
                'host' => $source['auth_host'] ?? '127.0.0.1',
                'port' => (int) ($source['auth_port'] ?? 3306),
                'database' => $source['auth_db'] ?? ($source['auth_database'] ?? ''),
                'username' => $source['auth_user'] ?? ($source['auth_username'] ?? ''),
                'password' => $source['auth_pass'] ?? ($source['auth_password'] ?? ''),
                'charset' => 'utf8mb4',
            ];
        }

        try {
            $auth = $_SESSION['setup']['auth'] ?? [];
            if (empty($auth['database'])) {
                return Response::json([
                    'success' => false,
                    'message' => Lang::get('app.setup.api.realms.missing_auth_db'),
                ]);
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $auth['host'],
                $auth['port'],
                $auth['database'],
                $auth['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $auth['username'] ?? '', $auth['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $rows = $pdo->query('SELECT id,name,port FROM realmlist ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }

            if (empty($_SESSION['setup']['realms']) || !is_array($_SESSION['setup']['realms'])) {
                $_SESSION['setup']['realms'] = [];
            }
            foreach ($rows as $idx => $row) {
                if (!isset($_SESSION['setup']['realms'][$idx])) {
                    $_SESSION['setup']['realms'][$idx] = [];
                }
                $_SESSION['setup']['realms'][$idx]['name'] = $row['name'];
                $_SESSION['setup']['realms'][$idx]['realm_id'] = (int) $row['id'];
                $_SESSION['setup']['realms'][$idx]['port'] = (int) $row['port'];
            }

            return Response::json([
                'success' => true,
                'realms' => $_SESSION['setup']['realms'],
            ]);
        } catch (\Throwable $e) {
            $error = $this->sanitizeErrorMessage('DB', $e->getMessage());

            return Response::json([
                'success' => false,
                'message' => Lang::get('app.setup.api.realms.connection_failed', ['error' => $error]),
            ]);
        }
    }
}
