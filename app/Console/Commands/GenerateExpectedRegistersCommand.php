<?php

namespace App\Console\Commands;

use App\Domains\Academics\Actions\GenerateExpectedRegistersAction;
use Illuminate\Console\Command;

class GenerateExpectedRegistersCommand extends Command
{
    protected $signature = 'registers:generate-expected
                            {--year= : Academic year id}
                            {--from= : Start date (Y-m-d), default today}
                            {--to= : End date (Y-m-d), default today}';

    protected $description = 'Create expected class registers from the timetable (calendar-aware, idempotent)';

    public function handle(GenerateExpectedRegistersAction $action): int
    {
        $year = $this->option('year') !== null && $this->option('year') !== ''
            ? (int) $this->option('year')
            : null;
        $from = $this->option('from') ?: now()->toDateString();
        $to = $this->option('to') ?: now()->addDays(14)->toDateString();

        $result = $action->execute($year, $from, $to);
        $this->info("Created {$result['created']} expected registers ({$result['skipped']} already present).");

        return self::SUCCESS;
    }
}
