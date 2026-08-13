<?php

namespace App\Console\Commands;

use App\Support\MorphMap;
use Illuminate\Console\Command;

/**
 * Verification gate for the Phase 0 morph-map backfill (rule 9).
 *
 * Non-zero remaining legacy App\Models\* / App\Notifications\* values → fail.
 */
class VerifyMorphMapCommand extends Command
{
    protected $signature = 'morph-map:verify';

    protected $description = 'Fail if polymorphic/class-name columns still hold legacy App\\Models\\* or App\\Notifications\\* FQCNs';

    public function handle(): int
    {
        $report = MorphMap::remainingLegacy();
        $failures = [];

        foreach ($report as $column => $info) {
            if ($info['count'] > 0) {
                $values = implode(', ', $info['values']);
                $failures[] = "{$column}: {$info['count']} row(s) — [{$values}]";
            }
        }

        if ($failures === []) {
            $this->info('morph-map:verify OK — no legacy App\\Models\\* or App\\Notifications\\* values remain.');

            return self::SUCCESS;
        }

        $this->error('morph-map:verify FAILED — legacy class names still present:');
        foreach ($failures as $line) {
            $this->error('  • '.$line);
        }
        $this->newLine();
        $this->error('Investigate unmapped classes before proceeding with deploy.');

        return self::FAILURE;
    }
}
