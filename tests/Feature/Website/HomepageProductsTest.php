<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The home page after the 2026-09-28 website design (W2, STATUS §5ki): the
 * four products straight under the hero; open courses; the Digital Library's
 * newest books and the Bookstore's newest items, each as its own shelf shows
 * them; and the School. A shelf with nothing on it is left out.
 */
function homeBook(string $title, bool $publish = true, array $extra = [])
{
    $item = app(SaveLibraryItemAction::class)->execute($extra + [
        'title' => $title,
        'content_type' => 'book',
        'access_type' => 'free_public',
        'language' => 'en',
        'body' => '<p>One.</p>',
    ]);
    if ($publish) {
        app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
    }

    return $item->refresh();
}

function homeProduct(string $title, string $vendorStatus = 'active', string $status = 'active'): Product
{
    $slug = Str::slug($title);
    $vendor = Vendor::query()->create(['name' => 'Shop '.$title, 'slug' => 'shop-'.$slug, 'code' => strtoupper(substr(md5($slug), 0, 3)), 'status' => $vendorStatus]);

    return Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => $slug, 'title' => $title, 'price' => 150, 'currency' => 'MVR',
        'tax_class' => 'standard', 'track_stock' => true, 'stock' => 5, 'status' => $status, 'visibility' => 'shop',
    ]);
}

function homeHtml(): string
{
    Cache::flush();

    return test()->withoutLocalizationMiddleware()->get(route('public.home'))->assertOk()->getContent();
}

it('puts the four products straight under the hero', function () {
    $html = homeHtml();

    foreach ([
        'courses' => route('public.courses.index'),
        'library' => route('public.library.index'),
        'bookstore' => route('public.shop.index'),
        'school' => route('public.admissions.create'),
    ] as $key => $href) {
        expect($html)->toContain('href="'.$href.'" class="home-tile" data-testid="home-tile-'.$key.'"');
    }
    expect(strpos($html, 'data-testid="home-products"'))->toBeLessThan(strpos($html, 'data-testid="home-courses-section"'))
        // The design ends on news and events; the old "Why Akuru" cards and closing banner are gone.
        ->and($html)->not->toContain('Why Akuru Institute?')
        ->and($html)->not->toContain('Ready to Start Your Journey?');
});

it('shows the library\'s newest published books, and never a draft', function () {
    homeBook('Seerah for Children');
    homeBook('Tajweed Rules', true, ['access_type' => 'paid', 'price' => 75]);
    homeBook('Unfinished Draft', false);

    $html = homeHtml();
    preg_match('#data-testid="home-library"(.*?)</section>#s', $html, $shelf);

    expect($shelf)->not->toBeEmpty()
        ->and(substr_count($shelf[1], 'data-testid="home-book"'))->toBe(2)
        ->and($shelf[1])->toContain('Seerah for Children')
        ->and($shelf[1])->toContain('href="'.route('public.library.show', 'tajweed-rules').'"')
        ->and($shelf[1])->toContain('MVR 75')
        ->and($shelf[1])->toContain('Free')
        ->and($shelf[1])->not->toContain('Unfinished Draft');
});

it('shows the bookstore\'s newest items for sale, and nothing a shop has not put on sale', function () {
    homeProduct('Wooden Quran Stand');
    homeProduct('Draft Puzzle', 'active', 'draft');
    homeProduct('Suspended Shop Mat', 'suspended');

    $html = homeHtml();
    preg_match('#data-testid="home-bookstore"(.*?)</section>#s', $html, $shelf);

    expect($shelf)->not->toBeEmpty()
        ->and(substr_count($shelf[1], 'data-testid="home-product"'))->toBe(1)
        ->and($shelf[1])->toContain('href="'.route('public.shop.product', 'wooden-quran-stand').'"')
        ->and($shelf[1])->toContain('Shop Wooden Quran Stand')
        ->and($shelf[1])->toContain('MVR 150')
        ->and($shelf[1])->toContain('href="'.route('vendor.apply').'"')
        ->and($shelf[1])->not->toContain('Draft Puzzle')
        ->and($shelf[1])->not->toContain('Suspended Shop Mat');
});

it('leaves an empty shelf out, and always offers the School', function () {
    $html = homeHtml();

    expect($html)->not->toContain('data-testid="home-library"')
        ->and($html)->not->toContain('data-testid="home-bookstore"')
        ->and($html)->toContain('data-testid="home-school"')
        ->and($html)->toMatch('#href="'.preg_quote(route('public.admissions.create'), '#').'"[^>]*data-testid="home-school-apply"#');
});

it('leads the hero with Akuru as a whole until the office adds its own slides', function () {
    expect(homeHtml())->toContain('Learn, read and grow — with one Akuru account')
        ->and(__('site.hero_title', [], 'dv'))->not->toBe('site.hero_title')
        ->and(__('site.hero_title', [], 'ar'))->not->toBe('site.hero_title');
});
