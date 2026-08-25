<?php

namespace App\Domains\People\Console;

use App\Domains\People\Actions\UnifyStudentsAction;
use Illuminate\Console\Command;

/**
 * Verification gate for the S1.1b student unification backfill (rule 9).
 *
 * Zero unresolved mappings = Deploy 2 gate. Fail loud.
 */
class VerifyStudentUnificationCommand extends Command
{
    protected $signature = 'students:verify-unification
                            {--backfill : Run the idempotent backfill before verifying}';

    protected $description = 'Fail if registration_students are not mapped 1:1 to students, enrollments lack unified_student_id, or guardian pivots were lost';

    public function handle(UnifyStudentsAction $action): int
    {
        if ($this->option('backfill') && $this->laravel->isProduction()) {
            $this->error('Refusing --backfill on production. Restore a dump to a scratch database (docs/migrations/restore-production-copy.md) and run verify there.');

            return self::FAILURE;
        }

        $report = $this->option('backfill')
            ? $action->execute()
            : $action->verify();

        $this->line('S1.1b unification report: '.($report->path ?? 'not written'));
        $this->line(sprintf(
            '  mapped: user_id=%d national_id=%d name_dob=%d already=%d',
            $report->mapped['user_id'],
            $report->mapped['national_id'],
            $report->mapped['name_dob'],
            $report->mapped['already_mapped'],
        ));
        $this->line(sprintf(
            '  created: active=%d prospective=%d',
            $report->created['active'],
            $report->created['prospective'],
        ));
        $this->line(sprintf(
            '  guardians: source=%d migrated=%d profiles_created=%d skipped=%d',
            $report->guardians['source'],
            $report->guardians['migrated'],
            $report->guardians['created_profiles'],
            $report->guardians['skipped_existing'],
        ));
        $this->line(sprintf(
            '  enrollments: filled=%d already_set=%d missing=%d',
            $report->enrollments['filled'],
            $report->enrollments['already_set'],
            count($report->enrollments['missing']),
        ));

        if ($report->ambiguous !== []) {
            $this->warn('  ambiguous: '.count($report->ambiguous).' (listed in report file)');
        }
        if ($report->collisions !== []) {
            $this->warn('  collisions: '.count($report->collisions).' (listed in report file)');
        }

        if ($report->passed()) {
            $this->info('students:verify-unification OK — every registration_student maps to exactly one student; enrollments and guardian pivots are complete.');

            return self::SUCCESS;
        }

        $this->error('students:verify-unification FAILED — unresolved unification rows:');
        foreach ($report->verification['failures'] as $failure) {
            $this->error('  • '.$failure);
        }
        $this->newLine();
        $this->error('Resolve ambiguous/colliding rows before Deploy 2 (switch reads).');

        return self::FAILURE;
    }
}
