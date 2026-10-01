<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ShopCustomerNote;
use App\Domains\Bookshop\Models\ShopCustomerProfile;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P7c: the office's customer view — everyone with a
 * paid order, biggest spenders first; tags to sort them; notes with a
 * follow-up date that shows as due and is ticked off when done.
 */
function custWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function custOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function custOrder(User $user, Vendor $vendor, float $total, bool $paid = true): Order
{
    $method = VendorDeliveryMethod::query()->firstOrCreate(['vendor_id' => $vendor->id, 'kind' => 'collect_vendor'], ['name' => 'Collect', 'fee' => 0, 'handling_days' => 1, 'is_active' => true]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'book-'.Str::random(8), 'title' => 'Book', 'price' => $total, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    app(CreditWalletAction::class)->execute($user->id, $total, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    custWeb()->actingAs($user)->post(route('public.shop.checkout.store'), [
        'recipient_name' => $user->name, 'phone' => '7700001', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'm'.$method->id], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();
    $order = Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    if (! $paid) {
        $order->forceFill(['status' => 'pending_payment', 'paid_at' => null])->save();
    }

    return $order;
}

beforeEach(function () {
    Mail::fake();
    $this->vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $this->aishath = User::factory()->create(['name' => 'Aishath Big', 'phone' => '7711111']);
    $this->hassan = User::factory()->create(['name' => 'Hassan Small', 'phone' => '7712222']);
    $this->unpaid = User::factory()->create(['name' => 'Never Paid']);
    custOrder($this->aishath, $this->vendor, 400);
    custOrder($this->aishath, $this->vendor, 100);
    $this->hassanOrder = custOrder($this->hassan, $this->vendor, 50);
    custOrder($this->unpaid, $this->vendor, 999, false);
});

it('lists everyone with a paid order, biggest spenders first, and finds one by name, phone or order number', function () {
    $office = custOffice();

    custWeb()->actingAs($office)->get(route('admin.bookshop.customers'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Customers')->has('customers', 2)
            ->where('customers.0.name', 'Aishath Big')->where('customers.0.orders', 2)->where('customers.0.spent', '500.00')
            ->where('customers.1.name', 'Hassan Small'));
    foreach (['Hassan', '7712222', $this->hassanOrder->number] as $q) {
        custWeb()->actingAs($office)->get(route('admin.bookshop.customers', ['q' => $q]))
            ->assertInertia(fn ($page) => $page->has('customers', 1)->where('customers.0.name', 'Hassan Small'));
    }
    $csv = custWeb()->actingAs($office)->get(route('admin.bookshop.customers.export'))->streamedContent();
    expect($csv)->toContain('Aishath Big')->toContain('500.00')->not->toContain('Never Paid');

    custWeb()->actingAs($this->aishath)->get(route('admin.bookshop.customers'))->assertForbidden();
    custWeb()->actingAs($this->aishath)->post(route('admin.bookshop.customers.notes', $this->aishath->id), ['body' => 'x'])->assertForbidden();
});

it('tags a customer and filters by tag', function () {
    $office = custOffice();

    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.tags', $this->hassan->id), ['tags' => ['  School ', 'school', 'Wholesale']])->assertSessionHasNoErrors();
    expect(ShopCustomerProfile::query()->where('user_id', $this->hassan->id)->value('tags'))->toBe(['school', 'wholesale']);
    custWeb()->actingAs($office)->get(route('admin.bookshop.customers', ['tag' => 'school']))
        ->assertInertia(fn ($page) => $page->has('customers', 1)->where('customers.0.tags', ['school', 'wholesale'])->where('tags', ['school', 'wholesale']));

    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.tags', $this->hassan->id), ['tags' => array_map(fn ($i) => "t{$i}", range(1, 11))])->assertSessionHasErrors('tags');
    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.tags', $this->hassan->id), ['tags' => []])->assertSessionHasNoErrors();
    expect(ShopCustomerProfile::query()->where('user_id', $this->hassan->id)->value('tags'))->toBe([]);
    // Someone who never ordered is not a customer here.
    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.tags', User::factory()->create()->id), ['tags' => ['x']])->assertNotFound();
});

it('keeps notes with a follow-up that shows as due and is ticked off when done', function () {
    $office = custOffice();

    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.notes', $this->aishath->id), ['body' => 'Wants the Grade 3 set in January', 'follow_up_on' => now()->toDateString()])->assertSessionHasNoErrors();
    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.notes', $this->aishath->id), ['body' => 'Prefers collection'])->assertSessionHasNoErrors();
    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.notes', $this->aishath->id), ['body' => 'Past', 'follow_up_on' => now()->subDay()->toDateString()])->assertSessionHasErrors('follow_up_on');
    $note = ShopCustomerNote::query()->where('body', 'like', 'Wants%')->sole();

    custWeb()->actingAs($office)->get(route('admin.bookshop.customers', ['follow_ups' => 1]))
        ->assertInertia(fn ($page) => $page->has('customers', 1)->where('customers.0.follow_ups_due', 1));
    custWeb()->actingAs($office)->get(route('admin.bookshop.customers.show', $this->aishath->id))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Customer')->where('customer.orders_count', 2)->has('customer.orders', 2)
            ->where('customer.notes.1.body', 'Wants the Grade 3 set in January')->where('customer.notes.1.due', true)->where('customer.notes.1.author', $office->name));

    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.notes.done', [$this->aishath->id, $note->id]))->assertSessionHasNoErrors();
    expect($note->fresh()->done_at)->not->toBeNull()->and($note->fresh()->done_by)->toBe($office->id);
    custWeb()->actingAs($office)->get(route('admin.bookshop.customers', ['follow_ups' => 1]))->assertInertia(fn ($page) => $page->has('customers', 0));
    // A note is ticked off once, and only on its own customer.
    custWeb()->actingAs($office)->post(route('admin.bookshop.customers.notes.done', [$this->hassan->id, $note->id]))->assertNotFound();

    custWeb()->actingAs($office)->get(route('admin.bookshop.customers.show', $this->unpaid->id))->assertOk();
    custWeb()->actingAs($office)->get(route('admin.bookshop.customers.show', User::factory()->create()->id))->assertNotFound();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['customers_title', 'customer_add_note', 'customer_note_due', 'customer_save_tags', 'customers_follow_ups'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
        expect(__('nav.shop_customers', [], $locale))->not->toBe(__('nav.shop_customers', [], 'en'));
    }
});
