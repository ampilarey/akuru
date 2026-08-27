<?php

namespace App\Console\Commands;

use App\Domains\Offerings\Actions\VerifyHalaqaMirrorAction;
use Illuminate\Console\Command;

/**
 * F1 gate (rule 9): engine offering sessions are the authoritative read for
 * halaqa-linked offerings only while this is green.
 */
class VerifyHalaqaMirrorCommand extends Command
{
    protected $signature = 'halaqa:verify-mirror {--mirror-missing : Create engine sessions for unmirrored halaqa sessions (additive, idempotent)}';

    protected $description = 'Fail unless every halaqa session of a dual-write link has a mirrored engine session and no link is orphaned';

    public function handle(VerifyHalaqaMirrorAction $action): int
    {
        if ($this->option('mirror-missing')) {
            $created = $action->mirrorMissing();
            $this->info("Mirrored {$created} missing session(s).");
        }

        $report = $action->execute();
        $this->info(sprintf(
            'links=%d legacy_sessions=%d mirrored=%d missing=%d orphan_links=%d',
            $report['links'],
            $report['legacy_sessions'],
            $report['mirrored'],
            count($report['missing']),
            count($report['orphan_links']),
        ));

        if ($report['ok']) {
            $this->info('halaqa:verify-mirror OK — engine sessions are a complete mirror.');

            return self::SUCCESS;
        }

        foreach ($report['missing'] as $row) {
            $this->error(sprintf(
                '  missing mirror: program=%d hifz_session=%d "%s"',
                $row['hifz_program_id'],
                $row['hifz_session_id'],
                $row['title'],
            ));
        }
        foreach ($report['orphan_links'] as $row) {
            $this->error(sprintf(
                '  orphan link: hifz_session=%d -> engine_session=%d (engine row gone)',
                $row['hifz_session_id'],
                $row['course_offering_session_id'],
            ));
        }
        $this->error('halaqa:verify-mirror FAILED — do not treat engine sessions as authoritative for halaqa offerings.');

        return self::FAILURE;
    }
}
