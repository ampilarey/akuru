<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\ClassRoom;
use Illuminate\Support\Collection;

class ListClassesForYearAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $query = ClassRoom::query()->orderBy('name')->orderBy('section');
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return $query->get(['id', 'academic_year_id', 'name', 'section', 'is_active'])->map(fn (ClassRoom $class) => [
            'id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'name' => $class->name,
            'section' => $class->section,
            'label' => trim($class->name.' '.$class->section),
            'is_active' => $class->is_active,
        ]);
    }
}
