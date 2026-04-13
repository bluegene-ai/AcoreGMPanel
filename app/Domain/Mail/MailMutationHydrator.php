<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Mail;

use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MutationHydrator;

final class MailMutationHydrator extends MutationHydrator
{
    public function mailId(array $input): array
    {
        $mailId = (int) ($input['mail_id'] ?? 0);
        $context = [
            'mail_id' => $mailId,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($mailId <= 0)
            return $this->invalid(Lang::get('app.common.validation.invalid_id'), 'invalid_id', $context);

        return $this->valid(['mail_id' => $mailId], $context);
    }

    public function mailIds(array $input): array
    {
        $rawIds = $input['ids'] ?? '';
        $ids = $this->positiveIntList($rawIds, 500);
        $context = [
            'count' => count($ids),
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($rawIds === '')
            return $this->invalid(Lang::get('app.common.validation.id_required'), 'missing_ids', $context);
        if ($ids === [])
            return $this->invalid(Lang::get('app.common.validation.no_valid_id'), 'no_valid_id', $context);

        return $this->valid(['ids' => $ids], $context);
    }
}