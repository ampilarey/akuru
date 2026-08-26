<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Term;

class ResolveDefaultTermPeriodAction
{
    /**
     * @return array{start_date: ?string, end_date: ?string}|null
     */
    public function execute(int $academicYearId): ?array
    {
        $term = Term::query()
            ->where('academic_year_id', $academicYearId)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('sort_order')
            ->first();

        if ($term === null) {
            return null;
        }

        return [
            'start_date' => $term->start_date?->toDateString(),
            'end_date' => $term->end_date?->toDateString(),
        ];
    }
}
