<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonRevision;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
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

        $courseIds = CourseEnrollment::query()
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->pluck('course_id');
        $lessonIds = Lesson::query()->whereIn('course_id', $courseIds)->pluck('id')->all();

        return $this->lessonsUseMedia($lessonIds, $mediaId);
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
