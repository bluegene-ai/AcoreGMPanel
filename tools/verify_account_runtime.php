<?php

declare(strict_types=1);

define('PANEL_CLI_AUTH_BYPASS', true);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Account\AccountRepository;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Http\Controllers\AccountController;
use Acme\Panel\Support\TransientCache;

Config::init(dirname(__DIR__) . '/config');
Lang::init();

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/account/api/runtime-check';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

final class FakeAccountRepository extends AccountRepository
{
    public array $calls = [];

    public function __construct()
    {
    }

    public function createAccount(string $username, string $password, string $email = ''): int
    {
        $this->calls[] = ['method' => 'createAccount', 'args' => [$username, $password, $email]];
        return 321;
    }

    public function setGmLevel(int $id, int $gm, int $realm = -1): bool
    {
        $this->calls[] = ['method' => 'setGmLevel', 'args' => [$id, $gm, $realm]];
        return true;
    }

    public function updateUsername(int $id, string $username, string $password): array
    {
        $this->calls[] = ['method' => 'updateUsername', 'args' => [$id, $username, $password]];
        return ['success' => true, 'id' => $id, 'username' => $username];
    }

    public function ban(int $id, string $reason, int $hours = 0): bool
    {
        $this->calls[] = ['method' => 'ban', 'args' => [$id, $reason, $hours]];
        return true;
    }

    public function unban(int $id): int
    {
        $this->calls[] = ['method' => 'unban', 'args' => [$id]];
        return 1;
    }

    public function accountCharactersPayload(int $id): array
    {
        $this->calls[] = ['method' => 'accountCharactersPayload', 'args' => [$id]];
        return [
            'items' => [
                ['guid' => 7, 'name' => 'Verifier', 'online' => 1],
            ],
            'ban' => ['active' => false],
        ];
    }

    public function accountsByLastIp(string $ip, int $excludeId = 0, int $limit = 50): array
    {
        $this->calls[] = ['method' => 'accountsByLastIp', 'args' => [$ip, $excludeId, $limit]];
        return [
            ['id' => 11, 'username' => 'linked-one'],
            ['id' => 12, 'username' => 'linked-two'],
        ];
    }
}

function makeRequest(array $post = [], string $method = 'POST'): Request
{
    $request = new Request();
    $request->method = $method;
    $request->uri = '/account/api/runtime-check';
    $request->get = [];
    $request->post = $post;
    $request->server = $_SERVER;

    return $request;
}

function setRefProperty(string $className, object $object, string $property, $value): void
{
    $ref = new ReflectionProperty($className, $property);
    $ref->setAccessible(true);
    $ref->setValue($object, $value);
}

