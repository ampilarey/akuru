<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\AbsenceType;
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

        // E10c: the reason is a row now, not one of five hardcoded strings.
        $type = $this->resolveType($data);

        // A school that requires a doctor's note for illness can now say so.
        // This is the honest half of the plan's "can't they be falsified?":
        // you cannot stop a parent writing what they like, but you can require
        // a document and record who accepted it.
        if ($type?->requires_evidence && ($data['attachment_path'] ?? null) === null) {
            throw ValidationException::withMessages([
                'attachment_path' => 'This reason needs a document attached before it can be sent.',
            ]);
        }

        return AbsenceNote::query()->create([
            'student_id' => (int) $data['student_id'],
            'created_by' => (int) $data['created_by'],
            'date' => $data['date'],
            'period_id' => isset($data['period_id']) && $data['period_id'] !== '' && $data['period_id'] !== null
                ? (int) $data['period_id']
                : null,
            'reason' => $reason,
            // Still written, because this is the deploy that stops *reading*
            // the old column, not the one that drops it (rule 9).
            'type' => $type?->code ?? (string) ($data['type'] ?? 'other'),
            'absence_type_id' => $type?->id,
            'status' => AbsenceNoteStatus::Submitted->value,
            'attachment_path' => $data['attachment_path'] ?? null,
            // Kept in step with the type, so the column and the policy cannot
            // disagree while both are still read.
            'affects_attendance' => $type?->excuses_absence
                ?? (array_key_exists('affects_attendance', $data) ? (bool) $data['affects_attendance'] : true),
        ]);
    }

    /**
     * Accepts an id (what the form sends now) or a code (what older callers
     * and seeders send). Neither resolving is fatal — `other` is a reason too.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveType(array $data): ?AbsenceType
    {
        if (! empty($data['absence_type_id'])) {
            return AbsenceType::query()->find((int) $data['absence_type_id']);
        }

        $code = trim((string) ($data['type'] ?? ''));

        return $code === '' ? null : AbsenceType::query()->where('code', $code)->first();
    }
}
