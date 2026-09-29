<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * STATUS §5lt, the owner (2026-09-29): the site's phone bar (Home · Courses ·
 * Library …) "doesn't fit for bookstore" — the Bookstore gets a shop's tabs,
 * as on iruali: Home · Categories · Deals · Account · Cart, and each shop's
 * pages the same tabs, pointing into that shop.
 */
function tabsSetup(): array
{
    $books = ProductCategory::query()->create(['name' => 'Books', 'name_dv' => 'ފޮތް', 'slug' => 'books', 'is_active' => true, 'sort_order' => 1]);
    $pens = ProductCategory::query()->create(['name' => 'Pens', 'slug' => 'pens', 'is_active' => true, 'sort_order' => 2]);
    ProductCategory::query()->create(['name' => 'Empty', 'slug' => 'empty', 'is_active' => true, 'sort_order' => 3]);

    $fitrah = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $other = Vendor::query()->create(['name' => 'Other', 'slug' => 'other', 'code' => 'OTH', 'status' => 'active']);
    $make = fn (Vendor $v, string $slug, ProductCategory $c, array $extra = []) => Product::query()->create($extra + [
        'vendor_id' => $v->id, 'slug' => $slug, 'title' => ucfirst($slug), 'price' => 50, 'currency' => 'MVR', 'product_category_id' => $c->id,
        'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop',
    ]);
    $make($fitrah, 'quran-reader', $books, ['sale_percent' => 20, 'sale_ends_at' => now()->addDay()]);
    $make($fitrah, 'story-book', $books);
    $make($other, 'blue-pen', $pens);
    VendorCollection::query()->create(['vendor_id' => $fitrah->id, 'slug' => 'ramadan', 'name' => 'Ramadan picks', 'rule' => ['kind' => 'all'], 'is_active' => true]);

    return [$fitrah, $other];
}

function tabsPage(string $url, ?User $user = null): string
{
    Cache::flush();
    $t = test()->withoutLocalizationMiddleware();

    return ($user ? $t->actingAs($user) : $t)->get($url)->assertOk()->getContent();
}

/** The one bar's HTML, and the sheet's. */
function tabsBar(string $html): string
{
    preg_match('#data-testid="shop-bottom-bar"(.*?)</nav>#s', $html, $m);

    return $m[1] ?? '';
}

function tabsSheet(string $html): string
{
    preg_match('#data-testid="shop-sheet"(.*?)<style>#s', $html, $m);

    return $m[1] ?? '';
}

it('gives the Bookstore a shop\'s tabs instead of the site\'s bar', function () {
    tabsSetup();

    $html = tabsPage(route('public.shop.index'));
    $bar = tabsBar($html);

    expect($html)->not->toContain('data-testid="bottom-bar"')->toContain('data-scope="store"')
        ->and(substr_count($html, 'data-testid="shop-bottom-bar"'))->toBe(1)
        ->and($bar)->toContain('data-testid="bar-home"')->toContain('href="'.route('public.shop.index').'"')
        ->toContain('data-testid="bar-categories"')->toContain('data-testid="bar-deals"')->toContain('href="'.route('public.shop.deals').'"')
        ->toContain('data-testid="bar-account"')->toContain('href="'.route('login').'"')->toContain(__('shop.bar_sign_in'))
        ->toContain('data-testid="bar-cart"')->toContain('href="'.route('public.shop.cart').'"')
        // Nothing of the site's bar.
        ->not->toContain(route('public.courses.index'))->not->toContain(route('public.library.index'));
    // Home is the page you are on.
    expect($bar)->toMatch('#data-testid="bar-home"\s+aria-current="page"#');

    // The sheet: every category with something for sale shop-wide, with how many; never an empty one.
    $sheet = tabsSheet($html);
    expect($sheet)->toContain('href="'.route('public.shop.category', 'books').'"')->toContain('href="'.route('public.shop.category', 'pens').'"')
        ->not->toContain(route('public.shop.category', 'empty'))
        ->toContain(route('public.shop.index').'#shops')->toContain(route('public.shop.track'));
});

