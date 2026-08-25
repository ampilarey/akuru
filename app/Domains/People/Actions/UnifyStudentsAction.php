<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;
use App\Domains\People\Support\StudentUnificationReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * S1.1b Deploy 1 backfill: map registration_students → students, migrate
 * guardian pivots, fill course_enrollments.unified_student_id.
 *
 * Does not switch reads. Does not import Courses/Identity models (rule 3).
 * Idempotent. Ambiguous / colliding rows are reported, not guessed.
 */
class UnifyStudentsAction
{
    /**
     * Normalized national_id values that must not be used as a match key
     * (blank is handled separately). Built once per execute().
     *
     * @var array<string, true>
     */
    private array $unusableNationalIds = [];

    public function execute(): StudentUnificationReport
    {
        $report = StudentUnificationReport::empty();
        $this->unusableNationalIds = $this->discoverUnusableNationalIds();

        DB::transaction(function () use ($report): void {
            $this->mapRegistrationStudents($report);
            $this->migrateGuardians($report);
            $this->backfillEnrollments($report);
            $this->verifyInto($report);
        });

        $report->write();

        return $report;
    }

    public function verify(): StudentUnificationReport
    {
        $report = StudentUnificationReport::load();
        $this->verifyInto($report);
        $report->write();

        return $report;
    }

