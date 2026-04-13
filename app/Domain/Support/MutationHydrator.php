<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Support;

abstract class MutationHydrator
{
    final protected function valid(array $payload, array $context): array
    {
        return [
            'valid' => true,
            'payload' => $payload,
            'context' => $context,
        ];
    }

    final protected function invalid(
        string $message,
        string $reason,
        array $context,
        int $status = 422
    ): array {
        return [
            'valid' => false,
            'message' => $message,
            'reason' => $reason,
            'status' => $status,
            'context' => $context,
        ];
    }

    final protected function positiveIntList($rawValues, int $max = 200): array
    {
        $ids = [];
        if (is_array($rawValues)) {
            foreach ($rawValues as $value) {
                $id = (int) $value;
                if ($id > 0)
                    $ids[] = $id;
            }
        } else {
            $parts = preg_split('/\s*,\s*/', (string) $rawValues, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $value) {
                $id = (int) $value;
                if ($id > 0)
                    $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($max > 0 && count($ids) > $max)
            $ids = array_slice($ids, 0, $max);

        return $ids;
    }
}