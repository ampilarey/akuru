<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\QuoteItem;
use App\Domains\Bookshop\Models\QuoteRequest;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bulk quotes for schools, the customer's side (BOOKSHOP_PLAN B9 "bulk
 * quotes for schools (B2B)", slice B9d). A signed-in customer asks one shop
 * to price the lines of theirs in the cart — for a school or group, with
 * enough items to be worth a quote — sees the shop's price and how long it
 * holds, and accepts: the lines go back into the cart at the quoted price
 * and quantity, and the checkout charges that while the quote holds. They
 * may withdraw instead. Only their own quotes, ever.
 */
class CustomerQuotesAction
{
    /**
     * @param  array{organisation: string, contact_phone?: ?string, note?: ?string}  $data
     */
    public function request(int $userId, string $vendorSlug, array $data): QuoteRequest
    {
        $vendor = Vendor::query()->where('slug', $vendorSlug)->where('status', 'active')->firstOrFail();
        $cart = Cart::query()->where('user_id', $userId)->first();
        $items = $cart === null ? collect() : $cart->items()->whereNull('quote_item_id')->with(['product', 'variant'])->get()
            ->filter(fn (CartItem $i) => $i->product !== null && (int) $i->product->vendor_id === (int) $vendor->id)->values();
        $min = (int) config('bookshop.quotes.min_quantity', 10);
        if ((int) $items->sum('quantity') < $min) {
            throw ValidationException::withMessages(['organisation' => __('shop.error_quote_min', ['min' => $min, 'vendor' => $vendor->name])]);
        }

        $quote = DB::transaction(function () use ($userId, $vendor, $items, $data) {
            $quote = QuoteRequest::query()->create([
                'number' => $this->nextNumber(),
                'vendor_id' => $vendor->id,
                'user_id' => $userId,
                'organisation' => trim($data['organisation']),
                'contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'status' => 'requested',
                'currency' => (string) config('bookshop.currency', 'MVR'),
            ]);
            $total = 0.0;
            foreach ($items as $item) {
                /** @var CartItem $item */
                $price = (float) ($item->variant?->price ?? $item->product->price);
                QuoteItem::query()->create([
                    'quote_request_id' => $quote->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'title' => $item->product->title,
                    'variant_name' => $item->variant?->name,
                    'sku' => $item->variant?->sku ?: $item->product->sku,
                    'quantity' => (int) $item->quantity,
                    'list_price' => $price,
                ]);
                $total += $price * (int) $item->quantity;
            }
            $quote->update(['list_total' => round($total, 2)]);

            return $quote->refresh();
        });

        app(NotifyBookshopUserAction::class)->vendor((int) $vendor->id, __('shop.notice_quote_requested_title'),
            __('shop.notice_quote_requested_body', ['number' => $quote->number, 'organisation' => $quote->organisation]), '/vendor/quotes', 'quote_requested');

        return $quote;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $userId): array
    {
        return QuoteRequest::query()->where('user_id', $userId)->with(['vendor:id,name,slug', 'items'])->orderByDesc('id')->get()
            ->map(fn (QuoteRequest $q) => self::present($q))->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function show(int $userId, string $number): ?array
    {
        $quote = QuoteRequest::query()->where('user_id', $userId)->where('number', $number)->with(['vendor:id,name,slug', 'items'])->first();

        return $quote === null ? null : self::present($quote);
    }

    /** Accept (or, once accepted, put back into the cart): the quoted lines at the quoted price. */
    public function accept(int $userId, string $number): QuoteRequest
    {
        return DB::transaction(function () use ($userId, $number) {
            $quote = QuoteRequest::query()->where('user_id', $userId)->where('number', $number)->lockForUpdate()->firstOrFail();
            if (! $quote->priceHolds()) {
                throw ValidationException::withMessages(['quote' => __('shop.error_quote_not_open')]);
            }
            $cart = Cart::query()->firstOrCreate(['user_id' => $userId]);
            foreach ($quote->items()->get() as $line) {
                /** @var QuoteItem $line */
                if ($line->product_id === null) {
                    continue;
                }
                // The quote replaces the same product in the cart, never adds to it.
                CartItem::query()->where('cart_id', $cart->id)->where('product_id', $line->product_id)->where('product_variant_id', $line->product_variant_id)->delete();
                CartItem::query()->create([
                    'cart_id' => $cart->id, 'product_id' => $line->product_id, 'product_variant_id' => $line->product_variant_id,
                    'quantity' => $line->quantity, 'quote_item_id' => $line->id,
                ]);
            }
            if ($quote->status === 'quoted') {
                $quote->update(['status' => 'accepted', 'accepted_at' => now()]);
            }

            return $quote->refresh();
        });
    }

    public function withdraw(int $userId, string $number): QuoteRequest
    {
        $quote = QuoteRequest::query()->where('user_id', $userId)->where('number', $number)->firstOrFail();
        if (! in_array($quote->status, ['requested', 'quoted', 'accepted'], true)) {
            throw ValidationException::withMessages(['quote' => __('shop.error_quote_not_open')]);
        }
        $quote->update(['status' => 'withdrawn']);
        CartItem::query()->whereIn('quote_item_id', $quote->items()->pluck('id'))->delete();

        return $quote->refresh();
    }

    /** A checkout with quoted lines was paid (or placed for cash): its quotes are ordered. */
    public function markOrdered(int $checkoutId): void
    {
        $lineIds = OrderItem::query()->whereHas('order', fn ($q) => $q->where('bookshop_checkout_id', $checkoutId))->whereNotNull('quote_item_id')->pluck('quote_item_id');
        if ($lineIds->isEmpty()) {
            return;
        }
        QuoteRequest::query()->whereIn('id', QuoteItem::query()->whereIn('id', $lineIds)->pluck('quote_request_id'))
            ->whereIn('status', ['quoted', 'accepted'])->update(['status' => 'ordered', 'ordered_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(QuoteRequest $q): array
    {
        return [
            'id' => $q->id,
            'number' => $q->number,
            'status' => $q->status,
            'vendor' => ['name' => $q->vendor?->name, 'slug' => $q->vendor?->slug],
            'organisation' => $q->organisation,
            'contact_phone' => $q->contact_phone,
            'note' => $q->note,
            'currency' => $q->currency,
            'list_total' => number_format((float) $q->list_total, 2, '.', ''),
            'quoted_total' => $q->quoted_total !== null ? number_format((float) $q->quoted_total, 2, '.', '') : null,
            'saving' => $q->quoted_total !== null ? number_format(max(0, (float) $q->list_total - (float) $q->quoted_total), 2, '.', '') : null,
            'valid_until' => $q->valid_until?->toDateString(),
            'holds' => $q->priceHolds(),
            'vendor_note' => $q->vendor_note,
            'requested_at' => $q->created_at?->toDateTimeString(),
            'quoted_at' => $q->quoted_at?->toDateTimeString(),
            'items' => $q->items->map(fn (QuoteItem $i) => [
                'id' => $i->id, 'title' => $i->title, 'variant' => $i->variant_name, 'sku' => $i->sku, 'quantity' => $i->quantity,
                'list_price' => number_format((float) $i->list_price, 2, '.', ''),
                'quoted_price' => $i->quoted_price !== null ? number_format((float) $i->quoted_price, 2, '.', '') : null,
            ])->values()->all(),
        ];
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $last = QuoteRequest::query()->where('number', 'like', 'QT-'.$year.'-%')->lockForUpdate()->max('number');
        $n = $last !== null ? (int) substr((string) $last, -6) + 1 : 1;

        return sprintf('QT-%s-%06d', $year, $n);
    }
}
