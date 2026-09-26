<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Models\Order;

/**
 * What a customer does to their own order after placing it (B3): cancel it
 * before it leaves the shop, or write to the shop. The order is looked up
 * by its number **and** the customer's id, so nobody reaches another
 * person's order.
 */
class CustomerOrderAction
{
    public function cancel(int $userId, string $number, string $reason): Order
    {
        return app(CancelOrderAction::class)->execute($this->own($userId, $number), $userId, $reason, byCustomer: true);
    }

    public function message(int $userId, string $number, string $body): int
    {
        return app(SendOrderMessageAction::class)->execute($this->own($userId, $number), $userId, $body);
    }

    private function own(int $userId, string $number): Order
    {
        return Order::query()->where('user_id', $userId)->where('number', $number)->with(['checkout', 'vendor', 'items'])->firstOrFail();
    }
}
