<?php

declare(strict_types=1);

define('PANEL_CLI_AUTH_BYPASS', true);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Mail\MailRepository;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Domain\Support\ReadModelCache;
use Acme\Panel\Http\Controllers\Mail\MailController;
use Acme\Panel\Support\TransientCache;

Config::init(dirname(__DIR__) . '/config');
Lang::init();

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/mail/api/runtime-check';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

final class FakeMailRepository extends MailRepository
{
    public array $calls = [];

    public function __construct()
    {
    }

    public function getWithItems(int $id): ?array
    {
        $this->calls[] = ['method' => 'getWithItems', 'args' => [$id]];
        if ($id !== 7)
            return null;

        return [
            'id' => 7,
            'sender' => 100,
            'receiver' => 200,
            'receiver_name' => 'VerifierReceiver',
            'subject' => 'Runtime check mail',
            'items' => [
                [
                    'item_template' => 6948,
                    'item_name' => 'Hearthstone',
                ],
            ],
        ];
    }

    public function markRead(int $id): bool
    {
        $this->calls[] = ['method' => 'markRead', 'args' => [$id]];
        return $id === 7;
    }

    public function markReadBulk(array $ids): array
    {
        $this->calls[] = ['method' => 'markReadBulk', 'args' => [$ids]];
        return ['affected' => count($ids), 'updated' => array_values($ids)];
    }

    public function delete(int $id): bool
    {
        $this->calls[] = ['method' => 'delete', 'args' => [$id]];
        return $id !== 13;
    }

    public function deleteBulk(array $ids): array
    {
        $this->calls[] = ['method' => 'deleteBulk', 'args' => [$ids]];
        return [
            'deleted' => array_values(array_intersect($ids, [7, 8])),
            'blocked' => array_values(array_intersect($ids, [9])),
        ];
    }
}

final class CacheOnlyMailRepository extends MailRepository
{
    public function __construct()
    {
    }
}

function makeRequest(array $post = [], string $method = 'POST'): Request
{
    $request = new Request();
    $request->method = $method;
    $request->uri = '/mail/api/runtime-check';
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

$controller = new MailController();
$repo = new FakeMailRepository();
setRefProperty(MailController::class, $controller, 'repo', $repo);
setRefProperty(MailController::class, $controller, 'repoError', null);

$checks = [];

$invalidViewBefore = count($repo->calls);
$invalidView = responsePayload($controller->apiView(makeRequest(['mail_id' => '0'])));
assertTrue(($invalidView['status'] ?? 0) === 422, 'view invalid mail id rejected');
assertTrue(count($repo->calls) === $invalidViewBefore, 'view invalid payload stopped before repository');
$checks[] = ['name' => 'mail.view_invalid', 'status' => 'passed', 'detail' => $invalidView['body']];

$view = responsePayload($controller->apiView(makeRequest(['mail_id' => '7'])));
assertTrue(($view['body']['success'] ?? false) === true, 'view succeeds');
assertTrue((($view['body']['mail']['items'] ?? []) !== []), 'view returns item payload');
$checks[] = ['name' => 'mail.view_success', 'status' => 'passed', 'detail' => $view['body']];

$invalidMarkBefore = count($repo->calls);
$invalidMark = responsePayload($controller->apiMarkRead(makeRequest(['mail_id' => '-1'])));
assertTrue(($invalidMark['status'] ?? 0) === 422, 'mark read invalid mail id rejected');
assertTrue(count($repo->calls) === $invalidMarkBefore, 'mark read invalid payload stopped before repository');
$checks[] = ['name' => 'mail.mark_read_invalid', 'status' => 'passed', 'detail' => $invalidMark['body']];

$markRead = responsePayload($controller->apiMarkRead(makeRequest(['mail_id' => '7'])));
assertTrue(($markRead['body']['success'] ?? false) === true, 'mark read succeeds');
$checks[] = ['name' => 'mail.mark_read_success', 'status' => 'passed', 'detail' => $markRead['body']];

$deleteFailure = responsePayload($controller->apiDelete(makeRequest(['mail_id' => '13'])));
assertTrue(($deleteFailure['body']['success'] ?? true) === false, 'delete failure branch surfaces repository false');
$checks[] = ['name' => 'mail.delete_failure', 'status' => 'passed', 'detail' => $deleteFailure['body']];

$invalidBulkBefore = count($repo->calls);
$invalidBulk = responsePayload($controller->apiDeleteBulk(makeRequest(['ids' => 'foo,bar'])));
assertTrue(($invalidBulk['status'] ?? 0) === 422, 'bulk delete invalid ids rejected');
assertTrue(count($repo->calls) === $invalidBulkBefore, 'bulk delete invalid payload stopped before repository');
$checks[] = ['name' => 'mail.delete_bulk_invalid', 'status' => 'passed', 'detail' => $invalidBulk['body']];

$deleteBulk = responsePayload($controller->apiDeleteBulk(makeRequest(['ids' => '7,8,9'])));
assertTrue(($deleteBulk['body']['success'] ?? false) === true, 'bulk delete succeeds');
assertTrue(count($deleteBulk['body']['deleted'] ?? []) === 2, 'bulk delete returns deleted ids');
assertTrue(count($deleteBulk['body']['blocked'] ?? []) === 1, 'bulk delete returns blocked ids');
$checks[] = ['name' => 'mail.delete_bulk_success', 'status' => 'passed', 'detail' => $deleteBulk['body']];

$cacheRepo = new CacheOnlyMailRepository();
$readCache = new ReadModelCache('mail_server_0');
setRefProperty(MultiServerRepository::class, $cacheRepo, 'serverId', 0);
setRefProperty(MailRepository::class, $cacheRepo, 'readCache', $readCache);

$readCache->set('detail', 'mail_77', ['id' => 77], 30);
$readCache->set('items', 'mail_77', [['item_template' => 6948]], 30);
$readCache->set('detail', 'mail_78', ['id' => 78], 30);
$readCache->set('stats', 'summary', ['unread_estimate' => 4], 30);

invokeRefMethod(MailRepository::class, $cacheRepo, 'invalidateMailReadCaches', [[77, 78], true]);

assertTrue(TransientCache::get('mail_server_0_detail', 'mail_77') === null, 'mail detail cache invalidated');
assertTrue(TransientCache::get('mail_server_0_items', 'mail_77') === null, 'mail item cache invalidated');
assertTrue(TransientCache::get('mail_server_0_detail', 'mail_78') === null, 'second mail detail cache invalidated');
assertTrue(TransientCache::get('mail_server_0_stats', 'summary') === null, 'mail stats cache invalidated');
$checks[] = ['name' => 'mail.cache_invalidation', 'status' => 'passed', 'detail' => ['mail_ids' => [77, 78]]];

echo json_encode([
    'success' => true,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;