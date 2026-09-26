<?php

use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\OrderReturn;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentRefund;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B3, fulfilment and returns: the shop works its queue
 * (processing → ready or dispatched → delivered or collected), cancels with
 * a reason before dispatch, answers returns and confirms its own transfers;
 * the customer tracks, cancels before dispatch, asks for returns and writes
 * to the shop; money goes back to the wallet at once or, for a card, through
 * the office; holiday mode pauses a shop.
 */
function fulfilShop(string $slug, array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'collect_vendor', 'name' => 'Collect', 'fee' => 0, 'handling_days' => 1, 'is_active' => true]);

    return [$vendor, $owner, $staff];
}

function fulfilProduct(Vendor $vendor, string $title, float $price, int $stock = 10): Product
{
    return Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function fulfilAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

/** Places an order through the real checkout and returns the orders it made, keyed by vendor slug. */
function fulfilBuy(User $customer, array $lines, string $method = 'wallet', string $delivery = 'courier_male', ?string $code = null): array
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    $choices = [];
    foreach ($lines as [$product, $quantity]) {
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
        $vendor = $product->vendor;
        $choices[$vendor->slug] = 'm'.VendorDeliveryMethod::query()->where('vendor_id', $vendor->id)->where('kind', $delivery)->value('id');
    }
    fulfilAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => $choices, 'payment_method' => $method, 'discount_code' => $code,
    ])->assertSessionHasNoErrors();

    // Oldest first, so keyBy keeps the newest order per shop.
    return Order::query()->where('user_id', $customer->id)->orderBy('id')->get()->keyBy(fn (Order $o) => $o->vendor->slug)->all();
}

function fulfilCustomer(float $wallet = 1000): User
{
    $customer = User::factory()->create();
    if ($wallet > 0) {
        app(CreditWalletAction::class)->execute($customer->id, $wallet, 'admin', null, 'Top-up');
    }

    return $customer;
}

function fulfilOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function fulfilBml(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/'.$payment->id);
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: 'x', providerReference: 'B3', status: 'completed', rawPayload: [], isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function walletOf(User $user): string
{
    return (string) Wallet::query()->where('user_id', $user->id)->value('balance');
}

it('shows the shop its queue by status and walks a delivery from paid to delivered, telling the customer', function () {
    [$fitrah, $owner, $staff] = fulfilShop('fitrah');
    [$other] = fulfilShop('other-shop');
    $customer = fulfilCustomer();
    $orders = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Workbook', 100), 2], [fulfilProduct($other, 'Puzzle', 50), 1]]);
    $order = $orders['fitrah'];

    fulfilAs($staff)->get(route('vendor.orders.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Bookshop/VendorOrders')
        ->has('orders', 1)
        ->where('orders.0.number', $order->number)
        ->where('orders.0.status', 'paid')
        ->where('orders.0.next', ['processing', 'dispatched'])
        ->where('orders.0.address.phone', '7712345')
        ->where('counts.paid', 1));

    // A step the order cannot take is refused; another shop's order is not found.
    fulfilAs($staff)->post(route('vendor.orders.advance', $order->id), ['to' => 'ready'])->assertSessionHasErrors('status');
    fulfilAs($staff)->post(route('vendor.orders.advance', $orders['other-shop']->id), ['to' => 'processing'])->assertNotFound();

    fulfilAs($staff)->post(route('vendor.orders.advance', $order->id), ['to' => 'processing'])->assertSessionHasNoErrors();
    fulfilAs($staff)->post(route('vendor.orders.advance', $order->id), ['to' => 'dispatched', 'carrier' => 'Maldives Post', 'tracking_note' => 'MP-445'])->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->status->value)->toBe('dispatched')->and($order->carrier)->toBe('Maldives Post')->and($order->dispatched_at)->not->toBeNull()
        ->and(DB::table('user_notifications')->where('user_id', $customer->id)->where('title', 'like', '%on its way%')->exists())->toBeTrue();

    // The customer tracks it, and can no longer cancel.
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()
        ->assertSee('Dispatched')->assertSee('Maldives Post MP-445')->assertDontSee('data-testid="cancel-order"', false);
    fulfilAs($customer)->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Too late'])->assertSessionHasErrors('order');

    fulfilAs($staff)->post(route('vendor.orders.advance', $order->id), ['to' => 'delivered'])->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('delivered');
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('Ask to return an item');

    // A collection order goes paid → ready → collected.
    $collect = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Pencils', 20), 1]], delivery: 'collect_vendor')['fitrah'];
    fulfilAs($owner)->post(route('vendor.orders.advance', $collect->id), ['to' => 'dispatched'])->assertSessionHasErrors('status');
    fulfilAs($owner)->post(route('vendor.orders.advance', $collect->id), ['to' => 'ready'])->assertSessionHasNoErrors();
    fulfilAs($owner)->post(route('vendor.orders.advance', $collect->id), ['to' => 'delivered'])->assertSessionHasNoErrors();
    fulfilAs($customer)->get(route('public.shop.orders.show', $collect->number))->assertOk()->assertSee('Collected');

    // Printable slip and label, own orders only; CSV of the queue.
    fulfilAs($staff)->get(route('vendor.orders.print', $order->id))->assertOk()->assertInertia(fn ($page) => $page->component('Bookshop/VendorOrderPrint')->where('order.number', $order->number));
    fulfilAs($staff)->get(route('vendor.orders.print', $orders['other-shop']->id))->assertNotFound();
    expect(fulfilAs($staff)->get(route('vendor.orders.export'))->assertOk()->streamedContent())->toContain($order->number)->not->toContain($orders['other-shop']->number);
    fulfilAs(User::factory()->create())->get(route('vendor.orders.index'))->assertForbidden();
});

