<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListSubjectsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return DB::table('subjects')
            ->orderBy('name')
            ->get(['id', 'name', 'name_arabic', 'name_dhivehi', 'code'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'name_arabic' => $row->name_arabic,
                'name_dhivehi' => $row->name_dhivehi,
                'code' => $row->code,
            ])
            ->values();
    }
}
