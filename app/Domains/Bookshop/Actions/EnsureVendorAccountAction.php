<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\Actions\ResolveUserByIdentifierAction;
use Illuminate\Support\Str;

/**
 * The account a vendor member signs in with (BOOKSHOP_PLAN §3: membership is
 * a row on the unified identity, never a second login).
 *
 * An existing account for the email is linked as it is — its owner already
 * has a password, so none is made. Otherwise an account is created with a
 * one-time password that the office sees once and sends to the person
 * (by Viber, by hand): production has no queue worker, so a mailed
 * invitation would sit unsent (plan audit finding 8). The account is marked
 * to change it, so the account page asks for a new password without asking
 * for this one.
 *
 * Either way the person carries the `vendor` role, which is what puts the
 * vendor portal in their menu.
 */
class EnsureVendorAccountAction
{
    /**
     * @return array{user_id: int, created: bool, temporary_password: ?string}
     */
    public function execute(string $name, string $email, ?string $phone = null): array
    {
        $existing = app(ResolveUserByIdentifierAction::class)->execute($email);

        if ($existing !== null) {
            $userId = (int) $existing->id;
            $this->grantVendorRole($userId);

            return ['user_id' => $userId, 'created' => false, 'temporary_password' => null];
        }

        $password = Str::password(12, letters: true, numbers: true, symbols: false);
        $created = app(CreateUserAction::class)->execute($name, $email, $password, $phone, 'vendor', forcePasswordChange: true);

        return ['user_id' => (int) $created['id'], 'created' => true, 'temporary_password' => $password];
    }

    /** The role on the unified identity, without importing Identity's model (rule 3). */
    private function grantVendorRole(int $userId): void
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->findOrFail($userId);
        if (! $user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }
    }
}
