<?php

namespace App\Domains\Bookshop\Enums;

/**
 * LENDING_AND_USED_BOOKS_PLAN U1 (decision D2): what state a product is
 * in. Everything sold before this slice is `new`. Anything else is a used
 * book, shown with its grade on the card and the page, on the Used shelf
 * and behind the Used filter.
 */
enum ProductCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';
    case Worn = 'worn';

    public function isUsed(): bool
    {
        return $this !== self::New;
    }

    /** The grade in the page's language (`shop.condition_<value>`). */
    public function label(): string
    {
        return __('shop.condition_'.$this->value);
    }

    /** Schema.org's word for it, for the product page's structured data. */
    public function schema(): string
    {
        return match ($this) {
            self::New => 'https://schema.org/NewCondition',
            self::Worn => 'https://schema.org/DamagedCondition',
            default => 'https://schema.org/UsedCondition',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
