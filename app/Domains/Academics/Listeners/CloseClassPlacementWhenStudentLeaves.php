<?php

namespace App\Domains\Academics\Listeners;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\People\Events\StudentStatusChanged;

/**
 * A pupil who has left the Institute comes off the class register.
 *
 * ## What was wrong
 *
 * `students.status` and `class_student.status` were two records of the same
 * fact and nothing reconciled them. `PromoteStudentsAction` moves both together
 * at the end of a year — that pairing is in `docs/S1_SPEC.md` §121 — but the
 * mid-year path had no equivalent: marking a child `withdrawn` in the student
 * directory left their roster row `active`, and `SaveStudentAction` only closes
 * a placement when the **class** changes, not when the pupil leaves.
 *
 * So a withdrawn pupil stayed on the register grid, indistinguishable from the
 * child sitting next to them. That is not a cosmetic difference: the grid
 * defaults every row to **present**, so submitting the register recorded a
 * child who had left as having attended, and marking them absent instead sent
 * their guardian an absence SMS about a school they no longer attend.
 *
 * Nobody would see the disagreement either, because no screen shows a roster
 * row's pupil status next to it.
 *
 * ## Why a listener and not a cascade
 *
 * The roster is Academics'. People raising an event and this domain acting on
 * it keeps the write on the side that owns the table (rule 3), and follows the
 * path `StudentMarkedAbsent` → `SendAbsenceSms` already takes.
 *
 * ## What it does not do
 *
 * It does not backfill. Placements left open by a withdrawal before today stay
 * open, because closing them retroactively would write `left_at` dates that
 * nobody chose — and on a database with no real students yet there is nothing
 * to backfill. A backfill is the operator's call with a date they pick.
 */
class CloseClassPlacementWhenStudentLeaves
{
    public function handle(StudentStatusChanged $event): void
    {
        if (! $event->isDeparture()) {
            return;
        }

        ClassStudent::query()
            ->where('student_id', $event->studentId)
            ->where('status', ClassStudentStatus::Active->value)
            ->whereNull('left_at')
            ->update([
                'status' => ClassStudentStatus::Left->value,
                // The date the office gave for the departure, not today's: a
                // withdrawal recorded a week late still ended when it ended.
                'left_at' => $event->effectiveDate,
                'updated_at' => now(),
            ]);
    }
}
