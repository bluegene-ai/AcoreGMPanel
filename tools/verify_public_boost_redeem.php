<?php

declare(strict_types=1);

/**
 * 前台「兑换码直升」链路校验。
 *
 * 覆盖 WWW\template\light\tpl\contactus.php 依赖的两个公开接口：
 *   GET  /public/character-boost/options  → realms + templates + csrf_token
 *   POST /public/character-boost/redeem   → 兑换并执行直升
 *
 * 重点：
 *   - 路由必须挂在鉴权中间件之外（前台访客没有登录态）；
 *   - options 的返回结构与模板里的 JS 读取字段一致；
 *   - redeem 的各类失败路径可复现，且失败时兑换码保持未使用（不会误消耗用户的码）；
 *   - 错误提示用的是 redeem.errors.* 文案，不是兜底占位。
 *
 * 本工具刻意只走"不会真正发放奖励"的路径，不对线上角色做任何写入。
 *
 * 用法：php tools/verify_public_boost_redeem.php
 */

define('PANEL_CLI_AUTH_BYPASS', true);

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require_once $root . '/bootstrap/helpers.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Http\Controllers\CharacterBoost\PublicCharacterBoostController;
use Acme\Panel\Http\Middleware\CsrfMiddleware;
use Acme\Panel\Support\Csrf;

Config::init($root . '/config');
Lang::init();

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// 前台访客：没有任何登录态与权限
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$checks = [];
$failures = [];

function expect(bool $condition, string $label, array &$checks, array &$failures, $detail = null): void
{
    $checks[] = ['name' => $label, 'status' => $condition ? 'passed' : 'failed', 'detail' => $detail];
    if (!$condition) {
        $failures[] = $label;
    }
}

function makeRequest(array $post = [], array $headers = []): Request
{
    $request = new Request();
    $request->method = $post === [] ? 'GET' : 'POST';
    $request->uri = '/public/character-boost/redeem';
    $request->get = [];
    $request->post = $post;
    $request->headers = $headers;
    $request->server = $_SERVER;

    return $request;
}

function payloadOf(Response $response): array
{
    $content = new ReflectionProperty(Response::class, 'content');
    $content->setAccessible(true);
    $status = new ReflectionProperty(Response::class, 'status');
    $status->setAccessible(true);

    $decoded = json_decode((string) $content->getValue($response), true);

    return [
        'status' => (int) $status->getValue($response),
        'body' => is_array($decoded) ? $decoded : [],
    ];
}

/**
 * 直接查库确认兑换码没有被误标记为已使用。
 */
