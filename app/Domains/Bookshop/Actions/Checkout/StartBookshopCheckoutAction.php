<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Enums\CheckoutPaymentMethod;
use App\Domains\Bookshop\Enums\CheckoutStatus;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CustomerAddress;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\StockReservation;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\CartPrice;
use App\Domains\Bookshop\Support\OrderNumbers;
use App\Domains\Bookshop\Support\Stock;
use App\Domains\Bookshop\Support\Tax;
use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\DescribeDiscountCodeAction;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A basket becomes a checkout and one order per vendor (BOOKSHOP_PLAN §4,
 * §8; decision 7).
 *
 * In one transaction: every line is re-checked against what can be sold
 * now, stock is **reserved** for thirty minutes (audit finding 2), prices,
 * a discount (Akuru-funded in B2; vendor-funded codes are B7), delivery
 * fees and tax are computed, and the checkout, orders, items and events
 * are written. Then, by payment method:
 *
 *  - **wallet**: debited at once and the checkout is paid (internal money);
 *  - **card**: a BML payment is initiated and the customer is sent to the
 *    bank; the webhook listener pays the checkout (rule 12);
 *  - **bank transfer**: the checkout waits for a slip; the office confirms;
 *  - a basket a discount brought to **zero** is paid at once.
 *
 * The cart is emptied only when the checkout is on its way — a refusal
 * leaves the basket as it was.
 */
class StartBookshopCheckoutAction
{
    /**
     * @param  array<string, mixed>  $data  address (id or fields), delivery (vendor slug => option key), payment_method, discount_code, notes
     * @param  (\Closure(string): string)|null  $returnUrl  given the checkout number, the page the bank sends the customer back to
     * @return array{checkout: BookshopCheckout, redirect_url: ?string, error: ?string, paid: bool}
     */
    public function execute(int $userId, Cart $cart, array $data, ?\Closure $returnUrl = null): array
    {
        $method = CheckoutPaymentMethod::from((string) ($data['payment_method'] ?? 'card'));
        $address = $this->address($userId, $data);

        $result = DB::transaction(function () use ($userId, $cart, $data, $method, $address) {
            $items = $cart->items()->with(['product.vendor', 'variant'])->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => __('shop.error_cart_empty')]);
            }

            // 1. Every line, checked and priced.
            $lines = [];
            foreach ($items as $item) {
                $product = Product::query()->whereKey($item->product_id)->with('vendor')->lockForUpdate()->first();
                $variant = $item->variant;
                $sellable = $product !== null && $product->status->value === 'active' && $product->vendor->status->value === 'active'
                    && ($variant === null || $variant->is_active);
                if (! $sellable) {
                    throw ValidationException::withMessages(['cart' => __('shop.error_not_for_sale_named', ['title' => $item->product->title])]);
                }
                if ($product->vendor->onHoliday()) {
                    throw ValidationException::withMessages(['cart' => __('shop.error_on_holiday', ['vendor' => $product->vendor->name, 'date' => $product->vendor->holiday_until->copy()->addDay()->toDateString()])]);
                }
                $available = Stock::available($product, $variant);
                if ($available !== null && $item->quantity > $available && ! Stock::madeToOrder($product)) {
                    throw ValidationException::withMessages(['cart' => $available <= 0
                        ? __('shop.error_sold_out', ['title' => $product->title])
                        : __('shop.error_only_n_left', ['count' => $available, 'title' => $product->title])]);
                }
                // B9d: the quoted price while the quote holds, else the list price.
                $unit = CartPrice::unit($item, $product, $variant);
                $lines[] = [
                    'product' => $product, 'variant' => $variant, 'quantity' => (int) $item->quantity,
                    'quote_item_id' => CartPrice::quoted($item, $product, $variant) !== null ? (int) $item->quote_item_id : null,
                    'unit' => $unit, 'total' => round($unit * $item->quantity, 2),
                ];
            }

            $byVendor = collect($lines)->groupBy(fn (array $l) => (int) $l['product']->vendor_id);
            $subtotal = round(collect($lines)->sum('total'), 2);

            // 2. The discount, on goods only (delivery is never discounted).
            $discount = 0.0;
            $resolved = null;
            $subtotals = $byVendor->map(fn ($ls) => round($ls->sum('total'), 2))->all();
            $code = trim((string) ($data['discount_code'] ?? ''));
            $scope = $code !== '' ? app(DescribeDiscountCodeAction::class)->execute($code) : null;
            if ($scope !== null && $scope['applies_to_type'] === 'vendor') {
                // B7 (§6.5): a vendor-funded code, priced against that vendor's goods only and borne by its order alone.
                $vendorId = (int) $scope['applies_to_id'];
                if (! array_key_exists($vendorId, $subtotals)) {
                    throw ValidationException::withMessages(['discount_code' => __('shop.error_code_other_shop', ['vendor' => (string) Vendor::query()->whereKey($vendorId)->value('name')])]);
                }
                $resolved = app(ResolveDiscountAction::class)->execute($code, $userId, $subtotals[$vendorId], $method === CheckoutPaymentMethod::Wallet, 'vendor', $vendorId);
                $discount = (float) $resolved['amount_discounted'];
                $shares = array_map(fn () => 0.0, $subtotals);
                $shares[$vendorId] = $discount;
            } else {
                if ($code !== '') {
                    $resolved = app(ResolveDiscountAction::class)->execute($code, $userId, $subtotal, $method === CheckoutPaymentMethod::Wallet);
                    $discount = (float) $resolved['amount_discounted'];
                }
                $shares = $this->shares($subtotals, $discount);
            }

