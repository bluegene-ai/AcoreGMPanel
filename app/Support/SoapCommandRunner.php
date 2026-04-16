<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

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

        return self::normalize($execution);
    }

    public static function normalize(array $execution): array
    {
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