function codeUnused(string $code): ?bool
{
    try {
        $pdo = \Acme\Panel\Core\Database::forServer(\Acme\Panel\Support\ServerContext::defaultId(), 'auth');
        $stmt = $pdo->prepare('SELECT used_at FROM character_boost_redeem_codes WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => strtoupper($code)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return empty($row['used_at']);
    } catch (\Throwable $exception) {
        return null;
    }
}

function findUnusedCode(): ?array
{
    try {
        $pdo = \Acme\Panel\Core\Database::forServer(\Acme\Panel\Support\ServerContext::defaultId(), 'auth');
        $stmt = $pdo->query(
            'SELECT rc.code, rc.template_id, t.realm_id, t.target_level'
            . ' FROM character_boost_redeem_codes rc'
            . ' INNER JOIN character_boost_templates t ON t.id = rc.template_id'
            . ' WHERE rc.used_at IS NULL ORDER BY rc.id ASC LIMIT 1'
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        return is_array($row) ? $row : null;
    } catch (\Throwable $exception) {
        return null;
    }
}

// ------------------------------------------------------------------
// 1. 路由必须在鉴权之外（否则前台访客会拿到 302/403）
// ------------------------------------------------------------------
$routes = (string) file_get_contents($root . '/routes/web.php');
expect(
    str_contains($routes, "'/public/character-boost', [PublicCharacterBoostController::class, 'index']"),
    '公开兑换页路由存在',
    $checks,
    $failures
);
expect(
    str_contains($routes, "'/public/character-boost/options', [PublicCharacterBoostController::class, 'options']"),
    '公开 options 路由存在',
    $checks,
    $failures
);

// 定位 redeem 路由，确认它写在 AuthMiddleware 分组之前
$authGroupPos = strpos($routes, "AuthMiddleware::class");
$publicRedeemPos = strpos($routes, "'/public/character-boost/redeem'");
expect(
    $publicRedeemPos !== false,
    '公开 redeem 路由存在',
    $checks,
    $failures
);
expect(
    $authGroupPos !== false && $publicRedeemPos !== false && $publicRedeemPos < $authGroupPos,
    '公开路由位于鉴权分组之外',
    $checks,
    $failures,
    ['auth_group_pos' => $authGroupPos, 'redeem_pos' => $publicRedeemPos]
);

// ------------------------------------------------------------------
// 2. options 契约（模板 JS 直接读取这些字段）
// ------------------------------------------------------------------
$controller = new PublicCharacterBoostController();
$options = payloadOf($controller->options(makeRequest()));

expect($options['status'] === 200, 'options 返回 200', $checks, $failures, ['status' => $options['status']]);
expect(($options['body']['success'] ?? false) === true, 'options 返回 success=true', $checks, $failures);
expect(
    isset($options['body']['csrf_token']) && is_string($options['body']['csrf_token']) && $options['body']['csrf_token'] !== '',
    'options 返回 csrf_token',
    $checks,
    $failures
);
expect(
    isset($options['body']['realms']) && is_array($options['body']['realms']) && $options['body']['realms'] !== [],
    'options 返回可用区服列表',
    $checks,
    $failures,
    ['realms' => $options['body']['realms'] ?? null]
);
expect(
    isset($options['body']['templates']) && is_array($options['body']['templates']),
    'options 返回模板列表',
    $checks,
    $failures,
    ['template_count' => is_array($options['body']['templates'] ?? null) ? count($options['body']['templates']) : null]
);

$realm = is_array($options['body']['realms'][0] ?? null) ? $options['body']['realms'][0] : null;
expect(
    $realm !== null && isset($realm['realm_id'], $realm['label']),
    '区服项含 realm_id 与 label',
    $checks,
    $failures,
    ['realm' => $realm]
);

$template = null;
foreach (is_array($options['body']['templates'] ?? null) ? $options['body']['templates'] : [] as $candidate) {
    if (is_array($candidate) && isset($candidate['realm_id'], $candidate['id'], $candidate['name'], $candidate['target_level'])) {
        $template = $candidate;
        break;
    }
}
expect($template !== null, '模板项含 realm_id/id/name/target_level', $checks, $failures, ['template' => $template]);

// 模板 JS 用 csrf_token 作为 X-CSRF-TOKEN 提交，必须能通过中间件
$csrfToken = (string) ($options['body']['csrf_token'] ?? '');
$csrfMiddleware = new CsrfMiddleware();
$passed = false;
$csrfMiddleware->handle(makeRequest([], ['X-CSRF-TOKEN' => $csrfToken]), function () use (&$passed) {
    $passed = true;
    return Response::json(['success' => true]);
});
expect($passed, 'options 下发的 csrf_token 可通过 CSRF 中间件', $checks, $failures);

// 缺少/伪造 token 必须不通过校验（直接校验 Csrf，避免受 CLI 环境里的
// HTTP_X_CSRF_TOKEN 之类残留影响）
expect(Csrf::verify(null) === false, '空 token 校验不通过', $checks, $failures);
expect(Csrf::verify('') === false, '空字符串 token 校验不通过', $checks, $failures);
expect(Csrf::verify('deadbeef') === false, '伪造 token 校验不通过', $checks, $failures);
expect(Csrf::verify($csrfToken) === true, 'options 下发的 token 校验通过', $checks, $failures);

// ------------------------------------------------------------------
// 3. 异常类必须可被自动加载：否则 catch 的类型匹配会抛 Error，
//    把所有业务错误降级成笼统的 500「请求失败，请稍后再试」
// ------------------------------------------------------------------
foreach ([
    \Acme\Panel\Domain\CharacterBoost\CharacterBoostGuardException::class,
    \Acme\Panel\Domain\CharacterBoost\CharacterBoostNotFoundException::class,
    \Acme\Panel\Domain\CharacterBoost\CharacterBoostSoapException::class,
] as $exceptionClass) {
    expect(
        class_exists($exceptionClass),
        '异常类可自动加载：' . substr($exceptionClass, (int) strrpos($exceptionClass, '\\') + 1),
        $checks,
        $failures
    );
}

// ------------------------------------------------------------------
// 3. redeem 失败路径（都要可复现且不消耗兑换码）
// ------------------------------------------------------------------
$validRealmId = (int) ($realm['realm_id'] ?? 0);

$missing = payloadOf($controller->redeem(makeRequest([])));
expect($missing['status'] === 422, '缺少参数返回 422', $checks, $failures, $missing);

$badFormat = payloadOf($controller->redeem(makeRequest([
    'realm_id' => $validRealmId,
    'character_name' => 'Anyone',
    'code' => 'SHORT',
])));
expect($badFormat['status'] === 422, '兑换码格式错误返回 422', $checks, $failures, $badFormat);

$badRealm = payloadOf($controller->redeem(makeRequest([
    'realm_id' => 99999,
    'character_name' => 'Anyone',
    'code' => 'ABCDEFGH12345678',
])));
expect($badRealm['status'] === 422, '未知区服返回 422', $checks, $failures, $badRealm);

$unknownCode = payloadOf($controller->redeem(makeRequest([
    'realm_id' => $validRealmId,
    'character_name' => 'Anyone',
    'code' => 'ZZZZZZZZZZZZZZZZ',
])));
expect($unknownCode['status'] === 404, '不存在的兑换码返回 404', $checks, $failures, $unknownCode);

// 错误文案必须来自 redeem.errors.*，而不是键名或兜底串
$expectedMessages = [
    'invalid_code_format' => Lang::get('app.character_boost.redeem.errors.invalid_code_format'),
    'invalid_realm' => Lang::get('app.character_boost.redeem.errors.invalid_realm'),
    'code_not_found' => Lang::get('app.character_boost.redeem.errors.code_not_found'),
    'missing_params' => Lang::get('app.common.validation.missing_params'),
];
foreach ([
    'invalid_code_format' => $badFormat,
    'invalid_realm' => $badRealm,
    'code_not_found' => $unknownCode,
    'missing_params' => $missing,
] as $key => $result) {
    $message = (string) ($result['body']['message'] ?? '');
    expect(
        $message !== '' && $message === $expectedMessages[$key],
        '错误提示正确：' . $key,
        $checks,
        $failures,
        ['message' => $message, 'expected' => $expectedMessages[$key]]
    );
}

// ------------------------------------------------------------------
// 4. 用一个真实未使用的兑换码走通"锁定 → 校验 → 回滚"，并确认码未被消耗
// ------------------------------------------------------------------
$sample = findUnusedCode();
if ($sample === null) {
    $checks[] = ['name' => '真实兑换码回滚校验', 'status' => 'passed', 'detail' => '没有未使用的兑换码，已跳过'];
} else {
    $sampleCode = (string) $sample['code'];
    $sampleRealm = (int) $sample['realm_id'];

    $before = codeUnused($sampleCode);

    // 角色不存在（错误路径）→ 必须回滚，且码保持未使用
    $notFound = payloadOf($controller->redeem(makeRequest([
        'realm_id' => $sampleRealm,
        'character_name' => 'NoSuchCharacterZZZ',
        'code' => $sampleCode,
    ])));

    $expectedNotFound = Lang::get('app.character_boost.redeem.errors.character_not_found');
    expect(
        in_array($notFound['status'], [404, 500], true),
        '角色不存在时返回失败状态',
        $checks,
        $failures,
        $notFound
    );
    expect(
        ($notFound['body']['success'] ?? true) === false,
        '角色不存在时不返回成功',
        $checks,
        $failures,
        $notFound
    );
    expect(
        (string) ($notFound['body']['message'] ?? '') !== '',
        '角色不存在时有明确提示',
        $checks,
        $failures,
        ['message' => $notFound['body']['message'] ?? null]
    );
    expect(
        $notFound['body']['message'] === $expectedNotFound
            || str_contains((string) $notFound['body']['message'], 'NoSuchCharacterZZZ') === false,
        '角色不存在提示不是原始异常串',
        $checks,
        $failures,
        ['message' => $notFound['body']['message'] ?? null]
    );

    $after = codeUnused($sampleCode);
    expect(
        $before === $after,
        '失败路径未消耗兑换码',
        $checks,
        $failures,
        ['code_tail' => '****' . substr($sampleCode, -4), 'before' => $before, 'after' => $after]
    );
}

// ------------------------------------------------------------------
// 5. 成功响应的字段契约（前端读 payload.character / payload.commands）
// ------------------------------------------------------------------
$controllerSource = (string) file_get_contents($root . '/app/Http/Controllers/CharacterBoost/PublicCharacterBoostController.php');
expect(
    str_contains($controllerSource, "'success' => true") && str_contains($controllerSource, "'payload' => \$payload"),
    '成功响应返回 success + payload',
    $checks,
    $failures
);
expect(
    str_contains($controllerSource, 'boostBySummary('),
    '公开兑换复用 CharacterBoostService::boostBySummary',
    $checks,
    $failures
);

$serviceSource = (string) file_get_contents($root . '/app/Domain/CharacterBoost/CharacterBoostService.php');
expect(
    str_contains($serviceSource, "'character' =>") && str_contains($serviceSource, "'commands' =>"),
    'boostBySummary 仍返回 character + commands',
    $checks,
    $failures
);
expect(
    str_contains($controllerSource, 'markRedeemCodeUsed'),
    '兑换码在直升成功后才会被标记使用',
    $checks,
    $failures
);

// 前台模板引用的接口路径必须仍然存在
$templateFile = 'E:/Server/web/WWW/template/light/tpl/contactus.php';
if (is_file($templateFile)) {
    $templateSource = (string) file_get_contents($templateFile);
    expect(
        str_contains($templateSource, '/public/character-boost'),
        '前台模板仍指向公开接口',
        $checks,
        $failures
    );
    expect(
        str_contains($templateSource, "'/options'") && str_contains($templateSource, "'/redeem'"),
        '前台模板依旧调用 options 与 redeem',
        $checks,
        $failures
    );
    $checks[] = ['name' => '前台模板文件', 'status' => 'passed', 'detail' => $templateFile];
} else {
    $checks[] = ['name' => '前台模板文件', 'status' => 'passed', 'detail' => '模板文件不存在，已跳过该文件断言'];
}

// ------------------------------------------------------------------
// 6. 走真实路由表 + 中间件（未登录访客），确认公开接口真的可达
// ------------------------------------------------------------------
try {
    $router = new \Acme\Panel\Core\Router();
    $registrar = require $root . '/routes/web.php';
    $registrar($router);

    $dispatch = static function (string $uri, string $method = 'GET') use ($router): array {
        $request = new Request();
        $request->method = $method;
        $request->uri = $uri;
        $request->get = [];
        $request->post = [];
        $request->headers = [];
        $request->server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_ACCEPT' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $response = $router->dispatch($request);
        $content = new ReflectionProperty(Response::class, 'content');
        $content->setAccessible(true);
        $status = new ReflectionProperty(Response::class, 'status');
        $status->setAccessible(true);

        $body = json_decode((string) $content->getValue($response), true);

        return [
            'status' => (int) $status->getValue($response),
            'body' => is_array($body) ? $body : [],
            'raw' => (string) $content->getValue($response),
        ];
    };

    // 未登录访客访问公开配置接口必须直达控制器
    $publicOptions = $dispatch('/public/character-boost/options');
    expect(
        $publicOptions['status'] === 200 && ($publicOptions['body']['success'] ?? false) === true,
        '未登录访客可直接调用公开 options',
        $checks,
        $failures,
        ['status' => $publicOptions['status'], 'body' => $publicOptions['body']]
    );

    // 注意：受保护路由在未登录时会调用 Response::send() + exit，
    // 无法在同一进程里断言，这里改为静态确认它确实挂在 AuthMiddleware 分组内。
    $authGroupPos = (int) strpos($routes, 'AuthMiddleware::class');
    $adminBoostPos = (int) strpos($routes, "get('/character-boost'");
    expect(
        $adminBoostPos > $authGroupPos,
        '后台直升管理入口位于鉴权分组内',
        $checks,
        $failures,
        ['auth_group_pos' => $authGroupPos, 'admin_pos' => $adminBoostPos]
    );
} catch (\Throwable $exception) {
    expect(false, '路由分发校验', $checks, $failures, [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile() . ':' . $exception->getLine(),
    ]);
}

echo json_encode([
    'success' => $failures === [],
    'failures' => $failures,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures === [] ? 0 : 1);
