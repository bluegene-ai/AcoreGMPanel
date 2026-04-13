<?php

declare(strict_types=1);

namespace Acme\Panel\Support {
    class SoapExecutor
    {
        public static array $calls = [];
        public static array $queuedResults = [];

        public function execute(string $command, array $opts = []): array
        {
            self::$calls[] = ['command' => $command, 'opts' => $opts];

            if (!self::$queuedResults) {
                return [
                    'success' => false,
                    'code' => 'missing_stub_result',
                    'message' => 'No stub result queued',
                ];
            }

            return array_shift(self::$queuedResults);
        }
    }
}

namespace {
    define('PANEL_CLI_AUTH_BYPASS', true);

    require dirname(__DIR__) . '/bootstrap/autoload.php';

    use Acme\Panel\Core\Config;
    use Acme\Panel\Core\Lang;
    use Acme\Panel\Core\Request;
    use Acme\Panel\Core\Response;
    use Acme\Panel\Domain\Aegis\AegisRepository;
    use Acme\Panel\Domain\Support\MultiServerRepository;
    use Acme\Panel\Domain\Support\ReadModelCache;
    use Acme\Panel\Http\Controllers\Aegis\AegisController;
    use Acme\Panel\Support\SoapExecutor;
    use Acme\Panel\Support\TransientCache;

    Config::init(dirname(__DIR__) . '/config');
    Lang::init();

    $_SESSION = [
        'panel_logged_in' => true,
        'panel_user' => 'cli-verifier',
        'panel_capabilities' => ['*'],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/aegis/api/runtime-check';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    final class FakeAegisRepository extends AegisRepository
    {
        public array $calls = [];
        public array $targets = [];
        public int $invalidations = 0;

        public function __construct()
        {
        }

        public function resolveCommandTarget(string $identifier): ?array
        {
            $this->calls[] = ['method' => 'resolveCommandTarget', 'args' => [$identifier]];
            return $this->targets[$identifier] ?? null;
        }

        public function invalidateReadCaches(): void
        {
            $this->calls[] = ['method' => 'invalidateReadCaches', 'args' => []];
            $this->invalidations++;
        }
    }

    final class CacheOnlyAegisRepository extends AegisRepository
    {
        public function __construct()
        {
        }
    }

    function makeRequest(array $post = [], string $method = 'POST'): Request
    {
        $request = new Request();
        $request->method = $method;
        $request->uri = '/aegis/api/runtime-check';
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

    $controller = new AegisController();
    $repo = new FakeAegisRepository();
    $repo->targets = [
        '77' => ['guid' => 77, 'name' => 'VerifierTarget'],
        'ghost' => null,
    ];
    setRefProperty(AegisController::class, $controller, 'repo', $repo);

    $checks = [];

    $invalidAction = responsePayload($controller->apiAction(makeRequest([
        'action' => 'noop',
        'target' => '77',
    ])));
    assertTrue(($invalidAction['status'] ?? 0) === 422, 'invalid action rejected');
    assertTrue($repo->calls === [], 'invalid action stopped before repository');
    $checks[] = ['name' => 'aegis.action_invalid', 'status' => 'passed', 'detail' => $invalidAction['body']];

    $missingTarget = responsePayload($controller->apiAction(makeRequest([
        'action' => 'clear',
        'target' => '',
    ])));
    assertTrue(($missingTarget['status'] ?? 0) === 422, 'missing target rejected');
    assertTrue($repo->calls === [], 'missing target stopped before repository');
    $checks[] = ['name' => 'aegis.target_required', 'status' => 'passed', 'detail' => $missingTarget['body']];

    $notFound = responsePayload($controller->apiAction(makeRequest([
        'action' => 'delete',
        'target' => 'ghost',
    ])));
    assertTrue(($notFound['status'] ?? 0) === 404, 'unknown target rejected');
    assertTrue(count(SoapExecutor::$calls) === 0, 'not found target stopped before soap execution');
    $checks[] = ['name' => 'aegis.target_not_found', 'status' => 'passed', 'detail' => $notFound['body']];

    SoapExecutor::$queuedResults[] = [
        'success' => true,
        'code' => 'ok',
        'message' => null,
    ];
    $success = responsePayload($controller->apiAction(makeRequest([
        'action' => 'clear',
        'target' => '77',
    ])));
    assertTrue(($success['status'] ?? 0) === 200, 'clear action succeeds');
    assertTrue(($success['body']['success'] ?? false) === true, 'success response returned');
    assertTrue(($repo->invalidations ?? 0) === 1, 'successful action invalidates read caches');
    assertTrue((SoapExecutor::$calls[0]['command'] ?? '') === '.aegis clear VerifierTarget', 'soap command uses resolved target');
    $checks[] = ['name' => 'aegis.action_success', 'status' => 'passed', 'detail' => $success['body']];

    SoapExecutor::$queuedResults[] = [
        'success' => false,
        'code' => 'soap.fault',
        'message' => 'stubbed failure',
    ];
    $failure = responsePayload($controller->apiAction(makeRequest([
        'action' => 'reload',
        'target' => '',
    ])));
    assertTrue(($failure['status'] ?? 0) === 422, 'reload failure branch returned');
    assertTrue(($repo->invalidations ?? 0) === 1, 'failed action does not invalidate caches');
    assertTrue((SoapExecutor::$calls[1]['command'] ?? '') === '.aegis reload', 'reload action omits target');
    $checks[] = ['name' => 'aegis.action_failure', 'status' => 'passed', 'detail' => $failure['body']];

    $cacheRepo = new CacheOnlyAegisRepository();
    $readCache = new ReadModelCache('aegis_server_0');
    setRefProperty(MultiServerRepository::class, $cacheRepo, 'serverId', 0);
    setRefProperty(AegisRepository::class, $cacheRepo, 'readCache', $readCache);

    $readCache->set('identity', 'server_0_identity_cli', ['guids' => [77]], 30);
    $readCache->set('accounts', 'account_ids_3', [3 => ['username' => 'Verifier']], 30);
    $readCache->set('character_summary', 'guid_77', ['guid' => 77, 'name' => 'VerifierTarget'], 30);

    $cacheRepo->invalidateReadCaches();

    assertTrue(TransientCache::get('aegis_server_0_identity', 'server_0_identity_cli') === null, 'identity cache invalidated');
    assertTrue(TransientCache::get('aegis_server_0_accounts', 'account_ids_3') === null, 'account map cache invalidated');
    assertTrue(TransientCache::get('aegis_server_0_character_summary', 'guid_77') === null, 'character summary cache invalidated');
    $checks[] = ['name' => 'aegis.cache_invalidation', 'status' => 'passed', 'detail' => ['server_id' => 0]];

    echo json_encode([
        'success' => true,
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}