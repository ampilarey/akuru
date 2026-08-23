<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Deploy 2 dual-write: student_guardians (legacy) + guardian_student (unified).
 */
class LinkGuardianDualWriteAction
{
    public function execute(
        int $guardianUserId,
        RegistrationStudent $rs,
        ?string $relationship = null,
        bool $isPrimary = true,
    ): void {
        $relationship = $this->normalizeRelationship($relationship);

        $legacyExists = DB::table('student_guardians')
            ->where('student_id', $rs->id)
            ->where('guardian_user_id', $guardianUserId)
            ->exists();

        if (! $legacyExists) {
            DB::table('student_guardians')->insert([
                'student_id' => $rs->id,
                'guardian_user_id' => $guardianUserId,
                'relationship' => $relationship,
                'is_primary' => $isPrimary,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $student = Student::query()->where('legacy_registration_student_id', $rs->id)->first()
            ?? app(DualWriteCourseStudentAction::class)->sync($rs);

        $parent = ParentGuardian::query()->where('user_id', $guardianUserId)->orderBy('id')->first()
            ?? $this->createParentFromUserId($guardianUserId, $relationship);

        if ($parent === null) {
            return;
        }

        $exists = DB::table('guardian_student')
            ->where('guardian_id', $parent->id)
            ->where('student_id', $student->id)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('guardian_student')->insert([
            'guardian_id' => $parent->id,
            'student_id' => $student->id,
            'relationship' => $relationship,
            'is_primary' => $isPrimary,
            'can_pickup' => true,
            'financial_responsible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createParentFromUserId(int $userId, string $relationship): ?ParentGuardian
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
            'relationship' => $relationship,
        ]);
    }

    private function normalizeRelationship(?string $relationship): string
    {
        if ($relationship !== null && GuardianRelationship::tryFrom($relationship) !== null) {
            return $relationship;
        }

        return GuardianRelationship::Guardian->value;
    }

    private function plain(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
