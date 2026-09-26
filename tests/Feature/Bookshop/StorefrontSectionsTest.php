<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Models\VendorStorefrontImage;
use App\Domains\Bookshop\Models\VendorStorefrontVersion;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B5, the storefront designer part 2: a vendor arranges
 * the home from sections, adds pages built from the same sections and
 * collections of its products, sets the storefront menu and SEO fields,
 * previews and publishes; the office moderates. Everything a vendor types is
 * data normalised by SectionTypes — nothing reaches the page unchecked.
 */
function sectionsShop(): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'tagline' => 'iman.noor.ihsan', 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    $category = ProductCategory::query()->create(['name' => 'Workbooks', 'slug' => 'workbooks', 'is_active' => true]);
    $products = [];
    foreach ([['tracing-book', 'Tracing Book', 85, ['arabic']], ['puzzle', 'Wooden Puzzle', 240, ['thaana']], ['prayer-mat', 'Kids Prayer Mat', 180, ['arabic', 'gift']]] as [$slug, $title, $price, $tags]) {
        $products[$slug] = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => $slug, 'title' => $title, 'price' => $price, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 5, 'status' => 'active', 'visibility' => 'shop', 'product_category_id' => $category->id, 'tags' => $tags]);
    }
    // Another shop, whose product and image a section must never be able to point at.
    $other = Vendor::query()->create(['name' => 'Other', 'slug' => 'other', 'code' => 'OTH', 'status' => 'active']);
    $products['other'] = Product::query()->create(['vendor_id' => $other->id, 'slug' => 'other-secret', 'title' => 'Other Secret', 'price' => 99, 'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => false, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $owner, $products, $category];
}

function sectionsAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function publishTheme(User $owner): void
{
    sectionsAs($owner)->post(route('vendor.storefront.draft'), ['theme' => ['preset' => 'ocean']])->assertSessionHasNoErrors();
}

function officeUser(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

it('normalises sections to the schema: own products and images only, allowed videos, bounded text, cleaned prose', function () {
    Storage::fake('public');
    [$vendor, $owner, $products] = sectionsShop();
    publishTheme($owner);

    sectionsAs($owner)->post(route('vendor.storefront.images.upload'), ['images' => [UploadedFile::fake()->image('shop.jpg', 1200, 800), UploadedFile::fake()->image('class.jpg', 800, 800)], 'alt' => 'Our shop'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $library = VendorStorefrontImage::query()->where('vendor_id', $vendor->id)->pluck('media_file_id')->all();
    expect($library)->toHaveCount(2);
    $strangerImage = VendorStorefrontImage::query()->create(['vendor_id' => $vendor->id + 1, 'media_file_id' => $library[0], 'alt' => null])->media_file_id;

    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), [
        'sections' => [
            ['type' => 'hero', 'settings' => ['heading' => str_repeat('H', 400), 'heading_dv' => 'ފިތުރަތު', 'images' => [$library[0], 999999, $library[1]], 'buttons' => [['kind' => 'product', 'target' => 'tracing-book', 'label' => 'Shop the book'], ['kind' => 'product', 'target' => 'other-secret', 'label' => 'Nope'], ['kind' => 'all', 'label' => 'Everything']], 'align' => 'sideways']],
            ['type' => 'featured_products', 'settings' => ['heading' => 'Picks', 'products' => [$products['puzzle']->id, $products['other']->id, $products['tracing-book']->id], 'layout' => 'carousel']],
            ['type' => 'text_image', 'settings' => ['heading' => 'Our story', 'body' => "Made with care.\n\n<script>alert(1)</script>Every item is tested.", 'image' => $library[1], 'image_side' => 'start']],
            ['type' => 'video', 'settings' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
            ['type' => 'video', 'settings' => ['url' => 'https://evil.example/embed/x']],
            ['type' => 'contact_map', 'settings' => ['lat' => '4.1755', 'lng' => '73.5093', 'zoom' => 99]],
            ['type' => 'announcement', 'settings' => ['text' => 'Free delivery in Malé over MVR 500', 'ends_at' => '2030-01-01', 'link' => ['kind' => 'all']], 'visibility' => 'scheduled', 'from' => '2020-01-01', 'until' => '2030-12-31', 'mobile_order' => 250],
            ['type' => 'faq', 'settings' => ['items' => [['question' => 'Do you deliver to the atolls?', 'answer' => 'Yes, by boat.'], ['question' => '', 'answer' => 'dropped']]]],
        ],
        'navigation' => [['kind' => 'all', 'label' => 'Shop'], ['kind' => 'page', 'target' => 'nowhere', 'label' => 'Gone'], ['kind' => 'collection', 'target' => 'none', 'label' => 'Gone too']],
        'seo' => ['title' => str_repeat('T', 100), 'description' => 'Learning materials for little hands.', 'image' => $library[0]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $draft = VendorStorefront::query()->where('vendor_id', $vendor->id)->firstOrFail();
    $s = $draft->draft_sections;
    expect($s)->toHaveCount(8)
        ->and($s[0]['id'])->toMatch('/^s[a-z0-9]{6,12}$/')
        ->and(mb_strlen($s[0]['settings']['heading']))->toBe(300)
        ->and($s[0]['settings']['heading_dv'])->toBe('ފިތުރަތު')
        ->and($s[0]['settings']['images'])->toBe([$library[0], $library[1]])
        ->and($s[0]['settings']['buttons'])->toHaveCount(2)
        ->and($s[0]['settings']['buttons'][0]['target'])->toBe('tracing-book')
        ->and($s[0]['settings']['buttons'][1]['kind'])->toBe('all')
        ->and($s[0]['settings']['align'])->toBe('start')
        ->and($s[1]['settings']['products'])->toBe([$products['puzzle']->id, $products['tracing-book']->id])
        ->and($s[1]['settings']['layout'])->toBe('carousel')
        ->and($s[2]['settings']['body'])->toContain('<p>Made with care.</p>')->not->toContain('<script')
        ->and($s[2]['settings']['image'])->toBe($library[1])
        ->and($s[3]['settings']['url'])->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($s[4]['settings']['url'])->toBeNull()
        ->and($s[5]['settings']['lat'])->toBe(4.1755)->and($s[5]['settings']['zoom'])->toBe(18)
        ->and($s[6]['visibility'])->toBe('scheduled')->and($s[6]['from'])->toBe('2020-01-01')->and($s[6]['mobile_order'])->toBe(99)
        ->and($s[7]['settings']['items'])->toHaveCount(1)
        ->and($draft->draft_navigation)->toHaveCount(1)->and($draft->draft_navigation[0]['label'])->toBe('Shop')
        ->and(mb_strlen($draft->draft_seo['title']))->toBe(70)->and($draft->draft_seo['image'])->toBe($library[0]);
    expect($strangerImage)->toBe($library[0]);

    // Too many sections, an unknown type, and a stranger's library image are refused or dropped.
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => array_fill(0, 21, ['type' => 'faq', 'settings' => []])])->assertSessionHasErrors('sections');
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'custom_html', 'settings' => []]]])->assertSessionHasErrors('sections');
    sectionsAs(User::factory()->create())->post(route('vendor.storefront.sections.save'), ['sections' => []])->assertForbidden();
});

