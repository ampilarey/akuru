<?php

namespace App\Domains\Academics\Exceptions;

use RuntimeException;

class TimetableConflictException extends RuntimeException
{
    /**
     * @param  list<array{type: string, timetable_id: int}>  $conflicts
     */
    public function __construct(public array $conflicts)
    {
        $list = collect($conflicts)
            ->map(fn (array $conflict) => __('academics.conflict_'.$conflict['type']))
            ->unique()
            ->implode(', ');

        parent::__construct(__('academics.error_timetable_conflicts', ['list' => $list]));
    }
}
