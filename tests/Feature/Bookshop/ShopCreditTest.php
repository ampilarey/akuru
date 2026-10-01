<?php

use App\Domains\Bookshop\Actions\ShopCreditAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ShopCreditAccount;
use App\Domains\Bookshop\Models\ShopCreditEntry;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P8c: credit accounts for schools. The office opens
 * an account; the school pays on account at checkout while the credit
 * covers it; the office records what the school paid; a refund goes back
 * onto the account. Every figure comes from an append-only ledger.
 */
beforeEach(function () {
    Mail::fake();
    $this->vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'commission_rate' => 10]);
    $this->method = VendorDeliveryMethod::query()->create(['vendor_id' => $this->vendor->id, 'kind' => 'collect_vendor', 'name' => 'Collect', 'fee' => 0, 'handling_days' => 1, 'is_active' => true]);
    $this->book = Product::query()->create(['vendor_id' => $this->vendor->id, 'slug' => 'fitrah-book', 'title' => 'Tracing Book', 'price' => 100, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    $this->school = User::factory()->create(['name' => 'Majeediyya School', 'email' => 'buyer@school.test', 'phone' => '7700111']);
});

function creditWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function creditOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function creditBuy(User $user, Product $book, VendorDeliveryMethod $method, int $quantity, string $payment = 'credit')
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => $quantity]);

    return creditWeb()->actingAs($user)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'School office', 'phone' => '7700111', 'delivery' => ['fitrah' => 'm'.$method->id], 'payment_method' => $payment,
    ]);
}

it('offers pay on account only to a customer with an active account, and pays at once with a charge on the ledger', function () {
    $office = creditOffice();
    // No account: not offered, and refused if forced.
    CartItem::query()->create(['cart_id' => Cart::query()->firstOrCreate(['user_id' => $this->school->id])->id, 'product_id' => $this->book->id, 'quantity' => 1]);
    creditWeb()->actingAs($this->school)->get(route('public.shop.checkout'))->assertOk()->assertDontSee('data-testid="pay-credit"', false);
    CartItem::query()->delete();
    creditBuy($this->school, $this->book, $this->method, 1)->assertSessionHasErrors('payment_method');
    expect(BookshopCheckout::query()->count())->toBe(0);

    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'organisation' => 'Majeediyya School', 'credit_limit' => 500, 'terms_days' => 30])->assertSessionHasNoErrors();
    CartItem::query()->delete();
    CartItem::query()->create(['cart_id' => Cart::query()->firstOrCreate(['user_id' => $this->school->id])->id, 'product_id' => $this->book->id, 'quantity' => 3]);
    creditWeb()->actingAs($this->school)->get(route('public.shop.checkout'))->assertSee('data-testid="pay-credit"', false)->assertSee('MVR 500.00');
    CartItem::query()->delete();

    creditBuy($this->school, $this->book, $this->method, 3)->assertSessionHasNoErrors();
    $checkout = BookshopCheckout::query()->sole();
    expect($checkout->payment_method->value)->toBe('credit')->and($checkout->paid_at)->not->toBeNull()
        ->and(ShopCreditEntry::query()->where('kind', 'charge')->sole()->amount)->toBe('300.00')
        ->and(app(ShopCreditAction::class)->standing($this->school->id))->toMatchArray(['owed' => '300.00', 'available' => '200.00'])
        // Paid like any other order: the shop's earning is recorded.
        ->and(VendorEarning::query()->where('order_id', Order::query()->value('id'))->exists())->toBeTrue();
    creditWeb()->actingAs($this->school)->get(route('public.shop.orders'))->assertSee('data-testid="credit-account"', false)->assertSee('MVR 200.00');
});

it('refuses an order the available credit cannot cover, and a suspended account', function () {
    $office = creditOffice();
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'credit_limit' => 250, 'terms_days' => 30]);
    creditBuy($this->school, $this->book, $this->method, 3)->assertSessionHasErrors('payment_method');
    expect(ShopCreditEntry::query()->count())->toBe(0)->and(BookshopCheckout::query()->count())->toBe(0);

    $account = ShopCreditAccount::query()->sole();
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.update', $account->id), ['credit_limit' => 1000, 'terms_days' => 30, 'status' => 'suspended'])->assertSessionHasNoErrors();
    CartItem::query()->delete();
    creditBuy($this->school, $this->book, $this->method, 1)->assertSessionHasErrors('payment_method');
});

