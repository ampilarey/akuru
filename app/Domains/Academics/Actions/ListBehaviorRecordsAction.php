<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\BehaviorRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListBehaviorRecordsAction
{
    /**
     * @param  array{student_id?: int|null, student_ids?: list<int>|null, academic_year_id?: int|null, parent_visible?: bool|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $rows = BehaviorRecord::query()
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['student_ids'] ?? null, fn ($query, $ids) => $query->whereIn('student_id', $ids))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when(array_key_exists('parent_visible', $filters) && $filters['parent_visible'] !== null, fn ($query) => $query->where('parent_visible', (bool) $filters['parent_visible']))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $rows->map(function (BehaviorRecord $row) use ($students) {
            $student = $students[$row->student_id] ?? null;

            return [
                'id' => $row->id,
                'student_id' => $row->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'academic_year_id' => $row->academic_year_id,
                'type' => $row->type?->value,
                'category' => $row->category,
                'description' => $row->description,
                'points' => $row->points,
                'date' => $row->date?->toDateString(),
                'parent_visible' => $row->parent_visible,
                'requires_followup' => $row->requires_followup,
                'followup_notes' => $row->followup_notes,
            ];
        })->values();
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        $value = DB::table('settings')->where('key', 'behavior_categories')->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) && $decoded !== [] ? array_values($decoded) : ['conduct', 'homework', 'other'];
    }
}
