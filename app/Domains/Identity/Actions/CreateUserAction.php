<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserAction
{
    /**
     * @param  string|null  $role  A Spatie role name to assign on creation. It
     *                             lives here, not in the caller, because the
     *                             User model is Identity's and role assignment
     *                             is a write to it — another domain creating a
     *                             staff account must not import the model to
     *                             finish the job.
     * @param  bool  $forcePasswordChange  The account starts with a password
     *                                     someone else chose (the office's
     *                                     one-time password for a new vendor
     *                                     owner, B1a), so its owner sets their
     *                                     own without being asked for this one.
     * @return array{id: int, email: string, name: string}
     */
    public function execute(string $name, string $email, ?string $password = null, ?string $phone = null, ?string $role = null, bool $forcePasswordChange = false): array
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password ?: Str::password(16)),
            'phone' => $phone,
            'is_active' => true,
            'force_password_change' => $forcePasswordChange,
        ]);

        // Without this the account cannot reset its own password by email:
        // the OTP flow resolves people through `user_contacts`, not
        // `users.email`.
        app(EnsureVerifiedEmailContactAction::class)->execute($user);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }
}
