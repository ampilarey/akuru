<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseReviewDecision as Decision;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseReviewDecision;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §35 "Dean / Supervisor Dashboard":
 *
 *   > View courses submitted for review · **Approve courses** · **Reject
 *   > courses** · **Request changes**
 *
 * and SPEC §34 "Course Creator Dashboard":
 *
 *   > Submit course for review · **View supervisor comments**
 *
 * The workflow itself worked. The catalog screen has "Submit review",
 * "Publish", "Return draft" and "Archive", and `TransitionCourseWorkflowAction`
 * enforces the transitions properly.
 *
 * **"Return draft" carried no reason at all.** A creator whose course was
 * bounced back was told nothing — no comment, no reviewer, no date, and no
 * distinction between a rejection and a request for changes. The supervisor's
 * review, the only part of the exchange with any content in it, was discarded
 * the instant the button was pressed. Four §34/§35 abilities rested on a record
 * nobody kept.
 *
 * This Action is the record and the transition together, so the two cannot
 * drift: a course does not move without a decision, and a decision is not
 * written for a move that was refused.
 */
class RecordCourseReviewDecisionAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(Course $course, Decision $decision, array $data, int $reviewerId, bool $canPublish): array
    {
        $from = $course->workflow_status instanceof CourseWorkflowStatus
            ? $course->workflow_status
            : CourseWorkflowStatus::tryFrom((string) $course->workflow_status);

        // §35's abilities are all about a course "submitted for review". A
        // decision on a draft or a published course is not a review, and
        // recording one would put a comment on the creator's screen about a
        // step that never happened.
        if ($from !== CourseWorkflowStatus::InReview) {
            throw ValidationException::withMessages([
                'decision' => ['Only a course submitted for review can be decided on.'],
            ]);
        }

        $comment = trim((string) ($data['comment'] ?? ''));
        if ($decision->requiresComment() && $comment === '') {
            throw ValidationException::withMessages([
                'comment' => ['Say why. A refusal with no reason is the thing this replaces.'],
            ]);
        }

        $to = $decision->targetStatus();

        // The transition is still the authority on what may move where, and on
        // who may publish. If it refuses, nothing is recorded.
        app(TransitionCourseWorkflowAction::class)->execute($course, $to, $canPublish);

        $row = CourseReviewDecision::query()->create([
            'course_id' => $course->id,
            'reviewer_id' => $reviewerId,
            'decision' => $decision,
            'comment' => $comment !== '' ? $comment : null,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'academic_year_id' => $this->positiveInt($data['academic_year_id'] ?? null),
        ]);

        return $this->serialize($row->fresh());
    }

    /**
     * §34 "View supervisor comments" — the whole history, newest first, not
     * only the latest verdict. A creator who has been through two rounds needs
     * to see both.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forCourse(int $courseId): Collection
    {
        return CourseReviewDecision::query()
            ->where('course_id', $courseId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CourseReviewDecision $row): array => $this->serialize($row));
    }

    /**
     * The same history for a whole listing, in one query rather than one per
     * row. §34's "View supervisor comments" belongs on the screen the creator
     * already works from, not behind another click.
     *
     * @param  list<int>  $courseIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function forCourses(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return CourseReviewDecision::query()
            ->whereIn('course_id', $courseIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('course_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (CourseReviewDecision $row): array => $this->serialize($row))
                ->values()
                ->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(CourseReviewDecision $row): array
    {
        return [
            'id' => $row->id,
            'course_id' => (int) $row->course_id,
            'reviewer_id' => (int) $row->reviewer_id,
            'decision' => $row->decision->value,
            'decision_label' => $row->decision->label(),
            'comment' => $row->comment,
            'from_status' => $row->from_status,
            'to_status' => $row->to_status,
            'academic_year_id' => $row->academic_year_id !== null ? (int) $row->academic_year_id : null,
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