it('renders the published sections on the public page in the visitor\'s language, with the draft preview showing hidden ones', function () {
    Storage::fake('public');
    [$vendor, $owner, $products] = sectionsShop();
    publishTheme($owner);
    sectionsAs($owner)->post(route('vendor.storefront.images.upload'), ['images' => [UploadedFile::fake()->image('shop.jpg', 1200, 800)]]);
    $image = VendorStorefrontImage::query()->where('vendor_id', $vendor->id)->value('media_file_id');

    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), [
        'sections' => [
            ['type' => 'hero', 'settings' => ['heading' => 'Learning made joyful', 'heading_dv' => 'އުނގެނުން އުފާވެރި', 'images' => [$image], 'buttons' => [['kind' => 'product', 'target' => 'tracing-book', 'label' => 'Shop the book', 'label_dv' => 'ފޮތް ގަންނަ']]]],
            ['type' => 'announcement', 'settings' => ['text' => 'Eid sale is over', 'ends_at' => '2020-01-01']],
            ['type' => 'featured_products', 'settings' => ['heading' => 'Picks', 'products' => [$products['puzzle']->id, $products['tracing-book']->id]]],
            ['type' => 'gallery', 'settings' => ['heading' => 'Our shop', 'images' => [$image]], 'visibility' => 'hidden'],
            ['type' => 'category_tiles', 'settings' => ['heading' => 'Browse']],
            ['type' => 'delivery_returns', 'settings' => ['body' => 'We pack every order by hand.']],
            ['type' => 'contact_map', 'settings' => ['lat' => 4.1755, 'lng' => 73.5093, 'zoom' => 15], 'mobile_order' => 0],
            ['type' => 'video', 'settings' => ['heading' => 'Watch', 'url' => 'https://youtu.be/dQw4w9WgXcQ']],
            ['type' => 'best_sellers', 'settings' => ['heading' => 'Loved']],
        ],
        'navigation' => [['kind' => 'all', 'label' => 'Shop']],
        'seo' => ['title' => 'Fitrah — learning materials', 'description' => 'Learning materials for little hands.', 'image' => $image],
    ])->assertSessionHasNoErrors();

    // Nothing published yet: the public page stays as it was; the preview shows the draft, hidden section included.
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertDontSee('data-testid="storefront-sections"', false);
    $preview = sectionsAs($owner)->get(route('vendor.storefront.preview'))->assertOk();
    $preview->assertSee('data-section-type="hero"', false)->assertSee('data-section-type="gallery"', false)->assertSee('data-draft-hidden="1"', false)
        ->assertSee('data-section-type="announcement"', false)->assertSee('Eid sale is over');

    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    $version = VendorStorefrontVersion::query()->firstOrFail();
    expect($version->sections)->toHaveCount(9)->and($version->navigation)->toHaveCount(1)->and($version->seo['title'])->toBe('Fitrah — learning materials');

    $public = test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk();
    $public->assertSee('<title>Fitrah — learning materials - ', false)->assertSee('Learning materials for little hands.')->assertSee('-w1600.webp', false)
        ->assertSee('data-testid="storefront-nav"', false)->assertSee('>Shop</a>', false)
        ->assertSee('data-section-type="hero"', false)->assertSee('Learning made joyful')->assertSee('href="'.route('public.shop.product', 'tracing-book').'"', false)->assertSee('Shop the book')
        ->assertDontSee('data-section-type="announcement"', false)->assertDontSee('Eid sale is over')
        ->assertDontSee('data-section-type="gallery"', false)->assertDontSee('data-draft-hidden', false)
        ->assertSee('data-section-type="featured_products"', false)->assertSee('data-product="puzzle"', false)
        ->assertSee('data-section-type="category_tiles"', false)->assertSee('Workbooks')
        ->assertSee('data-testid="delivery-methods"', false)->assertSee('We pack every order by hand.')->assertSee('Returns within 7 days')
        ->assertSee('openstreetmap.org/export/embed.html?bbox=', false)->assertSee('--sf-mobile-order: 0', false)
        ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertDontSee('data-section-type="best_sellers"', false);

    \Illuminate\Support\Facades\App::setLocale('dv');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertSee('އުނގެނުން އުފާވެރި')->assertSee('ފޮތް ގަންނަ');
    \Illuminate\Support\Facades\App::setLocale('en');

    // Best sellers appear once something has been paid for.
    $checkout = BookshopCheckout::query()->create(['number' => 'CHK-1', 'user_id' => $owner->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['name' => 'Owner'], 'subtotal' => 240, 'discount' => 0, 'delivery_total' => 0, 'total' => 240, 'currency' => 'MVR', 'paid_at' => now()]);
    $order = Order::query()->create(['number' => 'FIT-1', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $owner->id, 'status' => 'paid', 'delivery_kind' => 'collect_vendor', 'delivery_name' => 'Collect', 'address_snapshot' => ['name' => 'Owner'], 'subtotal' => 240, 'total' => 240, 'currency' => 'MVR', 'paid_at' => now()]);
    OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $products['puzzle']->id, 'title' => 'Wooden Puzzle', 'unit_price' => 240, 'quantity' => 3, 'line_total' => 720, 'tax_class' => 'zero_rated', 'tax_amount' => 0]);
    app(\App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction::class)->forget($vendor->id);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('data-section-type="best_sellers"', false);

    // The product page carries structured data.
    test()->withoutLocalizationMiddleware()->get(route('public.shop.product', 'tracing-book'))->assertOk()
        ->assertSee('application/ld+json', false)->assertSee('"@type":"Product"', false)->assertSee('"price":"85.00"', false)->assertSee('schema.org/InStock', false);
});

