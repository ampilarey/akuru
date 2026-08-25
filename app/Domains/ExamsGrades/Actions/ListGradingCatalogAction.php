<?php

namespace App\Domains\ExamsGrades\Actions;

use Illuminate\Support\Facades\DB;

class ListGradingCatalogAction
{
    /**
     * @return array{years: list<array<string, mixed>>, classes: list<array<string, mixed>>, subjects: list<array<string, mixed>>}
     */
    public function execute(): array
    {
        return [
            'years' => DB::table('academic_years')
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'status'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'status' => $row->status,
                ])
                ->all(),
            'classes' => DB::table('classes')
                ->orderBy('name')
                ->orderBy('section')
                ->get(['id', 'name', 'section', 'academic_year_id'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'section' => $row->section,
                    'academic_year_id' => $row->academic_year_id !== null ? (int) $row->academic_year_id : null,
                ])
                ->all(),
            'subjects' => DB::table('subjects')
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'code' => $row->code,
                ])
                ->all(),
        ];
    }
}
