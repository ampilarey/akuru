<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;

class ResolveConsentPersonForUserAction
{
    /**
     * @return array{person_type: string, person_id: int}|null
     */
    public function execute(int $userId): ?array
    {
        $studentId = Student::query()->where('user_id', $userId)->value('id');
        if ($studentId !== null) {
            return [
                'person_type' => 'student',
                'person_id' => (int) $studentId,
            ];
        }

        $guardianId = ParentGuardian::query()->where('user_id', $userId)->value('id');
        if ($guardianId !== null) {
            return [
                'person_type' => 'guardian',
                'person_id' => (int) $guardianId,
            ];
        }

        return null;
    }
}
