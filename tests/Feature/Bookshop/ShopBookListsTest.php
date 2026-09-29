<?php

use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5lc, school book lists: a shop marks a hand-picked collection as a
 * school's list for a grade, with how many of each item. Parents find it on
 * the store's front under School book lists, see what it comes to, and put
 * the whole list in the cart in one tap; what cannot go in is named.
 */
function listShop(string $slug = 'list-shop'): Vendor
{
    return Vendor::query()->create(['name' => 'List Shop', 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
}

function listProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 20, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

/** @param  array<int, int>  $items  product id => quantity */
function gradeList(Vendor $vendor, array $items, array $overrides = []): VendorCollection
{
    $list = VendorCollection::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => 'grade-3-ghiyasuddin', 'name' => 'Grade 3 list', 'book_list' => true,
        'school' => 'Ghiyasuddin School', 'grade' => 'Grade 3', 'is_active' => true,
    ]);
    $i = 0;
    foreach ($items as $productId => $quantity) {
        $list->products()->attach($productId, ['sort_order' => $i++, 'quantity' => $quantity]);
    }

    return $list;
}

function listVisitor()
{
    return test()->withoutLocalizationMiddleware();
}

it('shows a book list with its quantities, what it comes to, and one button for the whole list', function () {
    $shop = listShop();
    $maths = listProduct($shop, 'Maths Workbook 3', 50);
    $pencils = listProduct($shop, 'Pencil Pack', 10, ['sale_percent' => 50, 'sale_ends_at' => now()->addDay()]);
    $gone = listProduct($shop, 'Atlas', 120, ['stock' => 0]);
    gradeList($shop, [$maths->id => 1, $pencils->id => 3, $gone->id => 1]);

    listVisitor()->get(route('public.shop.vendor.collection', ['list-shop', 'grade-3-ghiyasuddin']))->assertOk()
        ->assertSee('data-testid="book-list"', false)
        ->assertSee('Ghiyasuddin School')->assertSee('Grade 3')
        ->assertSeeInOrder(['1 ×', 'Maths Workbook 3', 'MVR 50.00', '3 ×', 'Pencil Pack', 'MVR 15.00', '1 ×', 'Atlas', __('shop.book_list_unavailable')])
        // Only what can be bought is counted: 50 + 3 × 5 (on sale).
        ->assertSee(__('shop.book_list_total', ['count' => 2]).' MVR 65.00')
        ->assertSee('data-testid="book-list-add"', false);
});

it('puts the whole list in the cart in one tap, and names what could not go in', function () {
    $shop = listShop();
    $maths = listProduct($shop, 'Maths Workbook 3', 50);
    $pencils = listProduct($shop, 'Pencil Pack', 10);
    $gone = listProduct($shop, 'Atlas', 120, ['stock' => 0]);
    $bag = listProduct($shop, 'School Bag', 300);
    ProductVariant::query()->create(['product_id' => $bag->id, 'name' => 'Blue', 'price' => 300, 'stock' => 5, 'is_active' => true]);
    gradeList($shop, [$maths->id => 1, $pencils->id => 3, $gone->id => 1, $bag->id => 1]);

    // A guest: the cart is theirs by session.
    listVisitor()->post(route('public.shop.book-list.add', ['list-shop', 'grade-3-ghiyasuddin']))
        ->assertRedirect(route('public.shop.cart'))
        ->assertSessionHas('success', __('shop.book_list_added_flash', ['count' => 2]))
        ->assertSessionHas('warning', __('shop.book_list_skipped_flash', ['titles' => 'Atlas, School Bag']));
    $items = CartItem::query()->orderBy('id')->get();
    expect($items->pluck('quantity', 'product_id')->all())->toBe([$maths->id => 1, $pencils->id => 3]);

    // Again: the quantities add up, like a second tap on "Add to cart".
    listVisitor()->post(route('public.shop.book-list.add', ['list-shop', 'grade-3-ghiyasuddin']));
    expect(CartItem::query()->where('product_id', $pencils->id)->value('quantity'))->toBe(6);

    listVisitor()->get(route('public.shop.cart'))->assertOk()->assertSee('data-testid="flash-warning"', false)->assertSee('Maths Workbook 3');
});

it('adds a signed-in parent\'s list to their own cart', function () {
    $shop = listShop();
    $maths = listProduct($shop, 'Maths Workbook 3', 50);
    gradeList($shop, [$maths->id => 2]);
    $parent = User::factory()->create();

    listVisitor()->actingAs($parent)->post(route('public.shop.book-list.add', ['list-shop', 'grade-3-ghiyasuddin']))->assertRedirect(route('public.shop.cart'));
    expect(Cart::query()->where('user_id', $parent->id)->sole()->items()->sole()->quantity)->toBe(2);
});

