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
        $summary = collect($conflicts)
            ->map(fn (array $conflict) => $conflict['type'].'#'.$conflict['timetable_id'])
            ->implode(', ');

        parent::__construct('Timetable conflicts: '.$summary);
    }
}
