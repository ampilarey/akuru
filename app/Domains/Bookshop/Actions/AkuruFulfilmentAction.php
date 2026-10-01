<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\AkuruFulfilment;
use App\Domains\Bookshop\Support\OrderView;
use App\Domains\Settings\Actions\SetSettingAction;

/**
 * COMMERCE_PARITY_PLAN P6a, the office's side of Akuru fulfilment
 * (`/admin/bookshop/akuru`): the orders Akuru packs, oldest first, with
 * their next step; the shops Akuru packs or delivers for, with the stock
 * they have handed over; and the three charges.
 */
class AkuruFulfilmentAction
{
    public const OPEN = ['paid', 'processing', 'ready', 'dispatched', 'needs_attention'];

    /**
     * @return list<array<string, mixed>>
     */
    public function queue(int $limit = 200, bool $all = false): array
    {
        $query = Order::query()->with(['vendor:id,name,slug', 'items'])->where('fulfilled_by', 'akuru');
        $all ? $query->orderByDesc('id') : $query->whereIn('status', self::OPEN)->orderBy('paid_at');

        return $query->limit($limit)->get()->map(fn (Order $o) => [
            'id' => $o->id,
            'number' => $o->number,
            'vendor' => $o->vendor?->name,
            'status' => $o->status instanceof OrderStatus ? $o->status->value : (string) $o->status,
            'paid_at' => $o->paid_at?->format('Y-m-d H:i'),
            'delivery' => $o->delivery_name,
            'delivery_kind' => $o->delivery_kind?->value,
            'recipient' => trim((string) (($o->address_snapshot['recipient_name'] ?? '').' '.($o->address_snapshot['phone'] ?? ''))),
            'address' => collect([$o->address_snapshot['street'] ?? null, $o->address_snapshot['island'] ?? null, $o->address_snapshot['atoll'] ?? null])->filter()->implode(', '),
            'items' => $o->items->map(fn (OrderItem $i) => ['title' => $i->title, 'quantity' => (int) $i->quantity])->values()->all(),
            'handling_fee' => (string) $o->akuru_handling_fee,
            'next' => OrderView::nextSteps($o),
        ])->values()->all();
    }

    /**
     * The shops Akuru works for, and their counted products with what Akuru holds.
     *
     * @return list<array<string, mixed>>
     */
    public function shops(): array
    {
        return Vendor::query()->where(fn ($q) => $q->where('fulfilment', 'akuru')->orWhere('delivery_by', 'akuru'))->orderBy('name')->get()
            ->map(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'fulfilment' => $v->fulfilment,
                'delivery_by' => $v->delivery_by,
                'handling_fee' => number_format(AkuruFulfilment::handlingFee($v), 2, '.', ''),
                'products' => $v->fulfilment === 'akuru'
                    ? Product::query()->where('vendor_id', $v->id)->where('track_stock', true)->whereIn('status', ['active', 'draft', 'pending_review'])->orderBy('title')->limit(300)
                        ->get(['id', 'title', 'sku', 'stock', 'stock_at_akuru'])
                        ->map(fn (Product $p) => ['id' => $p->id, 'title' => $p->title, 'sku' => $p->sku, 'stock' => (int) $p->stock, 'at_akuru' => (int) $p->stock_at_akuru])->values()->all()
                    : [],
            ])->values()->all();
    }

    /** @param  array{handling_fee: mixed, delivery_fee: mixed, delivery_free_over: mixed}  $data */
    public function saveSettings(array $data): void
    {
        $set = app(SetSettingAction::class);
        foreach (AkuruFulfilment::SETTINGS as $field => $key) {
            $value = $data[$field] ?? null;
            $set->execute($key, $value === null || $value === '' ? '' : number_format((float) $value, 2, '.', ''), 'string', 'bookshop', 'Bookstore: Akuru '.str_replace('_', ' ', $field));
        }
    }
}
