<?php

namespace App\Domains\Lending\Enums;

/** What a listed book is offered as (L3): to borrow and bring back, or free to keep. */
enum BookOffer: string
{
    case Lend = 'lend';
    case Give = 'give';

    public function label(): string
    {
        return __('lending.offer_'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $o) => $o->value, self::cases());
    }
}
