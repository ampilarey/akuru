<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Models\AbsenceNote;
use Illuminate\Validation\ValidationException;

class RejectAbsenceNoteAction
{
    public function execute(AbsenceNote $note, int $reviewerId, ?string $reviewNotes = null): AbsenceNote
    {
        if ($note->isApproved() || $note->isRejected()) {
            throw ValidationException::withMessages([
                'status' => 'This note has already been reviewed.',
            ]);
        }

        $note->status = AbsenceNoteStatus::Rejected->value;
        $note->reviewed_by = $reviewerId;
        $note->reviewed_at = now();
        $note->review_notes = $reviewNotes;
        $note->save();

        return $note->refresh();
    }
}
