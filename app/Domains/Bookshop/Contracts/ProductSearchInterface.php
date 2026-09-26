<?php

namespace App\Domains\Bookshop\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shop search (BOOKSHOP_PLAN §10 "Search", slice B9e): narrows the
 * catalogue's product query to what matches the visitor's words. The
 * listing keeps every other filter, the "for sale" rule and the sort; a
 * driver only says which products match and, when the visitor chose no
 * sort, in what order. Bound in `BookshopServiceProvider` from
 * `bookshop.search.driver` (rule 4: no search SDK in the domain).
 */
interface ProductSearchInterface
{
    /** The driver's name, for the office screen and logs. */
    public function name(): string;

    /**
     * @param  bool  $rank  order by relevance (the visitor chose no sort)
     */
    public function apply(Builder $query, string $words, bool $rank): Builder;
}
