<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\ExamType;
use Illuminate\Support\Facades\DB;

class ListExamCatalogAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $catalog = app(ListGradingCatalogAction::class)->execute();

        return [
            ...$catalog,
            'terms' => DB::table('terms')
                ->orderBy('sort_order')
                ->orderBy('start_date')
                ->get(['id', 'academic_year_id', 'name', 'status'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'academic_year_id' => (int) $row->academic_year_id,
                    'name' => $row->name,
                    'status' => $row->status,
                ])
                ->all(),
            'rooms' => DB::table('rooms')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'bookable'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'bookable' => (bool) $row->bookable,
                ])
                ->all(),
            'examTypes' => ExamType::query()->where('active', true)->orderBy('name')->get()
                ->map(fn (ExamType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code->value,
                ])
                ->all(),
        ];
    }
}
