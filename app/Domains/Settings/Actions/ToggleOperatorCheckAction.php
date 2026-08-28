<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\OperatorCheck;
use Illuminate\Validation\ValidationException;

/**
 * Tick or untick one checklist item. A tick records who and when;
 * unticking deletes the row (this is progress tracking, not a ledger —
 * the durable evidence lives in STATUS.md per the merge-gate rule).
 */
class ToggleOperatorCheckAction
{
    /**
     * @return array{checked: bool}
     */
    public function execute(string $itemKey, int $userId): array
    {
        $known = array_merge(
            ListOperatorChecklistAction::itemKeys(),
            ListFeatureWalkthroughAction::itemKeys(),
        );
        if (! in_array($itemKey, $known, true)) {
            throw ValidationException::withMessages(['item' => 'Unknown checklist item.']);
        }

        $existing = OperatorCheck::query()->where('item_key', $itemKey)->first();
        if ($existing !== null) {
            $existing->delete();

            return ['checked' => false];
        }

        OperatorCheck::query()->create([
            'item_key' => $itemKey,
            'checked_by' => $userId,
            'checked_at' => now(),
        ]);

        return ['checked' => true];
    }
}
