<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Exam;
use Illuminate\Support\Facades\DB;

class AuthorizeExamEntryAction
{
    public function execute(Exam $exam, ?int $userId, bool $enterAny, bool $manage): bool
    {
        return $this->forClassSubject($exam->class_id, $exam->subject_id, $userId, $enterAny, $manage);
    }

    public function forClassSubject(int $classId, int $subjectId, ?int $userId, bool $enterAny, bool $manage): bool
    {
        if ($manage || $enterAny) {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        $teacherId = DB::table('teachers')->where('user_id', $userId)->value('id');
        if ($teacherId === null) {
            return false;
        }

        return DB::table('timetables')
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->exists();
    }
}
