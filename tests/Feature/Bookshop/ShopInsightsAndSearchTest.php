<?php

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\SyncProductSearchIndexAction;
use App\Domains\Bookshop\Contracts\ProductSearchInterface;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ShopDailyStat;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Services\DatabaseProductSearch;
use App\Domains\Bookshop\Services\MeilisearchProductSearch;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B9e: the shop's funnel (§6.8 "storefront analytics
 * beyond basics") — daily counters of visits, product views, adds to
 * cart, checkouts and paid orders, once per session and never for
 * crawlers or the shop's own team, read by the shop and the office — and
 * shop search behind a contract (§10), the database by default and a
 * Meilisearch server over HTTP when chosen, falling back when it is down.
 */
function insightShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active', 'commission_rate' => 10, 'cod_enabled' => true]);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);

    return [$vendor, $owner];
}

function insightProduct(Vendor $vendor, string $title, float $price = 50, array $extra = []): Product
{
    return Product::query()->create($extra + [
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 50, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function statOf(Vendor $vendor, string $metric, string $subject = ''): int
{
    return (int) ShopDailyStat::query()->where('vendor_id', $vendor->id)->where('metric', $metric)->where('subject', $subject)->sum('count');
}

function insightAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

it('counts each step of the funnel once per visit, never for crawlers or the shop\'s own team', function () {
    [$fitrah, $owner] = insightShop();
    $book = insightProduct($fitrah, 'Tracing Book', 85);

    insightAs()->get(route('public.shop.vendor', 'fitrah'))->assertOk();
    insightAs()->get(route('public.shop.vendor', 'fitrah'))->assertOk();
    insightAs()->get(route('public.shop.product', $book->slug))->assertOk();
    insightAs()->get(route('public.shop.product', $book->slug))->assertOk();
    expect(statOf($fitrah, 'shop_view', 'home'))->toBe(1)->and(statOf($fitrah, 'product_view', 'product:'.$book->id))->toBe(1);

    // A second visitor (a new session) counts again; a crawler and the owner do not.
    $this->flushSession();
    insightAs()->get(route('public.shop.product', $book->slug));
    insightAs()->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get(route('public.shop.product', $book->slug))->assertOk();
    $this->flushSession();
    insightAs($owner)->get(route('public.shop.product', $book->slug))->assertOk();
    expect(statOf($fitrah, 'product_view', 'product:'.$book->id))->toBe(2);

    // Adds to cart, the checkout and the paid (here cash) order, with what it sold.
    $customer = User::factory()->create();
    insightAs($customer)->post(route('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 3])->assertSessionHasNoErrors();
    expect(statOf($fitrah, 'cart_add', 'product:'.$book->id))->toBe(1);
    insightAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => ['fitrah' => 'm'.VendorDeliveryMethod::query()->where('vendor_id', $fitrah->id)->value('id')], 'payment_method' => 'cash_on_delivery',
    ])->assertSessionHasNoErrors();
    expect(statOf($fitrah, 'checkout'))->toBe(1)->and(statOf($fitrah, 'order_paid'))->toBe(1)
        ->and(statOf($fitrah, 'product_sold', 'product:'.$book->id))->toBe(3)
        ->and((float) ShopDailyStat::query()->where('metric', 'order_paid')->value('amount'))->toBe(285.0);

    // Nothing personal is in the table.
    expect(array_keys(ShopDailyStat::query()->first()->getAttributes()))->toBe(['id', 'vendor_id', 'day', 'metric', 'subject', 'count', 'amount']);
});

it('shows the shop its own funnel with rates, products, pages and CSVs, and the office every shop', function () {
    [$fitrah, $owner] = insightShop();
    [$noor, $noorOwner] = insightShop('noor');
    $book = insightProduct($fitrah, 'Tracing Book', 85);
    $today = now()->toDateString();
    foreach ([['shop_view', 'home', 40, 0], ['shop_view', 'page:about', 5, 0], ['product_view', 'product:'.$book->id, 20, 0], ['cart_add', 'product:'.$book->id, 5, 0],
        ['checkout', '', 4, 400], ['order_paid', '', 2, 170], ['product_sold', 'product:'.$book->id, 2, 170]] as [$metric, $subject, $count, $amount]) {
        ShopDailyStat::query()->create(['vendor_id' => $fitrah->id, 'day' => $today, 'metric' => $metric, 'subject' => $subject, 'count' => $count, 'amount' => $amount]);
    }
    ShopDailyStat::query()->create(['vendor_id' => $fitrah->id, 'day' => now()->subDays(40)->toDateString(), 'metric' => 'shop_view', 'subject' => 'home', 'count' => 999]);

    insightAs($owner)->get(route('vendor.insights.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Bookshop/VendorInsights')
        ->where('report.days', 30)
        ->where('report.funnel.0.count', 45)
        ->where('report.funnel.1.count', 20)
        ->where('report.funnel.2.rate', 25)
        ->where('report.funnel.4.count', 2)
        ->where('report.revenue', '170.00')
        ->where('report.conversion', 10)
        ->where('report.top_products.0.title', 'Tracing Book')
        ->where('report.top_products.0.sold', 2)
        ->where('report.top_pages.0.page', 'home'));
    insightAs($owner)->get(route('vendor.insights.index', ['days' => 90]))->assertInertia(fn ($page) => $page->where('report.funnel.0.count', 1044));
    insightAs($owner)->get(route('vendor.insights.index', ['days' => 5]))->assertInertia(fn ($page) => $page->where('report.days', 30));
    insightAs($noorOwner)->get(route('vendor.insights.index'))->assertInertia(fn ($page) => $page->where('report.funnel.0.count', 0)->where('report.top_products', []));

    expect(insightAs($owner)->get(route('vendor.insights.export'))->streamedContent())->toContain($today.',45,20,5,4,2,170.00');
    expect(insightAs($owner)->get(route('vendor.insights.export', ['list' => 'products']))->streamedContent())->toContain('"Tracing Book",20,5,2,170.00');

    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');
    insightAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('insights.days', 30)->where('insights.shops.0.vendor', 'Fitrah')->where('insights.shops.0.order_paid', 2)->where('insights.shops.1.shop_view', 0));
    expect(insightAs($office)->get(route('admin.bookshop.insights.export'))->streamedContent())->toContain('Fitrah,fitrah,45,20,5,4,2,170.00,10');
    insightAs($owner)->get(route('admin.bookshop.insights.export'))->assertForbidden();
});

it('searches the database by default, and a Meilisearch server in its order when chosen', function () {
    [$fitrah] = insightShop();
    $reader = insightProduct($fitrah, 'Arabic Reader');
    $letters = insightProduct($fitrah, 'Arabic Letters');
    $hidden = insightProduct($fitrah, 'Arabic Draft', 50, ['status' => 'draft']);
    $other = insightProduct($fitrah, 'Thaana Workbook');

    expect(app(ProductSearchInterface::class))->toBeInstanceOf(DatabaseProductSearch::class);
    $titles = fn () => collect(app(ListShopProductsAction::class)->execute(['q' => 'arabic'])->items())->pluck('title')->all();
    expect($titles())->toEqualCanonicalizing(['Arabic Reader', 'Arabic Letters']);

    config(['bookshop.search.driver' => 'meilisearch', 'bookshop.search.meilisearch.host' => 'http://search.test', 'bookshop.search.meilisearch.key' => 'k']);
    Http::fake(['search.test/indexes/*/search' => Http::response(['hits' => [['id' => $letters->id], ['id' => $hidden->id], ['id' => $other->id], ['id' => $reader->id]]])]);
    expect(app(ProductSearchInterface::class))->toBeInstanceOf(MeilisearchProductSearch::class);
    // The server's order (typo-tolerant: it matched the workbook too), and still never a draft.
    expect($titles())->toBe(['Arabic Letters', 'Thaana Workbook', 'Arabic Reader']);
    Http::assertSent(fn (HttpRequest $r) => $r['q'] === 'arabic' && $r->hasHeader('Authorization', 'Bearer k'));
    // A sort the visitor chose wins over relevance.
    expect(collect(app(ListShopProductsAction::class)->execute(['q' => 'arabic', 'sort' => 'name'])->items())->pluck('title')->all())->toBe(['Arabic Letters', 'Arabic Reader', 'Thaana Workbook']);

    // The server down: the database search, for that request.
    config(['bookshop.search.meilisearch.host' => 'http://down.test']);
    Http::fake(['down.test/*' => Http::response('down', 503)]);
    expect($titles())->toEqualCanonicalizing(['Arabic Reader', 'Arabic Letters']);
});

it('sends the catalogue to the search server only when it is the driver', function () {
    [$fitrah] = insightShop();
    insightProduct($fitrah, 'Arabic Reader', 50, ['description' => '<p>For <b>grade 1</b></p>']);
    insightProduct($fitrah, 'Arabic Draft', 50, ['status' => 'draft']);

    expect(app(SyncProductSearchIndexAction::class)->execute())->toBeNull();
    $this->artisan('bookshop:search-sync')->expectsOutputToContain('nothing to send')->assertSuccessful();

    config(['bookshop.search.driver' => 'meilisearch', 'bookshop.search.meilisearch.host' => 'http://search.test']);
    Http::fake(['search.test/*' => Http::response(['taskUid' => 1], 202)]);
    expect(app(SyncProductSearchIndexAction::class)->execute())->toBe(1);
    Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/indexes/akuru_bookstore_products/documents'));
    Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), '/documents?primaryKey=id')
        && count($r->data()) === 1 && $r->data()[0]['title'] === 'Arabic Reader' && $r->data()[0]['description'] === 'For grade 1' && $r->data()[0]['vendor'] === 'Fitrah');
    Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && $r['searchableAttributes'] === SyncProductSearchIndexAction::SEARCHABLE);
});
