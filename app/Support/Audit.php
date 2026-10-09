<?php
/**
 * File: app/Support/Audit.php
 * Purpose: 面板统一审计入口。所有写操作、异常与系统事件都经此落到 ac_eluna.panel_audit_log。
 */

namespace Acme\Panel\Support;

use PDO;
use Throwable;

class Audit
{
    /** detail 中这几个键同时用于填列，其余键原样进 detail JSON。 */
    private const RESERVED_KEYS = ['channel', 'status', 'severity', 'summary', 'actor', 'duration_ms'];

    private const CHANNEL_SEVERITY = [
        'audit' => 1,
        'system' => 1,
        'error' => 4,
    ];

    /**
     * 记录一次已授权的面板操作。签名保持稳定，既有调用点无需改动。
     */
    public static function log(string $module, string $action, string $target = '', array $detail = []): void
    {
        self::event($module, $action, $target, $detail);
    }

    /**
     * 完整入口。
     *
     * @param array $detail 业务上下文；其中 channel/status/severity/summary/actor/duration_ms 会被提升为列，
     *                      同时仍保留在 detail JSON 里，页面详情展示所见即调用方所传。
     * @param array $context 覆盖自动采集的请求上下文：method/uri/ip/user_agent/actor/realm_index/realm_id/realm_name
     */
    public static function event(string $module, string $action, string $target = '', array $detail = [], array $context = []): void
    {
        try {
            $module = self::clip($module !== '' ? $module : 'system', 48);
            $action = self::clip($action !== '' ? $action : 'event', 64);

            $channel = self::channel($detail, $context);
            $status = self::status($detail, $channel);
            $severity = self::severity($detail, $channel, $status);
            $actor = self::actor($detail, $context);
            $target = self::clip($target, 191);
            $summary = AuditCatalog::summarize($module, $action, $target, $detail, $status);
            $detailJson = self::encodeDetail($detail);

            $server = self::serverContext($context);
            $request = self::requestContext($context);

            AuditStore::insert([
                'ts' => self::now(),
                'channel' => $channel,
                'severity' => $severity,
                'status' => $status,
                'module' => $module,
                'action' => $action,
                'target' => $target,
                'summary' => $summary,
                'actor' => self::clip($actor, 64),
                'realm_index' => $server['realm_index'],
                'realm_id' => $server['realm_id'],
                'realm_name' => self::clip($server['realm_name'], 64),
                'method' => self::clip($request['method'], 8),
                'uri' => self::clip($request['uri'], 255),
                'ip' => self::clip($request['ip'], 45),
                'user_agent' => self::clip($request['user_agent'], 255),
                'duration_ms' => self::duration($detail, $context),
                'detail' => $detailJson,
            ]);
        } catch (Throwable $e) {
            // 审计写入永远不能把异常抛回业务路径。
        }
    }

    /**
     * 分阶段事件：stage 形如 `create.success` / `update.no_valid_fields`。
     *
     * 动作取 stage 的点号前段，点号后段作为明细里的 stage 并决定状态，这样
     * 「同一动作的不同结果」在页面上仍归到同一个动作下，靠状态就能分辨成败。
     *
     * @param array<int,string> $targetKeys 目标取值字段优先级；留空时用默认表
     */
    public static function stage(string $module, string $stage, array $context = [], array $targetKeys = []): void
    {
        $parts = array_pad(explode('.', $stage, 2), 2, '');
        $action = $parts[0] !== '' ? $parts[0] : 'action';
        $step = $parts[1];

        $context['stage'] = $step;
        // 调用方显式给了结果就尊重它，否则由阶段名推断。
        if (!isset($context['status'])) {
            $context['status'] = self::stageStatus($step);
        }

        self::event($module, $action, self::targetFrom($context, $targetKeys), $context);
    }

    private const DEFAULT_TARGET_KEYS = ['entry', 'new_id', 'id', 'guid', 'name', 'player', 'user', 'username'];

    private static function targetFrom(array $context, array $targetKeys): string
    {
        foreach ($targetKeys !== [] ? $targetKeys : self::DEFAULT_TARGET_KEYS as $key) {
            if (isset($context[$key]) && is_scalar($context[$key]) && (string) $context[$key] !== '') {
                return $key . '=' . (string) $context[$key];
            }
        }

        return '';
    }

    private static function stageStatus(string $step): string
    {
        return match ($step) {
            'success', 'ok', 'noop', 'no_changes', 'no_effect', 'skipped' => 'ok',
            'validate_fail', 'validation_fail', 'duplicate', 'copy_missing', 'no_valid_fields' => 'denied',
            default => 'fail',
        };
    }

