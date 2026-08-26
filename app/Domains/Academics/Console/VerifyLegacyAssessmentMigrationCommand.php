<?php

namespace App\Domains\Academics\Console;

use App\Domains\Academics\Actions\MigrateLegacyAssessmentsAction;
use Illuminate\Console\Command;

class VerifyLegacyAssessmentMigrationCommand extends Command
{
    protected $signature = 'assessments:verify-legacy-migration
                            {--backfill : Run the idempotent quiz/assignment backfill before verifying}';

    protected $description = 'Fail if class quizzes or assignments remain unmapped onto engine assessments';

    public function handle(MigrateLegacyAssessmentsAction $action): int
    {
        $report = $this->option('backfill')
            ? $action->execute()
            : $action->verify();

        foreach ($report as $label => $row) {
            $this->line(sprintf(
                '%s: %d migrated / %d source, %d remaining',
                $label,
                $row['migrated'],
                $row['source'],
                count($row['remaining']),
            ));
            if ($row['remaining'] !== []) {
                $this->line('  remaining ids: '.implode(', ', $row['remaining']));
            }
        }

        if (! $action->isClean($report)) {
            $this->error('assessments:verify-legacy-migration FAILED — unmapped legacy rows remain (attach each quiz/assignment to a class, then re-run with --backfill).');

            return self::FAILURE;
        }

        $this->info('assessments:verify-legacy-migration OK — every class quiz, question, attempt, assignment, and submission maps onto the engine.');

        return self::SUCCESS;
    }
}