            // 3. Delivery per vendor.
            $deliveryTotal = 0.0;
            $chosen = [];
            foreach ($byVendor as $vendorId => $vendorLines) {
                $vendor = $vendorLines->first()['product']->vendor;
                $options = app(ResolveDeliveryOptionsAction::class)->execute($vendor, (float) $vendorLines->sum('total'));
                $key = (string) (($data['delivery'] ?? [])[$vendor->slug] ?? '');
                $option = collect($options)->firstWhere('key', $key);
                if ($option === null || ! $option['offered']) {
                    throw ValidationException::withMessages(['delivery' => __('shop.error_choose_delivery', ['vendor' => $vendor->name])]);
                }
                $chosen[$vendorId] = $option;
                $deliveryTotal += (float) $option['fee'];
            }

            $total = round($subtotal - $discount + $deliveryTotal, 2);
            $effective = $total <= 0 ? CheckoutPaymentMethod::None : $method;
            // B9b: cash on delivery — every shop in the basket must take it, for this delivery and this amount.
            if ($effective === CheckoutPaymentMethod::CashOnDelivery) {
                foreach ($byVendor as $vendorId => $vendorLines) {
                    $vendor = $vendorLines->first()['product']->vendor;
                    $orderTotal = round($vendorLines->sum('total') - ($shares[$vendorId] ?? 0.0) + (float) $chosen[$vendorId]['fee'], 2);
                    $blocker = app(CashOnDeliveryAction::class)->blocker($vendor, (string) $chosen[$vendorId]['kind'], $orderTotal);
                    if ($blocker !== null) {
                        throw ValidationException::withMessages(['payment_method' => $blocker]);
                    }
                }
            }
            $minutes = (int) config('bookshop.checkout.reservation_minutes', 30);

            // 4. The checkout and its orders.
            $checkout = BookshopCheckout::query()->create([
                'number' => OrderNumbers::nextCheckoutNumber(),
                'user_id' => $userId,
                'status' => CheckoutStatus::PendingPayment->value,
                'payment_method' => $effective->value,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_total' => round($deliveryTotal, 2),
                'total' => $total,
                'currency' => config('bookshop.currency', 'MVR'),
                'discount_code_id' => $resolved['discount_code']->id ?? null,
                'address_snapshot' => $address,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'expires_at' => now()->addMinutes($minutes),
            ]);

