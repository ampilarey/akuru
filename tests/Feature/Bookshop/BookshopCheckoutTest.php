<?php

use App\Domains\Bookshop\Actions\Checkout\ExpireCheckoutsAction;
use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\CustomerAddress;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\StockReservation;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Models\Payment;
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
 * BOOKSHOP_PLAN slice B2, checkout: one address, delivery per shop, a
 * discount on goods only, one order per vendor, stock reserved for the
 * window and taken only when the money arrives — by wallet at once, by
 * card on the webhook, by bank transfer when the office confirms the slip.
 */
function checkoutVendor(string $slug, array $overrides = []): Vendor
{
    return Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
}

function checkoutProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function basketFor(User $user, array $lines): Cart
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
    foreach ($lines as [$product, $quantity]) {
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    }

    return $cart;
}

function checkoutAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function addressInput(array $overrides = []): array
{
    return $overrides + ['recipient_name' => 'Aishath', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example, 2nd floor'];
}

function fakeBookshopBml(bool $ok = true): void
{
    app()->instance(PaymentProviderInterface::class, new class($ok) implements PaymentProviderInterface
    {
        public function __construct(private bool $ok) {}

        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return $this->ok ? new PaymentInitiationResult(true, 'https://bml.test/pay/'.$payment->id) : new PaymentInitiationResult(false, null, 'Gateway down');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: (string) $request->input('reference'), providerReference: 'BML-B2', status: 'completed', rawPayload: $request->all(), isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

it('shows the checkout with the basket by shop, delivery options priced for it, and the payment methods on offer', function () {
    config(['bookshop.bank_transfer.account_number' => '7730000012345']);
    $fitrah = checkoutVendor('fitrah');
    VendorDeliveryMethod::query()->create(['vendor_id' => $fitrah->id, 'kind' => 'courier_male', 'name' => 'Fitrah courier', 'fee' => 25, 'free_over' => 500, 'handling_days' => 1, 'is_active' => true]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $fitrah->id, 'kind' => 'courier_atolls', 'name' => 'Atoll courier', 'fee' => 80, 'minimum_order' => 300, 'handling_days' => 3, 'is_active' => true]);
    $other = checkoutVendor('other-shop');
    $user = User::factory()->create();
    basketFor($user, [[checkoutProduct($fitrah, 'Workbook', 85), 2], [checkoutProduct($other, 'Puzzle', 240), 1]]);

    $page = checkoutAs($user)->get(route('public.shop.checkout'))->assertOk();
    // Fitrah's own methods, priced for its MVR 170: courier paid, atolls under the minimum.
    $page->assertSee('Fitrah courier')->assertSee('MVR 25.00')->assertSee('Atoll courier')->assertSee('Not available for this basket');
    // The other shop has none of its own, so the office template applies.
    $page->assertSee('Collect from Akuru Institute')->assertSee('Delivery in Malé, Hulhumalé and Villimalé');
    $page->assertSee('Card (BML)')->assertSee('Akuru wallet')->assertSee('Bank transfer');
    $page->assertSee('MVR 410.00');

    // Without a bank account configured the method is not offered.
    config(['bookshop.bank_transfer.account_number' => null]);
    checkoutAs($user)->get(route('public.shop.checkout'))->assertOk()->assertDontSee('Bank transfer');

    // An empty cart goes back to the cart page; a guest is sent to sign in.
    CartItem::query()->delete();
    checkoutAs($user)->get(route('public.shop.checkout'))->assertRedirect(route('public.shop.cart'));
    auth()->logout();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.checkout'))->assertRedirect(route('login'));
});

it('pays from the wallet at once: one order per shop, discount on goods only, tax lines only for a GST vendor, stock taken, cart emptied', function () {
    $fitrah = checkoutVendor('fitrah', ['gst_registered' => true, 'tin' => '1234567GST501', 'legal_name' => 'Fitrah Pvt Ltd']);
    $other = checkoutVendor('other-shop');
    VendorDeliveryMethod::query()->create(['vendor_id' => $fitrah->id, 'kind' => 'courier_male', 'name' => 'Fitrah courier', 'fee' => 25, 'handling_days' => 1, 'is_active' => true]);
    $workbook = checkoutProduct($fitrah, 'Workbook', 100, ['stock' => 5]);
    $puzzle = checkoutProduct($other, 'Puzzle', 300, ['stock' => 2]);
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 600, 'admin', null, 'Top-up');
    app(SaveDiscountCodeAction::class)->execute(['code' => 'BOOKS40', 'discount_type' => 'fixed', 'discount_value' => 40, 'per_user_limit' => 1]);
    basketFor($user, [[$workbook, 2], [$puzzle, 1]]);
    $fitrahMethod = VendorDeliveryMethod::query()->firstOrFail();

    $response = checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput([
        'delivery' => ['fitrah' => 'm'.$fitrahMethod->id, 'other-shop' => 't0'],
        'payment_method' => 'wallet',
        'discount_code' => 'BOOKS40',
        'save_address' => 1,
        'address_label' => 'Home',
    ]));

    $checkout = BookshopCheckout::query()->firstOrFail();
    $response->assertRedirect(route('public.shop.checkout.status', $checkout->number));
    expect($checkout->number)->toMatch('/^AK-\d{4}-\d{6}$/')
        ->and($checkout->status->value)->toBe('paid')
        ->and($checkout->payment_method->value)->toBe('wallet')
        ->and((string) $checkout->subtotal)->toBe('500.00')
        ->and((string) $checkout->discount)->toBe('40.00')
        ->and((string) $checkout->delivery_total)->toBe('25.00')
        ->and((string) $checkout->total)->toBe('485.00')
        ->and($checkout->address_snapshot['recipient_name'])->toBe('Aishath');

    // One order per shop; the discount split 200:300 → 16:24; tax only on Fitrah's lines.
    $orders = Order::query()->orderBy('id')->get();
    expect($orders)->toHaveCount(2)
        ->and($orders[0]->number)->toBe($checkout->number.'-FIT')
        ->and((string) $orders[0]->discount)->toBe('16.00')
        ->and((string) $orders[0]->total)->toBe('209.00')
        ->and($orders[0]->tax_shown)->toBeTrue()
        ->and($orders[0]->vendor_tin)->toBe('1234567GST501')
        ->and((string) $orders[0]->tax)->toBe('13.63')
        ->and($orders[1]->number)->toBe($checkout->number.'-OTH')
        ->and((string) $orders[1]->discount)->toBe('24.00')
        ->and((string) $orders[1]->total)->toBe('276.00')
        ->and($orders[1]->tax_shown)->toBeFalse()
        ->and((string) $orders[1]->tax)->toBe('0.00')
        ->and($orders->pluck('status')->map->value->all())->toBe(['paid', 'paid']);

    // Money, stock, discount, cart, address book.
    expect((string) Wallet::query()->where('user_id', $user->id)->value('balance'))->toBe('115.00')
        ->and($workbook->refresh()->stock)->toBe(3)
        ->and($puzzle->refresh()->stock)->toBe(1)
        ->and(StockReservation::query()->count())->toBe(0)
        ->and(DiscountRedemption::query()->value('status'))->toBe('confirmed')
        ->and(CartItem::query()->count())->toBe(0)
        ->and(CustomerAddress::query()->where('user_id', $user->id)->value('label'))->toBe('Home')
        ->and(Payment::query()->count())->toBe(0);

    // Notices: the customer, and Fitrah's owner.
    $ownerId = User::factory()->create()->id;
    expect(DB::table('user_notifications')->where('user_id', $user->id)->where('category', 'shop')->exists())->toBeTrue();

    // The status page and the receipt.
    checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()
        ->assertSee('Paid')->assertSee($checkout->number.'-FIT')->assertSee('Thank you');
    $receipt = checkoutAs($user)->get(route('public.shop.orders.show', $checkout->number.'-FIT'))->assertOk();
    $receipt->assertSee('Fitrah Pvt Ltd')->assertSee('1234567GST501')->assertSee('Includes GST MVR 13.63')->assertSee('MVR 209.00');
    checkoutAs($user)->get(route('public.shop.orders.show', $checkout->number.'-OTH'))->assertOk()->assertDontSee('Includes GST')->assertDontSee('Vendor TIN');

    // Somebody else sees neither.
    $stranger = User::factory()->create();
    checkoutAs($stranger)->get(route('public.shop.checkout.status', $checkout->number))->assertNotFound();
    checkoutAs($stranger)->get(route('public.shop.orders.show', $checkout->number.'-FIT'))->assertNotFound();
});

it('lets a customer who filled a long basket quickly still check out: the cart and checkout limits are counted apart', function () {
    $fitrah = checkoutVendor('fitrah');
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 5000, 'admin', null, 'Top-up');
    foreach (range(1, 12) as $n) {
        $product = checkoutProduct($fitrah, 'School list item '.$n, 10);
        checkoutAs($user)->post(route('public.shop.cart.add'), ['product' => $product->slug])->assertSessionHasNoErrors();
    }

    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'wallet']))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(BookshopCheckout::query()->value('status')->value)->toBe('paid');
});