it('lets the customer cancel before dispatch: stock back on the shelf, wallet money back at once, the shop told', function () {
    [$fitrah] = fulfilShop('fitrah');
    $book = fulfilProduct($fitrah, 'Workbook', 100, 5);
    $customer = fulfilCustomer(500);
    $order = fulfilBuy($customer, [[$book, 2]])['fitrah'];
    expect(walletOf($customer))->toBe('270.00')->and($book->refresh()->stock)->toBe(3);

    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('data-testid="cancel-order"', false);
    fulfilAs($customer)->post(route('public.shop.orders.cancel', $order->number), ['reason' => ''])->assertSessionHasErrors('reason');
    fulfilAs($customer)->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Ordered twice'])->assertSessionHasNoErrors();

    $order->refresh();
    $refund = OrderRefund::query()->firstOrFail();
    expect($order->status->value)->toBe('cancelled')->and($order->cancel_reason)->toBe('Ordered twice')
        ->and($book->refresh()->stock)->toBe(5)
        ->and($refund->status->value)->toBe('done')->and($refund->destination)->toBe('wallet')->and((string) $refund->amount)->toBe('230.00')
        ->and(walletOf($customer))->toBe('500.00')
        ->and(DB::table('user_notifications')->where('title', 'A customer cancelled an order')->exists())->toBeTrue();

    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('You cancelled this order.')->assertSee('refunded to your Akuru wallet.');

    // Twice changes nothing; a stranger reaches nothing.
    fulfilAs($customer)->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Again'])->assertSessionHasErrors('order');
    fulfilAs(User::factory()->create())->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Mine?'])->assertNotFound();
    expect(OrderRefund::query()->count())->toBe(1);
});

it('sends a card refund to the office when the shop cancels, which returns it through BML and records it in Finance', function () {
    fulfilBml();
    [$fitrah, $owner] = fulfilShop('fitrah');
    $office = fulfilOffice();
    $customer = fulfilCustomer(0);
    $order = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Workbook', 100), 1]], 'card')['fitrah'];
    $payment = Payment::query()->firstOrFail();
    $payment->update(['status' => 'confirmed']);
    event(new PaymentConfirmed($payment->fresh()));
    expect($order->refresh()->status->value)->toBe('paid')->and($order->checkout->payment_id)->toBe($payment->id);

    fulfilAs($owner)->post(route('vendor.orders.cancel', $order->id), ['reason' => 'Out of print'])->assertSessionHasNoErrors();
    $refund = OrderRefund::query()->firstOrFail();
    expect($refund->status->value)->toBe('pending')->and($refund->paid_with)->toBe('card')
        ->and(DB::table('user_notifications')->where('user_id', $office->id)->where('title', 'A card refund to send')->exists())->toBeTrue();
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('The shop cancelled this order.')->assertSee('refund on its way to your card.');

    fulfilAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('refunds.0.status', 'pending')->where('refunds.0.amount', '130.00'));
    fulfilAs($owner)->post(route('admin.bookshop.refunds.process', $refund->id), ['destination' => 'manual'])->assertForbidden();
    fulfilAs($office)->post(route('admin.bookshop.refunds.process', $refund->id), ['destination' => 'manual'])->assertSessionHasNoErrors();

    $refund->refresh();
    $financeRefund = PaymentRefund::query()->firstOrFail();
    expect($refund->status->value)->toBe('done')->and($refund->destination)->toBe('card')->and($refund->payment_refund_id)->toBe($financeRefund->id)
        ->and((string) $financeRefund->amount)->toBe('130.00')->and($financeRefund->destination)->toBe('manual')
        ->and($payment->refresh()->status)->toBe('refunded');
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('refunded to your card.');
    fulfilAs($office)->post(route('admin.bookshop.refunds.process', $refund->id), ['destination' => 'manual'])->assertSessionHasErrors('refund');
    expect(fulfilAs($office)->get(route('admin.bookshop.refunds.export'))->assertOk()->streamedContent())->toContain($order->number)->toContain('card');
    fulfilAs($owner)->get(route('admin.bookshop.refunds.export'))->assertForbidden();
});

