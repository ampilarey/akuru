<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use Illuminate\Support\Facades\DB;

class ListOfferingCompletionReportAction
{
    /**
     * @param  array{academic_year_id?: int|null, offering_id?: int|null, course_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters = []): array
    {
        $yearId = $this->positiveInt($filters['academic_year_id'] ?? null);
        $offeringId = $this->positiveInt($filters['offering_id'] ?? null);
        $courseId = $this->positiveInt($filters['course_id'] ?? null);

        $offeringQuery = DB::table('course_offerings')->whereNull('deleted_at');
        if ($yearId) {
            $offeringQuery->where('academic_year_id', $yearId);
        }
        if ($offeringId) {
            $offeringQuery->where('id', $offeringId);
        }
        if ($courseId) {
            $offeringQuery->where('course_id', $courseId);
        }
        $offeringIds = $offeringQuery->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $enrollments = CourseEnrollment::query()
            ->whereNotNull('course_offering_id')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($offeringIds !== [], fn ($query) => $query->whereIn('course_offering_id', $offeringIds))
            ->when($offeringIds === [] && ($yearId || $offeringId || $courseId), fn ($query) => $query->whereRaw('0 = 1'))
            ->orderByDesc('id')
            ->get();

        $rows = app(BuildEnrollmentReportRowsAction::class)->execute($enrollments);

        return [
            'filters' => [
                'academic_year_id' => $yearId,
                'offering_id' => $offeringId,
                'course_id' => $courseId,
            ],
            'years' => DB::table('academic_years')->orderByDesc('id')->get(['id', 'name'])
                ->map(fn ($row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all(),
            'courses' => app(ListEngineCoursesAction::class)->execute()->map(fn (array $course): array => [
                'id' => $course['id'],
                'title' => $course['title'],
            ])->values()->all(),
            'offerings' => DB::table('course_offerings')
                ->whereNull('deleted_at')
                ->orderBy('title')
                ->get(['id', 'course_id', 'title', 'academic_year_id'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'course_id' => (int) $row->course_id,
                    'title' => (string) $row->title,
                    'academic_year_id' => $row->academic_year_id ? (int) $row->academic_year_id : null,
                ])
                ->values()
                ->all(),
            'offering_summaries' => $this->summaries($rows, 'offering_id', 'offering_title'),
            'course_summaries' => $this->summaries($rows, 'course_id', 'course_title'),
            'rows' => $rows->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function summaries($rows, string $idKey, string $titleKey): array
    {
        return $rows
            ->filter(fn (array $row): bool => ! empty($row[$idKey]))
            ->groupBy($idKey)
            ->map(function ($group) use ($idKey, $titleKey): array {
                $first = $group->first();
                $attendance = $group->pluck('attendance_percent')->filter(fn ($value) => $value !== null);

                return [
                    'id' => $first[$idKey],
                    'title' => $first[$titleKey],
                    'enrolled' => $group->count(),
                    'completed' => $group->where('status', 'completed')->count(),
                    'average_progress' => (int) round($group->avg('progress_percentage') ?? 0),
                    'average_attendance' => $attendance->isEmpty() ? null : (int) round($attendance->avg()),
                ];
            })
            ->values()
            ->all();
    }

    private function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