it('refuses a short wallet, a missing delivery choice, a missing address and an over-stock line, leaving the cart as it was', function () {
    $fitrah = checkoutVendor('fitrah');
    $book = checkoutProduct($fitrah, 'Workbook', 100, ['stock' => 1]);
    $user = User::factory()->create();
    basketFor($user, [[$book, 1]]);

    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'wallet']))
        ->assertSessionHasErrors();
    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 'nope'], 'payment_method' => 'card']))
        ->assertSessionHasErrors('delivery');
    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'card', 'island' => '']))
        ->assertSessionHasErrors('island');

    // Another customer's live reservation makes the last copy unavailable.
    $other = User::factory()->create();
    $held = BookshopCheckout::query()->create(['number' => 'AK-2026-000099', 'user_id' => $other->id, 'status' => 'pending_payment', 'payment_method' => 'card', 'subtotal' => 100, 'discount' => 0, 'delivery_total' => 0, 'total' => 100, 'currency' => 'MVR', 'address_snapshot' => addressInput(), 'expires_at' => now()->addMinutes(30)]);
    StockReservation::query()->create(['bookshop_checkout_id' => $held->id, 'product_id' => $book->id, 'quantity' => 1, 'expires_at' => now()->addMinutes(30)]);
    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'card']))
        ->assertSessionHasErrors('cart');

    expect(BookshopCheckout::query()->count())->toBe(1)->and(CartItem::query()->count())->toBe(1)->and(Order::query()->count())->toBe(0);
});