    /**
     * 异常/告警落库（channel=error）。供 ErrorHandler 与非致命告警使用。
     */
    public static function error(string $module, string $action, string $message, array $detail = [], array $context = []): void
    {
        $detail = $detail + ['message' => $message];
        $detail['channel'] = $detail['channel'] ?? 'error';
        self::event($module, $action, (string) ($detail['target'] ?? ''), $detail, $context);
    }

    /**
     * 系统级事件（渠道切换、安装、清理等），channel=system。
     */
    public static function system(string $module, string $action, array $detail = [], array $context = []): void
    {
        $detail['channel'] = $detail['channel'] ?? 'system';
        self::event($module, $action, (string) ($detail['target'] ?? ''), $detail, $context);
    }

    /** 审计库连接（诊断用）。 */
    public static function storageFor(?int $serverId = null): ?PDO
    {
        return AuditStore::pdo();
    }

    public static function storageReady(): bool
    {
        return AuditStore::table() !== null;
    }

    private static function channel(array $detail, array $context): string
    {
        $channel = $detail['channel'] ?? ($context['channel'] ?? 'audit');
        $channel = is_string($channel) ? strtolower(trim($channel)) : 'audit';
        if ($channel === '' || !isset(self::CHANNEL_SEVERITY[$channel])) {
            return 'audit';
        }

        return $channel;
    }

    private static function status(array $detail, string $channel): string
    {
        $status = $detail['status'] ?? null;
        if (!is_string($status) || trim($status) === '') {
            $success = $detail['success'] ?? null;
            if ($success === false) {
                $status = 'fail';
            } elseif (!empty($detail['error'])) {
                $status = 'fail';
            } elseif ($channel === 'error') {
                $status = 'fail';
            } else {
                $status = 'ok';
            }
        }

        return self::clip(strtolower(trim($status)), 12);
    }

    private static function severity(array $detail, string $channel, string $status): int
    {
        $explicit = $detail['severity'] ?? null;
        if (is_numeric($explicit)) {
            return max(1, min(4, (int) $explicit));
        }

        if ($status === 'fail' || $status === 'denied') {
            return $channel === 'error' ? 4 : 3;
        }

        return self::CHANNEL_SEVERITY[$channel] ?? 1;
    }

    private static function actor(array $detail, array $context): string
    {
        foreach ([$detail['actor'] ?? null, $context['actor'] ?? null, Auth::user()] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return PHP_SAPI === 'cli' ? 'cli' : 'anonymous';
    }

    private static function duration(array $detail, array $context): int
    {
        $explicit = $detail['duration_ms'] ?? ($context['duration_ms'] ?? null);
        if (is_numeric($explicit)) {
            return max(0, (int) $explicit);
        }

        $started = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        if (is_numeric($started) && PHP_SAPI !== 'cli') {
            return max(0, (int) round((microtime(true) - (float) $started) * 1000));
        }

        return 0;
    }

    /** @return array{realm_index:int, realm_id:int, realm_name:string} */
    private static function serverContext(array $context): array
    {
        $index = $context['realm_index'] ?? null;
        $id = $context['realm_id'] ?? null;
        $name = $context['realm_name'] ?? null;

        if ($index === null) {
            try {
                $index = ServerContext::currentId();
            } catch (Throwable $e) {
                $index = 0;
            }
        }

        if ($id === null || $name === null) {
            try {
                $server = ServerContext::server((int) $index);
                $id ??= (int) ($server['realm_id'] ?? 0);
                $name ??= (string) ($server['name'] ?? '');
            } catch (Throwable $e) {
                $id ??= 0;
                $name ??= '';
            }
        }

        return [
            'realm_index' => (int) $index,
            'realm_id' => (int) $id,
            'realm_name' => (string) $name,
        ];
    }

    /** @return array{method:string, uri:string, ip:string, user_agent:string} */
    private static function requestContext(array $context): array
    {
        $server = is_array($_SERVER ?? null) ? $_SERVER : [];

        return [
            'method' => (string) ($context['method'] ?? ($server['REQUEST_METHOD'] ?? '')),
            'uri' => (string) ($context['uri'] ?? ($server['REQUEST_URI'] ?? '')),
            'ip' => (string) ($context['ip'] ?? ClientIp::resolve($server)),
            'user_agent' => (string) ($context['user_agent'] ?? ($server['HTTP_USER_AGENT'] ?? '')),
        ];
    }

    private static function encodeDetail(array $detail): ?string
    {
        if ($detail === []) {
            return null;
        }

        $json = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? null : $json;
    }

    private static function now(): string
    {
        $now = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));

        return ($now ?: new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }

    private static function clip(string $value, int $max): string
    {
        $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value;
        $value = trim($value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }

    /** 保留键清单，供读取端区分“列”与“业务明细”。 */
    public static function reservedKeys(): array
    {
        return self::RESERVED_KEYS;
    }
}
