<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryItem;

/**
 * L3: the ONE access decision (LIBRARY_PLAN §6 + §35.4). free_public and
 * free_login answer from the access type alone; every other type answers
 * from an ACTIVE access grant — which only webhook confirmation, course
 * links, or an admin ever create (§43.4/§43.5).
 */
class ResolveLibraryAccessAction
{
    /**
     * @return array{can_read: bool, requires_login: bool, locked: bool}
     */
    public function execute(LibraryItem $item, ?int $userId): array
    {
        $access = $item->access_type?->value;

        $canRead = match ($access) {
            'free_public' => true,
            'free_login' => $userId !== null,
            default => $userId !== null && LibraryAccessGrant::query()
                ->where('user_id', $userId)
                ->where('library_item_id', $item->id)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->exists(),
        };

        return [
            'can_read' => $canRead,
            'requires_login' => $userId === null && $access !== 'free_public',
            'locked' => ! $canRead && $userId !== null && ! in_array($access, ['free_public', 'free_login'], true),
        ];
    }
}
