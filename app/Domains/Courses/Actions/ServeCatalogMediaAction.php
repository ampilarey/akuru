<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonRevision;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\Progress\Actions\ListStudentAttemptSnapshotsAction;
use Illuminate\Contracts\Auth\Authenticatable;

class ServeCatalogMediaAction
{
    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}
     */
    public function execute(int $mediaId, ?Authenticatable $user): array
    {
        abort_unless($user !== null, 403);

        $allowed = (method_exists($user, 'can') && $user->can('courses.manage'))
            || $this->studentMayView($mediaId, (int) $user->getAuthIdentifier());
        abort_unless($allowed, 403);

        $file = app(ReadPrivateMediaAction::class)->execute($mediaId);
        abort_if($file === null, 404);

        return $file;
    }

    private function studentMayView(int $mediaId, int $userId): bool
    {
        $previewLessonIds = Lesson::query()
            ->where('is_preview', true)
            ->whereNotNull('current_revision_id')
            ->pluck('id');
        if ($this->lessonsUseMedia($previewLessonIds->all(), $mediaId)) {
            return true;
        }

        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($student === null) {
            return false;
        }

        // SPEC §20 lets a question carry audio, an image or a PDF, and §21
        // freezes those references into the attempt. This check only knew about
        // lesson content blocks, so a student sitting an audio question was
        // refused the audio — the one file the question is about.
        //
        // The attempt's own snapshot is the evidence, not the question bank:
        // a question detached from the assessment after the attempt started is
        // still on that student's paper, and a swapped attachment must not
        // retroactively open a file they were never shown.
        if ($this->attemptsUseMedia((int) $student['id'], $mediaId)) {
            return true;
        }

        $courseIds = CourseEnrollment::query()
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->pluck('course_id');
        $lessonIds = Lesson::query()->whereIn('course_id', $courseIds)->pluck('id')->all();

        return $this->lessonsUseMedia($lessonIds, $mediaId);
    }

    private function attemptsUseMedia(int $studentId, int $mediaId): bool
    {
        $media = app(ResolveQuestionMediaAction::class);

        foreach (app(ListStudentAttemptSnapshotsAction::class)->execute($studentId) as $snapshot) {
            if (in_array($mediaId, $media->mediaIds($snapshot['attachments'] ?? null), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $lessonIds
     */
    private function lessonsUseMedia(array $lessonIds, int $mediaId): bool
    {
        if ($lessonIds === []) {
            return false;
        }

        if (ContentBlock::query()->whereIn('lesson_id', $lessonIds)->where('data->media_id', $mediaId)->exists()) {
            return true;
        }

        return LessonRevision::query()
            ->whereIn('lesson_id', $lessonIds)
            ->get(['snapshot_json'])
            ->contains(function (LessonRevision $revision) use ($mediaId) {
                foreach ($revision->snapshot_json['blocks'] ?? [] as $block) {
                    if ((int) ($block['data']['media_id'] ?? 0) === $mediaId) {
                        return true;
                    }
                }

                return false;
            });
    }
}
