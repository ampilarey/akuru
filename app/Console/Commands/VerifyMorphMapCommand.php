<?php

namespace App\Console\Commands;

use App\Support\MorphMap;
use Illuminate\Console\Command;

/**
 * Verification gate for the Phase 0 morph-map backfill (rule 9).
 *
 * Morph columns must contain no backslashes (no raw FQCNs of any era).
 * notifications.type must not hold old App\Notifications\* or unexpected domain classes.
 */
class VerifyMorphMapCommand extends Command
{
    protected $signature = 'morph-map:verify';

    protected $description = 'Fail if morph columns still hold FQCNs (backslash invariant) or notifications.type is not a known-good class';

    public function handle(): int
    {
        $report = MorphMap::lastReport();
        if ($report !== null) {
            $collapseCounts = $report['collapse_counts'] ?? [];
            if ($collapseCounts === []) {
                $this->info('Collapse report: no composite-key duplicates were merged.');
            } else {
                $this->warn('Collapse report (duplicate pivots merged before rewrite):');
                foreach ($collapseCounts as $table => $count) {
                    $this->warn("  • {$table}: {$count} group(s) collapsed");
                }
                foreach ($report['collapses'] ?? [] as $collapse) {
                    $dropped = implode(', ', $collapse['dropped_model_types'] ?? []);
                    $pivot = isset($collapse['role_id'])
                        ? 'role_id='.$collapse['role_id']
                        : 'permission_id='.($collapse['permission_id'] ?? '?');
                    $this->line(sprintf(
                        '    - %s %s model_id=%s kept=%s dropped=[%s] → %s',
                        $collapse['table'],
                        $pivot,
                        $collapse['model_id'],
                        $collapse['kept_model_type'],
                        $dropped,
                        $collapse['target_alias']
                    ));
                }
            }
            $this->newLine();
        }

        $remaining = MorphMap::remainingLegacy();
        $failures = [];

        foreach ($remaining as $column => $info) {
            if ($info['count'] > 0) {
                $values = implode(', ', $info['values']);
                $failures[] = "{$column}: {$info['count']} row(s) — [{$values}]";
            }
        }

        if ($failures === []) {
            $this->info('morph-map:verify OK — morph columns have no FQCNs; notifications.type is clean.');

            return self::SUCCESS;
        }

        $this->error('morph-map:verify FAILED — raw class names still present:');
        foreach ($failures as $line) {
            $this->error('  • '.$line);
        }
        $this->newLine();
        $this->error('Investigate unmapped classes before proceeding with deploy.');

        return self::FAILURE;
    }
}
