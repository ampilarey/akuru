<?php

namespace App\Console\Commands;

use App\Domains\Offerings\Actions\VerifyHalaqaStructureAction;
use Illuminate\Console\Command;

/**
 * F2 gate (rule 9): engine structure may be treated as the authoritative
 * representation of Hifz programs only while this is green. Unresolved rows
 * are listed, never guessed.
 */
class VerifyHalaqaStructureCommand extends Command
{
    protected $signature = 'halaqa:verify-structure';

    protected $description = 'Fail unless every Hifz program, active enrollment, session and attendance row is mapped onto engine structure';

    public function handle(VerifyHalaqaStructureAction $action): int
    {
        $report = $action->execute();
        $this->info(sprintf(
            'programs=%d unmapped=%d enrollments=%d unlinked=%d sessions=%d unmirrored=%d attendance_expected=%d missing=%d',
            $report['programs'],
            count($report['unmapped_programs']),
            $report['enrollments'],
            count($report['unlinked_enrollments']),
            $report['sessions'],
            count($report['unmirrored_sessions']),
            $report['attendance_expected'],
            count($report['attendance_missing']),
        ));

        if ($report['ok']) {
            $this->info('halaqa:verify-structure OK — Hifz structure fully represented on the engine.');

            return self::SUCCESS;
        }

        foreach ($report['unmapped_programs'] as $row) {
            $this->error(sprintf('  unmapped program: %d "%s"', $row['hifz_program_id'], $row['name']));
        }
        foreach ($report['unlinked_enrollments'] as $row) {
            $this->error(sprintf(
                '  unlinked enrollment: program=%d hifz_enrollment=%d student=%d',
                $row['hifz_program_id'],
                $row['hifz_enrollment_id'],
                $row['student_id'],
            ));
        }
        foreach ($report['unmirrored_sessions'] as $row) {
            $this->error(sprintf(
                '  unmirrored session: program=%d hifz_session=%d',
                $row['hifz_program_id'],
                $row['hifz_session_id'],
            ));
        }
        foreach ($report['attendance_missing'] as $row) {
            $this->error(sprintf(
                '  missing attendance: hifz_session=%d student=%d',
                $row['hifz_session_id'],
                $row['student_id'],
            ));
        }
        $this->error('halaqa:verify-structure FAILED — do not treat engine structure as authoritative for Hifz.');

        return self::FAILURE;
    }
}
