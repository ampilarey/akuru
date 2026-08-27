<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryAccessGrant;

/**
 * L3: create (or reuse) an active grant. Idempotent per
 * (user, item, source) so webhook retries never stack grants.
 */
class GrantLibraryAccessAction
{
    public function execute(int $userId, int $itemId, string $sourceType, ?int $sourceId = null): LibraryAccessGrant
    {
        return LibraryAccessGrant::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'library_item_id' => $itemId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'access_type' => 'full',
                'status' => 'active',
                'starts_at' => now(),
            ],
        );
    }
}
