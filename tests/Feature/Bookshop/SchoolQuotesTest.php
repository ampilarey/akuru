<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\QuoteRequest;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN B9 "bulk quotes for schools (B2B)", slice B9d: a customer
 * asks one shop to price its lines in the cart (ten items or more), the
 * shop prices each line and how long it holds or declines with a note, the
 * customer accepts it into the cart, and the checkout charges the quoted
 * price while the quote holds — the list price once it lapses. Each side
 * sees only its own quotes; every list has a CSV.
 */
function quoteShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active', 'commission_rate' => 10, 'cod_enabled' => true]);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);

    return [$vendor, $owner];
}

function quoteProduct(Vendor $vendor, string $title, float $price, int $stock = 100): Product
{
    return Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function quoteAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function quoteCart(User $customer, Product $product, int $quantity): CartItem
{
    return CartItem::query()->create(['cart_id' => Cart::query()->firstOrCreate(['user_id' => $customer->id])->id, 'product_id' => $product->id, 'quantity' => $quantity]);
}

function askQuote(User $customer, string $vendor = 'fitrah')
{
    return quoteAs($customer)->post(route('public.shop.quotes.store'), ['vendor' => $vendor, 'organisation' => 'Majeediyya School', 'contact_phone' => '7700000', 'note' => 'Grade 3']);
}

it('asks a shop for a price from the cart, and the shop prices it for a number of days', function () {
    [$fitrah, $owner] = quoteShop();
    [$noor, $noorOwner] = quoteShop('noor');
    $reader = quoteProduct($fitrah, 'Reader', 50);
    $workbook = quoteProduct($fitrah, 'Workbook', 20);
    $other = quoteProduct($noor, 'Noor Book', 10);
    $school = User::factory()->create();

    quoteCart($school, $reader, 5);
    quoteAs($school)->get(route('public.shop.cart'))->assertSee(__('shop.quote_min_note', ['min' => 10]));
    askQuote($school)->assertSessionHasErrors('organisation');

    quoteCart($school, $workbook, 30);
    quoteCart($school, $other, 40);
    quoteAs($school)->get(route('public.shop.cart'))->assertSee('data-testid="quote-form-fitrah"', false);
    askQuote($school)->assertRedirect();
    $quote = QuoteRequest::query()->sole();
    expect($quote->number)->toStartWith('QT-')->and($quote->status)->toBe('requested')->and((float) $quote->list_total)->toBe(850.0)
        ->and($quote->items()->count())->toBe(2)->and($quote->vendor_id)->toBe($fitrah->id);
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_quote_requested_title'))->exists())->toBeTrue();

    // Only this shop's requests, and only this customer's.
    quoteAs($owner)->get(route('vendor.quotes.index'))->assertInertia(fn ($page) => $page->component('Bookshop/VendorQuotes')->has('quotes', 1)->where('quotes.0.organisation', 'Majeediyya School')->where('counts.requested', 1));
    quoteAs($noorOwner)->get(route('vendor.quotes.index'))->assertInertia(fn ($page) => $page->has('quotes', 0));
    quoteAs($noorOwner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [1 => 1], 'valid_days' => 7])->assertNotFound();
    quoteAs(User::factory()->create())->get(route('public.shop.quotes.show', $quote->number))->assertNotFound();

    [$readerLine, $workbookLine] = $quote->items()->get()->all();
    quoteAs($owner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [$readerLine->id => 0, $workbookLine->id => 15], 'valid_days' => 7])->assertSessionHasErrors();
    quoteAs($owner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [$readerLine->id => 40, $workbookLine->id => 15], 'valid_days' => 90])->assertSessionHasErrors('valid_days');
    quoteAs($owner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [$readerLine->id => 40, $workbookLine->id => 15], 'valid_days' => 7, 'note' => 'School price'])->assertSessionHasNoErrors();
    $quote->refresh();
    expect($quote->status)->toBe('quoted')->and((float) $quote->quoted_total)->toBe(650.0)->and($quote->valid_until->toDateString())->toBe(now()->addDays(7)->toDateString());
    expect(UserNotification::query()->where('user_id', $school->id)->where('title', __('shop.notice_quote_ready_title'))->exists())->toBeTrue();

    quoteAs($school)->get(route('public.shop.quotes'))->assertOk()->assertSee($quote->number)->assertSee('650.00');
    quoteAs($school)->get(route('public.shop.quotes.show', $quote->number))->assertOk()->assertSee(__('shop.quote_saving', ['amount' => 'MVR 200.00']))->assertSee('data-testid="quote-accept"', false);
    expect(quoteAs($school)->get(route('public.shop.quotes.export'))->streamedContent())->toContain($quote->number)->toContain('650.00');
    $csv = quoteAs($owner)->get(route('vendor.quotes.export'))->streamedContent();
    expect($csv)->toContain('Majeediyya School')->toContain('Workbook')->toContain('15.00');
    expect(quoteAs($noorOwner)->get(route('vendor.quotes.export'))->streamedContent())->not->toContain('Majeediyya');
});

