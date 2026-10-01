<?php

use App\Domains\Bookshop\Actions\Orders\CheckoutReceiptAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P8: receipts by SMS link. The paid notice carries a
 * short link that opens the receipt without signing in — the lines and
 * totals per shop, GST where the shop is registered — and nothing a
 * forwarded link should not show.
 */
beforeEach(function () {
    Mail::fake();
});

function receiptSms(): object
{
    $sms = new class implements SmsSenderInterface
    {
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = [$phoneNumber, $message];

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return ['success' => true, 'driver' => 'log'];
        }
    };
    app()->instance(SmsSenderInterface::class, $sms);

    return $sms;
}

function receiptCheckout(string $payment = 'wallet'): BookshopCheckout
{
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'legal_name' => 'Fitrah Pvt Ltd', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'gst_registered' => true, 'tin' => '1234567GST501']);
    $method = VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 25, 'handling_days' => 1, 'is_active' => true]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'fitrah-book', 'title' => 'Tracing Book', 'price' => 100, 'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    $customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($customer->id, 1000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 2]);
    test()->withoutLocalizationMiddleware()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath Secret', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Hidden Villa',
        'delivery' => [$vendor->slug => 'm'.$method->id], 'payment_method' => $payment,
    ])->assertSessionHasNoErrors();
    auth()->logout();

    return BookshopCheckout::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
}

it('sends the receipt link in the paid SMS, ahead of the tracking link, inside one message', function () {
    $sms = receiptSms();
    $checkout = receiptCheckout();

    expect($checkout->receipt_token)->toMatch('/^[a-z0-9]{16}$/');
    $toCustomer = collect($sms->sent)->firstWhere(0, '7712345');
    $link = route('public.shop.receipt', $checkout->receipt_token);
    expect($toCustomer[1])->toContain($link)
        ->and(strpos($toCustomer[1], $link))->toBeLessThan(strpos($toCustomer[1], route('public.shop.track', ['number' => $checkout->orders()->value('number')])) ?: PHP_INT_MAX)
        ->and(mb_strlen($toCustomer[1]))->toBeLessThanOrEqual((int) config('bookshop.notices.sms_max_length'));
});

it('opens the receipt without signing in: the shop, the lines, the totals and the GST, and never the address or phone', function () {
    receiptSms();
    $checkout = receiptCheckout();
    $order = $checkout->orders()->firstOrFail();

    $page = test()->withoutLocalizationMiddleware()->get(route('public.shop.receipt', $checkout->receipt_token))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $page->assertSee($order->number)->assertSee('Fitrah Pvt Ltd')->assertSee('Tracing Book')->assertSee((string) $order->total)
        ->assertSee('data-testid="receipt-gst"', false)
        ->assertDontSee('M. Hidden Villa')->assertDontSee('Aishath Secret')->assertDontSee('7712345');
});

it('gives the same link every time, and nothing for an unknown token or an unpaid checkout', function () {
    receiptSms();
    $checkout = receiptCheckout();
    $token = $checkout->receipt_token;
    expect(app(CheckoutReceiptAction::class)->link($checkout->fresh()))->toBe(route('public.shop.receipt', $token));

    test()->withoutLocalizationMiddleware()->get(route('public.shop.receipt', 'zzzzzzzzzzzzzzzz'))->assertNotFound();
    $checkout->forceFill(['paid_at' => null])->save();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.receipt', $token))->assertNotFound();
});

it('makes no link for a bank transfer until the office confirms the slip', function () {
    receiptSms();
    $checkout = receiptCheckout('bank_transfer');
    expect($checkout->receipt_token)->toBeNull();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['notice_paid_receipt', 'receipt_link_title', 'receipt_link_total', 'receipt_link_private'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