it('lets a shop confirm the transfer on its own checkout, recorded as a Finance payment, and refunds it to the wallet', function () {
    Storage::fake('local');
    config(['bookshop.bank_transfer.account_number' => '7730000012345']);
    [$fitrah, $owner, $staff] = fulfilShop('fitrah');
    [$other, $otherOwner] = fulfilShop('other-shop');
    $customer = fulfilCustomer(0);

    $order = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Workbook', 100), 1]], 'bank_transfer')['fitrah'];
    fulfilAs($staff)->get(route('vendor.orders.index'))->assertInertia(fn ($page) => $page->has('orders', 0));
    fulfilAs($customer)->post(route('public.shop.checkout.slip', $order->checkout->number), ['slip' => UploadedFile::fake()->image('slip.jpg')]);
    $slip = BankTransferSlip::query()->firstOrFail();

    fulfilAs($staff)->get(route('vendor.orders.index', ['status' => 'pending_payment']))->assertInertia(fn ($page) => $page->has('orders', 1)->where('orders.0.slip.can_confirm', true));
    fulfilAs($staff)->get(route('vendor.slips.show', $slip->id))->assertOk();
    fulfilAs($otherOwner)->get(route('vendor.slips.show', $slip->id))->assertNotFound();
    fulfilAs($otherOwner)->post(route('vendor.slips.decide', $slip->id), ['decision' => 'confirm'])->assertNotFound();

    fulfilAs($staff)->post(route('vendor.slips.decide', $slip->id), ['decision' => 'confirm'])->assertSessionHasNoErrors();
    $payment = Payment::query()->firstOrFail();
    expect($order->refresh()->status->value)->toBe('paid')
        ->and($payment->provider)->toBe('manual')->and($payment->getRawOriginal('payable_type'))->toBe('bookshop_checkout')
        ->and((string) $payment->amount)->toBe('130.00')->and($order->checkout->payment_id)->toBe($payment->id);

    // A basket split across two shops: only the office confirms.
    $split = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Crayons', 40), 1], [fulfilProduct($other, 'Puzzle', 50), 1]], 'bank_transfer');
    fulfilAs($customer)->post(route('public.shop.checkout.slip', $split['fitrah']->checkout->number), ['slip' => UploadedFile::fake()->image('slip2.jpg')]);
    $second = BankTransferSlip::query()->orderByDesc('id')->firstOrFail();
    fulfilAs($owner)->post(route('vendor.slips.decide', $second->id), ['decision' => 'confirm'])->assertForbidden();
    fulfilAs($owner)->get(route('vendor.slips.show', $second->id))->assertOk();

    // The shop cancels the first: back to the wallet at once, through Finance.
    fulfilAs($owner)->post(route('vendor.orders.cancel', $order->id), ['reason' => 'Damaged in storage'])->assertSessionHasNoErrors();
    $refund = OrderRefund::query()->firstOrFail();
    expect($refund->status->value)->toBe('done')->and($refund->destination)->toBe('wallet')->and($refund->payment_refund_id)->not->toBeNull()
        ->and(walletOf($customer))->toBe('130.00')->and($payment->refresh()->status)->toBe('refunded');
});

