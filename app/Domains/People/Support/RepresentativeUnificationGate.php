<?php

namespace App\Domains\People\Support;

use Illuminate\Support\Facades\DB;

final class RepresentativeUnificationGate
{
    public const MANIFEST_FILENAME = 'unification-representative-manifest.json';

    public static function manifestPath(): string
    {
        return storage_path('app/'.self::MANIFEST_FILENAME);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadManifest(): array
    {
        $path = self::manifestPath();
        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($path), true) ?: [];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{ok: bool, failures: list<string>}
     */
    public static function evaluate(StudentUnificationReport $report, array $manifest): array
    {
        $failures = [];

        $resolvable = array_map('intval', $manifest['resolvable_registration_student_ids'] ?? []);
        $expectedUnresolved = array_map('intval', $manifest['expected_unresolved_registration_student_ids'] ?? []);
        $expectedUnmappedGuardians = array_map('intval', $manifest['expected_unmapped_guardian_pivot_ids'] ?? []);
        $expectedMissingEnrollments = array_map('intval', $manifest['expected_missing_enrollment_ids'] ?? []);

        $mappedLegacy = DB::table('students')
            ->whereNotNull('legacy_registration_student_id')
            ->pluck('id', 'legacy_registration_student_id');

        foreach ($resolvable as $rsId) {
            $count = DB::table('students')->where('legacy_registration_student_id', $rsId)->count();
            if ($count !== 1) {
                $failures[] = "resolvable registration_students.id={$rsId} maps to {$count} student(s)";
            }
        }

        $actualUnresolved = array_map(
            'intval',
            array_column($report->unresolved, 'registration_student_id')
        );
        sort($actualUnresolved);
        $expectedUnresolvedSorted = $expectedUnresolved;
        sort($expectedUnresolvedSorted);
        if ($actualUnresolved !== $expectedUnresolvedSorted) {
            $failures[] = 'unexpected unresolved set: got ['.implode(',', $actualUnresolved).'] expected ['.implode(',', $expectedUnresolvedSorted).']';
        }

        foreach ($expectedUnresolved as $rsId) {
            if (isset($mappedLegacy[$rsId])) {
                $failures[] = "unguessable registration_students.id={$rsId} was linked (ADR-007 forbids guessing)";
            }
        }

        $unmappedGuardians = array_map('intval', $report->guardians['unmapped'] ?? []);
        foreach ($expectedUnmappedGuardians as $pivotId) {
            if (! in_array($pivotId, $unmappedGuardians, true)) {
                $failures[] = "expected orphan guardian pivot {$pivotId} was not reported unmapped";
            }
        }

        $missingEnrollments = array_map('intval', $report->enrollments['missing'] ?? []);
        sort($missingEnrollments);
        $expectedMissingSorted = $expectedMissingEnrollments;
        sort($expectedMissingSorted);
        if ($missingEnrollments !== $expectedMissingSorted) {
            $failures[] = 'unexpected missing enrollments: got ['.implode(',', $missingEnrollments).'] expected ['.implode(',', $expectedMissingSorted).']';
        }

        if (($report->matcher['national_id_unusable_skips'] ?? 0) < 1) {
            $failures[] = 'national_id unusable skips were 0 (duplicate/blank/placeholder cases did not run)';
        }
        if (($report->matcher['national_id_contradiction_fallthroughs'] ?? 0) < 1) {
            $failures[] = 'national_id contradiction fallthroughs were 0 (A2 corroboration did not run)';
        }

        /** @var array<string, mixed> $scenarios */
        $scenarios = $manifest['scenarios'] ?? [];
        foreach ($scenarios as $name => $row) {
            if (! is_array($row) || ! isset($row['registration_student_id'], $row['student_id'])) {
                continue;
            }
            $rsId = (int) $row['registration_student_id'];
            $expectedStudentId = (int) $row['student_id'];
            $actual = $mappedLegacy[$rsId] ?? null;
            if ((int) $actual !== $expectedStudentId) {
                $failures[] = "scenario {$name}: RS {$rsId} linked to student ".(string) ($actual ?? 'null')." not {$expectedStudentId} (wrong-student risk)";
            }
        }

        $wrongStudentId = isset($scenarios['nid_contradiction_wrong_student_id'])
            ? (int) $scenarios['nid_contradiction_wrong_student_id']
            : 0;
        if ($wrongStudentId > 0) {
            $legacy = DB::table('students')->where('id', $wrongStudentId)->value('legacy_registration_student_id');
            if ($legacy !== null) {
                $failures[] = "contradiction wrong-hit student {$wrongStudentId} received a legacy mapping";
            }
        }

        return [
            'ok' => $failures === [],
            'failures' => $failures,
        ];
    }

    public static function fillPaymentUnifiedIds(): int
    {
        $updated = 0;

        foreach (DB::table('payments')->whereNull('unified_student_id')->whereNotNull('student_id')->orderBy('id')->get() as $payment) {
            $unifiedId = DB::table('students')
                ->where('legacy_registration_student_id', $payment->student_id)
                ->value('id');
            if ($unifiedId === null) {
                continue;
            }
            DB::table('payments')->where('id', $payment->id)->update([
                'unified_student_id' => $unifiedId,
                'updated_at' => now(),
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    public static function paymentWrongStudentFailures(array $manifest): array
    {
        $failures = [];
        /** @var array<string, mixed> $scenarios */
        $scenarios = $manifest['scenarios'] ?? [];

        foreach ($scenarios as $name => $row) {
            if (! is_array($row) || ! isset($row['registration_student_id'], $row['student_id'])) {
                continue;
            }
            $rsId = (int) $row['registration_student_id'];
            $expectedStudentId = (int) $row['student_id'];
            $unified = DB::table('payments')->where('student_id', $rsId)->value('unified_student_id');
            if ((int) $unified !== $expectedStudentId) {
                $failures[] = "payment for scenario {$name} resolved to student ".(string) ($unified ?? 'null')." not {$expectedStudentId}";
            }
        }

        foreach (array_map('intval', $manifest['expected_unresolved_registration_student_ids'] ?? []) as $rsId) {
            $unified = DB::table('payments')->where('student_id', $rsId)->value('unified_student_id');
            if ($unified !== null) {
                $failures[] = "payment for unguessable RS {$rsId} resolved to student {$unified} (must stay null)";
            }
        }

        return $failures;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireManifest(): array
    {
        $manifest = self::loadManifest();
        if ($manifest === []) {
            throw new \RuntimeException('Representative manifest missing. Seed UnificationRepresentativeSeeder first.');
        }

        return $manifest;
    }
}
