<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

class ResolveDefaultSchoolIdAction
{
    public function execute(): ?int
    {
        $id = DB::table('schools')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
