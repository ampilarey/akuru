<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\LinkedAccount;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Collection;

/**
 * The accounts this person may switch to.
 *
 * Each carries the roles of the *other* identity, because "Teacher" and
 * "Parent" is the only label that makes the switcher meaningful — two rows
 * both saying the person's own name would not tell them which is which.
 */
class ListLinkedAccountsAction
{
    /**
     * @return Collection<int, array{id: int, name: string, roles: string}>
     */
    public function execute(int $userId): Collection
    {
        $ids = LinkedAccount::query()
            ->verified()
            ->where('user_id', $userId)
            ->pluck('linked_user_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->with('roles')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames()
                    ->map(fn (string $role): string => str_replace('_', ' ', $role))
                    ->implode(', ') ?: 'No role',
            ])
            ->values();
    }
}