it('publishes pages with the storefront, serves them at /p/<slug> with their SEO, and lists them in the menu and sitemap', function () {
    [$vendor, $owner, $products] = sectionsShop();
    publishTheme($owner);

    sectionsAs($owner)->post(route('vendor.storefront.pages.store'), ['title' => 'About us', 'title_dv' => 'އަހަރެމެންނާ ބެހޭ'])->assertRedirect()->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.pages.store'), ['title' => 'Delivery', 'slug' => 'About Us'])->assertSessionHasErrors('slug');
    sectionsAs($owner)->post(route('vendor.storefront.pages.store'), ['title' => 'Bulk orders for schools', 'slug' => 'schools'])->assertSessionHasNoErrors();
    $about = VendorPage::query()->where('slug', 'about-us')->firstOrFail();
    $schools = VendorPage::query()->where('slug', 'schools')->firstOrFail();

    sectionsAs($owner)->post(route('vendor.storefront.pages.sections', $about->id), [
        'sections' => [['type' => 'text_image', 'settings' => ['heading' => 'Who we are', 'body' => 'Two parents and a printer.']], ['type' => 'faq', 'settings' => ['items' => [['question' => 'Where are you?', 'answer' => 'Malé.']]]]],
        'seo' => ['title' => 'About Fitrah', 'description' => 'Two parents and a printer.'],
    ])->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['navigation' => [['kind' => 'page', 'target' => 'about-us', 'label' => 'About', 'label_dv' => 'ބެހޭ'], ['kind' => 'page', 'target' => 'schools', 'label' => 'Schools']]])->assertSessionHasNoErrors();

    // Not public until the storefront publishes.
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'about-us']))->assertNotFound();
    sectionsAs($owner)->get(route('vendor.storefront.pages.preview', $about->id))->assertOk()->assertSee('Who we are')->assertSee('data-preview="1"', false);

    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    expect($about->refresh()->published_at)->not->toBeNull()->and($about->published_sections)->toHaveCount(2)
        ->and(VendorStorefrontVersion::query()->firstOrFail()->pages)->toHaveCount(2);

    $page = test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'about-us']))->assertOk();
    $page->assertSee('<title>About Fitrah - Fitrah', false)->assertSee('Two parents and a printer.')->assertSee('data-testid="page-title"', false)->assertSee('About us')
        ->assertSee('Who we are')->assertSee('Where are you?')->assertSee('data-testid="storefront-nav"', false)->assertSee('sf-nav-active', false)->assertSee('>Schools</a>', false)
        ->assertSee('data-testid="storefront-head"', false)->assertDontSee('data-testid="storefront-about"', false);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'schools']))->assertOk()->assertSee('data-testid="page-empty"', false);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'nowhere']))->assertNotFound();

    \Illuminate\Support\Facades\App::setLocale('dv');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'about-us']))->assertOk()->assertSee('އަހަރެމެންނާ ބެހޭ')->assertSee('>ބެހޭ</a>', false);
    \Illuminate\Support\Facades\App::setLocale('en');

    $sitemap = collect(app(\App\Domains\Bookshop\Actions\Shop\ListShopSitemapEntriesAction::class)->execute())->pluck('path');
    expect($sitemap)->toContain('shop/fitrah/p/about-us')->toContain('shop/fitrah/p/schools');

    // Deleting a page drops it from the menu on the next render; the designer lists what is left.
    sectionsAs($owner)->delete(route('vendor.storefront.pages.destroy', $schools->id))->assertRedirect()->assertSessionHasNoErrors();
    app(\App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction::class)->forget($vendor->id);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertDontSee('>Schools</a>', false)->assertSee('>About</a>', false);
    sectionsAs($owner)->get(route('vendor.storefront.sections'))->assertOk()->assertInertia(fn ($p) => $p
        ->component('Bookshop/VendorSections')->has('designer.pages', 1)->where('designer.pages.0.slug', 'about-us')->has('designer.schema.hero')->where('designer.limits.pages', 10));
    sectionsAs(User::factory()->create())->get(route('vendor.storefront.sections'))->assertForbidden();
});

