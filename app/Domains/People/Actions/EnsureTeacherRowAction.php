<?php

namespace App\Domains\People\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Teacher;
use App\Domains\Settings\Models\School;

class EnsureTeacherRowAction
{
    public function execute(User $user): Teacher
    {
        $existing = Teacher::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $schoolId = School::query()->value('id');
        $parts = preg_split('/\s+/', trim($user->name)) ?: ['Teacher'];

        return Teacher::query()->create([
            'user_id' => $user->id,
            'school_id' => $schoolId,
            'teacher_id' => 'T-USER-'.$user->id,
            'first_name' => $parts[0] ?: 'Teacher',
            'last_name' => $parts[1] ?? $parts[0],
            'date_of_birth' => $user->date_of_birth ?? '1985-01-01',
            'gender' => $user->gender ?? 'male',
            'phone' => $user->phone,
            'address' => $user->address,
            'email' => $user->email,
            'qualification' => 'BA',
            'specialization' => 'General',
            'joining_date' => now()->toDateString(),
            'status' => 'active',
        ]);
    }
}
