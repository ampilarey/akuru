<?php

namespace App\Domains\Bookshop\Services;

use App\Domains\Bookshop\Contracts\ProductSearchInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The v1 search (§10): the words anywhere in the titles (three languages),
 * summary, description, SKU, barcode, tags or the shop's name. No ranking
 * of its own — the listing's sort applies.
 */
class DatabaseProductSearch implements ProductSearchInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function apply(Builder $query, string $words, bool $rank): Builder
    {
        $like = '%'.$words.'%';

        return $query->where(fn ($w) => $w
            ->where('title', 'like', $like)
            ->orWhere('title_dv', 'like', $like)
            ->orWhere('title_ar', 'like', $like)
            ->orWhere('summary', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('barcode', 'like', $like)
            ->orWhere('tags', 'like', $like)
            ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like)));
    }
}