it('keeps collections by hand or by rule, serves them at /shop/<vendor>/<slug> in the vendor\'s order, and shows them in a section', function () {
    [$vendor, $owner, $products, $category] = sectionsShop();
    publishTheme($owner);

    sectionsAs($owner)->post(route('vendor.storefront.collections.store'), ['name' => 'Starter kit', 'kind' => 'manual', 'product_ids' => [$products['prayer-mat']->id, $products['other']->id, $products['tracing-book']->id], 'description' => 'What every little learner needs.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.collections.store'), ['name' => 'Products', 'slug' => 'products', 'kind' => 'manual'])->assertSessionHasErrors('slug');
    sectionsAs($owner)->post(route('vendor.storefront.collections.store'), ['name' => 'Arabic', 'kind' => 'rule', 'rule' => ['tags' => ['arabic']]])->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.collections.store'), ['name' => 'Empty rule', 'kind' => 'rule', 'rule' => []])->assertSessionHasErrors('rule');
    $kit = VendorCollection::query()->where('slug', 'starter-kit')->firstOrFail();
    $arabic = VendorCollection::query()->where('slug', 'arabic')->firstOrFail();
    expect($kit->products()->pluck('products.id')->all())->toBe([$products['prayer-mat']->id, $products['tracing-book']->id])
        ->and($arabic->forSaleQuery()->pluck('slug')->all())->toEqualCanonicalizing(['tracing-book', 'prayer-mat']);

    // The public collection page, hand-picked order kept, a stranger's product never in it; CSV too.
    $page = test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.collection', ['fitrah', 'starter-kit']))->assertOk();
    $page->assertSee('data-testid="collection-title"', false)->assertSee('Starter kit')->assertSee('What every little learner needs.')->assertDontSee('other-secret');
    expect(strpos($page->getContent(), 'data-product="prayer-mat"'))->toBeLessThan(strpos($page->getContent(), 'data-product="tracing-book"'));
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.collection', ['fitrah', 'starter-kit']).'?sort=price_asc')->assertOk();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.collection', ['fitrah', 'arabic']))->assertOk()->assertSee('data-product="tracing-book"', false)->assertDontSee('data-product="puzzle"', false);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.collection', ['fitrah', 'nothing']))->assertNotFound();
    $csv = test()->withoutLocalizationMiddleware()->get(route('public.shop.export', ['vendor' => 'fitrah', 'collection' => 'arabic']))->assertOk()->streamedContent();
    expect($csv)->toContain('tracing-book')->not->toContain('puzzle');

    // A collection section and a menu entry; an inactive collection disappears from both.
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), [
        'sections' => [['type' => 'collection', 'settings' => ['collection' => $kit->id, 'count' => '4', 'see_all' => true]]],
        'navigation' => [['kind' => 'collection', 'target' => 'arabic', 'label' => 'Arabic']],
    ])->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()
        ->assertSee('data-section-type="collection"', false)->assertSee('Starter kit')->assertSee('href="'.route('public.shop.vendor.collection', ['fitrah', 'starter-kit']).'"', false)->assertSee('>Arabic</a>', false);
    expect(collect(app(\App\Domains\Bookshop\Actions\Shop\ListShopSitemapEntriesAction::class)->execute())->pluck('path'))->toContain('shop/fitrah/starter-kit');

    sectionsAs($owner)->post(route('vendor.storefront.collections.update', $arabic->id), ['name' => 'Arabic', 'kind' => 'rule', 'rule' => ['tags' => ['arabic'], 'category_id' => $category->id], 'is_active' => false])->assertSessionHasNoErrors();
    app(\App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction::class)->forget($vendor->id);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.collection', ['fitrah', 'arabic']))->assertNotFound();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertDontSee('>Arabic</a>', false);
    sectionsAs($owner)->delete(route('vendor.storefront.collections.destroy', $kit->id))->assertSessionHasNoErrors();
    expect(VendorCollection::query()->count())->toBe(1);
});