function invokeRefMethod(string $className, object $object, string $method, array $args = [])
{
    $ref = new ReflectionMethod($className, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($object, $args);
}

function responsePayload(Response $response): array
{
    $content = new ReflectionProperty(Response::class, 'content');
    $content->setAccessible(true);
    $status = new ReflectionProperty(Response::class, 'status');
    $status->setAccessible(true);

    return [
        'status' => $status->getValue($response),
        'body' => json_decode((string) $content->getValue($response), true),
    ];
}

function assertTrue(bool $condition, string $label): void
{
    if (!$condition)
        throw new RuntimeException('Assertion failed: ' . $label);
}

$controller = new AccountController();
$repo = new FakeAccountRepository();
setRefProperty(AccountController::class, $controller, 'repo', $repo);

$checks = [];

$create = responsePayload($controller->apiCreate(makeRequest([
    'username' => 'VerifierUser',
    'password' => 'StrongPass123',
    'password_confirm' => 'StrongPass123',
    'email' => 'verify@example.com',
    'gmlevel' => '0',
])));
assertTrue(($create['body']['success'] ?? false) === true, 'create account succeeds');
assertTrue(($repo->calls[0]['method'] ?? '') === 'createAccount', 'create uses repository after hydration');
$checks[] = ['name' => 'account.create', 'status' => 'passed', 'detail' => $create['body']];

$renameBefore = count($repo->calls);
$renameInvalid = responsePayload($controller->apiUpdateUsername(makeRequest([
    'id' => '321',
    'username' => '',
    'password' => 'short',
])));
assertTrue(($renameInvalid['status'] ?? 0) === 422, 'rename invalid payload rejected');
assertTrue(count($repo->calls) === $renameBefore, 'rename invalid payload stopped before repository');
$renameValid = responsePayload($controller->apiUpdateUsername(makeRequest([
    'id' => '321',
    'username' => 'VerifierRenamed',
    'password' => 'StrongPass123',
])));
assertTrue(($renameValid['body']['success'] ?? false) === true, 'rename succeeds');
$checks[] = ['name' => 'account.rename', 'status' => 'passed', 'detail' => $renameValid['body']];

$ban = responsePayload($controller->apiBan(makeRequest([
    'id' => '321',
    'hours' => '12',
    'reason' => 'runtime-check',
])));
assertTrue(($ban['body']['success'] ?? false) === true, 'ban succeeds');
$unban = responsePayload($controller->apiUnban(makeRequest(['id' => '321'])));
assertTrue(($unban['body']['updated'] ?? 0) === 1, 'unban succeeds');
$checks[] = ['name' => 'account.ban_unban', 'status' => 'passed', 'detail' => ['ban' => $ban['body'], 'unban' => $unban['body']]];

$characters = responsePayload($controller->apiCharacters(makeRequest(['id' => '321'])));
assertTrue(count($characters['body']['items'] ?? []) === 1, 'character payload available');
$checks[] = ['name' => 'account.characters', 'status' => 'passed', 'detail' => $characters['body']];

$ipAccounts = responsePayload($controller->apiAccountsByIp(makeRequest(['ip' => '8.8.8.8', 'exclude' => '0', 'limit' => '10'])));
assertTrue(count($ipAccounts['body']['items'] ?? []) === 2, 'ip account lookup available');
$checks[] = ['name' => 'account.ip_accounts', 'status' => 'passed', 'detail' => $ipAccounts['body']];

$ipLocation = responsePayload($controller->apiIpLocation(makeRequest(['ip' => '127.0.0.1'])));
assertTrue(($ipLocation['body']['success'] ?? false) === true, 'ip location lookup works for private ip');
$checks[] = ['name' => 'account.ip_location', 'status' => 'passed', 'detail' => $ipLocation['body']];

$cacheRepo = new FakeAccountRepository();
setRefProperty(MultiServerRepository::class, $cacheRepo, 'serverId', 0);
setRefProperty(AccountRepository::class, $cacheRepo, 'requestBanStatusCache', [42 => ['active' => true]]);
setRefProperty(AccountRepository::class, $cacheRepo, 'requestCharactersCache', [42 => [['guid' => 1]]]);
setRefProperty(AccountRepository::class, $cacheRepo, 'requestAccountsByIpCache', ['8.8.8.8:0:50' => [['id' => 42]]]);

TransientCache::set('account_bans', 'server_0_account_42_ban', ['active' => true], 30);
TransientCache::set('account_characters', 'server_0_account_42_characters', [['guid' => 1]], 30);
TransientCache::set('account_ip_relations_server_0', '8.8.8.8:0:50', [['id' => 42]], 30);

invokeRefMethod(AccountRepository::class, $cacheRepo, 'invalidateAccountReadCaches', [42, true, true]);

assertTrue(TransientCache::get('account_bans', 'server_0_account_42_ban') === null, 'ban cache invalidated');
assertTrue(TransientCache::get('account_characters', 'server_0_account_42_characters') === null, 'character cache invalidated');
assertTrue(TransientCache::get('account_ip_relations_server_0', '8.8.8.8:0:50') === null, 'ip cache invalidated');
$checks[] = ['name' => 'account.cache_invalidation', 'status' => 'passed', 'detail' => ['account_id' => 42]];

echo json_encode([
    'success' => true,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;