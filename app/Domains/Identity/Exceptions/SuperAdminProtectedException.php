<?php

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to delete the platform's last line of access.
 *
 * A super admin deleted their own account through the profile page and locked
 * themselves out of the platform entirely — `users` has no soft deletes, so
 * there was nothing to restore. AdminUserController already refused to delete
 * super admins; the profile page did not, which is why the guard now lives on
 * the model where every path meets.
 */
class SuperAdminProtectedException extends RuntimeException
{
    public static function cannotDelete(?int $userId = null): self
    {
        return new self(
            'Super admin accounts cannot be deleted'.($userId !== null ? " (user #{$userId})" : '').'. '
            .'Remove the super_admin role first if the account is genuinely being retired, '
            .'and only while another super admin exists.'
        );
    }
}
