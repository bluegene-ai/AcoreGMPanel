<?php
declare(strict_types=1);

namespace Acme\Panel\Domain\Account;

use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MutationHydrator;

final class AccountMutationHydrator extends MutationHydrator
{
    public function delete(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $context = [
            'id' => $id,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0)
            return $this->invalid(Lang::get('app.common.validation.missing_id'), 'missing_id', $context);

        return $this->valid(['id' => $id], $context);
    }

    public function bulk(array $input): array
    {
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        $allowed = ['delete', 'ban', 'unban'];
        if (!in_array($action, $allowed, true))
            return $this->invalid(Lang::get('app.common.validation.missing_params'), 'invalid_action', ['action' => $action]);

        $ids = $this->positiveIntList($input['ids'] ?? [], 200);
        if ($ids === [])
            return $this->invalid(Lang::get('app.common.validation.missing_params'), 'missing_ids', ['action' => $action]);

        $hours = max(0, (int) ($input['hours'] ?? 0));
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($action === 'ban' && $reason === '')
            $reason = Lang::get('app.account.api.defaults.no_reason');

        $context = [
            'action' => $action,
            'count' => count($ids),
            'hours' => $hours,
            'reason' => $reason,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        return $this->valid([
            'action' => $action,
            'ids' => $ids,
            'hours' => $hours,
            'reason' => $reason,
        ], $context);
    }

    public function updateEmail(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $email = trim((string) ($input['email'] ?? ''));
        $context = [
            'id' => $id,
            'email' => $email,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0)
            return $this->invalid(Lang::get('app.common.validation.missing_id'), 'missing_id', $context);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->invalid(Lang::get('app.account.email.invalid'), 'email_invalid', $context);

        return $this->valid(['id' => $id, 'email' => $email], $context);
    }

    public function updateUsername(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $context = [
            'id' => $id,
            'username' => $username,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0)
            return $this->invalid(Lang::get('app.common.validation.missing_id'), 'missing_id', $context);
        if ($username === '' || strlen($username) > 20)
            return $this->invalid(Lang::get('app.account.rename.invalid_username'), 'username_invalid', $context);
        if (strlen($password) < 8)
            return $this->invalid(Lang::get('app.account.rename.invalid_password'), 'password_invalid', $context);

        return $this->valid([
            'id' => $id,
            'username' => $username,
            'password' => $password,
        ], $context);
    }

    public function create(array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');
        $email = trim((string) ($input['email'] ?? ''));
        $gmLevel = (int) ($input['gmlevel'] ?? 0);
        if ($gmLevel < 0 || $gmLevel > 3)
            $gmLevel = 0;

        $context = [
            'username' => $username,
            'email' => $email,
            'gmlevel' => $gmLevel,
            'server' => (int) ($input['server'] ?? 0),
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($username === '')
            return $this->invalid(Lang::get('app.account.create.errors.username_required'), 'empty_username', $context);
        if (strlen($username) > 32)
            return $this->invalid(Lang::get('app.account.create.errors.username_length'), 'username_too_long', $context);
        if ($password === '')
            return $this->invalid(Lang::get('app.account.password.error_empty'), 'empty_password', $context);
        if (strlen($password) < 8)
            return $this->invalid(Lang::get('app.account.api.validation.password_min'), 'password_too_short', $context);
        if ($password !== $confirm)
            return $this->invalid(Lang::get('app.account.password.error_mismatch'), 'password_mismatch', $context);
        if ($email !== '' && strlen($email) > 128)
            return $this->invalid(Lang::get('app.account.create.errors.email_length'), 'email_too_long', $context);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            return $this->invalid(Lang::get('app.account.create.errors.email_invalid'), 'email_invalid', $context);

        return $this->valid([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'gmlevel' => $gmLevel,
        ], $context);
    }

    public function setGm(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $gm = (int) ($input['gm'] ?? 0);
        $realm = (int) ($input['realm'] ?? -1);
        $context = [
            'id' => $id,
            'gm' => $gm,
            'realm' => $realm,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0)
            return $this->invalid(Lang::get('app.common.validation.missing_id'), 'missing_id', $context);
        if ($gm < 0 || $gm > 6)
            return $this->invalid(Lang::get('app.account.api.validation.gm_range'), 'gm_out_of_range', $context);

        return $this->valid(['id' => $id, 'gm' => $gm, 'realm' => $realm], $context);
    }

    public function ban(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $hours = max(0, (int) ($input['hours'] ?? 0));
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '')
            $reason = Lang::get('app.account.api.defaults.no_reason');

        $context = [
            'id' => $id,
            'hours' => $hours,
            'reason' => $reason,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0)
            return $this->invalid(Lang::get('app.common.validation.missing_id'), 'missing_id', $context);

        return $this->valid(['id' => $id, 'hours' => $hours, 'reason' => $reason], $context);
    }

    public function unban(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $context = [
            'id' => $id,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0)
            return $this->invalid(Lang::get('app.common.validation.missing_id'), 'missing_id', $context);

        return $this->valid(['id' => $id], $context);
    }

    public function changePassword(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $context = [
            'id' => $id,
            'username' => $username,
            'ip' => (string) ($input['ip'] ?? ''),
        ];

        if ($id <= 0 || $username === '' || $password === '')
            return $this->invalid(Lang::get('app.common.validation.missing_params'), 'missing_params', $context);
        if (strlen($password) < 8)
            return $this->invalid(Lang::get('app.account.api.validation.password_min'), 'password_too_short', $context);

        return $this->valid([
            'id' => $id,
            'username' => $username,
            'password' => $password,
        ], $context);
    }
}