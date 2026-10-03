<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\LessonStatus;
use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\AssessmentQuestion;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonGlossaryItem;
use App\Domains\Courses\Models\Question;
use App\Domains\Courses\Models\Rubric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moodle parity slice M1 (STATUS §5oh): copy a whole course, the way Moodle's
 * "Copy course" does, so a teacher can run a course again, or start a new one
 * from one that works, without building it by hand.
 *
 * **What is copied** is the course's design: its details, modules, lessons,
 * content blocks, activities, assessments with their questions, the lessons'
 * glossary links, its own certificate templates, and its marking rubrics.
 *
 * **What is not** is anything that happened in it: enrolments, attempts,
 * progress, payments, offerings, certificates issued, review decisions,
 * published lesson revisions, teacher assignments, leads. The copy is a draft
 * with nobody in it, and goes through review like any new course.
 *
 * - Everything lands as a draft. Lessons have no published revision until the
 *   copy is published, so a learner sees nothing of it before then.
 * - Questions in the shared bank (no course) are linked, not copied: they are
 *   the bank's. Questions that belong to this course are copied with it, so an
 *   edit in the copy never changes the original's tests.
 * - Ids one item names in another are re-pointed to the copy: a quiz or
 *   assignment embedded in a block, a lesson unlocked by passing a test, a
 *   certificate that asks for one.
 * - Media is shared, not duplicated: a block holds a media id, and two blocks
 *   naming one file is an ordinary state (see DuplicateContentBlockAction).
 * - A classroom's own assessment (one with a classroom) belongs to that class,
 *   not to the course design, and stays behind.
 */
class CopyCourseAction
{
    /**
     * @param  array{title?: ?string}  $data
     */
    public function execute(Course $source, array $data, ?int $actorId): Course
    {
        return DB::transaction(function () use ($source, $data, $actorId): Course {
            $copy = $this->copyCourse($source, $data, $actorId);

            $modules = $this->copyRows(
                CourseModule::query()->where('course_id', $source->id)->orderBy('position')->get(),
                fn (CourseModule $module): array => ['course_id' => $copy->id, 'status' => ModuleStatus::Draft, 'created_by' => $actorId],
            );
            $lessons = $this->copyRows(
                Lesson::query()->where('course_id', $source->id)->whereIn('course_module_id', array_keys($modules))->orderBy('position')->get(),
                fn (Lesson $lesson): array => [
                    'course_id' => $copy->id,
                    'course_module_id' => $modules[(int) $lesson->course_module_id],
                    'status' => LessonStatus::Draft,
                    'current_revision_id' => null,
                    'published_at' => null,
                    'created_by' => $actorId,
                ],
            );
            // Moodle parity slice M2: the course's rubrics come too, and the
            // copy's items are marked by the copy's rubrics.
            $rubrics = $this->copyRows(
                Rubric::query()->where('course_id', $source->id)->get(),
                fn (Rubric $rubric): array => ['course_id' => $copy->id, 'created_by' => $actorId],
            );
            $questions = $this->copyRows(
                Question::query()->where('course_id', $source->id)->get(),
                fn (Question $question): array => ['course_id' => $copy->id, 'legacy_quiz_question_id' => null, 'legacy_assignment_id' => null, 'created_by' => $actorId],
            );
            $assessments = $this->copyRows(
                Assessment::query()->where('course_id', $source->id)->whereNull('classroom_id')->get(),
                fn (Assessment $assessment): array => [
                    'course_id' => $copy->id,
                    'course_module_id' => $modules[(int) $assessment->course_module_id] ?? null,
                    'lesson_id' => $lessons[(int) $assessment->lesson_id] ?? null,
                    'status' => AssessmentStatus::Draft,
                    'rubric_id' => $rubrics[(int) $assessment->rubric_id] ?? null,
                    'legacy_quiz_id' => null,
                    'legacy_assignment_id' => null,
                    'created_by' => $actorId,
                ],
            );
            $this->copyRows(
                AssessmentQuestion::query()->whereIn('assessment_id', array_keys($assessments))->get(),
                fn (AssessmentQuestion $link): array => [
                    'assessment_id' => $assessments[(int) $link->assessment_id],
                    'question_id' => $questions[(int) $link->question_id] ?? (int) $link->question_id,
                ],
            );
            $activities = $this->copyRows(
                Activity::query()->where('course_id', $source->id)->get(),
                fn (Activity $activity): array => [
                    'course_id' => $copy->id,
                    'course_module_id' => $modules[(int) $activity->course_module_id] ?? null,
                    'lesson_id' => $lessons[(int) $activity->lesson_id] ?? null,
                    'rubric_id' => $rubrics[(int) $activity->rubric_id] ?? null,
                    'created_by' => $actorId,
                ],
            );
            $this->copyRows(
                ContentBlock::query()->whereIn('lesson_id', array_keys($lessons))->orderBy('position')->get(),
                fn (ContentBlock $block): array => [
                    'course_id' => $copy->id,
                    'course_module_id' => $modules[(int) $block->course_module_id] ?? null,
                    'lesson_id' => $lessons[(int) $block->lesson_id],
                    'data' => $this->repointEmbed((array) $block->data, $assessments, $activities),
                    'created_by' => $actorId,
                ],
            );
            $this->copyRows(
                LessonGlossaryItem::query()->whereIn('lesson_id', array_keys($lessons))->get(),
                fn (LessonGlossaryItem $link): array => ['lesson_id' => $lessons[(int) $link->lesson_id]],
            );
            $this->copyRows(
                CertificateTemplate::query()->where('course_id', $source->id)->get(),
                fn (CertificateTemplate $template): array => [
                    'course_id' => $copy->id,
                    'rules' => $this->repointKey($template->rules, 'assessment_id', $assessments),
                    'created_by' => $actorId,
                ],
            );

            // A lesson unlocked by passing a test points at the copy's test.
            foreach ($lessons as $newId) {
                $lesson = Lesson::query()->findOrFail($newId);
                if (is_array($lesson->unlock_rule) && isset($lesson->unlock_rule['assessment_id'])) {
                    $lesson->unlock_rule = $this->repointKey($lesson->unlock_rule, 'assessment_id', $assessments);
                    $lesson->saveQuietly();
                }
            }

            return $copy->refresh();
        });
    }

