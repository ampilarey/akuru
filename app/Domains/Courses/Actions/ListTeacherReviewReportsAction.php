<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Progress\Actions\ListScoredAttemptsAction;
use Illuminate\Support\Facades\DB;

class ListTeacherReviewReportsAction
{
    /**
     * @param  array{academic_year_id?: int|null, course_id?: int|null, threshold?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters = []): array
    {
        $queue = app(ListReviewQueueAction::class);
        $yearId = $queue->positiveInt($filters['academic_year_id'] ?? null);
        $courseId = $queue->positiveInt($filters['course_id'] ?? null);
        $threshold = $this->threshold($filters['threshold'] ?? null);
        $enrollmentIds = $yearId ? $queue->enrollmentIdsForYear($yearId) : [];

        $pending = $queue->execute($filters);
        $scored = app(ListScoredAttemptsAction::class)->execute(['course_id' => $courseId])
            ->filter(fn (array $row): bool => $queue->matchesYear($row, $yearId, $enrollmentIds))
            ->values();

        $activities = Activity::query()
            ->whereIn('id', $scored->where('kind', 'activity')->pluck('activity_id')->filter()->all() ?: [0])
            ->get()
            ->keyBy('id');
        $assessments = Assessment::query()
            ->whereIn('id', $scored->where('kind', 'assessment')->pluck('assessment_id')->filter()->all() ?: [0])
            ->get()
            ->keyBy('id');
        $students = $queue->students($scored->pluck('student_id')->all());
        $courses = $queue->courses($scored->pluck('course_id')->all());

        $weaknesses = [];
        $revisions = [];

        foreach ($scored as $row) {
            $item = $this->decorateScored($row, $activities, $assessments, $students, $courses);
            if (! $this->isWeak($item, $threshold)) {
                continue;
            }
            $item['reason'] = $this->reason($item, $threshold);
            $item['recommendation'] = $this->recommendation($item);
            $weaknesses[] = $item;
            $revisions[] = $item;
        }

        return [
            'filters' => [
                'academic_year_id' => $yearId,
                'course_id' => $courseId,
                'threshold' => $threshold,
            ],
            'years' => DB::table('academic_years')->orderByDesc('id')->get(['id', 'name'])
                ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'courses' => app(ListEngineCoursesAction::class)->execute()->map(fn (array $course): array => [
                'id' => $course['id'],
                'title' => $course['title'],
            ])->values()->all(),
            'rows' => $pending->all(),
            'weaknesses' => $weaknesses,
            'revisions' => $revisions,
            'pending_count' => $pending->count(),
            'weak_item_count' => count($weaknesses),
            'weak_student_count' => collect($weaknesses)->pluck('student_id')->filter()->unique()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  \Illuminate\Support\Collection<int|string, Activity>  $activities
     * @param  \Illuminate\Support\Collection<int|string, Assessment>  $assessments
     * @param  \Illuminate\Support\Collection<int|string, object>  $students
     * @param  \Illuminate\Support\Collection<int|string, \App\Domains\Courses\Models\Course>  $courses
     * @return array<string, mixed>
     */
    private function decorateScored($row, $activities, $assessments, $students, $courses): array
    {
        $kind = (string) ($row['kind'] ?? '');
        $activity = $kind === 'activity' ? $activities->get($row['activity_id'] ?? 0) : null;
        $assessment = $kind === 'assessment' ? $assessments->get($row['assessment_id'] ?? 0) : null;
        $student = $students->get($row['student_id'] ?? 0);
        $course = $courses->get($row['course_id'] ?? 0);
        $score = $row['score'] !== null ? (int) $row['score'] : 0;
        $max = max(1, (int) ($row['max_score'] ?? $activity?->max_score ?? $assessment?->max_score ?? 1));
        $passing = $activity?->passing_score ?? $assessment?->passing_score;
        $passing = $passing !== null ? (int) $passing : null;
        $settings = is_array($activity?->settings) ? $activity->settings : [];

        return [
            'kind' => $kind,
            'attempt_id' => (int) $row['id'],
            'activity_id' => isset($row['activity_id']) ? (int) $row['activity_id'] : null,
            'assessment_id' => isset($row['assessment_id']) ? (int) $row['assessment_id'] : null,
            'student_id' => isset($row['student_id']) ? (int) $row['student_id'] : null,
            'student_name' => $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '',
            'course_id' => isset($row['course_id']) ? (int) $row['course_id'] : null,
            'course_title' => $course ? (string) $course->title : '',
            'title' => (string) ($activity?->title ?? $assessment?->title ?? ($kind === 'assessment' ? 'Assessment' : 'Activity')),
            'score' => $score,
            'max_score' => $max,
            'percent' => (int) round(($score / $max) * 100),
            'passing_score' => $passing,
            'attempt_number' => (int) ($row['attempt_number'] ?? 1),
            'attempt_count' => (int) ($row['attempt_count'] ?? 1),
            'retakes_allowed' => $kind === 'activity' ? (bool) ($settings['retakes_allowed'] ?? true) : true,
            'retake_limit' => $kind === 'activity'
                ? (isset($settings['retake_limit']) && $settings['retake_limit'] !== '' ? (int) $settings['retake_limit'] : null)
                : ($assessment?->retake_limit !== null ? (int) $assessment->retake_limit : null),
            'submitted_at' => $row['submitted_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isWeak(array $item, int $threshold): bool
    {
        if ($item['passing_score'] !== null) {
            return $item['score'] < $item['passing_score'];
        }

        return $item['percent'] < $threshold;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function reason(array $item, int $threshold): string
    {
        if ($item['passing_score'] !== null && $item['score'] < $item['passing_score']) {
            return 'Below passing score';
        }

        return 'Below '.$threshold.'% threshold';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function recommendation(array $item): string
    {
        $remaining = $this->retakesRemaining($item);
        if ($remaining === null || $remaining > 0) {
            return 'Retry '.$item['title'];
        }

        return 'Teacher review — retake not available';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function retakesRemaining(array $item): ?int
    {
        if (! $item['retakes_allowed']) {
            return 0;
        }
        if ($item['retake_limit'] === null) {
            return null;
        }

        return max(0, (int) $item['retake_limit'] - (int) $item['attempt_count']);
    }

    private function threshold(mixed $value): int
    {
        $threshold = (int) $value;

        return $threshold >= 1 && $threshold <= 100 ? $threshold : 50;
    }
}
