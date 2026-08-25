<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Audience;
use Illuminate\Support\Collection;

class ListAudiencesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return Audience::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (Audience $row) => [
                'id' => $row->id,
                'name_en' => $row->name_en,
                'name_dv' => $row->name_dv,
                'name_ar' => $row->name_ar,
                'slug' => $row->slug,
                'sort_order' => $row->sort_order,
                'active' => $row->active,
            ])->values();
    }
}