it('records the school\'s payments against what is owed, tells them, and never takes more than is owed', function () {
    $office = creditOffice();
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'credit_limit' => 1000, 'terms_days' => 30]);
    creditBuy($this->school, $this->book, $this->method, 4);
    $account = ShopCreditAccount::query()->sole();

    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.payment', $account->id), ['amount' => 500, 'reference' => 'BML-1'])->assertSessionHasErrors('amount');
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.payment', $account->id), ['amount' => 150, 'reference' => 'BML-TRX-778'])->assertSessionHasNoErrors();
    expect(app(ShopCreditAction::class)->standing($this->school->id))->toMatchArray(['owed' => '250.00', 'available' => '750.00'])
        ->and(UserNotification::query()->where('user_id', $this->school->id)->where('title', __('shop.notice_credit_payment_title'))->exists())->toBeTrue();

    creditWeb()->actingAs($office)->get(route('admin.bookshop.credit', ['account' => $account->id]))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Credit')->where('accounts.0.owed', '250.00')
            ->where('statement.entries.0.charge', '400.00')->where('statement.entries.1.credit', '150.00')->where('statement.entries.1.balance', '250.00'));
    expect(creditWeb()->actingAs($office)->get(route('admin.bookshop.credit.statement', $account->id))->streamedContent())->toContain('BML-TRX-778')
        ->and(creditWeb()->actingAs($this->school)->get(route('public.shop.credit.statement'))->streamedContent())->toContain('BML-TRX-778')
        ->and(creditWeb()->actingAs($office)->get(route('admin.bookshop.credit.export'))->streamedContent())->toContain('buyer@school.test');

    creditWeb()->actingAs($this->school)->get(route('admin.bookshop.credit'))->assertForbidden();
    creditWeb()->actingAs($this->school)->post(route('admin.bookshop.credit.payment', $account->id), ['amount' => 1, 'reference' => 'x'])->assertForbidden();
    creditWeb()->actingAs(User::factory()->create())->get(route('public.shop.credit.statement'))->assertNotFound();
});

it('takes a deposit ahead of any order, which leaves the account in credit and adds to what it may spend', function () {
    $office = creditOffice();
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'credit_limit' => 100, 'terms_days' => 30]);
    $account = ShopCreditAccount::query()->sole();
    // A plain payment cannot make credit; a deposit can.
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.payment', $account->id), ['amount' => 250, 'reference' => 'DEP-1'])->assertSessionHasErrors('amount');
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.payment', $account->id), ['amount' => 250, 'reference' => 'DEP-1', 'deposit' => 1])->assertSessionHasNoErrors();
    expect(ShopCreditEntry::query()->sole()->kind)->toBe('deposit')
        ->and(app(ShopCreditAction::class)->standing($this->school->id))->toMatchArray(['owed' => '0.00', 'in_credit' => '250.00', 'available' => '350.00']);

    // MVR 300 of books: past the limit alone, inside it with the deposit.
    creditBuy($this->school, $this->book, $this->method, 3)->assertSessionHasNoErrors();
    expect(app(ShopCreditAction::class)->standing($this->school->id))->toMatchArray(['owed' => '50.00', 'in_credit' => '0.00', 'available' => '50.00']);
});

it('puts a cancelled order paid on account back onto the account', function () {
    $office = creditOffice();
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'credit_limit' => 1000, 'terms_days' => 30]);
    creditBuy($this->school, $this->book, $this->method, 2);
    $order = Order::query()->sole();

    creditWeb()->actingAs($this->school)->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Ordered twice'])->assertSessionHasNoErrors();
    $refund = OrderRefund::query()->sole();
    expect($refund->destination)->toBe('credit')->and($refund->status->value)->toBe('done')
        ->and(ShopCreditEntry::query()->where('kind', 'refund')->sole()->amount)->toBe('200.00')
        ->and(app(ShopCreditAction::class)->standing($this->school->id))->toMatchArray(['owed' => '0.00', 'available' => '1000.00']);
});

it('keeps the ledger append-only, shows what is overdue past the terms, and opens one account per customer', function () {
    $office = creditOffice();
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'credit_limit' => 1000, 'terms_days' => 30]);
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'buyer@school.test', 'credit_limit' => 10, 'terms_days' => 30])->assertSessionHasErrors('identifier');
    creditWeb()->actingAs($office)->post(route('admin.bookshop.credit.open'), ['identifier' => 'nobody@example.test', 'credit_limit' => 10, 'terms_days' => 30])->assertSessionHasErrors('identifier');

    $this->travel(-40)->days();
    creditBuy($this->school, $this->book, $this->method, 2);
    $this->travelBack();
    $entry = ShopCreditEntry::query()->sole();
    expect(fn () => $entry->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(app(ShopCreditAction::class)->standing($this->school->id)['overdue'])->toBe('200.00');
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['pay_credit', 'pay_credit_hint', 'credit_title', 'credit_account_line', 'error_credit_short', 'notice_credit_payment_body', 'credit_deposit', 'credit_in_credit'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
        expect(__('nav.shop_credit', [], $locale))->not->toBe(__('nav.shop_credit', [], 'en'));
    }
});
