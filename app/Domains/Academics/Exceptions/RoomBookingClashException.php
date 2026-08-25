<?php

namespace App\Domains\Academics\Exceptions;

use RuntimeException;

class RoomBookingClashException extends RuntimeException
{
    /**
     * @param  list<array{type: string, id: int}>  $conflicts
     */
    public function __construct(public array $conflicts)
    {
        $summary = collect($conflicts)
            ->map(fn (array $conflict) => $conflict['type'].'#'.$conflict['id'])
            ->implode(', ');

        parent::__construct('Room booking clashes: '.$summary);
    }
}
