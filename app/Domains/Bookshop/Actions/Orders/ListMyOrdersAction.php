<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Models\Order;

/**
 * A customer's orders, newest first (BOOKSHOP_PLAN §4). Only their own.
 *
 * @return list<array<string, mixed>>
 */
class ListMyOrdersAction
{
    public function execute(int $userId, int $limit = 100): array
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with(['vendor', 'items'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $o) => PresentOrderAction::summary($o))
            ->values()->all();
    }
}
