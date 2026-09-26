<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorBankDetail;
use App\Domains\Bookshop\Models\VendorCommissionInvoice;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorPayout;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B6, money to vendors: an earning per paid order with
 * Akuru's commission on goods only; refunds reverse it in proportion; it
 * matures after delivery and the return window; the owner enters bank
 * details and asks for the matured balance; the office pays or declines;
 * Akuru issues its monthly commission tax invoice; statements and reports.
 */
function moneyShop(string $slug = 'fitrah', array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active', 'legal_name' => 'Fitrah Trading', 'tin' => '1001234GST001']);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'boat', 'name' => 'Boat', 'fee' => 0, 'carrier_paid_on_arrival' => true, 'handling_days' => 3, 'is_active' => true]);

    return [$vendor, $owner, $staff];
}

function moneyProduct(Vendor $vendor, string $title, float $price, int $stock = 10): Product
{
    return Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function moneyAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function moneyCustomer(float $wallet = 2000): User
{
    $customer = User::factory()->create();
    app(CreditWalletAction::class)->execute($customer->id, $wallet, 'admin', null, 'Top-up');

    return $customer;
}

/** Buys through the real checkout from the wallet and returns the one order it made for the vendor. */
function moneyBuy(User $customer, Vendor $vendor, array $lines, string $delivery = 'courier_male', ?string $code = null): Order
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    foreach ($lines as [$product, $quantity]) {
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    }
    $method = 'm'.VendorDeliveryMethod::query()->where('vendor_id', $vendor->id)->where('kind', $delivery)->value('id');
    moneyAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => $method], 'payment_method' => 'wallet', 'discount_code' => $code,
    ])->assertSessionHasNoErrors();

    return Order::query()->where('user_id', $customer->id)->where('vendor_id', $vendor->id)->orderByDesc('id')->firstOrFail();
}

function moneyOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function moneyDeliver(User $owner, Order $order): void
{
    foreach (['processing', 'dispatched', 'delivered'] as $step) {
        moneyAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => $step, 'carrier' => 'Bike', 'tracking_note' => 'x'])->assertSessionHasNoErrors();
    }
}

it('records an earning per paid order: commission on goods only, the delivery fee the vendor\'s, an Akuru-funded discount on the full price', function () {
    [$fitrah, $owner] = moneyShop('fitrah', ['commission_rate' => 12.5]);
    $book = moneyProduct($fitrah, 'Tracing Book', 100);
    $customer = moneyCustomer();

    // 2 × 100 goods + 30 courier: commission 12.5% of 200 = 25; net 200 + 30 − 25 = 205.
    $order = moneyBuy($customer, $fitrah, [[$book, 2]]);
    $earning = VendorEarning::query()->where('order_id', $order->id)->firstOrFail();
    expect((string) $earning->gross)->toBe('200.00')->and((string) $earning->delivery_fee)->toBe('30.00')
        ->and((string) $earning->commission_rate)->toBe('12.50')->and((string) $earning->commission)->toBe('25.00')
        ->and((string) $earning->commission_tax)->toBe('0.00')->and((string) $earning->net)->toBe('205.00')
        ->and($earning->status->value)->toBe('pending')->and($earning->available_at)->toBeNull();
    // Idempotent: the same order never earns twice.
    app(\App\Domains\Bookshop\Actions\Money\RecordVendorEarningAction::class)->execute($order->refresh());
    expect(VendorEarning::query()->count())->toBe(1);

    // A boat delivery's fee is the carrier's: nothing of it here.
    $boat = moneyBuy(moneyCustomer(), $fitrah, [[$book, 1]], 'boat');
    expect((string) VendorEarning::query()->where('order_id', $boat->id)->value('delivery_fee'))->toBe('0.00');

    // An Akuru-funded discount: the vendor earns on the full price, Akuru wears the discount.
    app(SaveDiscountCodeAction::class)->execute(['code' => 'AKURU20', 'discount_type' => 'percentage', 'discount_value' => 20, 'per_user_limit' => 5, 'discount_funding_source' => 'akuru']);
    $discounted = moneyBuy(moneyCustomer(), $fitrah, [[$book, 1]], 'courier_male', 'AKURU20');
    $e = VendorEarning::query()->where('order_id', $discounted->id)->firstOrFail();
    expect((string) $discounted->discount)->toBe('20.00')->and($e->discount_funding)->toBe('akuru')
        ->and((string) $e->commission_base)->toBe('100.00')->and((string) $e->commission)->toBe('12.50')->and((string) $e->net)->toBe('117.50');

    // The default rate applies where the office set none.
    [$other] = moneyShop('other-shop');
    $pen = moneyProduct($other, 'Pen', 40);
    $o = moneyBuy(moneyCustomer(), $other, [[$pen, 1]]);
    expect((string) VendorEarning::query()->where('order_id', $o->id)->value('commission_rate'))->toBe('10.00');
});