it('lets the office require changes, take a storefront down, lift it and lock section types', function () {
    [$vendor, $owner] = sectionsShop();
    $office = officeUser();
    publishTheme($owner);
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'video', 'settings' => ['url' => 'https://vimeo.com/123456789']]]]);
    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('data-testid="storefront"', false)->assertSee('player.vimeo.com/video/123456789', false);

    // Require changes: the note reaches the designer; publishing clears it.
    sectionsAs($office)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'require_changes'])->assertSessionHasErrors('note');
    sectionsAs($office)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'require_changes', 'note' => 'Please remove the video until the licence is clear.'])->assertRedirect()->assertSessionHasNoErrors();
    sectionsAs($owner)->get(route('vendor.storefront.sections'))->assertInertia(fn ($p) => $p->where('designer.moderation.note', 'Please remove the video until the licence is clear.')->where('designer.moderation.held', false));
    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    expect(VendorStorefront::query()->firstOrFail()->moderation_note)->toBeNull();

    // Take down: the public sees the plain page, publishing and rolling back are refused, the office preview still shows the draft.
    sectionsAs($office)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'hold', 'note' => 'Taken down pending the licence.'])->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertDontSee('data-testid="storefront"', false)->assertSee('iman.noor.ihsan');
    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasErrors('storefront');
    sectionsAs($owner)->post(route('vendor.storefront.roll-back', VendorStorefrontVersion::query()->firstOrFail()->id))->assertSessionHasErrors('storefront');
    sectionsAs($owner)->get(route('vendor.storefront.sections'))->assertInertia(fn ($p) => $p->where('designer.moderation.held', true));
    sectionsAs($office)->get(route('admin.bookshop.storefront.preview', 'fitrah'))->assertOk()->assertSee('data-preview="1"', false)->assertSee('player.vimeo.com', false);
    sectionsAs($owner)->get(route('admin.bookshop.storefront.preview', 'fitrah'))->assertForbidden();
    sectionsAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($p) => $p->where('vendors.0.storefront.live', false)->where('vendors.0.storefront.note', 'Taken down pending the licence.')->has('section_types', 15));

    // Lift: back at once, from the published copy.
    sectionsAs($office)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'lift'])->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('data-testid="storefront"', false);

    // Lock the video type: saving or publishing a draft with one is refused; the designer says so.
    sectionsAs($office)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'lock', 'locked_types' => ['video', 'bogus']])->assertSessionHasErrors('locked_types.1');
    sectionsAs($office)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'lock', 'locked_types' => ['video']])->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'video', 'settings' => ['url' => 'https://vimeo.com/123456789']]]])->assertSessionHasErrors('sections');
    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasErrors('sections');
    sectionsAs($owner)->get(route('vendor.storefront.sections'))->assertInertia(fn ($p) => $p->where('designer.moderation.locked_types', ['video']));
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'faq', 'settings' => ['items' => [['question' => 'Video?', 'answer' => 'Soon.']]]]]])->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    sectionsAs($owner)->post(route('admin.bookshop.storefront.moderate', $vendor->id), ['action' => 'lift'])->assertForbidden();
});