it('takes a return inside the window, refunds its share after the discount and the delivery fee when it was the shop\'s fault, once', function () {
    [$fitrah, $owner] = fulfilShop('fitrah');
    $book = fulfilProduct($fitrah, 'Workbook', 100, 10);
    $mat = fulfilProduct($fitrah, 'Prayer Mat', 300, 10);
    app(SaveDiscountCodeAction::class)->execute(['code' => 'TENOFF', 'discount_type' => 'percentage', 'discount_value' => 10, 'per_user_limit' => 5]);
    $customer = fulfilCustomer(1000);
    $order = fulfilBuy($customer, [[$book, 2], [$mat, 1]], code: 'TENOFF')['fitrah'];
    expect((string) $order->discount)->toBe('50.00')->and((string) $order->total)->toBe('480.00');
    $bookLine = $order->items->firstWhere('product_id', $book->id);
    $matLine = $order->items->firstWhere('product_id', $mat->id);

    // Not before it arrives.
    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 1, 'reason' => 'damaged'])->assertSessionHasErrors('order');
    fulfilAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'dispatched']);
    fulfilAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'delivered']);

    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 3, 'reason' => 'damaged'])->assertSessionHasErrors('quantity');
    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 1, 'reason' => 'nonsense'])->assertSessionHasErrors('reason');
    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 1, 'reason' => 'damaged', 'note' => 'Torn cover'])->assertSessionHasNoErrors();
    $return = OrderReturn::query()->firstOrFail();
    // 200 of goods less its 20 share of the 50 discount (10% of 500), per unit 90.00.
    expect((string) $return->refund_amount)->toBe('90.00')
        ->and(DB::table('user_notifications')->where('user_id', $owner->id)->where('title', 'A return was asked')->exists())->toBeTrue();
    fulfilAs(User::factory()->create())->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 1, 'reason' => 'damaged'])->assertNotFound();

    fulfilAs($owner)->get(route('vendor.orders.index', ['status' => 'returns']))->assertInertia(fn ($page) => $page->has('orders', 1)->where('counts.returns', 1));
    fulfilAs($owner)->post(route('vendor.returns.decide', $return->id), ['decision' => 'decline'])->assertSessionHasErrors('note');
    $before = walletOf($customer);
    fulfilAs($owner)->post(route('vendor.returns.decide', $return->id), ['decision' => 'accept', 'restock' => 0])->assertSessionHasNoErrors();
    expect($return->refresh()->status->value)->toBe('accepted')->and($return->refunds_delivery)->toBeTrue()
        ->and((float) walletOf($customer) - (float) $before)->toBe(120.0)
        ->and($book->refresh()->stock)->toBe(8);
    fulfilAs($owner)->post(route('vendor.returns.decide', $return->id), ['decision' => 'accept'])->assertSessionHasErrors('return');

    // A second fault on the same order: no second delivery fee. A change of mind, restocked.
    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $matLine->id, 'quantity' => 1, 'reason' => 'changed_mind']);
    $second = OrderReturn::query()->orderByDesc('id')->firstOrFail();
    fulfilAs($owner)->post(route('vendor.returns.decide', $second->id), ['decision' => 'accept', 'restock' => 1]);
    expect($second->refresh()->refunds_delivery)->toBeFalse()->and((string) $second->refund_amount)->toBe('270.00')->and($mat->refresh()->stock)->toBe(10);

    // The last book, declined with a reason the customer reads.
    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 1, 'reason' => 'not_as_described']);
    $third = OrderReturn::query()->orderByDesc('id')->firstOrFail();
    fulfilAs($owner)->post(route('vendor.returns.decide', $third->id), ['decision' => 'decline', 'note' => 'Used, pages written in']);
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()
        ->assertSee('Used, pages written in')->assertSee('Declined')->assertSee('Accepted');
    expect((float) OrderRefund::query()->sum('amount'))->toBe(390.0);

    // After the window: no more returns, and the shop no longer sees the phone or street.
    $this->travel(8)->days();
    fulfilAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $bookLine->id, 'quantity' => 1, 'reason' => 'damaged'])->assertSessionHasErrors('order');
    fulfilAs($owner)->get(route('vendor.orders.index'))->assertInertia(fn ($page) => $page->where('orders.0.address.street', '•••')->where('orders.0.address.phone', '77•••45')->where('orders.0.address.masked', true));
    expect(fulfilAs($owner)->get(route('vendor.orders.export'))->streamedContent())->not->toContain('7712345');
});