it('reverses the earning in proportion when money goes back, wholly on cancellation, and matures it after delivery and the return window', function () {
    [$fitrah, $owner] = moneyShop();
    $book = moneyProduct($fitrah, 'Tracing Book', 100);
    $mat = moneyProduct($fitrah, 'Prayer Mat', 200);
    $customer = moneyCustomer(3000);

    // Cancelled before dispatch: everything back, the earning reversed.
    $cancelled = moneyBuy($customer, $fitrah, [[$book, 1]]);
    moneyAs($customer)->post(route('public.shop.orders.cancel', $cancelled->number), ['reason' => 'Changed my mind'])->assertSessionHasNoErrors();
    $e = VendorEarning::query()->where('order_id', $cancelled->id)->firstOrFail();
    expect($e->status->value)->toBe('reversed')->and((string) $e->net)->toBe('0.00')->and((string) $e->commission)->toBe('0.00')->and((string) $e->refunded)->toBe('130.00');

    // Delivered, then one of two items returned (shop's fault: delivery back too): a proportional reversal.
    $order = moneyBuy($customer, $fitrah, [[$book, 1], [$mat, 1]]); // goods 300 + 30 = 330; commission 30; net 300
    moneyDeliver($owner, $order);
    $e = VendorEarning::query()->where('order_id', $order->id)->firstOrFail();
    expect($e->available_at?->toDateString())->toBe(now()->addDays(7)->toDateString())->and($e->status->value)->toBe('pending');
    $item = $order->items()->where('product_id', $mat->id)->firstOrFail();
    moneyAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $item->id, 'quantity' => 1, 'reason' => 'damaged', 'note' => 'Torn'])->assertSessionHasNoErrors();
    $return = \App\Domains\Bookshop\Models\OrderReturn::query()->where('order_id', $order->id)->firstOrFail();
    moneyAs($owner)->post(route('vendor.returns.decide', $return->id), ['decision' => 'accept', 'restock' => 1])->assertSessionHasNoErrors();
    $e->refresh();
    // 200 + 30 back of 330 = 69.7%; 30.3% kept: commission 9.09, delivery 9.09, net 90.91.
    expect((string) $e->refunded)->toBe('230.00')->and((string) $e->commission)->toBe('9.09')->and((string) $e->net)->toBe('90.91')->and($e->status->value)->toBe('pending');

    // The window passes: the command matures it.
    test()->travel(8)->days();
    test()->artisan('bookshop:mature-earnings')->expectsOutputToContain('Earnings matured: 1')->assertSuccessful();
    expect($e->refresh()->status->value)->toBe('available');
    // An undelivered order never matures.
    $waiting = moneyBuy($customer, $fitrah, [[$book, 1]]);
    test()->travel(30)->days();
    test()->artisan('bookshop:mature-earnings')->expectsOutputToContain('Earnings matured: 0');
    expect(VendorEarning::query()->where('order_id', $waiting->id)->firstOrFail()->status->value)->toBe('pending');
});

