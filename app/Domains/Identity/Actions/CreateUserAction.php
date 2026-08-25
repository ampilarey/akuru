<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserAction
{
    /**
     * @return array{id: int, email: string, name: string}
     */
    public function execute(string $name, string $email, ?string $password = null, ?string $phone = null): array
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password ?: Str::password(16)),
            'phone' => $phone,
        ]);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }
}