it('rolls back sections, menu and pages with the version, and caches the published storefront until a publish clears it', function () {
    [$vendor, $owner] = sectionsShop();
    publishTheme($owner);
    sectionsAs($owner)->post(route('vendor.storefront.pages.store'), ['title' => 'About'])->assertSessionHasNoErrors();
    $about = VendorPage::query()->firstOrFail();
    sectionsAs($owner)->post(route('vendor.storefront.pages.sections', $about->id), ['sections' => [['type' => 'faq', 'settings' => ['heading' => 'First answers', 'items' => [['question' => 'One?', 'answer' => 'Yes.']]]]]]);
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'faq', 'settings' => ['heading' => 'Home FAQ v1', 'items' => [['question' => 'Q1', 'answer' => 'A1']]]]], 'navigation' => [['kind' => 'page', 'target' => 'about', 'label' => 'About v1']]]);
    sectionsAs($owner)->post(route('vendor.storefront.publish'), ['note' => 'v1'])->assertSessionHasNoErrors();

    sectionsAs($owner)->post(route('vendor.storefront.pages.sections', $about->id), ['sections' => [['type' => 'faq', 'settings' => ['heading' => 'Second answers', 'items' => [['question' => 'Two?', 'answer' => 'Also yes.']]]]]]);
    sectionsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'faq', 'settings' => ['heading' => 'Home FAQ v2', 'items' => [['question' => 'Q2', 'answer' => 'A2']]]]], 'navigation' => [['kind' => 'page', 'target' => 'about', 'label' => 'About v2']]]);
    sectionsAs($owner)->post(route('vendor.storefront.publish'), ['note' => 'v2'])->assertSessionHasNoErrors();
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('Home FAQ v2')->assertSee('About v2');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'about']))->assertSee('Second answers');

    // Cached: a direct change to the published copy is not seen until the cache is cleared.
    VendorStorefront::query()->firstOrFail()->update(['published_navigation' => [['kind' => 'all', 'target' => null, 'label' => 'Sneaky']]]);
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('About v2')->assertDontSee('Sneaky');

    $v1 = VendorStorefrontVersion::query()->where('number', 1)->firstOrFail();
    sectionsAs($owner)->post(route('vendor.storefront.roll-back', $v1->id))->assertSessionHasNoErrors();
    $storefront = VendorStorefront::query()->firstOrFail();
    expect($storefront->published_sections[0]['settings']['heading'])->toBe('Home FAQ v1')->and($storefront->draft_navigation[0]['label'])->toBe('About v1')
        ->and($about->refresh()->published_sections[0]['settings']['heading'])->toBe('First answers')->and($about->draft_sections[0]['settings']['heading'])->toBe('First answers');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor', 'fitrah'))->assertSee('Home FAQ v1')->assertSee('About v1')->assertDontSee('Sneaky');
    test()->withoutLocalizationMiddleware()->get(route('public.shop.vendor.page', ['fitrah', 'about']))->assertSee('First answers');
});
