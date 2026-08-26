<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\ClassRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignClassTeacherAction
{
    public function execute(ClassRoom $class, ?int $userId): ClassRoom
    {
        if ($userId !== null) {
            $exists = DB::table('teachers')->where('user_id', $userId)->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    'class_teacher_id' => 'That user is not a teacher.',
                ]);
            }
        }

        $class->forceFill(['class_teacher_id' => $userId])->save();

        return $class->refresh();
    }
}