it('keeps the tabs on the store\'s other pages, and marks where you are', function () {
    tabsSetup();
    $buyer = User::factory()->create();

    expect(tabsBar(tabsPage(route('public.shop.deals'))))->toMatch('#data-testid="bar-deals"\s+aria-current="page"#')
        ->and(tabsBar(tabsPage(route('public.shop.product', 'story-book'))))->toContain('data-testid="bar-home"')
        ->and(tabsBar(tabsPage(route('public.shop.cart'))))->toMatch('#data-testid="bar-cart"\s+aria-current="page"#');

    // Signed in: Account goes to My orders, and is marked there.
    $orders = tabsBar(tabsPage(route('public.shop.orders'), $buyer));
    expect($orders)->toContain('href="'.route('public.shop.orders').'"')->toContain(__('shop.bar_account'))
        ->toMatch('#data-testid="bar-account"\s+aria-current="page"#');

    // The cart counts what is in it.
    test()->withoutLocalizationMiddleware()->actingAs($buyer)->post(route('public.shop.cart.add'), ['product' => 'story-book', 'quantity' => 2])->assertSessionHasNoErrors();
    expect(tabsBar(tabsPage(route('public.shop.index'), $buyer)))->toContain('data-testid="bar-cart-count">2<');
});

it('points every tab into the shop on a shop\'s own pages', function () {
    tabsSetup();

    $html = tabsPage(route('public.shop.vendor', 'fitrah'));
    $bar = tabsBar($html);
    expect($html)->toContain('data-scope="shop"')->not->toContain('data-testid="bottom-bar"')
        ->and($bar)->toContain('href="'.route('public.shop.vendor', 'fitrah').'"')->toContain(__('shop.bar_shop'))
        ->toContain('href="'.e(route('public.shop.vendor', ['vendor' => 'fitrah', 'deals' => 1])).'"')
        ->toMatch('#data-testid="bar-home"\s+aria-current="page"#');

    // Its sheet: its collections, its categories only, and the way back to the whole store.
    $sheet = tabsSheet($html);
    expect($sheet)->toContain('Ramadan picks')->toContain(route('public.shop.vendor.collection', ['fitrah', 'ramadan']))
        ->toContain(e(route('public.shop.vendor', ['vendor' => 'fitrah', 'category' => 'books'])))
        ->not->toContain('Pens')
        ->toContain('data-testid="shop-sheet-whole-store"');

    // The shop's Deals tab shows the shop's deals, and is marked.
    $deals = tabsPage(route('public.shop.vendor', ['vendor' => 'fitrah', 'deals' => 1]));
    expect(tabsBar($deals))->toMatch('#data-testid="bar-deals"\s+aria-current="page"#')->not->toMatch('#data-testid="bar-home"\s+aria-current#')
        ->and($deals)->toContain('Quran-reader')->not->toContain('>Story-book<');

    // And on a collection, the Categories tab is the marked one.
    expect(tabsBar(tabsPage(route('public.shop.vendor.collection', ['fitrah', 'ramadan']))))->toContain('data-scope')
        ->toMatch('#shop-tab is-active" data-testid="bar-categories"#');
});

it('says a shop with nothing listed has nothing yet, rather than an empty list', function () {
    Vendor::query()->create(['name' => 'New Shop', 'slug' => 'new-shop', 'code' => 'NEW', 'status' => 'active']);

    expect(tabsSheet(tabsPage(route('public.shop.vendor', 'new-shop'))))->toContain('data-testid="shop-sheet-empty"');
});

it('names the tabs in Dhivehi and Arabic', function () {
    tabsSetup();

    foreach (['dv', 'ar'] as $locale) {
        app()->setLocale($locale);
        $html = tabsPage(route('public.shop.index'));
        expect(tabsBar($html))->toContain(__('shop.bar_home', [], $locale))->toContain(__('shop.bar_deals', [], $locale))->toContain(__('shop.bar_cart', [], $locale));
    }
    app()->setLocale('dv');
    expect(tabsSheet(tabsPage(route('public.shop.index'))))->toContain('ފޮތް');
});
