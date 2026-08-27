<?php

namespace App\Console\Commands;

use App\Domains\Courses\Components\Quran\Actions\SyncHifzMilestoneProgressAction;
use App\Domains\Offerings\Actions\BackfillHalaqaStructureAction;
use App\Support\Contracts\HalaqaReferenceReader;
use Illuminate\Console\Command;

/**
 * F2 (rule 9 additive step): map Hifz programs/enrollments/sessions/attendance
 * onto engine structure, then sync milestone progress per program. Idempotent;
 * additive only. Gate the read switch with halaqa:verify-structure.
 */
class BackfillHalaqaStructureCommand extends Command
{
    protected $signature = 'halaqa:backfill-structure {--by= : User id recorded as creator on backfilled rows}';

    protected $description = 'Backfill engine Course/Offering/enrollment/attendance structure for every Hifz program (idempotent, additive)';

    public function handle(BackfillHalaqaStructureAction $action): int
    {
        $by = $this->option('by') !== null ? (int) $this->option('by') : null;
        $report = $action->execute($by);
        $this->info(sprintf(
            'programs=%d mapped=%d sessions_mirrored=%d enrollments_linked=%d attendance_written=%d',
            $report['programs'],
            $report['programs_mapped'],
            $report['sessions_mirrored'],
            $report['enrollments_linked'],
            $report['attendance_written'],
        ));

        $evaluated = 0;
        $completed = 0;
        foreach (app(HalaqaReferenceReader::class)->listPrograms() as $program) {
            $sync = app(SyncHifzMilestoneProgressAction::class)->execute((int) $program['id']);
            $evaluated += $sync['evaluated'];
            $completed += $sync['completed'];
        }
        $this->info(sprintf('milestone progress: evaluated=%d completed=%d', $evaluated, $completed));

        $this->info('halaqa:backfill-structure done — run halaqa:verify-structure before switching reads.');

        return self::SUCCESS;
    }
}
