<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveStudentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $changedBy, ?Student $student = null): Student
    {
        return DB::transaction(function () use ($data, $changedBy, $student) {
            $creating = $student === null;
            $previousClassId = $creating ? null : $student->class_id;
            $student ??= new Student;

            $status = StudentStatus::from((string) ($data['status'] ?? StudentStatus::Active->value));
            $guardianId = $this->optionalId($data['guardian_id'] ?? null);
            $relationship = (string) ($data['guardian_relationship'] ?? GuardianRelationship::Guardian->value);
            $isPrimary = (bool) ($data['is_primary'] ?? true);
            $canPickup = (bool) ($data['can_pickup'] ?? true);
            $financial = (bool) ($data['financial_responsible'] ?? false);

            $payload = $this->payload($data);
            $this->fillSchoolFromClass($payload);
            $this->assertOptionalFks($payload);

            $student->fill($payload);
            $student->save();

            if ($creating || $student->status !== $status) {
                app(ChangeStudentStatusAction::class)->execute(
                    $student,
                    $status,
                    $changedBy,
                    $creating ? 'Created via student directory' : 'Status changed via student directory',
                    $payload['admission_date'] ?? now()->toDateString(),
                );
            }

            $this->syncClassRoster(
                $student->fresh(),
                $payload['class_id'] ?? null,
                $payload['admission_date'] ?? null,
                $previousClassId,
            );

            if ($creating && $guardianId !== null) {
                app(AttachGuardianAction::class)->execute(
                    $student->fresh(),
                    ParentGuardian::query()->findOrFail($guardianId),
                    $relationship,
                    $isPrimary,
                    $canPickup,
                    $financial,
                );
            }

            return $student->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $nullable = [
            'user_id',
            'school_id',
            'class_id',
            'student_id',
            'admission_date',
            'first_name_dhivehi',
            'last_name_dhivehi',
            'first_name_arabic',
            'last_name_arabic',
            'national_id',
            'passport',
            'email',
            'nationality',
            'place_of_birth',
            'phone',
            'address',
            'notes',
        ];

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
        ];

        foreach ($nullable as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            $payload[$key] = ($value === '' || $value === null) ? null : $value;
        }

        if (isset($payload['class_id'])) {
            $payload['class_id'] = $this->optionalId($payload['class_id']);
        }
        if (isset($payload['school_id'])) {
            $payload['school_id'] = $this->optionalId($payload['school_id']);
        }
        if (isset($payload['user_id'])) {
            $payload['user_id'] = $this->optionalId($payload['user_id']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fillSchoolFromClass(array &$payload): void
    {
        if (($payload['class_id'] ?? null) === null || ($payload['school_id'] ?? null) !== null) {
            return;
        }

        $schoolId = DB::table('classes')->where('id', $payload['class_id'])->value('school_id');
        if ($schoolId !== null) {
            $payload['school_id'] = (int) $schoolId;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertOptionalFks(array $payload): void
    {
        if (($payload['school_id'] ?? null) !== null && ! DB::table('schools')->where('id', $payload['school_id'])->exists()) {
            throw ValidationException::withMessages(['school_id' => 'School not found.']);
        }

        if (($payload['class_id'] ?? null) !== null && ! DB::table('classes')->where('id', $payload['class_id'])->exists()) {
            throw ValidationException::withMessages(['class_id' => 'Class not found.']);
        }

        if (($payload['user_id'] ?? null) !== null && ! DB::table('users')->where('id', $payload['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => 'User not found.']);
        }
    }

    private function syncClassRoster(Student $student, ?int $classId, mixed $enrolledAt, ?int $previousClassId): void
    {
        if ($previousClassId !== null && $previousClassId !== $classId) {
            DB::table('class_student')
                ->where('class_id', $previousClassId)
                ->where('student_id', $student->id)
                ->whereNull('left_at')
                ->update([
                    'left_at' => now()->toDateString(),
                    'status' => 'left',
                    'updated_at' => now(),
                ]);
        }

        if ($classId === null) {
            return;
        }

        $yearId = DB::table('classes')->where('id', $classId)->value('academic_year_id');
        if ($yearId === null) {
            throw ValidationException::withMessages(['class_id' => 'Class has no academic year.']);
        }

        $now = now();
        $enrolled = $enrolledAt ?: $now->toDateString();
        $existing = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            DB::table('class_student')->where('id', $existing->id)->update([
                'academic_year_id' => $yearId,
                'enrolled_at' => $enrolled,
                'left_at' => null,
                'status' => 'active',
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('class_student')->insert([
            'class_id' => $classId,
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'enrolled_at' => $enrolled,
            'left_at' => null,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
