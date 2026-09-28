<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Gives a login a role it does not hold yet, and never takes one away — the
 * one door another domain uses to grant a role as a consequence of something
 * it records (docs/SIGN_IN_PLAN.md ID2c: the office verifying a guardian
 * link makes the guardian's login a `parent`). The role screen
 * (`SetUserRolesAction`) stays the only place roles are set outright.
 */
class GrantRoleAction
{
    /**
     * @return bool whether the role was granted now
     */
    public function execute(int $userId, string $role): bool
    {
        $user = User::query()->find($userId);
        if ($user === null || $user->hasRole($role)) {
            return false;
        }

        $user->assignRole(Role::findOrCreate($role, 'web'));

        return true;
    }
}
