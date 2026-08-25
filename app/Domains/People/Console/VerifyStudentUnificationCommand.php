<?php

namespace App\Domains\People\Console;

use App\Domains\People\Actions\UnifyStudentsAction;
use App\Domains\People\Support\RepresentativeUnificationGate;
use Database\Seeders\UnificationRepresentativeSeeder;
use Illuminate\Console\Command;

/**
 * Verification gate for the S1.1b student unification backfill (rule 9 / ADR-021).
 *
 * Default: DB-authoritative strict verify (every RS 1:1).
 * --representative: seeded A2 dataset; expected unguessable rows allowed.
 */
class VerifyStudentUnificationCommand extends Command
{
    protected $signature = 'students:verify-unification
                            {--backfill : Run the idempotent backfill before verifying}
                            {--representative : Seed the ADR-021 representative dataset, backfill, then gate (refuses production)}';

    protected $description = 'Fail if registration_students are not mapped 1:1 to students, enrollments lack unified_student_id, or guardian pivots were lost';

    public function handle(UnifyStudentsAction $action): int
    {
        $representative = (bool) $this->option('representative');
        $backfill = (bool) $this->option('backfill') || $representative;

        if (($backfill || $representative) && $this->laravel->isProduction()) {
            $this->error('Refusing --backfill/--representative on production. Restore a dump to a scratch database (docs/migrations/restore-production-copy.md) or run --representative against a non-production database (ADR-021).');

            return self::FAILURE;
        }

        if ($representative) {
            $this->call('db:seed', [
                '--class' => UnificationRepresentativeSeeder::class,
                '--force' => true,
            ]);
        }

        $report = $backfill
            ? $action->execute()
            : $action->verify();

        if ($representative) {
            RepresentativeUnificationGate::fillPaymentUnifiedIds();
        }

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
            '  A2 matcher national_id_unusable_skips=%d',
            $report->matcher['national_id_unusable_skips'],
        ));
        $this->line(sprintf(
            '  A2 matcher national_id_contradiction_fallthroughs=%d',
            $report->matcher['national_id_contradiction_fallthroughs'],
        ));
        $this->line(sprintf(
            '  guardians: source=%d migrated=%d profiles_created=%d skipped=%d unmapped=%d',
            $report->guardians['source'],
            $report->guardians['migrated'],
            $report->guardians['created_profiles'],
            $report->guardians['skipped_existing'],
            count($report->guardians['unmapped'] ?? []),
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

        if ($representative) {
            $manifest = RepresentativeUnificationGate::requireManifest();
            $gate = RepresentativeUnificationGate::evaluate($report, $manifest);
            $paymentFailures = RepresentativeUnificationGate::paymentWrongStudentFailures($manifest);
            $gate['failures'] = array_merge($gate['failures'], $paymentFailures);
            $gate['ok'] = $gate['failures'] === [];
            RepresentativeUnificationGate::applyManifestVerdict($report, $manifest, $gate['failures']);

            $this->line(sprintf(
                '  verification verdict=%s raw_ok=%s unexpected_failures=%d',
                $report->verification['verdict'],
                $report->verification['raw_ok'] ? 'true' : 'false',
                count($report->verification['unexpected_failures']),
            ));

            if ($report->verification['unexpected_failures'] === []) {
                $this->info('students:verify-unification OK — representative dataset: resolvable rows mapped; expected unguessable rows listed; no enrollment/payment resolved to the wrong student.');

                return self::SUCCESS;
            }

            $this->error('students:verify-unification FAILED — unexpected unification rows (not in seeder manifest):');
            foreach ($report->verification['unexpected_failures'] as $failure) {
                $this->error('  • '.$failure);
            }

            return self::FAILURE;
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
