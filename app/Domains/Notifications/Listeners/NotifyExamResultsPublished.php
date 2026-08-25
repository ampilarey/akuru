<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use Illuminate\Support\Facades\DB;

class NotifyExamResultsPublished
{
    public function handle(ExamResultsPublished $event): void
    {
        $studentIds = DB::table('class_student')
            ->where('class_id', $event->classId)
            ->where('status', 'active')
            ->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return;
        }

        $userIds = DB::table('guardian_student')
            ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
            ->whereIn('guardian_student.student_id', $studentIds)
            ->whereNotNull('parent_guardians.user_id')
            ->pluck('parent_guardians.user_id')
            ->unique()
            ->filter()
            ->values();

        $now = now();
        $date = $event->examDate ?? 'an upcoming date';
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'user_id' => (int) $userId,
                'type' => 'exam_results',
                'title' => 'Exam results published',
                'message' => "{$event->examName} results are now available ({$date}).",
                'data' => json_encode(['exam_id' => $event->examId]),
                'is_read' => false,
                'priority' => 'normal',
                'action_url' => '/portal/exams',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('app_notifications')->insert($rows);
        }
    }
}
