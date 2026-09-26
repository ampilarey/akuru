<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Support\LowStock;

/**
 * Low stock across every shop, for the office (BOOKSHOP_PLAN §7 Reports
 * "low stock across vendors", slice B8), and its CSV.
 */
class ListLowStockAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $limit = 2000): array
    {
        return LowStock::rows(null, $limit);
    }
}
