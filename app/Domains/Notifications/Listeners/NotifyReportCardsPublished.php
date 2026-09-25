<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\ExamsGrades\Events\ReportCardsPublished;
use Illuminate\Support\Facades\DB;

class NotifyReportCardsPublished
{
    public function handle(ReportCardsPublished $event): void
    {
        if ($event->studentIds === []) {
            return;
        }

        $userIds = DB::table('guardian_student')
            ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
            // Item 13: a child's news reaches verified guardians only.
            ->where('guardian_student.verification_status', 'verified')
            ->whereIn('guardian_student.student_id', $event->studentIds)
            ->whereNotNull('parent_guardians.user_id')
            ->pluck('parent_guardians.user_id')
            ->unique()
            ->filter()
            ->values();

        $now = now();
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'user_id' => (int) $userId,
                'type' => 'report_cards',
                'title' => 'Report card published',
                'message' => "A report card for {$event->termName} is now available.",
                'data' => json_encode(['term_id' => $event->termId, 'class_id' => $event->classId]),
                'is_read' => false,
                'priority' => 'normal',
                'action_url' => '/portal/report-cards',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('app_notifications')->insert($rows);
        }
    }
}
