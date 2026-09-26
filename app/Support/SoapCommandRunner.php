<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

final class SoapCommandRunner
{
    public static function execute(string $command, array $options = []): array
    {
        $serverId = isset($options['server_id'])
            ? (int) $options['server_id']
            : ServerContext::currentId();

        $executor = new SoapExecutor();
        $execution = $executor->execute($command, [
            'server_id' => $serverId,
            'audit' => $options['audit'] ?? true,
        ]);

        return self::normalize($execution, [
            'strict_marker' => $options['strict_marker'] ?? false,
        ]);
    }

    /**
     * @param array $options 支持 strict_marker（默认 false）：为 true 时，输出里既没有 [AGMP_OK] 也没有
     *        [AGMP_ERROR] 标记的调用会被判定为失败，避免「命令根本没到游戏」被当成成功。
     */
    public static function normalize(array $execution, array $options = []): array
    {
        $strictMarker = ($options['strict_marker'] ?? false) === true;
        $output = trim((string) ($execution['output'] ?? ''));
        $marked = self::extractMarker($output);

        if (($execution['success'] ?? false) !== true) {
            return [
                'success' => false,
                'message' => trim((string) ($execution['message'] ?? '')) ?: $output,
                'output' => $output,
                'marked' => $marked !== null,
                'execution' => $execution,
            ];
        }

        if ($marked !== null) {
            return [
                'success' => $marked['type'] === 'AGMP_OK',
                'message' => $marked['message'] !== ''
                    ? $marked['message']
                    : $output,
                'output' => $output,
                'marked' => true,
                'execution' => $execution,
            ];
        }

        if ($strictMarker) {
            return [
                'success' => false,
                'message' => Lang::get('app.boss.errors.marker_missing'),
                'output' => $output,
                'marked' => false,
                'execution' => $execution,
            ];
        }

        return [
            'success' => true,
            'message' => $output,
            'output' => $output,
            'marked' => false,
            'execution' => $execution,
        ];
    }

    private static function extractMarker(string $output): ?array
    {
        if ($output === '')
            return null;

        if (!preg_match('/\[(AGMP_OK|AGMP_ERROR)\]\s*(.*)/us', $output, $matches))
            return null;

        return [
            'type' => (string) ($matches[1] ?? ''),
            'message' => trim((string) ($matches[2] ?? '')),
        ];
    }
}