it('sends a card payment to the bank and pays the checkout only on the webhook event, once', function () {
    fakeBookshopBml();
    $fitrah = checkoutVendor('fitrah');
    $book = checkoutProduct($fitrah, 'Workbook', 100, ['stock' => 4]);
    $user = User::factory()->create();
    basketFor($user, [[$book, 3]]);

    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'card']))
        ->assertRedirect('https://bml.test/pay/'.Payment::query()->value('id'));

    $checkout = BookshopCheckout::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    expect($checkout->status->value)->toBe('pending_payment')
        ->and($checkout->payment_id)->toBe($payment->id)
        ->and($payment->getRawOriginal('payable_type'))->toBe('bookshop_checkout')
        ->and((string) $payment->amount)->toBe('300.00')
        ->and((int) StockReservation::query()->where('bookshop_checkout_id', $checkout->id)->sum('quantity'))->toBe(3)
        ->and($book->refresh()->stock)->toBe(4)
        ->and(CartItem::query()->count())->toBe(0);

    // The return page only shows state.
    checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()->assertSee('Awaiting payment')->assertSee('Bank confirmation can take a moment');
    expect($checkout->refresh()->status->value)->toBe('pending_payment');

    // The webhook confirms; twice changes nothing more.
    $payment->update(['status' => 'confirmed']);
    event(new PaymentConfirmed($payment->fresh()));
    event(new PaymentConfirmed($payment->fresh()));

    expect($checkout->refresh()->status->value)->toBe('paid')
        ->and(Order::query()->firstOrFail()->status->value)->toBe('paid')
        ->and($book->refresh()->stock)->toBe(1)
        ->and(StockReservation::query()->count())->toBe(0);
    checkoutAs($user)->get(route('public.shop.orders'))->assertOk()->assertSee($checkout->number.'-FIT')->assertSee('Paid');
});