it('refuses a list that is not one: an ordinary collection, an inactive list, a suspended shop', function () {
    $shop = listShop();
    $maths = listProduct($shop, 'Maths Workbook 3', 50);
    gradeList($shop, [$maths->id => 1], ['slug' => 'plain', 'book_list' => false]);
    gradeList($shop, [$maths->id => 1], ['slug' => 'hidden', 'is_active' => false]);
    $closed = listShop('closed-shop');
    $closed->update(['status' => 'suspended']);
    gradeList($closed, [listProduct($closed, 'Closed Book', 10)->id => 1], ['slug' => 'closed-list']);

    listVisitor()->post(route('public.shop.book-list.add', ['list-shop', 'plain']))->assertNotFound();
    listVisitor()->post(route('public.shop.book-list.add', ['list-shop', 'hidden']))->assertNotFound();
    listVisitor()->post(route('public.shop.book-list.add', ['closed-shop', 'closed-list']))->assertNotFound();
    listVisitor()->get(route('public.shop.vendor.collection', ['list-shop', 'plain']))->assertOk()->assertDontSee('data-testid="book-list"', false);
    expect(CartItem::query()->count())->toBe(0);
});

it('lists the schools\' lists on the store\'s front, and says so when there are none', function () {
    listVisitor()->get(route('public.shop.index'))->assertOk()
        ->assertSee('id="book-lists"', false)->assertSee(__('shop.book_lists_none'))
        ->assertSee('data-testid="shop-link-book-lists"', false);

    $shop = listShop();
    gradeList($shop, [listProduct($shop, 'Maths Workbook 3', 50)->id => 1]);
    gradeList($shop, [listProduct($shop, 'Draft Book', 5, ['status' => 'draft'])->id => 1], ['slug' => 'empty-list', 'school' => 'Empty School']);

    listVisitor()->get(route('public.shop.index'))->assertOk()
        ->assertSee(__('shop.book_lists_intro'))
        ->assertSee('data-book-list="list-shop/grade-3-ghiyasuddin"', false)->assertSee('Ghiyasuddin School')
        ->assertDontSee('Empty School');
});

it('lets a shop mark a hand-picked collection as a school list with quantities', function () {
    Role::findOrCreate('vendor', 'web');
    $created = app(CreateVendorAction::class)->execute(['name' => 'Portal Shop', 'owner_name' => 'Owner', 'owner_email' => 'list-owner@example.test'], User::factory()->create()->id);
    VendorMember::query()->where('vendor_id', $created['vendor_id'])->update(['agreement_accepted_at' => now()]);
    $owner = User::query()->findOrFail($created['owner_user_id']);
    $vendor = Vendor::query()->findOrFail($created['vendor_id']);
    $maths = listProduct($vendor, 'Maths Workbook 3', 50);
    $pencils = listProduct($vendor, 'Pencil Pack', 10);
    $other = listProduct(listShop(), 'Someone Elses Book', 10);

    listVisitor()->actingAs($owner)->post(route('vendor.storefront.collections.store'), [
        'name' => 'Grade 3 list', 'kind' => 'manual', 'product_ids' => [$maths->id, $pencils->id, $other->id],
        'book_list' => 1, 'school' => 'Ghiyasuddin School', 'grade' => 'Grade 3',
        'quantities' => [$maths->id => 1, $pencils->id => 3, $other->id => 9],
    ])->assertSessionHasNoErrors();

    $list = VendorCollection::query()->where('vendor_id', $vendor->id)->sole();
    expect($list->book_list)->toBeTrue()->and($list->school)->toBe('Ghiyasuddin School')->and($list->grade)->toBe('Grade 3')
        ->and($list->products()->get()->mapWithKeys(fn ($p) => [$p->id => (int) $p->pivot->quantity])->all())->toBe([$maths->id => 1, $pencils->id => 3]);

    // A collection by rule cannot be a list.
    listVisitor()->actingAs($owner)->post(route('vendor.storefront.collections.update', $list->id), [
        'name' => 'Grade 3 list', 'kind' => 'rule', 'rule' => ['tags' => ['grade 3']], 'book_list' => 1, 'school' => 'X', 'grade' => 'Y',
    ])->assertSessionHasNoErrors();
    expect($list->refresh()->book_list)->toBeFalse()->and($list->school)->toBeNull();
});