it('lets the owner enter bank details and request the matured balance, and the office pay it or decline it, with a later refund clawed back', function () {
    [$fitrah, $owner, $staff] = moneyShop();
    $office = moneyOffice();
    $book = moneyProduct($fitrah, 'Tracing Book', 100);
    $customer = moneyCustomer(3000);
    $first = moneyBuy($customer, $fitrah, [[$book, 2]]); // net 210 (200 + 30 − 20)
    $second = moneyBuy($customer, $fitrah, [[$book, 1]]); // net 120
    moneyDeliver($owner, $first);
    moneyDeliver($owner, $second);

    // Nothing matured yet, no bank details: the page says so and the request is refused.
    moneyAs($owner)->get(route('vendor.money.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Bookshop/VendorMoney')
        ->where('money.summary.in_window', '330.00')->where('money.summary.requestable_money', '0.00')->where('money.summary.can_request', false)->where('money.bank', null)->has('money.earnings', 2));
    moneyAs($owner)->post(route('vendor.money.payout-request'))->assertSessionHasErrors('payout');
    moneyAs($staff)->post(route('vendor.money.bank-details'), ['bank_name' => 'BML', 'account_name' => 'Fitrah Trading', 'account_number' => '7730 000 123 456'])->assertForbidden();
    moneyAs($owner)->post(route('vendor.money.bank-details'), ['bank_name' => 'BML', 'account_name' => 'Fitrah Trading', 'account_number' => '123'])->assertSessionHasErrors('account_number');
    moneyAs($owner)->post(route('vendor.money.bank-details'), ['bank_name' => 'BML', 'account_name' => 'Fitrah Trading', 'account_number' => '7730 000 123 456'])->assertRedirect()->assertSessionHasNoErrors();
    expect(VendorBankDetail::query()->where('vendor_id', $fitrah->id)->value('account_number'))->toBe('7730000123456');
    moneyAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.bank.account_number_masked', '•••••••••3456')->where('money.bank.bank_name', 'BML'));

    test()->travel(8)->days();
    moneyAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.available', '330.00')->where('money.summary.requestable_money', '330.00')->where('money.summary.can_request', true));
    moneyAs($staff)->post(route('vendor.money.payout-request'))->assertForbidden();
    moneyAs($owner)->post(route('vendor.money.payout-request'))->assertRedirect()->assertSessionHasNoErrors();
    $payout = VendorPayout::query()->firstOrFail();
    expect((string) $payout->amount)->toBe('330.00')->and($payout->status->value)->toBe('requested')->and($payout->bank_snapshot['account_number'])->toBe('7730000123456')
        ->and(VendorEarning::query()->where('open_payout_id', $payout->id)->count())->toBe(2);
    moneyAs($owner)->post(route('vendor.money.payout-request'))->assertSessionHasErrors('payout');

    // The office sees where to pay, pays with a reference (a reference is required), and the earnings are paid.
    moneyAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($p) => $p->has('money.requests', 1)->where('money.requests.0.bank.account_number', '7730000123456')->where('money.requests.0.vendor', 'Fitrah')->where('money.vendors.0.requested', '330.00')->where('money.vendors.0.requestable_money', '0.00'));
    moneyAs($office)->post(route('admin.bookshop.payouts.decide', $payout->id), ['decision' => 'paid'])->assertSessionHasErrors('reference');
    moneyAs($office)->post(route('admin.bookshop.payouts.decide', $payout->id), ['decision' => 'paid', 'reference' => 'BML-TRF-0001'])->assertRedirect()->assertSessionHasNoErrors();
    expect($payout->refresh()->status->value)->toBe('paid')->and($payout->reference)->toBe('BML-TRF-0001')
        ->and(VendorEarning::query()->where('status', 'paid')->count())->toBe(2)->and((string) VendorEarning::query()->sum('paid_amount'))->toBe('330.00');
    moneyAs($office)->post(route('admin.bookshop.payouts.decide', $payout->id), ['decision' => 'paid', 'reference' => 'again'])->assertSessionHasErrors('payout');
    moneyAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.paid', '330.00')->where('money.summary.requestable_money', '0.00')->where('money.payouts.0.reference', 'BML-TRF-0001')->where('money.payouts.0.bank', null));

    // A refund after the payout (the office's goodwill) is owed back: the next payout claws it back.
    $third = moneyBuy($customer, $fitrah, [[$book, 3]]); // net 300
    moneyDeliver($owner, $third);
    app(\App\Domains\Bookshop\Actions\Orders\RefundOrderAction::class)->request($second->refresh(), 65.0, 'Goodwill', $office->id); // half of 130 back: net 120 → 60, balance −60
    test()->travel(8)->days();
    moneyAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.requestable_money', '240.00'));
    moneyAs($owner)->post(route('vendor.money.payout-request'))->assertSessionHasNoErrors();
    $clawed = VendorPayout::query()->where('status', 'requested')->firstOrFail();
    expect((string) $clawed->amount)->toBe('240.00');
    // Declined: the earnings are free again, with a note the shop reads.
    moneyAs($office)->post(route('admin.bookshop.payouts.decide', $clawed->id), ['decision' => 'rejected'])->assertSessionHasErrors('note');
    moneyAs($office)->post(route('admin.bookshop.payouts.decide', $clawed->id), ['decision' => 'rejected', 'note' => 'Account name does not match'])->assertSessionHasNoErrors();
    expect(VendorEarning::query()->whereNotNull('open_payout_id')->count())->toBe(0);
    moneyAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.requestable_money', '240.00')->where('money.payouts.0.status', 'rejected')->where('money.payouts.0.note', 'Account name does not match'));
    moneyAs(User::factory()->create())->get(route('vendor.money.index'))->assertForbidden();
});