it('keeps a longer return window a shop offers, and refuses one below seven days or from staff', function () {
    [$fitrah, $owner, $staff] = fulfilShop('fitrah');
    fulfilAs($owner)->post(route('vendor.settings.save'), ['return_window_days' => 5])->assertSessionHasErrors('return_window_days');
    fulfilAs($staff)->post(route('vendor.settings.save'), ['return_window_days' => 14])->assertForbidden();
    fulfilAs($owner)->post(route('vendor.settings.save'), ['return_window_days' => 14, 'return_conditions' => 'Unused, in the box'])->assertSessionHasNoErrors();

    $customer = fulfilCustomer();
    $order = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Workbook', 100), 1]])['fitrah'];
    fulfilAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'dispatched']);
    fulfilAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'delivered']);
    $this->travel(10)->days();
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('Unused, in the box')->assertSee('data-testid="return-form"', false);
});

it('pauses a shop on holiday: its products stay visible marked back on, the cart and checkout refuse them', function () {
    [$fitrah, $owner] = fulfilShop('fitrah');
    $book = fulfilProduct($fitrah, 'Workbook', 100);
    $customer = fulfilCustomer();
    CartItem::query()->create(['cart_id' => Cart::query()->create(['user_id' => $customer->id])->id, 'product_id' => $book->id, 'quantity' => 1]);

    fulfilAs($owner)->post(route('vendor.settings.save'), ['return_window_days' => 7, 'holiday_from' => now()->toDateString(), 'holiday_until' => now()->subDay()->toDateString()])->assertSessionHasErrors('holiday_until');
    fulfilAs($owner)->post(route('vendor.settings.save'), [
        'return_window_days' => 7, 'holiday_from' => now()->toDateString(), 'holiday_until' => now()->addDays(3)->toDateString(), 'holiday_notice' => 'Eid break',
    ])->assertSessionHasNoErrors();
    $back = now()->addDays(4)->toDateString();

    fulfilAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('shop_settings.on_holiday', true));
    test()->withoutLocalizationMiddleware()->get(route('public.shop.product', $book->slug))->assertOk()
        ->assertSee('Back on '.$back)->assertSee('Eid break')->assertDontSee('data-testid="add-to-cart"', false);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertSee('Back on '.$back);
    fulfilAs($customer)->post(route('public.shop.cart.add'), ['product' => $book->slug])->assertSessionHasErrors('product');
    fulfilAs($customer)->get(route('public.shop.cart'))->assertOk()->assertSee('Fitrah is away. Back on '.$back);
    fulfilAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'A', 'phone' => '7', 'atoll' => 'K', 'island' => 'M', 'street' => 'S',
        'delivery' => ['fitrah' => 't0'], 'payment_method' => 'wallet',
    ])->assertSessionHasErrors('cart');

    // The day after the last day away, it sells again.
    $this->travel(4)->days();
    fulfilAs($customer)->post(route('public.shop.cart.add'), ['product' => $book->slug])->assertSessionHasNoErrors();
});

it('lets the customer and the shop write to each other about an order on one Messages thread', function () {
    [$fitrah, $owner, $staff] = fulfilShop('fitrah');
    $customer = fulfilCustomer();
    $order = fulfilBuy($customer, [[fulfilProduct($fitrah, 'Workbook', 100), 1]])['fitrah'];

    fulfilAs($customer)->post(route('public.shop.orders.message', $order->number), ['body' => ''])->assertSessionHasErrors('body');
    fulfilAs($customer)->post(route('public.shop.orders.message', $order->number), ['body' => 'Can you gift-wrap it?'])->assertSessionHasNoErrors();
    $threadId = $order->refresh()->message_thread_id;
    expect($threadId)->not->toBeNull()
        ->and(DB::table('message_threads')->where('id', $threadId)->value('context_type'))->toBe('order')
        ->and(DB::table('message_participants')->where('message_thread_id', $threadId)->pluck('user_id')->sort()->values()->all())
        ->toEqualCanonicalizing([$customer->id, $owner->id, $staff->id]);

    fulfilAs($staff)->post(route('vendor.orders.message', $order->id), ['body' => 'Yes, no charge.'])->assertSessionHasNoErrors();
    expect($order->refresh()->message_thread_id)->toBe($threadId)
        ->and(DB::table('messages')->where('thread_id', $threadId)->where('content', 'Yes, no charge.')->where('recipient_id', $customer->id)->exists())->toBeTrue();

    fulfilAs($owner)->get('/portal/messages/'.$threadId)->assertOk();
    fulfilAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('/portal/messages/'.$threadId, false);
    fulfilAs(User::factory()->create())->post(route('public.shop.orders.message', $order->number), ['body' => 'Hi'])->assertNotFound();
});
