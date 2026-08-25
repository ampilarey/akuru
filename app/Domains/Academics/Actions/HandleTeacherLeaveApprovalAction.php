<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\RequestTypeHandler;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\HR\Actions\ApproveStaffLeaveAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandleTeacherLeaveApprovalAction implements RequestTypeHandler
{
    public function onApproved(SchoolRequest $request): void
    {
        $payload = $request->payload ?? [];
        $teacherId = (int) ($payload['teacher_id'] ?? $request->regarding_id ?? 0);
        $from = (string) ($payload['from_date'] ?? '');
        $to = (string) ($payload['to_date'] ?? $from);

        if ($teacherId < 1 || $from === '') {
            throw ValidationException::withMessages([
                'payload' => 'Teacher leave needs teacher_id and from_date.',
            ]);
        }

        DB::transaction(function () use ($request, $payload, $teacherId, $from, $to): void {
            app(RecordApprovedTeacherLeaveAction::class)->execute(
                $teacherId,
                $from,
                $to,
                $request->reason,
                (int) $request->requester_id,
                (int) $request->reviewed_by,
                (int) $request->id,
            );

            if (! empty($payload['leave_type_id']) && ! empty($payload['staff_profile_id'])) {
                app(ApproveStaffLeaveAction::class)->execute([
                    'staff_profile_id' => (int) $payload['staff_profile_id'],
                    'leave_type_id' => (int) $payload['leave_type_id'],
                    'from_date' => $from,
                    'to_date' => $to,
                    'half_day' => (bool) ($payload['half_day'] ?? false),
                    'request_id' => (int) $request->id,
                    'marked_by' => (int) $request->reviewed_by,
                ]);
            }
        });
    }
}
