<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use Illuminate\Support\Facades\DB;

/**
 * S2 spec: "substitution assignment (substitute teacher)".
 *
 * Sent whether the teacher took the slot themselves or an admin assigned it —
 * in the self-take case it doubles as a confirmation.
 */
class NotifySubstituteAssignedAction
{
    public function execute(SubstitutionAssignment $assignment): void
    {
        $teacherId = (int) $assignment->substitute_teacher_id;
        if ($teacherId < 1) {
            return;
        }

        $userId = (int) DB::table('teachers')->where('id', $teacherId)->value('user_id');
        if ($userId < 1) {
            return;
        }

        $date = DB::table('substitution_requests')
            ->where('id', $assignment->substitution_request_id)
            ->value('date');

        app(SendUserNotificationAction::class)->execute(
            $userId,
            trans('notifications.substitution.assigned_title'),
            trans('notifications.substitution.assigned_body', [
                'date' => (string) ($date ?? ''),
            ]),
            [
                'category' => 'academics',
                'substitution_request_id' => $assignment->substitution_request_id,
                'assignment_id' => $assignment->id,
            ],
        );
    }
}