            foreach ($byVendor as $vendorId => $vendorLines) {
                /** @var Vendor $vendor */
                $vendor = $vendorLines->first()['product']->vendor;
                $option = $chosen[$vendorId];
                $vendorSubtotal = round($vendorLines->sum('total'), 2);
                $vendorDiscount = $shares[$vendorId] ?? 0.0;
                $taxShown = (bool) $vendor->gst_registered;

                $order = Order::query()->create([
                    'number' => OrderNumbers::orderNumber($checkout->number, $vendor->code),
                    'bookshop_checkout_id' => $checkout->id,
                    'vendor_id' => $vendor->id,
                    'user_id' => $userId,
                    'status' => OrderStatus::PendingPayment->value,
                    'delivery_kind' => $option['kind'],
                    'delivery_name' => $option['name'],
                    'delivery_fee' => (float) $option['fee'],
                    'delivery_carrier_paid' => $option['carrier_paid'],
                    'delivery_handling_days' => $option['handling_days'],
                    'address_snapshot' => $address,
                    'subtotal' => $vendorSubtotal,
                    'discount' => $vendorDiscount,
                    'tax' => 0,
                    'total' => round($vendorSubtotal - $vendorDiscount + (float) $option['fee'], 2),
                    'currency' => $checkout->currency,
                    'tax_shown' => $taxShown,
                    'vendor_tin' => $taxShown ? $vendor->tin : null,
                    'notes' => $checkout->notes,
                ]);

                $tax = 0.0;
                foreach ($vendorLines as $l) {
                    // The vendor's discount share falls on its lines in proportion.
                    $lineShare = $vendorSubtotal > 0 ? round($vendorDiscount * $l['total'] / $vendorSubtotal, 2) : 0.0;
                    $lineTax = $taxShown ? Tax::inclusive($l['total'] - $lineShare, $l['product']->tax_class->value) : 0.0;
                    $tax += $lineTax;
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $l['product']->id,
                        'product_variant_id' => $l['variant']?->id,
                        'title' => $l['product']->title,
                        'variant_name' => $l['variant']?->name,
                        'sku' => $l['variant']?->sku ?: $l['product']->sku,
                        'unit_price' => $l['unit'],
                        'quantity' => $l['quantity'],
                        'line_total' => $l['total'],
                        'tax_class' => $l['product']->tax_class->value,
                        'tax_amount' => $lineTax,
                        'quote_item_id' => $l['quote_item_id'] ?? null,
                    ]);
                    if ($l['product']->track_stock) {
                        StockReservation::query()->create([
                            'bookshop_checkout_id' => $checkout->id,
                            'product_id' => $l['product']->id,
                            'product_variant_id' => $l['variant']?->id,
                            'quantity' => $l['quantity'],
                            'expires_at' => $checkout->expires_at,
                        ]);
                    }
                }
                $order->update(['tax' => round($tax, 2)]);
                OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'placed', 'actor_user_id' => $userId, 'created_at' => now(), 'meta' => ['payment_method' => $effective->value]]);
            }

            if ($resolved !== null) {
                app(RecordDiscountRedemptionAction::class)->execute($resolved['discount_code']->id, $userId, 'bookshop_checkout', $checkout->id, $discount);
            }

            // 5. Internal money settles here; the cart empties on its way out.
            if ($effective === CheckoutPaymentMethod::Wallet) {
                app(DebitWalletAction::class)->execute($userId, $total, 'bookshop_checkout', $checkout->id, 'Akuru Bookstore '.$checkout->number);
            }
            $cart->items()->delete();

            return $checkout;
        });

        $checkout = $result;
        if (in_array($checkout->payment_method, [CheckoutPaymentMethod::Wallet, CheckoutPaymentMethod::None], true)) {
            app(MarkCheckoutPaidAction::class)->execute($checkout->id, $checkout->payment_method->value);

            return ['checkout' => $checkout->refresh(), 'redirect_url' => null, 'error' => null, 'paid' => true];
        }

        if ($checkout->payment_method === CheckoutPaymentMethod::BankTransfer) {
            return ['checkout' => $checkout, 'redirect_url' => null, 'error' => null, 'paid' => false];
        }
        if ($checkout->payment_method === CheckoutPaymentMethod::CashOnDelivery) {
            app(CashOnDeliveryAction::class)->place($checkout->id);

            return ['checkout' => $checkout->refresh(), 'redirect_url' => null, 'error' => null, 'paid' => false];
        }

        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'bookshop_checkout', $checkout->id, $userId, (float) $checkout->total, $checkout->currency,
            $returnUrl !== null ? $returnUrl($checkout->number) : null,
        );
        $checkout->payment_id = $initiated['payment']->id;
        if ($initiated['redirect_url'] === null) {
            $checkout->status = CheckoutStatus::Failed;
            $checkout->reservations()->delete();
            app(RecordDiscountRedemptionAction::class)->releaseAbandoned('bookshop_checkout', [$checkout->id]);
        }
        $checkout->save();

        return ['checkout' => $checkout->refresh(), 'redirect_url' => $initiated['redirect_url'], 'error' => $initiated['error'], 'paid' => false];
    }

    /**
     * A saved address by id, or new fields (saved to the book when asked).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, ?string>
     */
    private function address(int $userId, array $data): array
    {
        if (! empty($data['address_id'])) {
            $saved = CustomerAddress::query()->where('user_id', $userId)->find((int) $data['address_id']);
            if ($saved === null) {
                throw ValidationException::withMessages(['address_id' => __('shop.error_address')]);
            }

            return $saved->snapshot();
        }

        $fields = ['recipient_name', 'phone', 'atoll', 'island', 'street'];
        foreach ($fields as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw ValidationException::withMessages([$field => __('shop.error_address')]);
            }
        }
        $snapshot = [
            'recipient_name' => trim((string) $data['recipient_name']),
            'phone' => trim((string) $data['phone']),
            'atoll' => trim((string) $data['atoll']),
            'island' => trim((string) $data['island']),
            'street' => trim((string) $data['street']),
            'notes' => trim((string) ($data['address_notes'] ?? '')) ?: null,
        ];
        if (! empty($data['save_address'])) {
            CustomerAddress::query()->create(['user_id' => $userId, 'label' => trim((string) ($data['address_label'] ?? '')) ?: null, 'is_default' => ! CustomerAddress::query()->where('user_id', $userId)->exists()] + $snapshot);
        }

        return $snapshot;
    }

    /**
     * A basket-wide discount split across vendors in proportion to their
     * goods, the last vendor absorbing the rounding so the shares add up.
     *
     * @param  array<int, float>  $subtotals
     * @return array<int, float>
     */
    private function shares(array $subtotals, float $discount): array
    {
        $total = array_sum($subtotals);
        if ($discount <= 0 || $total <= 0) {
            return array_map(fn () => 0.0, $subtotals);
        }
        $shares = [];
        $given = 0.0;
        $ids = array_keys($subtotals);
        foreach ($ids as $i => $id) {
            $share = $i === array_key_last($ids) ? round($discount - $given, 2) : round($discount * $subtotals[$id] / $total, 2);
            $shares[$id] = $share;
            $given += $share;
        }

        return $shares;
    }
}
