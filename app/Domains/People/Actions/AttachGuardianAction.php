<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttachGuardianAction
{
    public function execute(
        Student $student,
        ParentGuardian $guardian,
        GuardianRelationship|string $relationship,
        bool $isPrimary = false,
        bool $canPickup = true,
        bool $financialResponsible = false,
    ): void {
        $relationship = $relationship instanceof GuardianRelationship
            ? $relationship
            : GuardianRelationship::from($relationship);

        if ($student->guardians()->where('parent_guardians.id', $guardian->id)->exists()) {
            throw new InvalidArgumentException('Guardian is already attached to this student.');
        }

        $student->guardians()->attach($guardian->id, [
            'relationship' => $relationship->value,
            'is_primary' => $isPrimary,
            'can_pickup' => $canPickup,
            'financial_responsible' => $financialResponsible,
        ]);

        $this->dualWriteLegacy($student, $guardian, $relationship->value, $isPrimary);
    }

    private function dualWriteLegacy(Student $student, ParentGuardian $guardian, string $relationship, bool $isPrimary): void
    {
        if (! $student->legacy_registration_student_id || ! $guardian->user_id) {
            return;
        }

        $exists = DB::table('student_guardians')
            ->where('student_id', $student->legacy_registration_student_id)
            ->where('guardian_user_id', $guardian->user_id)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('student_guardians')->insert([
            'student_id' => $student->legacy_registration_student_id,
            'guardian_user_id' => $guardian->user_id,
            'relationship' => $relationship,
            'is_primary' => $isPrimary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
