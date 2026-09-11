<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\FoundItemStatus;
use App\Domains\Academics\Models\FoundItem;
use Illuminate\Validation\ValidationException;

/**
 * Hand an item back.
 *
 * Returning is a separate action from editing because it is a different event
 * with a different record: who released it, when, and to whom. Folding it into
 * a general "update" would make it possible to flip the status without ever
 * saying who took the item away, which is the only fact anyone asks about
 * afterwards.
 *
 * Idempotent by refusal rather than by silence — returning an item twice means
 * two different people believe they collected it, and that is worth an error.
 */
class ReturnFoundItemAction
{
    public function execute(FoundItem $item, int $userId, ?string $returnedTo = null): FoundItem
    {
        if ($item->status === FoundItemStatus::Returned) {
            throw ValidationException::withMessages([
                'status' => 'This item was already returned on '.$item->returned_at?->toDateString().'.',
            ]);
        }

        $item->update([
            'status' => FoundItemStatus::Returned->value,
            'returned_at' => now(),
            'returned_by' => $userId,
            'returned_to' => ($returnedTo !== null && trim($returnedTo) !== '') ? trim($returnedTo) : null,
        ]);

        return $item->refresh();
    }
}
