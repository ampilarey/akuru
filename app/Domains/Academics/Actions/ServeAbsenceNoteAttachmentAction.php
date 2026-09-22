<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\People\Actions\GuardianCanAccessStudentAction;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The document a guardian attached to an absence note (S2.4 "with
 * attachment"), for the two people entitled to it: a reviewer with
 * `manage_attendance`, or a guardian of the pupil the note is about.
 *
 * Resolved from the note, never from a file id — the same shape as the other
 * private readers (`PrivateMediaReadersAreScopedTest`): "give me note 12's
 * attachment" cannot be pivoted into "give me file 12".
 *
 * Until 2026-09-22 nothing served these at all: the portal stored the file
 * and the review screen never showed it, so a document a school required as
 * evidence was evidence nobody could open.
 */
class ServeAbsenceNoteAttachmentAction
{
    public const DISK = 'local';

    public function execute(int $viewerUserId, bool $canManageAttendance, AbsenceNote $note): StreamedResponse
    {
        $allowed = $canManageAttendance
            || app(GuardianCanAccessStudentAction::class)->execute($viewerUserId, (int) $note->student_id);

        abort_unless($allowed, 403);

        $path = (string) ($note->attachment_path ?? '');
        abort_if($path === '' || ! Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->download($path, 'absence-note-'.$note->id.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}
