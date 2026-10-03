<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Enums\Hifz\HifzEnrollmentStatus;
use Illuminate\Validation\ValidationException;

/**
 * End a Hifz enrolment (BACKLOG C16 slice N4, STATUS §5nz). The owner,
 * 2026-10-03 (OWNER_ACTIONS 15): add `withdrawn`. The row says which way it
 * ended — withdrawn (left the Institute), transferred (another halaqa),
 * completed — on what date, with an optional note and who ended it. Every
 * reader that asks `where('status', 'active')` stops seeing the pupil the
 * same moment; nothing else about the row changes.
 */
class EndHifzEnrollmentAction
{
    public function execute(HifzEnrollment $enrollment, HifzEnrollmentStatus $ending, string $endedAt, ?string $reason, ?int $endedBy): HifzEnrollment
    {
        if (! $ending->isEnded()) {
            throw ValidationException::withMessages(['status' => trans('admin.hifz_end_not_an_ending')]);
        }
        if ($enrollment->status instanceof HifzEnrollmentStatus && $enrollment->status->isEnded()) {
            throw ValidationException::withMessages(['status' => trans('admin.hifz_end_already', ['status' => trans('admin.hifz_enrollment_status_'.$enrollment->status->value)])]);
        }

        $enrollment->forceFill([
            'status' => $ending,
            'ended_at' => $endedAt,
            'end_reason' => ($reason = trim((string) $reason)) !== '' ? $reason : null,
            'ended_by' => $endedBy,
        ])->save();

        return $enrollment->refresh();
    }
}
