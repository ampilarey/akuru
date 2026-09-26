<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\Actions\ResolveUserByIdentifierAction;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Bookstore admins (slice B10b): people with the `bookshop_manager`
 * role, who run /admin/bookshop — vendors, orders, payouts, the shop — and
 * nothing else of the school system. Full admins add and remove them.
 *
 * Adding someone who already has an account gives that account the role;
 * otherwise an account is made with a one-time password the admin sees
 * once and passes on by hand, and it must be changed at first sign-in (the
 * B1a vendor-owner precedent: production mail may sit unsent).
 */
class ManageBookshopTeamAction
{
    public const ROLE = 'bookshop_manager';

    /**
     * @return list<array{id: int, name: string, email: ?string, phone: ?string, last_login_at: ?string}>
     */
    public function list(): array
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::query()->role(self::ROLE)->orderBy('name')->get()->map(fn ($u) => [
            'id' => (int) $u->id, 'name' => (string) $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'last_login_at' => $u->last_login_at?->toDateTimeString(),
        ])->values()->all();
    }

    /**
     * @return array{user_id: int, name: string, created: bool, temporary_password: ?string}
     */
    public function add(string $email, ?string $name = null, ?string $phone = null): array
    {
        $email = strtolower(trim($email));
        $existing = app(ResolveUserByIdentifierAction::class)->execute($email);
        if ($existing !== null) {
            $userModel = config('auth.providers.users.model');
            $user = $userModel::query()->findOrFail($existing->id);
            if (! $user->hasRole(self::ROLE)) {
                $user->assignRole(self::ROLE);
            }

            return ['user_id' => (int) $user->id, 'name' => (string) $user->name, 'created' => false, 'temporary_password' => null];
        }
        if (trim((string) $name) === '') {
            throw ValidationException::withMessages(['name' => __('shop.error_team_name_needed')]);
        }
        $password = Str::password(12, letters: true, numbers: true, symbols: false);
        $created = app(CreateUserAction::class)->execute(trim((string) $name), $email, $password, $phone ?: null, self::ROLE, forcePasswordChange: true);

        return ['user_id' => (int) $created['id'], 'name' => (string) $created['name'], 'created' => true, 'temporary_password' => $password];
    }

    public function remove(int $userId, int $byUserId): void
    {
        if ($userId === $byUserId) {
            throw ValidationException::withMessages(['team' => __('shop.error_team_self')]);
        }
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->findOrFail($userId);
        $user->removeRole(self::ROLE);
    }
}