    private function copyCourse(Course $source, array $data, ?int $actorId): Course
    {
        $title = trim((string) ($data['title'] ?? '')) ?: $source->title.' (copy)';

        // The dates, the featured flag and the open/closed state belong to a
        // run of the course, not to its design.
        $copy = $source->replicate(['slug', 'start_date', 'end_date', 'enrollment_deadline']);
        $copy->forceFill([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'workflow_status' => CourseWorkflowStatus::Draft,
            'status' => 'closed',
            'is_featured' => false,
            'created_by' => $actorId,
            'meta' => array_merge(is_array($source->meta) ? $source->meta : [], ['copied_from_course_id' => (int) $source->id]),
        ]);
        $copy->save();

        return $copy;
    }

    /**
     * Replicate every row with the given changes; return old id => new id.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  iterable<T>  $rows
     * @param  callable(T): array<string, mixed>  $changes
     * @return array<int, int>
     */
    private function copyRows(iterable $rows, callable $changes): array
    {
        $map = [];
        foreach ($rows as $row) {
            $copy = $row->replicate();
            $copy->forceFill($changes($row));
            $copy->saveQuietly();
            $map[(int) $row->getKey()] = (int) $copy->getKey();
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $assessments
     * @param  array<int, int>  $activities
     * @return array<string, mixed>
     */
    private function repointEmbed(array $data, array $assessments, array $activities): array
    {
        if (! empty($data['quiz_id'])) {
            $data['quiz_id'] = $assessments[(int) $data['quiz_id']] ?? $data['quiz_id'];
        }
        if (! empty($data['assignment_id'])) {
            $id = (int) $data['assignment_id'];
            $data['assignment_id'] = $assessments[$id] ?? $activities[$id] ?? $data['assignment_id'];
        }

        return $data;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function repointKey(mixed $rules, string $key, array $map): mixed
    {
        if (! is_array($rules) || empty($rules[$key])) {
            return $rules;
        }
        $rules[$key] = $map[(int) $rules[$key]] ?? $rules[$key];

        return $rules;
    }
}
