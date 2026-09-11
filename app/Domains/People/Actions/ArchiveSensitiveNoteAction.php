<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\StudentSensitiveNote;
use Illuminate\Validation\ValidationException;

/**
 * Take a note out of use.
 *
 * **Not a delete.** Retention is one of the three questions the plan says must
 * be answered before this module is trusted, and nothing here guesses at it:
 * an archived note stops appearing in the working list and stays on the
 * record. Deleting a child's allergy history because a rule was assumed is the
 * failure that cannot be undone.
 */
class ArchiveSensitiveNoteAction
{
    public function execute(int $noteId, int $staffUserId): StudentSensitiveNote
    {
        $note = StudentSensitiveNote::query()->find($noteId);

        if ($note === null) {
            throw ValidationException::withMessages(['note' => 'That note no longer exists.']);
        }

        if ($note->archived_at !== null) {
            throw ValidationException::withMessages(['note' => 'That note is already archived.']);
        }

        $note->update(['archived_at' => now(), 'archived_by' => $staffUserId]);

        return $note->refresh();
    }
}
