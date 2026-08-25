<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Award;
use App\Domains\ExamsGrades\Models\StudentAward;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListAwardsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function awards(): Collection
    {
        return Award::query()->orderBy('title')->get()->map(fn (Award $award) => [
            'id' => $award->id,
            'title' => $award->title,
            'level' => $award->level?->value,
            'active' => $award->active,
            'description' => $award->description,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function students(): Collection
    {
        return DB::table('students')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                'number' => $row->student_id,
            ]);
    }

    /**
     * @param  array{academic_year_id?: int|null, student_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function issued(array $filters = []): Collection
    {
        $rows = StudentAward::query()
            ->with('award')
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->orderByDesc('awarded_date')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');

        return $rows->map(function (StudentAward $row) use ($students) {
            $student = $students[$row->student_id] ?? null;

            return [
                'id' => $row->id,
                'student_id' => $row->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'student_number' => $student->student_id ?? null,
                'award' => $row->award?->title,
                'level' => $row->award?->level?->value,
                'awarded_date' => $row->awarded_date?->toDateString(),
                'certificate_document_id' => $row->certificate_document_id,
            ];
        })->values();
    }
}
