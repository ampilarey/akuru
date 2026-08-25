<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Contracts\RequestTypeHandler;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\HR\Actions\ApproveStaffLeaveAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandleStaffLeaveApprovalAction implements RequestTypeHandler
{
    public function onApproved(SchoolRequest $request): void
    {
        $payload = $request->payload ?? [];
        $staffProfileId = (int) ($payload['staff_profile_id'] ?? $request->regarding_id ?? 0);
        $leaveTypeId = (int) ($payload['leave_type_id'] ?? 0);
        $from = (string) ($payload['from_date'] ?? '');
        $to = (string) ($payload['to_date'] ?? $from);

        if ($staffProfileId < 1 || $leaveTypeId < 1 || $from === '') {
            throw ValidationException::withMessages([
                'payload' => 'Staff leave needs staff_profile_id, leave_type_id, and from_date.',
            ]);
        }

        DB::transaction(function () use ($request, $payload, $staffProfileId, $leaveTypeId, $from, $to): void {
            app(ApproveStaffLeaveAction::class)->execute([
                'staff_profile_id' => $staffProfileId,
                'leave_type_id' => $leaveTypeId,
                'from_date' => $from,
                'to_date' => $to,
                'half_day' => (bool) ($payload['half_day'] ?? false),
                'request_id' => (int) $request->id,
                'marked_by' => (int) $request->reviewed_by,
            ]);

            $teacherId = (int) ($payload['teacher_id'] ?? 0);
            if ($teacherId < 1) {
                $teacherId = (int) (app(ResolveTeacherIdForStaffProfileAction::class)->execute($staffProfileId) ?? 0);
            }

            if ($teacherId > 0) {
                app(RecordApprovedTeacherLeaveAction::class)->execute(
                    $teacherId,
                    $from,
                    $to,
                    $request->reason,
                    (int) $request->requester_id,
                    (int) $request->reviewed_by,
                    (int) $request->id,
                );
            }
        });
    }
}