it('accepts the quote into the cart and the checkout charges the quoted price, then marks it ordered', function () {
    [$fitrah, $owner] = quoteShop();
    $reader = quoteProduct($fitrah, 'Reader', 50);
    $school = User::factory()->create();
    quoteCart($school, $reader, 12);
    askQuote($school);
    $quote = QuoteRequest::query()->sole();
    $line = $quote->items()->sole();
    quoteAs($owner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [$line->id => 42.5], 'valid_days' => 14]);

    quoteAs($school)->post(route('public.shop.quotes.accept', $quote->number))->assertRedirect(route('public.shop.cart'));
    expect($quote->refresh()->status)->toBe('accepted');
    $item = CartItem::query()->sole();
    expect($item->quote_item_id)->toBe($line->id)->and($item->quantity)->toBe(12);
    quoteAs($school)->get(route('public.shop.cart'))->assertSee('data-testid="cart-quoted"', false)->assertSee('MVR 510.00');

    // The quoted quantity is locked; more of it is a separate, list-price line.
    quoteAs($school)->post(route('public.shop.cart.update', $item->id), ['quantity' => 20])->assertSessionHasErrors('quantity');
    expect($item->refresh()->quantity)->toBe(12);

    quoteAs($school)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Majeediyya', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => ['fitrah' => 'm'.VendorDeliveryMethod::query()->where('vendor_id', $fitrah->id)->value('id')], 'payment_method' => 'cash_on_delivery',
    ])->assertSessionHasNoErrors();
    $checkout = BookshopCheckout::query()->sole();
    expect((float) $checkout->subtotal)->toBe(510.0)->and((float) $checkout->total)->toBe(540.0);
    expect(OrderItem::query()->sole()->quote_item_id)->toBe($line->id);
    expect($quote->refresh()->status)->toBe('ordered')->and($quote->ordered_at)->not->toBeNull();
    quoteAs($school)->get(route('public.shop.quotes.show', $quote->number))->assertDontSee('data-testid="quote-accept"', false);
});

it('charges the list price once the quote lapses, and lets the line be changed again', function () {
    [$fitrah, $owner] = quoteShop();
    $reader = quoteProduct($fitrah, 'Reader', 50);
    $school = User::factory()->create();
    quoteCart($school, $reader, 10);
    askQuote($school);
    $quote = QuoteRequest::query()->sole();
    quoteAs($owner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [$quote->items()->sole()->id => 30], 'valid_days' => 3]);
    quoteAs($school)->post(route('public.shop.quotes.accept', $quote->number));
    quoteAs($school)->get(route('public.shop.cart'))->assertSee('MVR 300.00');

    $this->travel(5)->days();
    quoteAs($school)->get(route('public.shop.cart'))->assertSee('MVR 500.00')->assertSee('data-testid="cart-quote-lapsed"', false);
    quoteAs($school)->post(route('public.shop.quotes.accept', $quote->number))->assertSessionHasErrors('quote');
    $item = CartItem::query()->sole();
    quoteAs($school)->post(route('public.shop.cart.update', $item->id), ['quantity' => 11])->assertSessionHasNoErrors();
    expect($item->refresh()->quantity)->toBe(11)->and($item->quote_item_id)->toBeNull();
});

it('declines with a note, withdraws with the lines leaving the cart, and the office sees all quotes', function () {
    [$fitrah, $owner] = quoteShop();
    $reader = quoteProduct($fitrah, 'Reader', 50);
    $school = User::factory()->create();
    quoteCart($school, $reader, 10);
    askQuote($school);
    $first = QuoteRequest::query()->sole();

    quoteAs($owner)->post(route('vendor.quotes.decline', $first->id), ['note' => ''])->assertSessionHasErrors('note');
    quoteAs($owner)->post(route('vendor.quotes.decline', $first->id), ['note' => 'Out of print'])->assertSessionHasNoErrors();
    expect($first->refresh()->status)->toBe('declined');
    expect(UserNotification::query()->where('user_id', $school->id)->where('title', __('shop.notice_quote_declined_title'))->exists())->toBeTrue();
    quoteAs($school)->get(route('public.shop.quotes.show', $first->number))->assertSee('Out of print');
    quoteAs($owner)->post(route('vendor.quotes.quote', $first->id), ['prices' => [$first->items()->sole()->id => 30], 'valid_days' => 3])->assertSessionHasErrors('quote');

    askQuote($school);
    $second = QuoteRequest::query()->latest('id')->first();
    quoteAs($owner)->post(route('vendor.quotes.quote', $second->id), ['prices' => [$second->items()->sole()->id => 45], 'valid_days' => 3]);
    quoteAs($school)->post(route('public.shop.quotes.accept', $second->number));
    expect(CartItem::query()->whereNotNull('quote_item_id')->count())->toBe(1);
    quoteAs($school)->post(route('public.shop.quotes.withdraw', $second->number))->assertSessionHasNoErrors();
    expect($second->refresh()->status)->toBe('withdrawn')->and(CartItem::query()->count())->toBe(0);

    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');
    quoteAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('quotes.counts.declined', 1)->where('quotes.counts.withdrawn', 1));
    expect(quoteAs($office)->get(route('admin.bookshop.quotes.export'))->streamedContent())->toContain($first->number)->toContain($second->number);
    quoteAs($school)->get(route('admin.bookshop.quotes.export'))->assertForbidden();
});
