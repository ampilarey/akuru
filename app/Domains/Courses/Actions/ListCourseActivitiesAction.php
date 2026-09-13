<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Components\Quran\Actions\ResolveQuranPassageAction;
use App\Domains\Courses\Enums\ActivityPattern;
use App\Domains\Courses\Enums\ActivitySubmissionKind;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Course;
use Illuminate\Support\Collection;

class ListCourseActivitiesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Course $course, bool $includeAnswerKeys = true): Collection
    {
        return Activity::query()
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $activity): array => $this->serialize($activity, $includeAnswerKeys));
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Activity $activity, bool $includeAnswerKeys = true): array
    {
        $data = $activity->data;
        if (! $includeAnswerKeys) {
            unset($data['correct_ids'], $data['acceptable'], $data['correct_order']);
        }

        $settings = $activity->settings ?? [];

        return [
            'id' => $activity->id,
            'course_id' => $activity->course_id,
            'course_module_id' => $activity->course_module_id,
            'lesson_id' => $activity->lesson_id,
            'title' => $activity->title,
            'description' => $activity->description,
            'pattern' => $activity->pattern->value,
            'activity_type' => $activity->activity_type,
            'data' => $data,
            'settings' => $settings,
            'quran' => app(ResolveQuranPassageAction::class)->execute(
                isset($settings['surah_id']) ? (int) $settings['surah_id'] : null,
                isset($settings['ayah_start']) ? (int) $settings['ayah_start'] : null,
                isset($settings['ayah_end']) ? (int) $settings['ayah_end'] : null,
            ),
            'max_score' => (int) $activity->max_score,
            'passing_score' => $activity->passing_score !== null ? (int) $activity->passing_score : null,
            'is_required' => (bool) $activity->is_required,
            'submission' => $this->submission($activity),
        ];
    }

    /**
     * SPEC §36 asks the teacher to "play audio/voice submissions" and "view
     * uploaded files". `data.submission_kind` was stored for a teacher-marked
     * activity and read by nobody, so the player rendered a text box whatever
     * it said. This is the key the player needed.
     *
     * Sent as resolved flags rather than a bare string so the MIME allowlist
     * and §30's size cap are decided once, server-side, instead of a second
     * copy living in the browser.
     *
     * @return array<string, mixed>|null
     */
    private function submission(Activity $activity): ?array
    {
        if ($activity->pattern !== ActivityPattern::TeacherMarked) {
            return null;
        }

        $kind = ActivitySubmissionKind::fromValue($activity->data['submission_kind'] ?? null);

        return [
            'kind' => $kind->value,
            'label' => $kind->label(),
            'accepts_uploads' => $kind->acceptsUploads(),
            'accepts_text' => $kind === ActivitySubmissionKind::Written,
            'accept' => implode(',', $kind->allowedMimes()),
            'max_bytes' => $kind->maxBytes(),
        ];
    }
}
