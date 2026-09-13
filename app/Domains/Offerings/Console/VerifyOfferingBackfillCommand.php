<?php

namespace App\Domains\Offerings\Console;

use App\Domains\Offerings\Actions\BackfillCourseOfferingsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The gate for ROADMAP §3.4's backfill, in the shape rule 9 asks for:
 * *"verification scripts are gates, not suggestions."*
 *
 * Modelled on `students:verify-unification`, deliberately — including its
 * refusal to write on production. The two commands guard the same class of
 * risk (a migration whose second half was deferred), and a reader who knows
 * one should not have to learn a second vocabulary for the other.
 *
 * Without `--backfill` it only reports, so it is safe to run anywhere and is
 * the thing to paste into STATUS.md before the deploy it gates. A gate whose
 * evidence is not recorded has not run.
 */
class VerifyOfferingBackfillCommand extends Command
{
    protected $signature = 'offerings:verify-backfill
                            {--backfill : Run the idempotent backfill before verifying}';

    protected $description = 'Fail if any course has no offering, or any enrollment still has a null course_offering_id (ROADMAP §3.4)';

    public function handle(): int
    {
        $backfill = (bool) $this->option('backfill');

        if ($backfill && $this->laravel->isProduction()) {
            $this->error('Refusing --backfill on production. Restore a dump to a scratch database and run it there first (rule 9: additive migration + backfill + verify, as three deploys).');

            return self::FAILURE;
        }

        if ($backfill) {
            $report = app(BackfillCourseOfferingsAction::class)->execute();
            $this->line(sprintf(
                'Backfill: %d offering(s) created, %d filled from course columns, %d enrollment(s) repointed.',
                $report['offerings_created'],
                $report['columns_filled'],
                $report['enrollments_repointed'],
            ));
        }

        $coursesWithoutOffering = DB::table('courses')
            ->whereNull('deleted_at')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('course_offerings')
                ->whereColumn('course_offerings.course_id', 'courses.id')
                ->whereNull('course_offerings.deleted_at'))
            ->count();

        $unpointedEnrollments = DB::table('course_enrollments')
            ->whereNull('deleted_at')
            ->whereNull('course_offering_id')
            ->count();

        $this->line('Courses with no offering: '.$coursesWithoutOffering);
        $this->line('Enrollments with no course_offering_id: '.$unpointedEnrollments);

        if ($coursesWithoutOffering > 0 || $unpointedEnrollments > 0) {
            $this->error('offerings:verify-backfill FAILED — ROADMAP §3.4 is not complete. Run with --backfill on a non-production copy first, then re-verify.');

            return self::FAILURE;
        }

        $this->info('offerings:verify-backfill OK — every course has an offering and every enrollment points at one.');

        // Said plainly so nobody reads a green gate as more than it is: this
        // proves the write side of rule 9's first deploy. Reads still branch on
        // a nullable column, and §3.5's column drop is a later deploy again.
        $this->line('Reads are unchanged — this gate covers the backfill only, not the read switch (§3.5).');

        return self::SUCCESS;
    }
}
