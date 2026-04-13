<?php

declare(strict_types=1);

define('PANEL_CLI_AUTH_BYPASS', true);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\MassMail\MassMailService;
use Acme\Panel\Http\Controllers\MassMail\MassMailController;

Config::init(dirname(__DIR__) . '/config');
Lang::init();

$_SESSION = [
    'panel_logged_in' => true,
    'panel_user' => 'cli-verifier',
    'panel_capabilities' => ['*'],
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/mass-mail/api/runtime-check';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

final class FakeMassMailService extends MassMailService
{
    public array $calls = [];
    public array $targetsByType = [];
    public array $announceResponses = [];
    public array $bulkResponses = [];
    public array $logs = [];

    public function __construct()
    {
    }

    public function resolveTargets(string $type, ?string $customList): array
    {
        $this->calls[] = ['method' => 'resolveTargets', 'args' => [$type, $customList]];
        return $this->targetsByType[$type] ?? [];
    }

    public function sendAnnounce(string $message): array
    {
        $this->calls[] = ['method' => 'sendAnnounce', 'args' => [$message]];
        if ($this->announceResponses !== [])
            return array_shift($this->announceResponses);

        return ['success' => true, 'message' => 'announce ok'];
    }

    public function sendBulk(string $action, string $subject, string $body, array $targets, string $itemsRaw = '', ?int $amount = null): array
    {
        $this->calls[] = ['method' => 'sendBulk', 'args' => [$action, $subject, $body, $targets, $itemsRaw, $amount]];
        if ($this->bulkResponses !== [])
            return array_shift($this->bulkResponses);

        return ['success' => true, 'message' => 'bulk ok'];
    }

    public function recentLogs(int $limit = 30): array
    {
        $this->calls[] = ['method' => 'recentLogs', 'args' => [$limit]];
        return array_slice($this->logs, 0, $limit);
    }
}

function makeRequest(array $post = [], string $method = 'POST'): Request
{
    $request = new Request();
    $request->method = $method;
    $request->uri = '/mass-mail/api/runtime-check';
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

$controllerRef = new ReflectionClass(MassMailController::class);
$controller = $controllerRef->newInstanceWithoutConstructor();
$svc = new FakeMassMailService();
setRefProperty(MassMailController::class, $controller, 'svc', $svc);

$checks = [];

$svc->announceResponses[] = ['success' => false, 'message' => 'announce failed'];
$announceFailure = responsePayload($controller->apiAnnounce(makeRequest([
    'message' => 'runtime announcement failure',
])));
assertTrue(($announceFailure['status'] ?? 0) === 422, 'announce failure returned 422');
assertTrue(($svc->calls[0]['method'] ?? '') === 'sendAnnounce', 'announce delegates to service');
$checks[] = ['name' => 'mass_mail.announce_failure', 'status' => 'passed', 'detail' => $announceFailure['body']];

$svc->announceResponses[] = ['success' => true, 'message' => 'announce ok'];
$announceSuccess = responsePayload($controller->apiAnnounce(makeRequest([
    'message' => 'runtime announcement success',
])));
assertTrue(($announceSuccess['status'] ?? 0) === 200, 'announce success returned 200');
assertTrue(($announceSuccess['body']['success'] ?? false) === true, 'announce success payload returned');
$checks[] = ['name' => 'mass_mail.announce_success', 'status' => 'passed', 'detail' => $announceSuccess['body']];

$svc->targetsByType['custom'] = ['VerifierOne', 'VerifierTwo'];
$svc->bulkResponses[] = ['success' => true, 'message' => 'mail bulk ok', 'success_count' => 2, 'fail_count' => 0];
$sendMail = responsePayload($controller->apiSend(makeRequest([
    'action' => 'send_mail',
    'subject' => 'Runtime Subject',
    'body' => 'Runtime Body',
    'target_type' => 'custom',
    'custom_char_list' => "VerifierOne\nVerifierTwo",
])));
assertTrue(($sendMail['status'] ?? 0) === 200, 'send_mail success returned 200');
assertTrue(($sendMail['body']['success'] ?? false) === true, 'send_mail success payload returned');
$sendMailResolveCall = $svc->calls[2] ?? [];
$sendMailBulkCall = $svc->calls[3] ?? [];
assertTrue(($sendMailResolveCall['method'] ?? '') === 'resolveTargets', 'send_mail resolves targets');
assertTrue(($sendMailBulkCall['method'] ?? '') === 'sendBulk', 'send_mail delegates to sendBulk');
assertTrue(($sendMailBulkCall['args'][0] ?? '') === 'send_mail', 'send_mail action preserved');
assertTrue(($sendMailBulkCall['args'][3] ?? []) === ['VerifierOne', 'VerifierTwo'], 'resolved targets forwarded');
$checks[] = ['name' => 'mass_mail.send_mail_success', 'status' => 'passed', 'detail' => $sendMail['body']];

$svc->targetsByType['online'] = ['VerifierOnline'];
$svc->bulkResponses[] = ['success' => true, 'message' => 'item bulk ok', 'success_count' => 1, 'fail_count' => 0];
$sendItem = responsePayload($controller->apiSend(makeRequest([
    'action' => 'send_item',
    'subject' => 'Item Subject',
    'body' => 'Item Body',
    'target_type' => 'online',
    'itemId' => '6948',
    'quantity' => '2',
])));
assertTrue(($sendItem['status'] ?? 0) === 200, 'send_item success returned 200');
$sendItemBulkCall = $svc->calls[5] ?? [];
assertTrue(($sendItemBulkCall['method'] ?? '') === 'sendBulk', 'send_item delegates to sendBulk');
assertTrue(($sendItemBulkCall['args'][4] ?? '') === '6948:2', 'legacy item fallback normalized');
$checks[] = ['name' => 'mass_mail.send_item_legacy_fallback', 'status' => 'passed', 'detail' => $sendItem['body']];

$svc->targetsByType['custom'] = ['VerifierGold'];
$svc->bulkResponses[] = ['success' => false, 'message' => 'gold delivery mismatch', 'success_count' => 0, 'fail_count' => 1];
$sendGoldFailure = responsePayload($controller->apiSend(makeRequest([
    'action' => 'send_gold',
    'subject' => 'Gold Subject',
    'body' => 'Gold Body',
    'target_type' => 'custom',
    'custom_char_list' => 'VerifierGold',
    'amount' => '10000',
])));
assertTrue(($sendGoldFailure['status'] ?? 0) === 422, 'send_gold failure returned 422');
$sendGoldBulkCall = $svc->calls[7] ?? [];
assertTrue(($sendGoldBulkCall['args'][5] ?? null) === 10000, 'gold amount forwarded as integer');
$checks[] = ['name' => 'mass_mail.send_gold_failure', 'status' => 'passed', 'detail' => $sendGoldFailure['body']];

$svc->logs = [
    ['id' => 12, 'action' => 'send_mail'],
    ['id' => 11, 'action' => 'announce'],
];
$logs = responsePayload($controller->apiLogs(makeRequest(['limit' => '1'])));
assertTrue(($logs['status'] ?? 0) === 200, 'logs returned 200');
assertTrue(count($logs['body']['logs'] ?? []) === 1, 'logs obey limit');
$logsCall = $svc->calls[8] ?? [];
assertTrue(($logsCall['method'] ?? '') === 'recentLogs', 'logs delegates to recentLogs');
assertTrue(($logsCall['args'][0] ?? null) === 1, 'logs limit forwarded');
$checks[] = ['name' => 'mass_mail.logs_success', 'status' => 'passed', 'detail' => $logs['body']];

$boostMissingName = responsePayload($controller->apiBoost(makeRequest([
    'template_id' => '1',
])));
assertTrue(($boostMissingName['status'] ?? 0) === 422, 'boost missing character name rejected');
$checks[] = ['name' => 'mass_mail.boost_missing_name', 'status' => 'passed', 'detail' => $boostMissingName['body']];

echo json_encode([
    'success' => true,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;