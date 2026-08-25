<?php

namespace App\Domains\ExamsGrades\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ListStandardsAction
{
    /**
     * @return Collection<int, array{id: int, code: string, title: string}>
     */
    public function execute(): Collection
    {
        if (! Schema::hasTable('standards')) {
            return collect();
        }

        return collect(DB::table('standards')->where('active', true)->orderBy('code')->get())
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'title' => (string) $row->title,
            ])
            ->values();
    }
}
