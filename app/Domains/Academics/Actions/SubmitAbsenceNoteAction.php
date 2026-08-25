<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Models\AbsenceNote;
use Illuminate\Validation\ValidationException;

class SubmitAbsenceNoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AbsenceNote
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return AbsenceNote::query()->create([
            'student_id' => (int) $data['student_id'],
            'created_by' => (int) $data['created_by'],
            'date' => $data['date'],
            'period_id' => isset($data['period_id']) && $data['period_id'] !== '' && $data['period_id'] !== null
                ? (int) $data['period_id']
                : null,
            'reason' => $reason,
            'type' => (string) ($data['type'] ?? 'other'),
            'status' => AbsenceNoteStatus::Submitted->value,
            'attachment_path' => $data['attachment_path'] ?? null,
            'affects_attendance' => array_key_exists('affects_attendance', $data)
                ? (bool) $data['affects_attendance']
                : true,
        ]);
    }
}
