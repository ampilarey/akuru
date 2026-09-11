<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentMovement;
use Illuminate\Validation\ValidationException;

/**
 * Undo a mis-tap.
 *
 * **Voids rather than deletes.** A parent who was told their child left at
 * 13:40 and is later told they did not is owed an explanation, and a deleted
 * row cannot give one. The movement stops counting immediately; the fact that
 * somebody recorded it and took it back stays on the record.
 *
 * Voiding an already-voided row is refused rather than ignored, so two members
 * of staff cannot both believe they were the one who fixed it.
 */
class VoidStudentMovementAction
{
    public function execute(int $movementId, int $staffUserId): StudentMovement
    {
        $movement = StudentMovement::query()->find($movementId);

        if ($movement === null) {
            throw ValidationException::withMessages([
                'movement' => 'That movement no longer exists.',
            ]);
        }

        if ($movement->voided_at !== null) {
            throw ValidationException::withMessages([
                'movement' => 'That movement has already been taken back.',
            ]);
        }

        $movement->update([
            'voided_at' => now(),
            'voided_by' => $staffUserId,
        ]);

        return $movement->refresh();
    }
}
