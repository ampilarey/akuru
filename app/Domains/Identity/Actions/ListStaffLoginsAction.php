<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;

/**
 * The staff accounts an instructor profile can be linked to (C16 slice N6):
 * everyone who holds a school staff role, as id, name and email, so a form
 * outside Identity can offer them without reading Identity's models.
 */
class ListStaffLoginsAction
{
    public const ROLES = ['teacher', 'headmaster', 'supervisor', 'admin', 'course_creator'];

    /**
     * @return list<array{id: int, name: string, email: string|null}>
     */
    public function execute(): array
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::ROLES))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => $user->email !== null ? (string) $user->email : null,
            ])
            ->values()
            ->all();
    }
}
