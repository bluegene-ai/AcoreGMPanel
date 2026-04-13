<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\CharacterBoost {
    class CharacterBoostGuardException extends \RuntimeException {}
    class CharacterBoostNotFoundException extends \RuntimeException {}
    class CharacterBoostSoapException extends \RuntimeException {}

    class BoostTemplateRepository
    {
        public static array $calls = [];
        public static array $templates = [
            1 => ['id' => 1, 'name' => 'Starter', 'target_level' => 80],
            2 => ['id' => 2, 'name' => 'Veteran', 'target_level' => 60],
        ];
        public static bool $deleteUnusedResult = true;
        public static int $purgeUnusedResult = 2;
        public static bool $throwOnGenerate = false;

        public function __construct(int $serverId = 0)
        {
        }

        public static function reset(): void
        {
            self::$calls = [];
            self::$templates = [
                1 => ['id' => 1, 'name' => 'Starter', 'target_level' => 80],
                2 => ['id' => 2, 'name' => 'Veteran', 'target_level' => 60],
            ];
            self::$deleteUnusedResult = true;
            self::$purgeUnusedResult = 2;
            self::$throwOnGenerate = false;
        }

        public function listForRealm(int $realmId): array
        {
            self::$calls[] = ['method' => 'listForRealm', 'args' => [$realmId]];
            return array_values(self::$templates);
        }

        public function listForRealmWithRewards(int $realmId): array
        {
            self::$calls[] = ['method' => 'listForRealmWithRewards', 'args' => [$realmId]];
            return array_values(self::$templates);
        }

        public function findForRealm(int $realmId, int $id): ?array
        {
            self::$calls[] = ['method' => 'findForRealm', 'args' => [$realmId, $id]];
            return self::$templates[$id] ?? null;
        }

        public function generateRedeemCodes(int $templateId, int $count): array
        {
            self::$calls[] = ['method' => 'generateRedeemCodes', 'args' => [$templateId, $count]];
            if (self::$throwOnGenerate)
                throw new \RuntimeException('stubbed generate failure');

            $codes = [];
            for ($i = 1; $i <= $count; $i++) {
                $codes[] = sprintf('CODE%02dT%02dABCDEFGH', $i, $templateId);
            }

            return $codes;
        }

        public function redeemCodeStatsForRealm(int $realmId, ?int $templateId = null): array
        {
            self::$calls[] = ['method' => 'redeemCodeStatsForRealm', 'args' => [$realmId, $templateId]];
            return [
                'total' => 5,
                'unused' => 3,
                'used' => 2,
            ];
        }

        public function listRedeemCodesForRealm(int $realmId, ?int $templateId, ?bool $unusedOnly, int $page, int $perPage, string $sort = 'id', string $dir = 'desc'): array
        {
            self::$calls[] = ['method' => 'listRedeemCodesForRealm', 'args' => [$realmId, $templateId, $unusedOnly, $page, $perPage, $sort, $dir]];
            return [
                'page' => $page,
                'per_page' => $perPage,
                'total' => 2,
                'rows' => [
                    ['id' => 10, 'code' => 'CODE10ABCDEFGHJK', 'used_at' => null],
                    ['id' => 9, 'code' => 'CODE09ABCDEFGHJK', 'used_at' => '2026-04-12 12:00:00'],
                ],
            ];
        }

        public function deleteUnusedRedeemCode(int $realmId, int $id): bool
        {
            self::$calls[] = ['method' => 'deleteUnusedRedeemCode', 'args' => [$realmId, $id]];
            return self::$deleteUnusedResult;
        }

        public function purgeUnusedRedeemCodes(int $realmId, ?int $templateId = null): int
        {
            self::$calls[] = ['method' => 'purgeUnusedRedeemCodes', 'args' => [$realmId, $templateId]];
            return self::$purgeUnusedResult;
        }

        public function authPdo(): object
        {
            return new class {
                public function beginTransaction(): void {}
                public function commit(): void {}
                public function rollBack(): void {}
                public function inTransaction(): bool { return false; }
            };
        }

        public function updateTemplate(int $realmId, int $id, string $name, int $targetLevel, int $moneyGold, bool $requireMatch): bool
        {
            self::$calls[] = ['method' => 'updateTemplate', 'args' => [$realmId, $id, $name, $targetLevel, $moneyGold, $requireMatch]];
            return true;
        }

        public function createTemplate(int $realmId, string $name, int $targetLevel, int $moneyGold, bool $requireMatch): int
        {
            self::$calls[] = ['method' => 'createTemplate', 'args' => [$realmId, $name, $targetLevel, $moneyGold, $requireMatch]];
            return 91;
        }

        public function replaceTemplateItems(int $templateId, array $items): void
        {
            self::$calls[] = ['method' => 'replaceTemplateItems', 'args' => [$templateId, $items]];
        }

        public function replaceTemplateClassRewards(int $templateId, array $tiers): void
        {
            self::$calls[] = ['method' => 'replaceTemplateClassRewards', 'args' => [$templateId, $tiers]];
        }

        public function deleteTemplate(int $realmId, int $id): bool
        {
            self::$calls[] = ['method' => 'deleteTemplate', 'args' => [$realmId, $id]];
            return true;
        }
    }

    class CharacterBoostService
    {
        public static array $calls = [];
        public static ?\Throwable $nextException = null;

        public function __construct(int $serverId = 0)
        {
        }

        public static function reset(): void
        {
            self::$calls = [];
            self::$nextException = null;
        }

        public function boostByGuid(int $realmId, int $guid, ?int $templateId, ?int $targetLevel, array $actor = []): array
        {
            self::$calls[] = ['method' => 'boostByGuid', 'args' => [$realmId, $guid, $templateId, $targetLevel, $actor]];
            if (self::$nextException !== null) {
                $e = self::$nextException;
                self::$nextException = null;
                throw $e;
            }

            return [
                'character' => [
                    'guid' => $guid,
                    'name' => 'VerifierBoosted',
                ],
                'commands' => [
                    [
                        'command' => '.character level ' . $guid . ' ' . (string) ($targetLevel ?? 80),
                        'response' => ['success' => true],
                    ],
                ],
            ];
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
    use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
    use Acme\Panel\Domain\CharacterBoost\CharacterBoostGuardException;
    use Acme\Panel\Domain\CharacterBoost\CharacterBoostService;
    use Acme\Panel\Http\Controllers\Character\CharacterController;
    use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostRedeemCodeAdminController;
    use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostTemplateAdminController;

    Config::init(dirname(__DIR__) . '/config');
    Lang::init();

    $_SESSION = [
        'panel_logged_in' => true,
        'panel_user' => 'cli-verifier',
        'panel_capabilities' => ['*'],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/character-boost/api/runtime-check';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    function makeRequest(string $uri, array $post = [], string $method = 'POST'): Request
    {
        $request = new Request();
        $request->method = $method;
        $request->uri = $uri;
        $request->get = [];
        $request->post = $post;
        $request->server = array_merge($_SERVER, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ]);

        return $request;
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

    BoostTemplateRepository::reset();
    CharacterBoostService::reset();

    $checks = [];

    $templateController = new CharacterBoostTemplateAdminController();
    $invalidTemplateBefore = count(BoostTemplateRepository::$calls);
    $invalidTemplate = responsePayload($templateController->apiSave(makeRequest('/character-boost/api/templates/save', [
        'name' => '',
        'target_level' => '0',
    ])));
    assertTrue(($invalidTemplate['status'] ?? 0) === 422, 'template invalid payload rejected');
    assertTrue(count(BoostTemplateRepository::$calls) === $invalidTemplateBefore, 'template invalid payload stopped before repository');
    $checks[] = ['name' => 'boost.templates.save_invalid', 'status' => 'passed', 'detail' => $invalidTemplate['body']];

    $saveTemplate = responsePayload($templateController->apiSave(makeRequest('/character-boost/api/templates/save', [
        'name' => 'Runtime Template',
        'target_level' => '80',
        'money_gold' => '200',
        'items' => "6948:1\n17031:2",
        'class_rewards' => 'mage paladin',
    ])));
    assertTrue(($saveTemplate['body']['success'] ?? false) === true, 'template save success');
    assertTrue((int) (($saveTemplate['body']['payload']['id'] ?? 0)) === 91, 'template save returns stub id');
    $checks[] = ['name' => 'boost.templates.save_success', 'status' => 'passed', 'detail' => $saveTemplate['body']];

    $codesController = new CharacterBoostRedeemCodeAdminController();
    $invalidCountBefore = count(BoostTemplateRepository::$calls);
    $invalidCount = responsePayload($codesController->apiGenerate(makeRequest('/character-boost/api/redeem-codes/generate', [
        'template_id' => '1',
        'count' => '0',
    ])));
    assertTrue(($invalidCount['status'] ?? 0) === 422, 'redeem generate invalid count rejected');
    assertTrue(count(BoostTemplateRepository::$calls) === $invalidCountBefore, 'invalid count stopped before repository');
    $checks[] = ['name' => 'boost.codes.generate_invalid_count', 'status' => 'passed', 'detail' => $invalidCount['body']];

    $invalidTemplateId = responsePayload($codesController->apiGenerate(makeRequest('/character-boost/api/redeem-codes/generate', [
        'template_id' => '999',
        'count' => '2',
    ])));
    assertTrue(($invalidTemplateId['status'] ?? 0) === 422, 'redeem generate invalid template rejected');
    $checks[] = ['name' => 'boost.codes.generate_invalid_template', 'status' => 'passed', 'detail' => $invalidTemplateId['body']];

    $generate = responsePayload($codesController->apiGenerate(makeRequest('/character-boost/api/redeem-codes/generate', [
        'template_id' => '1',
        'count' => '2',
    ])));
    assertTrue(($generate['body']['success'] ?? false) === true, 'redeem generate success');
    assertTrue(count($generate['body']['payload']['generated'][0]['codes'] ?? []) === 2, 'redeem generate returns two codes');
    $checks[] = ['name' => 'boost.codes.generate_success', 'status' => 'passed', 'detail' => $generate['body']];

    $statsInvalid = responsePayload($codesController->apiStats(makeRequest('/character-boost/api/redeem-codes/stats', [
        'template_id' => '999',
    ])));
    assertTrue(($statsInvalid['status'] ?? 0) === 422, 'stats invalid template rejected');
    $checks[] = ['name' => 'boost.codes.stats_invalid_template', 'status' => 'passed', 'detail' => $statsInvalid['body']];

    $list = responsePayload($codesController->apiList(makeRequest('/character-boost/api/redeem-codes/list', [
        'template_id' => 'all',
        'unused_only' => '1',
        'page' => '2',
        'per_page' => '25',
        'dir' => 'ASC',
    ])));
    assertTrue(($list['body']['success'] ?? false) === true, 'list success');
    assertTrue((int) (($list['body']['payload']['list']['page'] ?? 0)) === 2, 'list uses normalized page');
    $checks[] = ['name' => 'boost.codes.list_success', 'status' => 'passed', 'detail' => $list['body']];

    $missingDelete = responsePayload($codesController->apiDeleteUnused(makeRequest('/character-boost/api/redeem-codes/delete-unused', [
        'id' => '0',
    ])));
    assertTrue(($missingDelete['status'] ?? 0) === 422, 'delete unused missing id rejected');
    $checks[] = ['name' => 'boost.codes.delete_unused_invalid', 'status' => 'passed', 'detail' => $missingDelete['body']];

    BoostTemplateRepository::$deleteUnusedResult = false;
    $deleteFailure = responsePayload($codesController->apiDeleteUnused(makeRequest('/character-boost/api/redeem-codes/delete-unused', [
        'id' => '77',
    ])));
    assertTrue(($deleteFailure['status'] ?? 0) === 422, 'delete unused failure surfaced');
    $checks[] = ['name' => 'boost.codes.delete_unused_failure', 'status' => 'passed', 'detail' => $deleteFailure['body']];
    BoostTemplateRepository::$deleteUnusedResult = true;

    $purgeInvalid = responsePayload($codesController->apiPurgeUnused(makeRequest('/character-boost/api/redeem-codes/purge-unused', [
        'template_id' => '999',
    ])));
    assertTrue(($purgeInvalid['status'] ?? 0) === 422, 'purge invalid template rejected');
    $checks[] = ['name' => 'boost.codes.purge_invalid_template', 'status' => 'passed', 'detail' => $purgeInvalid['body']];

    $purge = responsePayload($codesController->apiPurgeUnused(makeRequest('/character-boost/api/redeem-codes/purge-unused', [
        'template_id' => 'all',
    ])));
    assertTrue(($purge['body']['success'] ?? false) === true, 'purge success');
    assertTrue((int) (($purge['body']['payload']['deleted'] ?? 0)) === 2, 'purge returns deleted count');
    $checks[] = ['name' => 'boost.codes.purge_success', 'status' => 'passed', 'detail' => $purge['body']];

    $characterController = new CharacterController();
    $missingGuidBefore = count(CharacterBoostService::$calls);
    $missingGuid = responsePayload($characterController->apiBoost(makeRequest('/character/api/boost', [
        'guid' => '0',
    ])));
    assertTrue(($missingGuid['status'] ?? 0) === 422, 'character boost missing guid rejected');
    assertTrue(count(CharacterBoostService::$calls) === $missingGuidBefore, 'missing guid stopped before boost service');
    $checks[] = ['name' => 'boost.apply_missing_guid', 'status' => 'passed', 'detail' => $missingGuid['body']];

    CharacterBoostService::$nextException = new CharacterBoostGuardException('stubbed guard failure');
    $guardFailure = responsePayload($characterController->apiBoost(makeRequest('/character/api/boost', [
        'guid' => '123',
        'template_id' => '1',
    ])));
    assertTrue(($guardFailure['status'] ?? 0) === 422, 'character boost guard failure surfaced');
    $checks[] = ['name' => 'boost.apply_guard_failure', 'status' => 'passed', 'detail' => $guardFailure['body']];

    $boostSuccess = responsePayload($characterController->apiBoost(makeRequest('/character/api/boost', [
        'guid' => '123',
        'template_id' => '1',
        'target_level' => '80',
    ])));
    assertTrue(($boostSuccess['body']['success'] ?? false) === true, 'character boost success');
    assertTrue(((int) ($boostSuccess['body']['payload']['character']['guid'] ?? 0)) === 123, 'character boost returns payload');
    $checks[] = ['name' => 'boost.apply_success', 'status' => 'passed', 'detail' => $boostSuccess['body']];

    echo json_encode([
        'success' => true,
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}