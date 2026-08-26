<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\Teacher;
use Illuminate\Support\Facades\DB;

class EnsureTeacherRowAction
{
    public function execute(int $userId, int $schoolId): Teacher
    {
        $existing = Teacher::query()->where('user_id', $userId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $user = DB::table('users')->where('id', $userId)->first();
        $name = trim((string) ($user->name ?? 'Teacher'));
        $parts = preg_split('/\s+/', $name) ?: ['Teacher'];

        return Teacher::query()->create([
            'user_id' => $userId,
            'school_id' => $schoolId,
            'teacher_id' => 'T-USER-'.$userId,
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
