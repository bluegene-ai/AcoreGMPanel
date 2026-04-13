<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Setup;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Support\Csrf;
use Acme\Panel\Support\SetupPageData;
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

        return $this->stepView(self::STEP_ENV, 'setup.env', [
            'checks' => $checks,
            'allOk' => (bool) $allOk,
            'locales' => $locales,
            'currentLocale' => Lang::locale(),
        ]);
    }

    private function stepMode(): Response
    {
        $state = &$_SESSION['setup'];
        $state['mode'] = $this->normalizeMode((string) ($state['mode'] ?? 'single'));

        if ($this->isSharedAuthRealmMode($state['mode']) && !empty($state['auth']['database'])) {
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

        return $this->stepView(self::STEP_MODE, 'setup.mode', ['state' => $state]);
    }

    private function stepTest(): Response
    {
        $state = $_SESSION['setup'] ?? [];
        $mode = $this->normalizeMode((string) ($state['mode'] ?? 'single'));
        if ($this->isSharedAuthRealmMode($mode)) {
            $this->syncRealmNames();
            $state = $_SESSION['setup'] ?? [];
        }

        if (empty($state['mode'])) {
            return Response::redirect('/setup?step=2');
        }

        $authResult = null;
        $realmGroups = [];
        $allOk = true;

        if ($this->isSharedAuthRealmMode($mode)) {
            if (isset($state['auth'])) {
                [$ok, $msg] = $this->testPdo($state['auth']);
                $authResult = [
                    'name' => 'Auth DB',
                    'label' => $this->databaseResultLabel('Auth DB', $state['auth']),
                    'ok' => $ok,
                    'msg' => $msg,
                ];
                $allOk = $allOk && $ok;
            }

            foreach (($state['realms'] ?? []) as $idx => $realm) {
                $labelPrefix = $this->realmResultLabel($realm, $idx, 'Realm');
                $groupResults = [];
                $soapWarning = null;
                $groupOk = true;

                foreach (['characters' => 'Characters', 'world' => 'World'] as $rk => $rLabel) {
                    if (!empty($realm[$rk])) {
                        [$ok, $msg] = $this->testPdo($realm[$rk]);
                        $groupResults[] = [
                            'name' => $labelPrefix . ' ' . $rLabel,
                            'label' => $this->databaseResultLabel($rLabel, $realm[$rk]),
                            'ok' => $ok,
                            'msg' => $msg,
                            'required' => true,
                        ];
                        $groupOk = $groupOk && $ok;
                        $allOk = $allOk && $ok;
                    }
                }
                if (!empty($realm['soap'])) {
                    [$ok, $msg] = $this->testSoap($realm['soap']);
                    $groupResults[] = [
                        'name' => $labelPrefix . ' SOAP',
                        'label' => 'SOAP',
                        'ok' => $ok,
                        'msg' => $msg,
                        'required' => false,
                    ];
                    if (!$ok)
                        $soapWarning = Lang::get('app.setup.test.soap_warning');
                }

                $realmGroups[] = [
                    'label' => $labelPrefix,
                    'ok' => $groupOk,
                    'soap_warning' => $soapWarning,
                    'results' => $groupResults,
                ];
            }
        } else {
            $serverGroups = $this->sessionServerGroups($state);
            foreach ($serverGroups as $idx => $server) {
                $labelPrefix = $this->realmResultLabel($server, $idx, 'Server');
                $groupResults = [];
                $soapWarning = null;
                $groupOk = true;

                foreach (['auth' => 'Auth', 'characters' => 'Characters', 'world' => 'World'] as $key => $label) {
                    if (!empty($server[$key])) {
                        [$ok, $msg] = $this->testPdo($server[$key]);
                        $groupResults[] = [
                            'name' => $labelPrefix . ' ' . $label . ' DB',
                            'label' => $this->databaseResultLabel($label . ' DB', $server[$key]),
                            'ok' => $ok,
                            'msg' => $msg,
                            'required' => true,
                        ];
                        $groupOk = $groupOk && $ok;
                        $allOk = $allOk && $ok;
                    }
                }

                if (!empty($server['soap'])) {
                    [$ok, $msg] = $this->testSoap($server['soap']);
                    $groupResults[] = [
                        'name' => $labelPrefix . ' SOAP',
                        'label' => 'SOAP',
                        'ok' => $ok,
                        'msg' => $msg,
                        'required' => false,
                    ];
                    if (!$ok)
                        $soapWarning = Lang::get('app.setup.test.soap_warning');
                }

                $realmGroups[] = [
                    'label' => $labelPrefix,
                    'ok' => $groupOk,
                    'soap_warning' => $soapWarning,
                    'results' => $groupResults,
                ];
            }
        }

        return $this->stepView(self::STEP_TEST, 'setup.test', [
            'authResult' => $authResult,
            'realmGroups' => $realmGroups,
            'allOk' => $allOk,
        ]);
    }

    private function stepAdmin(): Response
    {
        return $this->stepView(self::STEP_ADMIN, 'setup.admin', ['admin' => $_SESSION['setup']['admin'] ?? []]);
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

        $mode = $this->normalizeMode((string) ($state['mode'] ?? 'single'));
        $serverGroups = $this->isSharedAuthRealmMode($mode) ? [] : $this->sessionServerGroups($state);
        $primaryServer = $serverGroups[0] ?? null;
        $primaryAuth = $primaryServer['auth'] ?? ($state['auth'] ?? []);

        $dbArr = [
            'default' => 'auth',
            'connections' => [
                'auth' => $this->dbExport($primaryAuth),
            ],
        ];

        if ($mode === 'single' && count($serverGroups) <= 1) {
            foreach (['world', 'characters'] as $r) {
                $source = $primaryServer[$r] ?? ($state[$r] ?? null);
                if (is_array($source)) {
                    $dbArr['connections'][$r] = $this->dbExport($source);
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

        if ($this->isSharedAuthRealmMode($mode) || $mode === 'multi-full' || count($serverGroups) > 1) {
            $servers = [];
            $serverSource = $this->isSharedAuthRealmMode($mode)
                ? $this->filterConfiguredRealmEntries($state['realms'] ?? [])
                : $serverGroups;
            foreach ($serverSource as $idx => $realm) {
                $servers[$idx] = [
                    'realm_id' => $realm['realm_id'] ?? ($idx + 1),
                    'name' => $realm['name'] ?? Lang::get('app.server.default_option', ['id' => $idx + 1]),
                    'port' => $realm['port'] ?? 0,
                    'auth' => $this->dbExport($realm['auth'] ?? $primaryAuth),
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

        $soapRealms = $this->isSharedAuthRealmMode($mode)
            ? $this->filterConfiguredRealmEntries($state['realms'] ?? [])
            : $serverGroups;
        $effectiveSoapMode = ($mode === 'single' && count($serverGroups) <= 1) ? 'single' : $mode;
        $soapState = $state;
        if ($this->isSharedAuthRealmMode($mode)) {
            $soapState['realms'] = $soapRealms;
        } else {
            $soapState['realms'] = $serverGroups;
            if ($primaryServer && !empty($primaryServer['soap'])) {
                $soapState['soap'] = $primaryServer['soap'];
            }
        }

        $shouldWriteSoap = isset($soapState['soap'])
            || ($effectiveSoapMode !== 'single' && $this->hasRealmSoapConfigs($soapRealms));
        if ($shouldWriteSoap) {
            $soapConfig = $this->buildSoapConfig($soapState, $effectiveSoapMode);
            $atomicWrite('soap.php', "<?php\nreturn " . var_export($soapConfig, true) . ";\n");
            if (empty($errors)) {
                $record('soap.php');
            }
        }

        $admin = $state['admin'];
        if (empty($admin['capabilities']) || !is_array($admin['capabilities'])) {
            $admin['capabilities'] = ['*'];
        }
        $atomicWrite('auth.php', "<?php\nreturn " . var_export(['admin' => $admin], true) . ";\n");
        if (empty($errors)) {
            $record('auth.php');
        }

        $appFile = $cfgDir . '/app.php';
        $appArr = [];
        if (is_file($appFile)) {
            $loadedApp = require $appFile;
            if (is_array($loadedApp)) {
                $appArr = $loadedApp;
            }
        }

        $basePath = rtrim($_SERVER['BASE_PATH'] ?? ($_SERVER['APP_BASE_PATH'] ?? ''), '/');
        $resolvedBasePath = $basePath ? '/' . ltrim($basePath, '/') : (defined('APP_BASE_PATH') ? APP_BASE_PATH : '');
        $existingBasePath = trim((string) ($appArr['base_path'] ?? ''));

        $appArr['debug'] = (bool) ($appArr['debug'] ?? false);
        $appArr['base_path'] = $existingBasePath !== '' ? $existingBasePath : $resolvedBasePath;

        $atomicWrite('app.php', "<?php\nreturn " . var_export($appArr, true) . ";\n");
        if (empty($errors)) {
            $record('app.php');
        }

        $atomicWrite('install.lock', date('c'));
        if (empty($errors)) {
            $record('install.lock');
        }

        if (!empty($errors)) {
            foreach ($written as $f) {
                @unlink($cfgDir . '/' . $f);
            }

            return $this->stepView(self::STEP_FINISH, 'setup.finish', ['success' => false, 'errors' => $errors]);
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
        $mode = $this->normalizeMode((string) ($req->post['mode'] ?? 'single'));
        $_SESSION['setup']['mode'] = $mode;

        if ($this->isSharedAuthRealmMode($mode)) {
            $_SESSION['setup']['auth'] = $this->readDbFromPost($req, 'auth_');
            $authDefaults = $_SESSION['setup']['auth'];
            $realms = is_array($req->post['realms'] ?? null) ? $req->post['realms'] : [];
            $normalized = [];

            foreach ($realms as $idx => $realmInput) {
                $normalized[] = [
                    'name' => trim((string) ($realmInput['name'] ?? '')),
                    'realm_id' => (int) ($realmInput['realm_id'] ?? ($idx + 1)),
                    'port' => (int) ($realmInput['port'] ?? 0),
                    'characters' => $this->inheritIfEmpty($this->normalizeDbArray($realmInput['characters'] ?? []), $authDefaults),
                    'world' => $this->inheritIfEmpty($this->normalizeDbArray($realmInput['world'] ?? []), $authDefaults),
                    'soap' => $this->normalizeSoapConfig($realmInput['soap'] ?? []),
                ];
            }

            $_SESSION['setup']['realms'] = $normalized;
            $_SESSION['setup']['selected_realm_ids'] = array_values(array_filter(array_map(
                static fn (array $realm): int => (int) ($realm['realm_id'] ?? 0),
                $normalized
            )));
            if (!empty($normalized[0]['soap'])) {
                $_SESSION['setup']['soap'] = $normalized[0]['soap'];
            }
            unset($_SESSION['setup']['shared'], $_SESSION['setup']['characters'], $_SESSION['setup']['world']);
        } else {
            $groups = is_array($req->post['realms'] ?? null) ? $req->post['realms'] : [];
            $normalized = [];

            foreach ($groups as $idx => $groupInput) {
                $normalized[] = [
                    'name' => trim((string) ($groupInput['name'] ?? '')),
                    'realm_id' => (int) ($groupInput['realm_id'] ?? ($idx + 1)),
                    'port' => (int) ($groupInput['port'] ?? 0),
                    'auth' => $this->normalizeDbArray($groupInput['auth'] ?? []),
                    'characters' => $this->normalizeDbArray($groupInput['characters'] ?? []),
                    'world' => $this->normalizeDbArray($groupInput['world'] ?? []),
                    'soap' => $this->normalizeSoapConfig($groupInput['soap'] ?? []),
                ];
            }

            $_SESSION['setup']['realms'] = $normalized;
            $primary = $normalized[0] ?? null;
            if ($primary !== null) {
                $_SESSION['setup']['auth'] = $primary['auth'];
                $_SESSION['setup']['characters'] = $primary['characters'];
                $_SESSION['setup']['world'] = $primary['world'];
                $_SESSION['setup']['soap'] = $primary['soap'];
            }
            unset($_SESSION['setup']['selected_realm_ids']);
            unset($_SESSION['setup']['shared']);
        }

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
            'capabilities' => ['*'],
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

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, ['single', 'multi', 'multi-full'], true) ? $mode : 'single';
    }

    private function isSharedAuthRealmMode(string $mode): bool
    {
        return $mode === 'multi';
    }

    private function sessionServerGroups(array $state): array
    {
        if (!empty($state['realms']) && is_array($state['realms'])) {
            return array_values($state['realms']);
        }

        if (empty($state['auth']) && empty($state['characters']) && empty($state['world'])) {
            return [];
        }

        return [[
            'name' => '',
            'realm_id' => 1,
            'port' => 0,
            'auth' => $state['auth'] ?? $this->normalizeDbArray([]),
            'characters' => $state['characters'] ?? $this->normalizeDbArray([]),
            'world' => $state['world'] ?? $this->normalizeDbArray([]),
            'soap' => $this->normalizeSoapConfig($state['soap'] ?? []),
        ]];
    }

    private function realmResultLabel(array $realm, int $idx, string $fallbackPrefix): string
    {
        $name = trim((string) ($realm['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $realmId = (int) ($realm['realm_id'] ?? 0);
        if ($realmId > 0) {
            return $fallbackPrefix . '#' . $realmId;
        }

        return $fallbackPrefix . '#' . ($idx + 1);
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

    private function stepView(int $step, string $view, array $vars = []): Response
    {
        if (!isset($vars['currentLocale'])) {
            $vars['currentLocale'] = Lang::locale();
        }

        $vars['__layout'] = 'setup';
        $vars['__setupStep'] = $step;
        $vars['setupPage'] = SetupPageData::stepData($step);
        $vars['meta'] = array_replace($this->setupStepMeta($step), is_array($vars['meta'] ?? null) ? $vars['meta'] : []);

        return Response::view($view, $vars);
    }

    private function setupStepMeta(int $step): array
    {
        $pageTitle = Lang::get('app.setup.layout.page_title');
        $stepTitle = Lang::get('app.setup.layout.step_titles.' . $step, [], 'Step ' . $step);

        return [
            'title' => $stepTitle . ' - ' . $pageTitle,
            'breadcrumbs' => [
                ['label' => $pageTitle],
                ['label' => $stepTitle],
            ],
        ];
    }

    private function syncRealmNames(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $state = &$_SESSION['setup'];
        $mode = $this->normalizeMode((string) ($state['mode'] ?? 'single'));
        if (!$this->isSharedAuthRealmMode($mode)) {
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
            $rows = $pdo->query('SELECT * FROM realmlist ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return;
            }

            $selectedRealmIds = array_values(array_filter(array_map(
                static fn ($realmId): int => (int) $realmId,
                is_array($state['selected_realm_ids'] ?? null) ? $state['selected_realm_ids'] : []
            )));
            if ($selectedRealmIds !== []) {
                $rows = array_values(array_filter($rows, static function (array $row) use ($selectedRealmIds): bool {
                    return in_array((int) ($row['id'] ?? 0), $selectedRealmIds, true);
                }));
            }

            $state['realms'] = $this->buildSharedRealmEntries($rows, $auth, $state['realms'] ?? []);
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

        if (!empty($source['mode'])) {
            $_SESSION['setup']['mode'] = $this->normalizeMode((string) $source['mode']);
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
                ], 422);
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
            $rows = $pdo->query('SELECT * FROM realmlist ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }

            $_SESSION['setup']['realms'] = $this->buildSharedRealmEntries($rows, $auth, $_SESSION['setup']['realms'] ?? []);
            $_SESSION['setup']['selected_realm_ids'] = array_values(array_filter(array_map(
                static fn (array $realm): int => (int) ($realm['realm_id'] ?? 0),
                $_SESSION['setup']['realms']
            )));

            $message = empty($_SESSION['setup']['realms'])
                ? Lang::get('app.setup.mode.messages.verify_empty')
                : Lang::get('app.setup.mode.messages.verify_success', ['count' => count($_SESSION['setup']['realms'])]);

            return Response::json([
                'success' => true,
                'message' => $message,
                'realms' => $_SESSION['setup']['realms'],
            ]);
        } catch (\Throwable $e) {
            $error = $this->sanitizeErrorMessage('DB', $e->getMessage());

            return Response::json([
                'success' => false,
                'message' => Lang::get('app.setup.api.realms.connection_failed', ['error' => $error]),
            ], 422);
        }
    }

    private function buildSharedRealmEntries(array $rows, array $auth, array $existingRealms = []): array
    {
        $entries = [];
        foreach ($rows as $idx => $row) {
            $existing = $this->findExistingRealmEntry($existingRealms, $row, $idx);
            $realmId = (int) ($row['id'] ?? ($existing['realm_id'] ?? ($idx + 1)));
            $name = trim((string) ($row['name'] ?? ($existing['name'] ?? 'Realm ' . ($idx + 1))));
            $port = (int) ($row['port'] ?? ($existing['port'] ?? 0));
            $entries[] = [
                'name' => $name,
                'realm_id' => $realmId,
                'port' => $port,
                'characters' => $this->prefillRealmDatabaseConfig('characters', $row, $auth, $existing['characters'] ?? [], $name, $realmId),
                'world' => $this->prefillRealmDatabaseConfig('world', $row, $auth, $existing['world'] ?? [], $name, $realmId),
                'soap' => $this->prefillRealmSoapConfig($row, $existing['soap'] ?? [], $auth, $idx),
            ];
        }

        return $entries;
    }

    private function findExistingRealmEntry(array $existingRealms, array $row, int $idx): array
    {
        $realmId = (int) ($row['id'] ?? 0);
        foreach ($existingRealms as $existing) {
            if ($realmId > 0 && (int) ($existing['realm_id'] ?? 0) === $realmId) {
                return is_array($existing) ? $existing : [];
            }
        }

        return isset($existingRealms[$idx]) && is_array($existingRealms[$idx])
            ? $existingRealms[$idx]
            : [];
    }

    private function prefillRealmDatabaseConfig(
        string $type,
        array $row,
        array $auth,
        array $existing,
        string $name,
        int $realmId
    ): array {
        $host = $row[$type . '_host'] ?? ($existing['host'] ?? ($auth['host'] ?? '127.0.0.1'));
        $port = $row[$type . '_port'] ?? ($existing['port'] ?? ($auth['port'] ?? 3306));
        $database = $row[$type . '_database']
            ?? $row[$type . '_db']
            ?? ($existing['database'] ?? '');
        $username = $row[$type . '_username']
            ?? $row[$type . '_user']
            ?? ($existing['username'] ?? ($auth['username'] ?? ''));
        $password = $row[$type . '_password']
            ?? $row[$type . '_pass']
            ?? ($existing['password'] ?? ($auth['password'] ?? ''));

        return $this->normalizeDbArray([
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ]);
    }

    private function prefillRealmSoapConfig(array $row, array $existing, array $auth, int $idx): array
    {
        return $this->normalizeSoapConfig([
            'host' => $row['soap_host'] ?? ($existing['host'] ?? ($auth['host'] ?? '127.0.0.1')),
            'port' => $row['soap_port'] ?? ($existing['port'] ?? (7878 + $idx)),
            'username' => $row['soap_username'] ?? ($existing['username'] ?? ''),
            'password' => $row['soap_password'] ?? ($existing['password'] ?? ''),
            'uri' => $row['soap_uri'] ?? ($existing['uri'] ?? 'urn:AC'),
        ]);
    }

    private function filterConfiguredRealmEntries(array $realms): array
    {
        $configured = [];

        foreach ($realms as $realm) {
            if (!$this->isConfiguredRealmEntry($realm)) {
                continue;
            }

            $configured[] = $realm;
        }

        return $configured;
    }

    private function isConfiguredRealmEntry(array $realm): bool
    {
        $charactersDb = trim((string) (($realm['characters']['database'] ?? '')));
        $worldDb = trim((string) (($realm['world']['database'] ?? '')));

        return $charactersDb !== '' && $worldDb !== '';
    }

    private function databaseResultLabel(string $label, array $cfg): string
    {
        $database = trim((string) ($cfg['database'] ?? ''));
        if ($database === '')
            return $label;

        return $label . ' [' . $database . ']';
    }
}
