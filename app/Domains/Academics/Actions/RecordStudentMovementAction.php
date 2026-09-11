<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\MovementDirection;
use App\Domains\Academics\Enums\MovementSource;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\StudentMovement;
use Illuminate\Validation\ValidationException;

/**
 * The single writer for `student_movements` (rule 11).
 *
 * A card reader, a QR scanner and a member of staff with a tablet all record
 * the same fact through here, differing only in `source`. That is what makes
 * the hardware question the plan flags a *binding* decision rather than a
 * rewrite.
 *
 * **It does not refuse two arrivals in a row.** A child who leaves for a
 * dentist and comes back produces in/out/in/out; a gate log that started at
 * 10am produces an `out` with no `in`. Both are ordinary. Refusing the second
 * `in` would leave somebody at the gate unable to record what they are looking
 * at, and a member of staff who cannot record what they see stops using the
 * system — which is precisely the *"manual log nobody fills"* the plan warns
 * about. The console shows the child's current state instead, so the operator
 * can see the oddity and decide.
 *
 * The one thing it does refuse is an accident: the same direction for the same
 * child within two minutes returns the row that already exists rather than
 * making a second one. That is a double tap, not a child who left and returned
 * inside two minutes.
 */
class RecordStudentMovementAction
{
    private const DOUBLE_TAP_SECONDS = 120;

    public function execute(
        int $studentId,
        MovementDirection $direction,
        ?int $recordedBy = null,
        MovementSource $source = MovementSource::Manual,
        ?string $note = null,
    ): StudentMovement {
        $now = now();

        $recent = StudentMovement::query()
            ->live()
            ->where('student_id', $studentId)
            ->where('direction', $direction->value)
            ->where('at', '>=', $now->copy()->subSeconds(self::DOUBLE_TAP_SECONDS))
            ->latest('at')
            ->first();

        if ($recent !== null) {
            return $recent;
        }

        $yearId = (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            throw ValidationException::withMessages([
                'movement' => 'No academic year is active, so there is nothing to record this against.',
            ]);
        }

        return StudentMovement::query()->create([
            'academic_year_id' => $yearId,
            'student_id' => $studentId,
            'direction' => $direction->value,
            'at' => $now,
            'recorded_by' => $recordedBy,
            'source' => $source->value,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);
    }
}