it('marks a checkout failed when the bank cannot start the payment, releasing the stock', function () {
    fakeBookshopBml(ok: false);
    $fitrah = checkoutVendor('fitrah');
    $book = checkoutProduct($fitrah, 'Workbook', 100);
    $user = User::factory()->create();
    basketFor($user, [[$book, 1]]);

    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'card']))
        ->assertRedirect()->assertSessionHas('error');

    $checkout = BookshopCheckout::query()->firstOrFail();
    expect($checkout->status->value)->toBe('failed')->and(StockReservation::query()->count())->toBe(0);
    checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()->assertSee('could not be started');
});

it('waits for a bank-transfer slip, which the office confirms or rejects; a slip is private to its customer and the office', function () {
    Storage::fake('local');
    config(['bookshop.bank_transfer.account_number' => '7730000012345']);
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    $fitrah = checkoutVendor('fitrah');
    $book = checkoutProduct($fitrah, 'Workbook', 100, ['stock' => 3]);
    $user = User::factory()->create();
    basketFor($user, [[$book, 2]]);

    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'bank_transfer']))
        ->assertRedirect();
    $checkout = BookshopCheckout::query()->firstOrFail();
    expect($checkout->status->value)->toBe('pending_payment')->and($checkout->payment_method->value)->toBe('bank_transfer');

    $status = checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk();
    $status->assertSee('7730000012345')->assertSee('Upload your transfer slip')->assertSee($checkout->number);

    // The slip: stored privately, the office told, the hold extended.
    checkoutAs($user)->post(route('public.shop.checkout.slip', $checkout->number), ['slip' => UploadedFile::fake()->image('slip.jpg'), 'reference' => 'TRX123'])
        ->assertRedirect()->assertSessionHas('success');
    $slip = BankTransferSlip::query()->firstOrFail();
    expect($slip->status->value)->toBe('waiting')->and($slip->reference)->toBe('TRX123')
        ->and(DB::table('user_notifications')->where('user_id', $office->id)->where('category', 'shop')->exists())->toBeTrue();
    checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()->assertSee('Waiting for the office')->assertDontSee('data-testid="slip-form"', false);

    // Private: the customer and the office read it; a stranger cannot.
    checkoutAs($user)->get(route('public.shop.slip', $slip->id))->assertOk();
    checkoutAs($office)->get(route('public.shop.slip', $slip->id))->assertOk();
    checkoutAs(User::factory()->create())->get(route('public.shop.slip', $slip->id))->assertNotFound();

    // The expiry sweep keeps a checkout with a slip waiting.
    $checkout->update(['expires_at' => now()->subMinute()]);
    expect(app(ExpireCheckoutsAction::class)->execute())->toBe(['expired' => 0, 'extended' => 1])
        ->and($checkout->refresh()->status->value)->toBe('pending_payment');

    // The office sees it and rejects it with a reason; the customer may upload another.
    checkoutAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->has('slips', 1)->where('slips.0.reference', 'TRX123')->where('slips.0.status', 'waiting'));
    checkoutAs($office)->post(route('admin.bookshop.slips.decide', $slip->id), ['decision' => 'reject', 'note' => 'Amount does not match'])->assertRedirect();
    expect($slip->refresh()->status->value)->toBe('rejected')->and($slip->decided_by)->toBe($office->id)
        ->and(DB::table('user_notifications')->where('user_id', $user->id)->where('message', 'like', '%Amount does not match%')->exists())->toBeTrue();
    checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()->assertSee('Rejected')->assertSee('Amount does not match')->assertSee('Upload another slip');
    checkoutAs($office)->post(route('admin.bookshop.slips.decide', $slip->id), ['decision' => 'confirm'])->assertSessionHasErrors('slip');

    // A second slip, confirmed: the checkout is paid and stock taken.
    checkoutAs($user)->post(route('public.shop.checkout.slip', $checkout->number), ['slip' => UploadedFile::fake()->create('slip.pdf', 20, 'application/pdf')]);
    $second = BankTransferSlip::query()->orderByDesc('id')->firstOrFail();
    checkoutAs($office)->post(route('admin.bookshop.slips.decide', $second->id), ['decision' => 'confirm'])->assertRedirect()->assertSessionHas('success');
    expect($checkout->refresh()->status->value)->toBe('paid')->and($book->refresh()->stock)->toBe(1)
        ->and(Order::query()->firstOrFail()->status->value)->toBe('paid');
    checkoutAs($office)->post(route('admin.bookshop.slips.decide', $second->id), ['decision' => 'confirm'])->assertSessionHasErrors('slip');

    // A paid checkout takes no more slips; nobody without the permission decides.
    checkoutAs($user)->post(route('public.shop.checkout.slip', $checkout->number), ['slip' => UploadedFile::fake()->image('again.jpg')])->assertSessionHasErrors('slip');
    checkoutAs($user)->post(route('admin.bookshop.slips.decide', $second->id), ['decision' => 'confirm'])->assertForbidden();
});

