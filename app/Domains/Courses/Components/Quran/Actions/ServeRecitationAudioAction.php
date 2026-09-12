<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Serves the two recordings attached to a recitation submission: the student's
 * own recitation, and the teacher's spoken correction (SPEC §36).
 *
 * `ServeCatalogMediaAction` cannot do this. It authorizes by asking whether the
 * media id appears in a lesson's content blocks — the right question for course
 * material, and the wrong one entirely for a recording of a named child's
 * voice. These files are somebody's submission, not published content, so they
 * get their own authorization: the student who recorded it, and a teacher.
 */
class ServeRecitationAudioAction
{
    public const KIND_SUBMISSION = 'submission';

    public const KIND_CORRECTION = 'correction';

    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}
     */
    public function execute(int $submissionId, string $kind, ?Authenticatable $user): array
    {
        abort_unless($user !== null, 403);
        abort_unless(in_array($kind, [self::KIND_SUBMISSION, self::KIND_CORRECTION], true), 404);

        $submission = QuranRecitationSubmission::query()->find($submissionId);
        abort_if($submission === null, 404);

        abort_unless($this->mayHear($submission, $user), 403);

        $mediaId = $kind === self::KIND_CORRECTION
            ? $submission->correction_audio_media_file_id
            : $submission->audio_media_file_id;
        abort_if($mediaId === null, 404);

        $file = app(ReadPrivateMediaAction::class)->execute((int) $mediaId);
        abort_if($file === null, 404);

        return $file;
    }

    private function mayHear(QuranRecitationSubmission $submission, Authenticatable $user): bool
    {
        // Exactly the gate the review screen itself uses (a teacher row or
        // `courses.manage`), so anyone who can mark the recitation can hear it.
        // That is the point of the slice: marking without hearing is what the
        // queue allowed before.
        if (method_exists($user, 'can') && $user->can('courses.manage')) {
            return true;
        }
        if (app(ResolveTeacherForUserAction::class)->execute((int) $user->getAuthIdentifier()) !== null) {
            return true;
        }

        // The student it belongs to. Their own recitation, and the correction
        // recorded for them — a correction nobody can play is not feedback.
        $student = app(ResolveStudentForUserAction::class)->execute((int) $user->getAuthIdentifier());

        return $student !== null && (int) $student['id'] === (int) $submission->student_id;
    }
}
