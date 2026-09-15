<?php

namespace App\Console\Commands;

use App\Domains\Finance\Actions\VerifyPendingPayloadDrainAction;
use Illuminate\Console\Command;

/**
 * Phase 4 cleanup gate (rule 9, deploy 3). The legacy
 * `enrollment_pending_payload` read branch may be deleted, and the column
 * dropped, only while this is green **on the deployment being cleaned up**.
 *
 * It was the one Phase 4 gate with no command — a line of SQL inside a
 * 14,000-line status file, which an operator had to find and paste. Gates are
 * meant to be run, so it is a command like the others.
 */
class VerifyPendingPayloadDrainCommand extends Command
{
    protected $signature = 'payments:verify-payload-drain';

    protected $description = 'Fail unless no payment still depends on the legacy enrollment_pending_payload safety net';

    public function handle(VerifyPendingPayloadDrainAction $action): int
    {
        $report = $action->execute();

        $this->info(sprintf(
            'payments=%d with_payload=%d blocking=%d',
            $report['payments'],
            $report['with_payload'],
            $report['blocking'],
        ));

        if (! $report['ok']) {
            foreach ($report['blocking_rows'] as $row) {
                $this->error(sprintf(
                    '  still depends on the payload: payment=%d ref=%s status=%s created=%s',
                    $row['id'],
                    $row['reference'],
                    $row['status'],
                    $row['created_at'],
                ));
            }

            $this->error('payments:verify-payload-drain FAILED — the safety net is still load-bearing here.');

            return self::FAILURE;
        }

        // Green, but say which kind of green. A database that never wrote the
        // column answers `0` exactly like one that has drained, and treating
        // the two the same is how a gate becomes decoration (STATUS §5en).
        if ($report['vacuous']) {
            $this->warn('No payment here has ever carried a payload, so this run proves nothing');
            $this->warn('about any other deployment. Run it where the old payments actually live');
            $this->warn('before scheduling the cleanup deploy.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'payments:verify-payload-drain OK — %d payment(s) carried a payload and all of them have settled.',
            $report['with_payload'],
        ));

        return self::SUCCESS;
    }
}
