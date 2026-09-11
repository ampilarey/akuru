<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentWork;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * The photo behind a piece of work, scoped to who is asking.
 *
 * Exists so Portal can serve a family their child's photo without importing
 * Academics' model (rule 3) — and so the scoping lives in one place rather
 * than in two controllers.
 *
 * `$allowedStudentIds` is supplied by the caller from who the viewer is, never
 * from the request. A family passes their own children; staff pass null,
 * meaning no restriction, and can also see hidden work because that is their
 * record to review.
 */
class ReadStudentWorkPhotoAction
{
    /**
     * @param  list<int>|null  $allowedStudentIds  null = staff, no restriction
     * @return array{contents: string, mime: string, original_name: string}|null
     */
    public function execute(int $workId, ?array $allowedStudentIds = null): ?array
    {
        $query = StudentWork::query()->whereKey($workId);

        if ($allowedStudentIds !== null) {
            // A family also never sees hidden work, whatever the id.
            $query->visible()->whereIn('student_id', $allowedStudentIds);
        }

        $mediaId = $query->value('photo_media_id');

        if ($mediaId === null) {
            return null;
        }

        $media = app(ReadPrivateMediaAction::class)->execute((int) $mediaId);

        if ($media === null) {
            return null;
        }

        return [
            'contents' => $media['contents'],
            'mime' => $media['mime'],
            'original_name' => $media['original_name'],
        ];
    }
}
