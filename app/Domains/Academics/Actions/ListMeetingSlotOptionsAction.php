<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\Term;
use Illuminate\Support\Facades\DB;

class ListMeetingSlotOptionsAction
{
    /**
     * @return array{years: list<array<string, mixed>>, terms: list<array<string, mixed>>, teachers: list<array<string, mixed>>, classes: list<array<string, mixed>>, rooms: list<array<string, mixed>>}
     */
    public function execute(?int $academicYearId = null): array
    {
        $years = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status', 'is_current']);
        $yearId = $academicYearId ?: (int) ($years->firstWhere('is_current', true)?->id ?: $years->first()?->id);

        return [
            'yearId' => $yearId ?: null,
            'years' => $years->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
                'status' => $year->status?->value ?? (string) $year->status,
            ])->values()->all(),
            'terms' => Term::query()
                ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                ->orderBy('sort_order')
                ->get(['id', 'name', 'academic_year_id'])
                ->map(fn (Term $term) => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'academic_year_id' => $term->academic_year_id,
                ])->values()->all(),
            'teachers' => DB::table('teachers')
                ->where('status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                ])->values()->all(),
            'classes' => ClassRoom::query()
                ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                ->orderBy('name')
                ->orderBy('section')
                ->get(['id', 'name', 'section', 'academic_year_id'])
                ->map(fn (ClassRoom $class) => [
                    'id' => $class->id,
                    'name' => trim($class->name.' '.($class->section ?? '')),
                    'academic_year_id' => $class->academic_year_id,
                ])->values()->all(),
            'rooms' => Room::query()
                ->where('active', true)
                ->where('bookable', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Room $room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                ])->values()->all(),
        ];
    }
}