it('issues Akuru\'s monthly commission tax invoice per vendor, with GST only when Akuru is registered, once', function () {
    [$fitrah, $owner] = moneyShop();
    [$other] = moneyShop('other-shop');
    $office = moneyOffice();
    $book = moneyProduct($fitrah, 'Tracing Book', 100);
    $pen = moneyProduct($other, 'Pen', 50);
    $customer = moneyCustomer(3000);

    test()->travelTo(\Carbon\Carbon::parse('2026-08-10 10:00', 'Indian/Maldives'));
    $a = moneyBuy($customer, $fitrah, [[$book, 2]]); // commission 20
    $b = moneyBuy($customer, $fitrah, [[$book, 1]]); // commission 10, cancelled below
    moneyBuy($customer, $other, [[$pen, 2]]); // commission 10 for the other shop
    moneyAs($customer)->post(route('public.shop.orders.cancel', $b->number), ['reason' => 'Oops'])->assertSessionHasNoErrors();
    test()->travelTo(\Carbon\Carbon::parse('2026-09-01 03:20', 'Indian/Maldives'));

    test()->artisan('bookshop:issue-commission-invoices')->expectsOutputToContain('Commission invoices issued for 2026-08: 2')->assertSuccessful();
    $invoice = VendorCommissionInvoice::query()->where('vendor_id', $fitrah->id)->firstOrFail();
    expect($invoice->number)->toBe('ACI-202608-FIT')->and((string) $invoice->sales)->toBe('200.00')->and((string) $invoice->commission)->toBe('20.00')
        ->and((string) $invoice->tax)->toBe('0.00')->and((string) $invoice->total)->toBe('20.00')->and($invoice->orders_count)->toBe(1)
        ->and($invoice->vendor_legal_name)->toBe('Fitrah Trading')->and($invoice->vendor_tin)->toBe('1001234GST001')->and($invoice->issuer_name)->toBe('Akuru Institute');
    // Once: a second run issues nothing; the office's button for that month issues nothing either.
    test()->artisan('bookshop:issue-commission-invoices', ['--month' => '2026-08'])->expectsOutputToContain('issued for 2026-08: 0');
    moneyAs($office)->post(route('admin.bookshop.invoices.issue'), ['month' => '2026-08'])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, '0 commission invoices'));
    expect(VendorCommissionInvoice::query()->count())->toBe(2);

    // The shop and the office both read it, with its lines; a stranger's shop cannot.
    moneyAs($owner)->get(route('vendor.money.invoices.show', $invoice->id))->assertOk()->assertInertia(fn ($p) => $p->component('Bookshop/CommissionInvoice')->where('invoice.number', 'ACI-202608-FIT')->has('invoice.lines', 1)->where('invoice.lines.0.order_number', $a->number));
    moneyAs($owner)->get(route('vendor.money.invoices.show', VendorCommissionInvoice::query()->where('vendor_id', $other->id)->value('id')))->assertNotFound();
    moneyAs($office)->get(route('admin.bookshop.invoices.show', $invoice->id))->assertOk();
    moneyAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.invoices.0.number', 'ACI-202608-FIT')->where('money.statements.0.month', '2026-08')->where('money.statements.0.invoice_number', 'ACI-202608-FIT')->where('money.statements.0.commission', '20.00')->where('money.statements.0.refunded', '130.00'));

    // With Akuru registered, GST rides on the commission from the earning onward and the invoice is a tax invoice.
    config(['bookshop.money.issuer_gst_registered' => true, 'bookshop.money.commission_tax_rate' => 8, 'bookshop.money.issuer_tin' => '1000001GST501']);
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 10:00', 'Indian/Maldives'));
    $c = moneyBuy($customer, $fitrah, [[$book, 1]]); // commission 10, tax 0.80, net 100 + 30 − 10.80 = 119.20
    $e = VendorEarning::query()->where('order_id', $c->id)->firstOrFail();
    expect((string) $e->commission_tax)->toBe('0.80')->and((string) $e->net)->toBe('119.20');
    test()->travelTo(\Carbon\Carbon::parse('2026-10-01 03:20', 'Indian/Maldives'));
    test()->artisan('bookshop:issue-commission-invoices')->assertSuccessful();
    $september = VendorCommissionInvoice::query()->where('vendor_id', $fitrah->id)->where('number', 'ACI-202609-FIT')->firstOrFail();
    expect((string) $september->tax)->toBe('0.80')->and((string) $september->total)->toBe('10.80')->and($september->issuer_tin)->toBe('1000001GST501');

    // The office's tax report and CSVs.
    moneyAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($p) => $p->where('money.tax_report.0.month', '2026-09')->where('money.tax_report.0.commission_tax', '0.80')->where('money.tax_report.1.month', '2026-08')->where('money.tax_report.1.commission', '30.00')->where('money.tax_report.1.invoiced', '30.00'));
    expect(moneyAs($office)->get(route('admin.bookshop.money.export', 'tax-report'))->assertOk()->streamedContent())->toContain('2026-08')->toContain('30.00');
    expect(moneyAs($office)->get(route('admin.bookshop.money.export', 'balances'))->assertOk()->streamedContent())->toContain('Fitrah');
    expect(moneyAs($owner)->get(route('vendor.money.earnings.export'))->assertOk()->streamedContent())->toContain($a->number)->toContain('reversed');
    expect(moneyAs($owner)->get(route('vendor.money.statements.export'))->assertOk()->streamedContent())->toContain('2026-08,')->toContain('ACI-202608-FIT');
    moneyAs($owner)->get(route('admin.bookshop.money.export', 'payouts'))->assertForbidden();
});
