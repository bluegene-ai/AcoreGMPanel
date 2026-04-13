<?php
/**
 * File: app/Core/Bootstrap.php
 * Purpose: Defines class Bootstrap for the app/Core module.
 * Classes:
 *   - Bootstrap
 * Functions:
 *   - atomicWrite()
 *   - run()
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

class Bootstrap
{
    private static function atomicWrite(string $file, string $content): bool
    {
        $tmp = $file . '.tmp';

        if (file_put_contents($tmp, $content) === false) {
            return false;
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private static function pushWarnFlash(string $message): void
    {
        $existing = $_SESSION['flashes']['warn'] ?? [];
        if (is_array($existing) && in_array($message, $existing, true)) {
            return;
        }

        $_SESSION['flashes']['warn'][] = $message;
    }

    private static function persistBasePathOverride(string $generatedConfigDir, string $basePath): bool
    {
        $normalizedBasePath = trim($basePath);
        if ($normalizedBasePath === '') {
            return false;
        }

        if (!is_dir($generatedConfigDir) && !@mkdir($generatedConfigDir, 0775, true) && !is_dir($generatedConfigDir)) {
            return false;
        }

        $cfgFile = $generatedConfigDir . '/app.php';
        $appConfig = Config::get('app', []);
        if (!is_array($appConfig)) {
            $appConfig = [];
        }

        $appConfig['base_path'] = $normalizedBasePath;
        $content = "<?php\nreturn " . var_export($appConfig, true) . ";\n";

        $writable = (is_dir($generatedConfigDir) && is_writable($generatedConfigDir))
            || (is_file($cfgFile) && is_writable($cfgFile));

        if (!$writable || !self::atomicWrite($cfgFile, $content)) {
            return false;
        }

        Config::set('app.base_path', $normalizedBasePath);
        $_SESSION['__auto_base_written'] = 1;

        return true;
    }

    public static function run(): void
    {
        if (ob_get_level() === 0) {
            ob_start();
        }

        Config::init(__DIR__ . '/../../config');
        ErrorHandler::register();
        self::ensureSessionStarted();

        Lang::init();

        $requestedLocale = isset($_GET['lang']) ? (string) $_GET['lang'] : null;
        if ($requestedLocale !== null && $requestedLocale !== '') {
            Lang::setLocale($requestedLocale);
            $_SESSION['panel_locale'] = Lang::locale();
        } elseif (!empty($_SESSION['panel_locale'])) {
            Lang::setLocale((string) $_SESSION['panel_locale']);
        }
        Config::set('app.locale_active', Lang::locale());

        if (!ini_get('date.timezone')) {
            date_default_timezone_set(Config::get('app.timezone', 'UTC'));
        }

        $base = rtrim(Config::get('app.base_path', ''), '/');
        $detectedPrefix = null;
        $projectSlug = basename(dirname(__DIR__, 2));
        $escapedSlug = $projectSlug && $projectSlug !== '.' ? preg_quote($projectSlug, '#') : null;

        $uriForDetect = null;
        if (isset($_SERVER['REQUEST_URI'])) {
            $uriForDetect = parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        }

        if ($uriForDetect && $uriForDetect !== '/') {
            if ($escapedSlug && preg_match('#^(.*?/' . $escapedSlug . ')/public(?:/index\.php)?(?:/|$)#i', $uriForDetect, $matches)) {
                $detectedPrefix = rtrim($matches[1], '/');
            } elseif (preg_match('#^(.*?)/public(?:/index\.php)?(?:/|$)#i', $uriForDetect, $matches)) {
                $detectedPrefix = rtrim($matches[1], '/');
            }

            if ($detectedPrefix === null && $escapedSlug && preg_match('#^(.*/' . $escapedSlug . ')(?:/|$)#i', $uriForDetect, $matches)) {
                $detectedPrefix = rtrim($matches[1], '/');
            }
        }

        if ($detectedPrefix === null && isset($_SERVER['SCRIPT_NAME'])) {
            $scriptName = str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']);

            if ($escapedSlug && preg_match('#^(.*?/' . $escapedSlug . ')/public/index\.php$#', $scriptName, $matches)) {
                $detectedPrefix = rtrim($matches[1], '/');
            } else {
                $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

                if ($dir === '/' || $dir === '.') {
                    $dir = '';
                } elseif (preg_match('#/public$#', $dir)) {
                    $dir = rtrim(substr($dir, 0, -7), '/');
                }

                if ($dir !== '') {
                    $detectedPrefix = $dir;
                }
            }
        }

        $configDir = __DIR__ . '/../../config';
        $generatedConfigDir = $configDir . '/generated';

        if ($base === '') {
            if ($detectedPrefix) {
                $base = $detectedPrefix;
                Config::set('app.base_path', $base);

                if (empty($_SESSION['__auto_base_written']) && self::persistBasePathOverride($generatedConfigDir, $base)) {
                    unset($_SESSION['__auto_base_path']);
                } else {
                    $shownBase = (string) ($_SESSION['__auto_base_path_notice'] ?? '');
                    if ($shownBase !== $base) {
                        self::pushWarnFlash(Lang::get('app.alerts.bootstrap.auto_detect_base_path', [
                            'base' => $base,
                        ]));
                        $_SESSION['__auto_base_path_notice'] = $base;
                    }
                    $_SESSION['__auto_base_path'] = $base;
                }
            }
        } elseif ($detectedPrefix && $detectedPrefix !== $base) {
            self::pushWarnFlash(Lang::get('app.alerts.bootstrap.base_path_mismatch', [
                'detected' => $detectedPrefix,
                'configured' => $base,
            ]));
        }

        $reqUriRaw = $_SERVER['REQUEST_URI'] ?? '/';
        $reqPath = strtok($reqUriRaw, '?');
        $rawQuery = parse_url($reqUriRaw, PHP_URL_QUERY);

        if ($base !== '') {
            $normalizedBase = $base . '/';
            $redirectTarget = null;

            if (
                $reqPath === $base . '/public'
                || $reqPath === $base . '/public/'
                || $reqPath === $base . '/public/index.php'
            ) {
                $redirectTarget = $normalizedBase;
            } elseif ($reqPath === $base . '/index.php') {
                $redirectTarget = $normalizedBase;
            } elseif (
                $reqPath === $base . '/pubilc'
                || $reqPath === $base . '/pubilc/'
                || $reqPath === $base . '/pubilc/index.php'
            ) {
                $redirectTarget = $normalizedBase;
            } elseif (str_contains($base, '/')) {
                $parts = explode('/', trim($base, '/'));
                $last = array_pop($parts);

                if ($last && $reqPath === '/' . $last) {
                    $redirectTarget = $normalizedBase;
                }
            }

            if ($redirectTarget && !headers_sent()) {
                if (empty($_SESSION['__normalized_flash'])) {
                    self::pushWarnFlash(Lang::get('app.alerts.bootstrap.normalized_path', [
                        'target' => $normalizedBase,
                    ]));
                    $_SESSION['__normalized_flash'] = 1;
                }

                if ($rawQuery) {
                    $redirectTarget .= '?' . $rawQuery;
                }

                header('Location: ' . $redirectTarget, true, 302);

                return;
            }
        }

        if ($base !== '' && str_starts_with($reqPath, $base . '/')) {
            $newPath = substr($reqPath, strlen($base));

            if ($newPath === '') {
                $newPath = '/';
            }

            $_SERVER['REQUEST_URI'] = $newPath . ($rawQuery ? ('?' . $rawQuery) : '');
        } elseif ($base !== '' && $reqPath === $base) {
            $_SERVER['REQUEST_URI'] = '/' . ($rawQuery ? ('?' . $rawQuery) : '');
        } elseif ($base !== '' && !str_starts_with($reqPath, $base)) {
        }

        if (isset($_GET['__diag']) && ($_GET['__diag'] === '1' || $_GET['__diag'] === 'base')) {
            header('Content-Type: text/plain; charset=utf-8');

            echo 'base_path=' . $base . "\n";
            echo 'orig_path=' . $reqPath . "\n";
            echo 'internal_REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
            echo 'raw_query=' . ($rawQuery ?? '') . "\n";
            echo 'php_self=' . ($_SERVER['PHP_SELF'] ?? '') . "\n";
            echo 'script_name=' . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
            echo 'cwd=' . getcwd() . "\n";

            return;
        }

        $installLock = $generatedConfigDir . '/install.lock';

        $installed = is_file($installLock) && Config::get('auth.admin.username');
        $logicalReq = $_SERVER['REQUEST_URI'] ?? '/';
        $pathOnly = strtok($logicalReq, '?');

        if (!$installed) {
            $setupPathInternal = '/setup';

            if ($pathOnly !== $setupPathInternal && !str_starts_with($pathOnly, $setupPathInternal . '/')) {
                header('Location: ' . Url::to($setupPathInternal));

                return;
            }
        } else {
            try {
                $autoBaseNotWritten = empty($_SESSION['__auto_base_written']);
                $autoBase = $_SESSION['__auto_base_path'] ?? '';
                $baseUnsetInConfig = Config::get('app.base_path', '') === '';

                if ($autoBase && $autoBaseNotWritten && $baseUnsetInConfig) {
                    if (self::persistBasePathOverride($generatedConfigDir, $autoBase)) {
                        self::pushWarnFlash(Lang::get('app.alerts.bootstrap.auto_write_base_path', [
                            'base' => $autoBase,
                        ]));
                        unset($_SESSION['__auto_base_path']);
                    }
                }
            } catch (\Throwable $e) {

            }
        }

        // Global server switch via query param (used by the server switch dropdown).
        // Only allow switching for authenticated panel sessions.
        if (isset($_GET['server']) && $_GET['server'] !== '') {
            $rawServer = $_GET['server'];

            // Accept non-negative integer server ids (including 0).
            if (is_string($rawServer) && preg_match('/^\d+$/', $rawServer)) {
                $sid = (int) $rawServer;

                try {
                    if (\Acme\Panel\Support\Auth::check() && \Acme\Panel\Support\ServerList::valid($sid)) {
                        if (\Acme\Panel\Support\ServerContext::currentId() !== $sid) {
                            \Acme\Panel\Support\ServerContext::set($sid);
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore server switch failures to avoid breaking requests.
                }
            }
        }

        $helpers = dirname(__DIR__, 2) . '/bootstrap/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        $request = Request::capture();
        $response = Router::loadAndDispatch($request);
        $response->send();

        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
}

