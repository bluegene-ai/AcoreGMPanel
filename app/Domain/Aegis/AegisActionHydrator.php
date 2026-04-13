<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Aegis;

use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MutationHydrator;

final class AegisActionHydrator extends MutationHydrator
{
    public function action(array $input): array
    {
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        $target = trim((string) ($input['target'] ?? ''));
        $allowed = ['clear', 'delete', 'purge', 'reload'];
        $context = [
            'action' => $action,
            'target' => $target,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if (!in_array($action, $allowed, true))
            return $this->invalid(Lang::get('app.aegis.errors.invalid_action'), 'invalid_action', $context);

        if (in_array($action, ['clear', 'delete'], true) && $target === '') {
            return $this->invalid(
                Lang::get('app.aegis.errors.target_required'),
                'target_required',
                $context
            );
        }

        return $this->valid([
            'action' => $action,
            'target' => $target,
            'requires_target' => in_array($action, ['clear', 'delete'], true),
        ], $context);
    }
}