<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\Order;

/**
 * Every order, for the office (BOOKSHOP_PLAN §7) and its CSV. Customers are
 * named through the auth model from config (rule 3); the phone is on the
 * order's own address snapshot.
 *
 * @return list<array<string, mixed>>
 */
class ListOrdersAction
{
    /**
     * @param  array{vendor?: mixed, status?: ?string, from?: ?string, to?: ?string}  $filters  B8: the export's filters
     */
    public function execute(int $limit = 500, array $filters = []): array
    {
        $orders = $this->query($filters)->with(['vendor', 'items', 'checkout'])->orderByDesc('id')->limit($limit)->get();

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $orders->pluck('user_id')->unique()->all())->get(['id', 'name', 'email'])->keyBy('id');

        return $orders->map(fn (Order $o) => [
            'id' => $o->id,
            'number' => $o->number,
            'status' => $o->status->value,
            'vendor' => $o->vendor->name,
            'customer' => $people->get($o->user_id)?->name ?? ('#'.$o->user_id),
            'customer_email' => $people->get($o->user_id)?->email,
            'payment_method' => $o->checkout?->payment_method->value,
            'items' => (int) $o->items->sum('quantity'),
            'subtotal' => (string) $o->subtotal,
            'discount' => (string) $o->discount,
            'delivery' => $o->delivery_name,
            'delivery_fee' => (string) $o->delivery_fee,
            'tax' => (string) $o->tax,
            'total' => (string) $o->total,
            'currency' => $o->currency,
            'island' => trim(($o->address_snapshot['atoll'] ?? '').' '.($o->address_snapshot['island'] ?? '')),
            'placed_at' => $o->created_at?->toDateTimeString(),
            'paid_at' => $o->paid_at?->toDateTimeString(),
        ])->values()->all();
    }

    /**
     * B8: one row per order line across every shop, for the office's books.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function lines(array $filters = [], int $limit = 20000): array
    {
        $orders = $this->query($filters)->with(['vendor', 'items'])->orderByDesc('id')->limit($limit)->get();
        $out = [];
        foreach ($orders as $o) {
            foreach ($o->items as $i) {
                $out[] = [
                    'number' => $o->number, 'status' => $o->status->value, 'vendor' => $o->vendor->name,
                    'placed_at' => $o->created_at?->toDateTimeString(), 'paid_at' => $o->paid_at?->toDateTimeString(),
                    'sku' => $i->sku, 'title' => $i->title, 'variant' => $i->variant_name, 'quantity' => (int) $i->quantity,
                    'unit_price' => (string) $i->unit_price, 'line_total' => (string) $i->line_total,
                    'tax_class' => $i->tax_class instanceof \BackedEnum ? $i->tax_class->value : (string) $i->tax_class,
                    'tax_amount' => (string) $i->tax_amount, 'currency' => $o->currency,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters)
    {
        return Order::query()
            ->when(! empty($filters['vendor']), fn ($q) => $q->where('vendor_id', (int) $filters['vendor']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', (string) $filters['status']))
            ->when(! empty($filters['from']), fn ($q) => $q->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(! empty($filters['to']), fn ($q) => $q->where('created_at', '<=', $filters['to'].' 23:59:59'));
    }
}