    private function mapRegistrationStudents(StudentUnificationReport $report): void
    {
        $ids = RegistrationStudent::query()->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            $rs = RegistrationStudent::query()->find($id);
            if ($rs === null) {
                continue;
            }

            $this->mapOne($rs, $report);
        }
    }

    private function mapOne(RegistrationStudent $rs, StudentUnificationReport $report): void
    {
        $already = Student::query()
            ->where('legacy_registration_student_id', $rs->id)
            ->orderBy('id')
            ->get();

        if ($already->count() > 1) {
            $report->unresolved[] = [
                'registration_student_id' => $rs->id,
                'reason' => 'duplicate_legacy_mapping',
                'student_ids' => $already->pluck('id')->all(),
            ];

            return;
        }

        if ($already->count() === 1) {
            $this->fillMissingFields($already->first(), $rs);
            $report->mapped['already_mapped']++;

            return;
        }

        foreach ([
            'user_id' => fn () => $this->candidatesByUserId($rs),
            'national_id' => fn () => $this->candidatesByNationalId($rs),
            'name_dob' => fn () => $this->candidatesByNameDob($rs),
        ] as $method => $finder) {
            $candidates = $finder();
            if ($candidates->isEmpty()) {
                continue;
            }

            if ($candidates->count() > 1) {
                $this->recordAmbiguous($rs, $method, $candidates, $report);

                return;
            }

            $candidate = $candidates->first();
            if ($method === 'national_id' && $this->nameDobContradicts($rs, $candidate)) {
                $this->recordAmbiguous($rs, $method, $candidates, $report, 'name_dob_contradiction');

                return;
            }

            $this->link($rs, $candidate, $method, $report);

            return;
        }

        $this->createFromRegistration($rs, $report);
    }

    /**
     * @return Collection<int, Student>
     */
    private function candidatesByUserId(RegistrationStudent $rs): Collection
    {
        if ($rs->user_id === null) {
            return collect();
        }

        return Student::query()->where('user_id', $rs->user_id)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Student>
     */
    private function candidatesByNationalId(RegistrationStudent $rs): Collection
    {
        $nationalId = $this->plain($rs->national_id);
        if ($nationalId === null || $this->isUnusableNationalId($nationalId)) {
            return collect();
        }

        return Student::query()->where('national_id', $nationalId)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Student>
     */
    private function candidatesByNameDob(RegistrationStudent $rs): Collection
    {
        $first = trim((string) $rs->first_name);
        $last = trim((string) $rs->last_name);
        $dob = $rs->dob?->toDateString();

        if ($first === '' || $last === '' || $dob === null) {
            return collect();
        }

        return Student::query()
            ->where('first_name', $first)
            ->where('last_name', $last)
            ->whereDate('date_of_birth', $dob)
            ->orderBy('id')
            ->get();
    }

    /**
     * True when the RS has a complete name+dob key that does not include this
     * national_id candidate. Incomplete keys cannot contradict (no fallthrough
     * signal). A contradiction is ambiguous — do not fall through to name_dob.
     */
    private function nameDobContradicts(RegistrationStudent $rs, Student $candidate): bool
    {
        $first = trim((string) $rs->first_name);
        $last = trim((string) $rs->last_name);
        $dob = $rs->dob?->toDateString();

        if ($first === '' || $last === '' || $dob === null) {
            return false;
        }

        return ! $this->candidatesByNameDob($rs)->contains(
            fn (Student $student): bool => $student->id === $candidate->id
        );
    }

    /**
     * @param  Collection<int, Student>  $candidates
     */
    private function recordAmbiguous(
        RegistrationStudent $rs,
        string $method,
        Collection $candidates,
        StudentUnificationReport $report,
        ?string $reason = null,
    ): void {
        $row = [
            'registration_student_id' => $rs->id,
            'method' => $method,
            'candidate_student_ids' => $candidates->pluck('id')->all(),
        ];
        if ($reason !== null) {
            $row['reason'] = $reason;
        }

        $report->ambiguous[] = $row;
        $report->unresolved[] = [
            'registration_student_id' => $rs->id,
            'reason' => 'ambiguous',
            'method' => $method,
        ];
    }

    /**
     * @return array<string, true>
     */
    private function discoverUnusableNationalIds(): array
    {
        $unusable = [];

        foreach (config('unification.national_id_placeholders', []) as $placeholder) {
            $normalized = $this->normalizedNationalId((string) $placeholder);
            if ($normalized !== null) {
                $unusable[$normalized] = true;
            }
        }

        $rsCounts = [];
        foreach (RegistrationStudent::query()->orderBy('id')->get() as $rs) {
            $normalized = $this->normalizedNationalId($this->plain($rs->national_id));
            if ($normalized === null) {
                continue;
            }
            $rsCounts[$normalized] = ($rsCounts[$normalized] ?? 0) + 1;
        }

        $studentCounts = [];
        foreach (Student::query()->orderBy('id')->get(['id', 'national_id']) as $student) {
            $normalized = $this->normalizedNationalId($this->plain($student->national_id));
            if ($normalized === null) {
                continue;
            }
            $studentCounts[$normalized] = ($studentCounts[$normalized] ?? 0) + 1;
        }

        foreach ([$rsCounts, $studentCounts] as $counts) {
            foreach ($counts as $nationalId => $count) {
                if ($count > 1) {
                    $unusable[$nationalId] = true;
                }
            }
        }

        return $unusable;
    }

    private function isUnusableNationalId(string $nationalId): bool
    {
        $normalized = $this->normalizedNationalId($nationalId);

        return $normalized === null || isset($this->unusableNationalIds[$normalized]);
    }

    private function normalizedNationalId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function link(
        RegistrationStudent $rs,
        Student $student,
        string $method,
        StudentUnificationReport $report,
    ): void {
        if ($student->legacy_registration_student_id === $rs->id) {
            $this->fillMissingFields($student, $rs);
            $report->mapped['already_mapped']++;

            return;
        }

        if ($student->legacy_registration_student_id !== null) {
            $report->collisions[] = [
                'registration_student_id' => $rs->id,
                'student_id' => $student->id,
                'existing_legacy_registration_student_id' => $student->legacy_registration_student_id,
                'method' => $method,
            ];
            $report->unresolved[] = [
                'registration_student_id' => $rs->id,
                'reason' => 'collision',
                'method' => $method,
                'student_id' => $student->id,
            ];

            return;
        }

        $student->legacy_registration_student_id = $rs->id;
        $this->fillMissingFields($student, $rs);
        $report->mapped[$method]++;
    }

    private function createFromRegistration(RegistrationStudent $rs, StudentUnificationReport $report): void
    {
        $hasActiveEnrollment = DB::table('course_enrollments')
            ->where('student_id', $rs->id)
            ->where('status', 'active')
            ->exists();

        $status = $hasActiveEnrollment ? StudentStatus::Active : StudentStatus::Prospective;

        $student = new Student;
        $student->forceFill([
            'user_id' => $rs->user_id,
            'school_id' => null,
            'class_id' => null,
            'student_id' => null,
            'admission_date' => null,
            'first_name' => $rs->first_name,
            'last_name' => $rs->last_name,
            'date_of_birth' => $rs->dob,
            'gender' => $rs->gender ?? 'male',
            'national_id' => $this->plain($rs->national_id),
            'passport' => $this->plain($rs->passport),
            'legacy_registration_student_id' => $rs->id,
            'status' => $status,
        ]);
        $student->save();

        $report->created[$status->value]++;
    }

    private function fillMissingFields(Student $student, RegistrationStudent $rs): void
    {
        $passport = $this->plain($rs->passport);
        if ($this->plain($student->passport) === null && $passport !== null) {
            $student->passport = $passport;
        }

        $nationalId = $this->plain($rs->national_id);
        if ($this->plain($student->national_id) === null && $nationalId !== null) {
            $student->national_id = $nationalId;
        }

        if ($student->isDirty()) {
            $student->save();
        }
    }

    private function migrateGuardians(StudentUnificationReport $report): void
    {
        $rows = DB::table('student_guardians')->orderBy('id')->get();
        $report->guardians['source'] = $rows->count();

        foreach ($rows as $row) {
            $student = Student::query()
                ->where('legacy_registration_student_id', $row->student_id)
                ->first();

            if ($student === null) {
                $report->guardians['unmapped'][] = $row->id;

                continue;
            }

            $parent = ParentGuardian::query()
                ->where('user_id', $row->guardian_user_id)
                ->orderBy('id')
                ->first();

            if ($parent === null) {
                $parent = $this->createParentFromUserId(
                    (int) $row->guardian_user_id,
                    is_string($row->relationship) ? $row->relationship : null,
                );

                if ($parent === null) {
                    $report->guardians['unmapped'][] = $row->id;

                    continue;
                }

                $report->guardians['created_profiles']++;
            }

            $exists = DB::table('guardian_student')
                ->where('guardian_id', $parent->id)
                ->where('student_id', $student->id)
                ->exists();

            if ($exists) {
                $report->guardians['skipped_existing']++;

                continue;
            }

            DB::table('guardian_student')->insert([
                'guardian_id' => $parent->id,
                'student_id' => $student->id,
                'relationship' => $this->normalizeRelationship(
                    is_string($row->relationship) ? $row->relationship : null
                ),
                'is_primary' => (bool) $row->is_primary,
                'can_pickup' => true,
                'financial_responsible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $report->guardians['migrated']++;
        }
    }

    private function createParentFromUserId(int $userId, ?string $relationship): ?ParentGuardian
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if ($user === null) {
            return null;
        }

        $parts = preg_split('/\s+/', trim((string) ($user->name ?? '')), 2) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';

        if ($first === '') {
            $first = 'Guardian';
        }
        if ($last === '') {
            $last = '—';
        }

        return ParentGuardian::query()->create([
            'user_id' => $userId,
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $this->plain(isset($user->phone) ? (string) $user->phone : null) ?? '—',
            'email' => $this->plain(isset($user->email) ? (string) $user->email : null) ?? "guardian-{$userId}@unification.invalid",
            'address' => $this->plain(isset($user->address) ? (string) $user->address : null) ?? '—',
            'relationship' => $this->normalizeRelationship($relationship),
        ]);
    }

    private function normalizeRelationship(?string $relationship): string
    {
        if ($relationship !== null && GuardianRelationship::tryFrom($relationship) !== null) {
            return $relationship;
        }

        return GuardianRelationship::Guardian->value;
    }

    private function backfillEnrollments(StudentUnificationReport $report): void
    {
        $enrollments = DB::table('course_enrollments')->orderBy('id')->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->unified_student_id !== null) {
                $report->enrollments['already_set']++;

                continue;
            }

            $student = Student::query()
                ->where('legacy_registration_student_id', $enrollment->student_id)
                ->first();

            if ($student === null) {
                $report->enrollments['missing'][] = $enrollment->id;

                continue;
            }

            DB::table('course_enrollments')->where('id', $enrollment->id)->update([
                'unified_student_id' => $student->id,
                'updated_at' => now(),
            ]);

            $report->enrollments['filled']++;
        }
    }

    private function verifyInto(StudentUnificationReport $report): void
    {
        $failures = [];

        $registrationIds = DB::table('registration_students')->orderBy('id')->pluck('id');
        $legacyCounts = DB::table('students')
            ->whereNotNull('legacy_registration_student_id')
            ->select('legacy_registration_student_id', DB::raw('count(*) as aggregate'))
            ->groupBy('legacy_registration_student_id')
            ->pluck('aggregate', 'legacy_registration_student_id');

        foreach ($registrationIds as $registrationId) {
            $count = (int) ($legacyCounts[$registrationId] ?? 0);
            if ($count !== 1) {
                $failures[] = "registration_students.id={$registrationId} maps to {$count} student(s)";
            }
        }

        $unfilled = DB::table('course_enrollments')->whereNull('unified_student_id')->count();
        if ($unfilled > 0) {
            $failures[] = "{$unfilled} course_enrollments missing unified_student_id";
        }

        $sourceGuardians = DB::table('student_guardians')->count();
        $migratedGuardians = DB::table('guardian_student')
            ->join('students', 'students.id', '=', 'guardian_student.student_id')
            ->whereNotNull('students.legacy_registration_student_id')
            ->count();

        if ($sourceGuardians !== $migratedGuardians) {
            $failures[] = "guardian pivot count mismatch: student_guardians={$sourceGuardians} migrated guardian_student={$migratedGuardians}";
        }

        $report->verification = [
            'ok' => $failures === [],
            'failures' => $failures,
        ];
    }

    private function plain(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
