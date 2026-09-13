<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\GlossaryItem;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonGlossaryItem;
use App\Domains\Courses\Models\LessonRevision;
use App\Domains\Courses\Models\Question;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\Progress\Actions\AnyAttemptUsesMediaAction;
use App\Domains\Progress\Actions\ListStudentAttemptSnapshotsAction;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may be handed a private file through `/learn/media/{id}` and
 * `/catalog/media/{id}`.
 *
 * The student half has always been a careful allow-list. **The staff half was
 * one line** — `$user->can('courses.manage')` — and `ReadPrivateMediaAction`
 * behind it is `MediaFile::find($id)` with no scope, so a *Courses* permission
 * spent as "may read every private file in the application".
 *
 * Twelve callers reach `StorePrivateMediaAction`. Besides course media that
 * meant children's Qur'an recitations, children's pronunciation attempts,
 * students' work photographs, lost-property photographs, class materials, and
 * the Library's paid PDF originals — the last being the one file
 * LIBRARY_PLAN §36 says must never be exposed, and the reason the protected
 * reader exists at all. `courses.manage` is held by super_admin, admin,
 * headmaster, supervisor **and course_creator**; SPEC §8 deliberately withholds
 * `courses.publish` from a course creator, so the intent to keep that role
 * narrow is already on the record.
 *
 * The staff path is now an allow-list too, and it covers exactly the two things
 * staff legitimately open here:
 *
 *  1. **Catalog media** — a file referenced by any lesson's content blocks or
 *     published revisions, by a glossary term (§22), or by a question (§20).
 *     This is what a catalog manager sees while building.
 *  2. **A submission attachment** — what a student handed in. `Reviews.jsx`
 *     plays and opens these, so narrowing to catalog media alone would have
 *     broken teacher review, which `SubmissionUploadsRenderTest` pins.
 *
 * Everything else is refused. Qur'an recitation review is unaffected: it has
 * its own endpoint (`recitations/{submission}/audio/{kind}`) and never came
 * through here.
 */
class ServeCatalogMediaAction
{
    /**
     * @return array{id: int, contents: string, mime: string, original_name: string}
     */
    public function execute(int $mediaId, ?Authenticatable $user): array
    {
        abort_unless($user !== null, 403);

        $userId = (int) $user->getAuthIdentifier();

        $allowed = $this->studentMayView($mediaId, $userId)
            || (method_exists($user, 'can')
                && $user->can('courses.manage')
                && $this->staffMayView($mediaId));

        abort_unless($allowed, 403);

        $file = app(ReadPrivateMediaAction::class)->execute($mediaId);
        abort_if($file === null, 404);

        return $file;
    }

    /**
     * Course media, or something a student handed in. Not "any file".
     */
    private function staffMayView(int $mediaId): bool
    {
        $lessonIds = Lesson::query()->pluck('id')->all();

        if ($this->lessonsUseMedia($lessonIds, $mediaId)) {
            return true;
        }

        if ($this->questionsUseMedia($mediaId)) {
            return true;
        }

        // Through Progress's own Action: attempts and their answers belong to
        // Progress, and a Courses action holding `ActivityAttempt` is rule 3's
        // boundary — `Phase1ABoundariesTest` caught exactly that here.
        return app(AnyAttemptUsesMediaAction::class)->execute($mediaId);
    }

    /**
     * §20 question attachments, which an author must be able to open while
     * building a bank — and which no lesson references.
     */
    private function questionsUseMedia(int $mediaId): bool
    {
        $media = app(ResolveQuestionMediaAction::class);

        foreach (Question::query()->whereNotNull('attachments')->pluck('attachments') as $attachments) {
            if (in_array($mediaId, $media->mediaIds($attachments), true)) {
                return true;
            }
        }

        return false;
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

    /**
     * @param  list<int>  $lessonIds
     */
    private function lessonTermsUseMedia(array $lessonIds, int $mediaId): bool
    {
        return GlossaryItem::query()
            ->whereIn('id', LessonGlossaryItem::query()->whereIn('lesson_id', $lessonIds)->select('glossary_item_id'))
            ->where(function ($query) use ($mediaId): void {
                foreach (array_keys(StoreGlossaryMediaAction::SLOTS) as $slot) {
                    $query->orWhere($slot, $mediaId);
                }
            })
            ->exists();
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

        // SPEC §22's glossary media hangs off the **term**, reached through
        // `lesson_glossary_items` — never off a content block. So this check,
        // which only knew about blocks, would have refused a student the
        // pronunciation recording for a term on the very lesson they were
        // reading, exactly as it refused §20's question audio before that slice.
        if ($this->lessonTermsUseMedia($lessonIds, $mediaId)) {
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
