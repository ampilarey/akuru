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
        $list = collect($conflicts)
            ->map(fn (array $conflict) => __('academics.conflict_'.$conflict['type']))
            ->unique()
            ->implode(', ');

        parent::__construct(__('academics.error_booking_clashes', ['list' => $list]));
    }
}
