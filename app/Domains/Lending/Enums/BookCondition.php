<?php

namespace App\Domains\Lending\Enums;

/** How worn a lent book is. The Bookstore grades its own; a lent book is never "new". */
enum BookCondition: string
{
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';
    case Worn = 'worn';

    public function label(): string
    {
        return __('lending.condition_'.$this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