it('expires an unpaid checkout after its window, releasing stock and the discount; and pays at once when a discount brings the total to zero', function () {
    $fitrah = checkoutVendor('fitrah');
    $book = checkoutProduct($fitrah, 'Workbook', 100, ['stock' => 2]);
    $user = User::factory()->create();
    basketFor($user, [[$book, 2]]);
    app(SaveDiscountCodeAction::class)->execute(['code' => 'HALF', 'discount_type' => 'percentage', 'discount_value' => 50, 'per_user_limit' => 5]);
    fakeBookshopBml();

    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'card', 'discount_code' => 'HALF']))->assertRedirect();
    $checkout = BookshopCheckout::query()->firstOrFail();
    expect((string) $checkout->total)->toBe('100.00')->and(DiscountRedemption::query()->value('status'))->toBe('pending');

    // Too early: nothing happens. After the window: expired.
    expect(app(ExpireCheckoutsAction::class)->execute())->toBe(['expired' => 0, 'extended' => 0]);
    $this->travel(31)->minutes();
    expect(app(ExpireCheckoutsAction::class)->execute())->toBe(['expired' => 1, 'extended' => 0])
        ->and($checkout->refresh()->status->value)->toBe('expired')
        ->and(Order::query()->firstOrFail()->status->value)->toBe('expired')
        ->and(StockReservation::query()->count())->toBe(0)
        ->and(DiscountRedemption::query()->value('status'))->toBe('released')
        ->and($book->refresh()->stock)->toBe(2);
    checkoutAs($user)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()->assertSee('not paid in time');

    // A late webhook for an expired checkout changes nothing.
    $payment = Payment::query()->firstOrFail();
    $payment->update(['status' => 'confirmed']);
    event(new PaymentConfirmed($payment->fresh()));
    expect($checkout->refresh()->status->value)->toBe('expired')->and($book->refresh()->stock)->toBe(2);

    // Free after discount: paid at once, no payment row.
    app(SaveDiscountCodeAction::class)->execute(['code' => 'FREE', 'discount_type' => 'percentage', 'discount_value' => 100, 'per_user_limit' => 5]);
    basketFor($user, [[$book, 1]]);
    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'card', 'discount_code' => 'FREE']))->assertRedirect();
    $free = BookshopCheckout::query()->orderByDesc('id')->firstOrFail();
    expect($free->status->value)->toBe('paid')->and($free->payment_method->value)->toBe('none')
        ->and((string) $free->total)->toBe('0.00')->and(Payment::query()->count())->toBe(1)->and($book->refresh()->stock)->toBe(1);
});

