<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListActiveTeachersAction
{
    /**
     * @return Collection<int, object{id: int, first_name: string, last_name: string}>
     */
    public function execute(): Collection
    {
        return DB::table('teachers')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }
}
