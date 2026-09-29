<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STATUS §5lj, track an order without signing in: its number and the phone
 * it is going to, both or nothing; the status, steps, tracking note and what
 * is in it — never the address, the name, the money or the messages.
 */
function trackedOrder(): Order
{
    $vendor = Vendor::query()->create(['name' => 'Track Shop', 'slug' => 'track-shop', 'code' => 'TRK', 'status' => 'active']);
    $buyer = User::factory()->create();
    $checkout = BookshopCheckout::query()->create(['number' => 'AK-2026-000777', 'user_id' => $buyer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['recipient_name' => 'Aishath Secret', 'phone' => '+960 771-2345', 'street' => 'Secret Street', 'island' => 'Malé', 'atoll' => 'K'], 'subtotal' => 250, 'discount' => 0, 'delivery_total' => 0, 'total' => 250, 'currency' => 'MVR', 'paid_at' => now()]);
    $order = Order::query()->create(['number' => 'AK-2026-000777-TRK', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $buyer->id, 'status' => 'dispatched', 'delivery_kind' => 'courier_male', 'delivery_name' => 'Courier', 'address_snapshot' => $checkout->address_snapshot, 'subtotal' => 250, 'total' => 250, 'currency' => 'MVR', 'paid_at' => now()->subDays(2), 'processing_at' => now()->subDay(), 'dispatched_at' => now(), 'carrier' => 'Bike', 'tracking_note' => 'Out today', 'notes' => 'Private note']);
    OrderItem::query()->create(['order_id' => $order->id, 'title' => 'Maths Workbook', 'unit_price' => 125, 'quantity' => 2, 'line_total' => 250, 'tax_class' => 'zero_rated', 'tax_amount' => 0]);

    return $order;
}

function trackGet(array $query = [])
{
    return test()->withoutLocalizationMiddleware()->get(route('public.shop.track', $query));
}

it('shows the form, then the order\'s status, steps, tracking and items for the right number and phone', function () {
    trackedOrder();

    trackGet()->assertOk()->assertSee('data-testid="track-form"', false)->assertDontSee('data-testid="track-result"', false);

    trackGet(['number' => 'AK-2026-000777-TRK', 'phone' => '7712345'])->assertOk()
        ->assertSee('data-testid="track-result"', false)
        ->assertSee('data-status="dispatched"', false)
        ->assertSee('data-step="dispatched" data-done="1"', false)->assertSee('data-step="delivered" data-done="0"', false)
        ->assertSee('Bike')->assertSee('Out today')
        ->assertSee('2 × Maths Workbook')->assertSee('Track Shop')
        ->assertDontSee('Aishath Secret')->assertDontSee('Secret Street')->assertDontSee('Private note')->assertDontSee('250.00');

    // The phone as written at checkout, with the country code and dashes, is the same phone.
    trackGet(['number' => 'AK-2026-000777-TRK', 'phone' => '+960 7712345'])->assertSee('data-testid="track-result"', false);
});

it('shows nothing for a wrong phone, a wrong number, or a phone too short to mean anything', function () {
    trackedOrder();

    foreach ([['AK-2026-000777-TRK', '7719999'], ['AK-2026-000778-TRK', '7712345'], ['AK-2026-000777-TRK', '45']] as [$number, $phone]) {
        trackGet(['number' => $number, 'phone' => $phone])->assertOk()
            ->assertSee(__('shop.track_none'))->assertDontSee('Maths Workbook');
    }
});

it('is throttled against guessing', function () {
    trackedOrder();
    foreach (range(1, 10) as $n) {
        trackGet(['number' => 'AK-2026-000777-TRK', 'phone' => '77100'.str_pad((string) $n, 2, '0', STR_PAD_LEFT)])->assertOk();
    }
    trackGet(['number' => 'AK-2026-000777-TRK', 'phone' => '7712345'])->assertStatus(429);
});