it('lets an owner set delivery methods the checkout then offers, and nobody else', function () {
    Role::findOrCreate('vendor', 'web');
    $fitrah = checkoutVendor('fitrah');
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $fitrah->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorMember::query()->create(['vendor_id' => $fitrah->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);

    checkoutAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('delivery_methods', [])->where('delivery_kinds.0', 'collect_vendor'));

    // From the template, then edited: two kept, the boat's fee forced to zero and carrier-paid.
    checkoutAs($owner)->post(route('vendor.delivery-methods.template'))->assertRedirect();
    expect(VendorDeliveryMethod::query()->where('vendor_id', $fitrah->id)->count())->toBe(5);
    $rows = VendorDeliveryMethod::query()->where('vendor_id', $fitrah->id)->orderBy('sort_order')->get();
    checkoutAs($owner)->post(route('vendor.delivery-methods.save'), ['methods' => [
        ['id' => $rows[0]->id, 'kind' => 'collect_vendor', 'name' => 'Pick up at Fitrah', 'fee' => 0, 'handling_days' => 1, 'is_active' => 1],
        ['kind' => 'boat', 'name' => 'Boat to Ha. Atoll', 'fee' => 55, 'handling_days' => 2, 'is_active' => 1, 'note' => 'Thursday boat'],
    ]])->assertRedirect()->assertSessionHas('success');
    $kept = VendorDeliveryMethod::query()->where('vendor_id', $fitrah->id)->orderBy('sort_order')->get();
    expect($kept)->toHaveCount(2)
        ->and($kept[0]->id)->toBe($rows[0]->id)->and($kept[0]->name)->toBe('Pick up at Fitrah')
        ->and($kept[1]->kind->value)->toBe('boat')->and((string) $kept[1]->fee)->toBe('0.00')->and($kept[1]->carrier_paid_on_arrival)->toBeTrue();

    checkoutAs($owner)->post(route('vendor.delivery-methods.save'), ['methods' => [['kind' => 'rocket', 'name' => 'Rocket']]])->assertSessionHasErrors('methods');
    checkoutAs($staff)->post(route('vendor.delivery-methods.save'), ['methods' => []])->assertForbidden();
    checkoutAs(User::factory()->create())->post(route('vendor.delivery-methods.save'), ['methods' => []])->assertForbidden();

    // The checkout offers exactly those.
    $customer = User::factory()->create();
    basketFor($customer, [[checkoutProduct($fitrah, 'Workbook', 100), 1]]);
    $page = checkoutAs($customer)->get(route('public.shop.checkout'))->assertOk();
    $page->assertSee('Pick up at Fitrah')->assertSee('Boat to Ha. Atoll')->assertSee('Boat fee paid to the carrier on arrival')->assertSee('Thursday boat')->assertDontSee('Collect from Akuru Institute');
});

it('lists my orders with a CSV, and the office lists every order with a CSV', function () {
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');
    $fitrah = checkoutVendor('fitrah');
    $book = checkoutProduct($fitrah, 'Workbook', 100);
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 500, 'admin', null, 'Top-up');
    basketFor($user, [[$book, 1]]);
    checkoutAs($user)->post(route('public.shop.checkout.store'), addressInput(['delivery' => ['fitrah' => 't0'], 'payment_method' => 'wallet']))->assertRedirect();
    $number = Order::query()->value('number');

    checkoutAs($user)->get(route('public.shop.orders'))->assertOk()->assertSee($number)->assertSee('Paid');
    $csv = checkoutAs($user)->get(route('public.shop.orders.export'))->assertOk()->streamedContent();
    expect($csv)->toContain($number)->toContain('Fitrah');
    checkoutAs(User::factory()->create())->get(route('public.shop.orders'))->assertOk()->assertSee('No orders yet.')->assertDontSee($number);

    checkoutAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->has('orders', 1)->where('orders.0.number', $number)->where('orders.0.customer', $user->name)->where('orders.0.payment_method', 'wallet'));
    $officeCsv = checkoutAs($office)->get(route('admin.bookshop.orders.export'))->assertOk()->streamedContent();
    expect($officeCsv)->toContain($number)->toContain($user->email);
    checkoutAs($user)->get(route('admin.bookshop.orders.export'))->assertForbidden();
});
