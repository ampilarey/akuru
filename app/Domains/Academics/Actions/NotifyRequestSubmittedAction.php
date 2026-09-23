<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Identity\Actions\ListUserIdsWithPermissionAction;
use App\Domains\Notifications\Actions\SendUserNotificationAction;
use Illuminate\Support\Facades\DB;

/**
 * E5: "notify requester and approver at each transition". The decision half
 * (`NotifyRequestDecisionAction`) shipped with S2.10; this is the other
 * half, which nothing did until the requests walk (STATUS §5fw) — a family
 * could file a request and nobody was told it was waiting.
 *
 * Who is told: everyone who can review (`requests.review`), and, when the
 * request is about a pupil, the class teacher of the pupil's class as well
 * — they cannot review it (owner decision, §5fw), but it concerns their
 * class and E5's acceptance line names them. The requester is never told
 * about their own submission.
 *
 * Cross-domain by Actions and DB only (rule 3): Identity's permission
 * lookup, and a read of `class_student`/`classes` for the class teacher.
 */
class NotifyRequestSubmittedAction
{
    public function execute(SchoolRequest $request): void
    {
        $requesterId = (int) $request->requester_id;
        $recipients = collect(app(ListUserIdsWithPermissionAction::class)->execute('requests.review'));

        $regarding = null;
        if ($request->regarding_type === 'student' && $request->regarding_id) {
            $student = DB::table('students')->where('id', (int) $request->regarding_id)->first(['first_name', 'last_name']);
            $regarding = $student ? trim($student->first_name.' '.$student->last_name) : null;

            // Every class the pupil is active in — a pupil can be in more than
            // one (a subject class beside the form class), and a class with no
            // class teacher set says nothing about the others.
            DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->where('class_student.student_id', (int) $request->regarding_id)
                ->where('class_student.status', 'active')
                ->whereNotNull('classes.class_teacher_id')
                ->pluck('classes.class_teacher_id')
                ->each(fn ($id) => $recipients->push((int) $id));
        }

        $requesterName = (string) (DB::table('users')->where('id', $requesterId)->value('name') ?? '');
        $type = $request->type instanceof \BackedEnum ? $request->type->value : (string) $request->type;

        foreach ($recipients->map(fn ($id) => (int) $id)->unique()->reject(fn (int $id) => $id === $requesterId) as $userId) {
            app(SendUserNotificationAction::class)->execute(
                $userId,
                trans('notifications.request.submitted_title', ['name' => $requesterName]),
                trans('notifications.request.submitted_body', [
                    'type' => str_replace('_', ' ', $type),
                    'regarding' => $regarding ?? $requesterName,
                    'reason' => (string) $request->reason,
                ]),
                [
                    'category' => 'academics',
                    'request_id' => $request->id,
                    'href' => '/academics/requests',
                ],
            );
        }
    }
}
