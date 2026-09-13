<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Support\Facades\DB;

/**
 * ROADMAP §3.4's missing half.
 *
 * The split says: *"every existing course → course + one auto-created
 * offering … `course_enrollments` gain `course_offering_id` and are
 * repointed."* The 1B audit recorded on 2026-08-27 that **the backfill was
 * never written**, justified by ADR-021 (no real data), and that it
 * **"becomes mandatory before first real use"** — the same trigger that
 * reactivates rule 9 in full.
 *
 * Three consequences were left behind, and this closes all three:
 *
 *  1. Offerings are created **lazily** by `EnsureSelfLearningOfferingAction`
 *     on the next enrolment, so a course nobody has enrolled in since the
 *     split has no offering at all.
 *  2. `course_enrollments.course_offering_id` stays **null** on every legacy
 *     row, and read paths branch on it.
 *  3. The offering the lazy path creates **copies none of the legacy columns**,
 *     so `courses.seats` and `courses.enrollment_deadline` are still the only
 *     place the numbers live — which is why the public site still reads them
 *     and why §3.5's "drop offering columns" cleanup is blocked.
 *
 * **This is the backfill step of rule 9's three deploys, and only that.** It
 * writes the offering side and leaves every read where it is; switching reads
 * is the next deploy and dropping the legacy columns the one after. Nothing
 * here changes what any screen displays today.
 *
 * **Idempotent by construction.** Offerings are created through
 * `EnsureSelfLearningOfferingAction`, which returns the existing one rather
 * than making a second — so there is one creator for this shape in the
 * codebase, not a parallel one (rule 11). Column copying only fills values
 * that are still `null`, so a re-run never overwrites a number an admin has
 * since corrected on the offering.
 *
 * **Why self-learning**, where §3.4's parenthetical says "face-to-face or as
 * appropriate": the appropriate mode is the one the live read path looks for.
 * `DefaultSelfLearningOfferingAction` — which checkout uses to find an
 * offering and its price override — filters on `self_learning`. A backfill
 * that created face-to-face offerings would satisfy the sentence and leave
 * checkout still finding nothing.
 */
class BackfillCourseOfferingsAction
{
    /**
     * @return array{offerings_created: int, columns_filled: int, enrollments_repointed: int}
     */
    public function execute(?int $createdBy = null): array
    {
        $created = 0;
        $filled = 0;
        $repointed = 0;

        DB::table('courses')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select(['id', 'title', 'seats', 'fee', 'registration_fee_amount', 'start_date', 'end_date'])
            ->chunkById(100, function ($courses) use (&$created, &$filled, &$repointed, $createdBy) {
                foreach ($courses as $course) {
                    $before = CourseOffering::query()
                        ->where('course_id', $course->id)
                        ->exists();

                    $offering = app(EnsureSelfLearningOfferingAction::class)->execute(
                        ['id' => (int) $course->id, 'title' => (string) $course->title],
                        $createdBy,
                    );

                    if (! $before) {
                        $created++;
                    }

                    if ($this->fillFromCourse($offering, $course)) {
                        $filled++;
                    }

                    // Legacy enrolments predate the split and point at the
                    // course alone. They belong to the course's offering.
                    $repointed += DB::table('course_enrollments')
                        ->where('course_id', $course->id)
                        ->whereNull('course_offering_id')
                        ->update(['course_offering_id' => $offering->id]);
                }
            });

        return [
            'offerings_created' => $created,
            'columns_filled' => $filled,
            'enrollments_repointed' => $repointed,
        ];
    }

    /**
     * Move §3.4's listed columns onto the offering, without ever overwriting a
     * value the offering already carries — an admin who has corrected a seat
     * limit on the offering must not have it reverted by a re-run.
     */
    private function fillFromCourse(CourseOffering $offering, object $course): bool
    {
        $updates = [];

        if ($offering->seat_limit === null && $course->seats !== null && (int) $course->seats > 0) {
            $updates['seat_limit'] = (int) $course->seats;
        }

        // §3.4 moves `fee` to the offering. `registration_fee_amount` is the
        // column the engine actually charges from, so it wins when both exist.
        $price = $course->registration_fee_amount ?? $course->fee;
        if ($offering->price_override === null && $price !== null && (float) $price > 0) {
            $updates['price_override'] = round((float) $price, 2);
        }

        if ($offering->starts_at === null && $course->start_date !== null) {
            $updates['starts_at'] = $course->start_date;
        }

        if ($offering->ends_at === null && $course->end_date !== null) {
            $updates['ends_at'] = $course->end_date;
        }

        if ($updates === []) {
            return false;
        }

        $offering->forceFill($updates)->save();

        return true;
    }
}
