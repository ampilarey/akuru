<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * W3 of the 2026-09-28 website design (STATUS §5kk): the footer is grouped by
 * the four products plus About — open on a desk, folded on a phone — and the
 * home page's stats row counts what each product holds, never a made-up
 * number.
 */
function footerPage(string $route = 'public.library.index'): string
{
    Cache::flush();

    return test()->withoutLocalizationMiddleware()->get(route($route))->assertOk()->getContent();
}

it('groups the footer by product, with About last', function () {
    $html = footerPage();

    preg_match_all('#data-testid="footer-([a-z-]+)"#', $html, $groups);
    expect($groups[1])->toBe(['e-learning', 'library', 'bookstore', 'school', 'about']);

    foreach ([
        'library' => ['public.library.index', 'public.gift-cards.index', 'write.index'],
        'bookstore' => ['public.shop.index', 'vendor.apply'],
        'school' => ['public.admissions.create', 'dashboard', 'public.careers'],
        'about' => ['public.about', 'public.news.index', 'public.research.index', 'public.prayer-times', 'public.contact.create'],
    ] as $group => $routes) {
        preg_match('#data-testid="footer-'.$group.'"(.*?)</details>#s', $html, $block);
        foreach ($routes as $name) {
            expect($block[1])->toContain('href="'.route($name).'"');
        }
    }

    // Served open so the links are there without script; a phone folds them.
    expect(substr_count($html, 'data-footer-group data-testid="footer-'))->toBe(5)
        ->and($html)->toMatch('#data-testid="footer-about" open>#')
        ->and($html)->toContain("window.matchMedia('(min-width: 768px)')");
});

it('counts courses, books and store items in one row, and never invents a number', function () {
    $item = app(SaveLibraryItemAction::class)->execute(['title' => 'Seerah', 'content_type' => 'book', 'access_type' => 'free_public', 'language' => 'en', 'body' => '<p>One.</p>']);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);
    app(SaveLibraryItemAction::class)->execute(['title' => 'Draft', 'content_type' => 'book', 'access_type' => 'free_public', 'language' => 'en', 'body' => '<p>One.</p>']);
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    foreach (['Stand', 'Mat'] as $title) {
        Product::query()->create(['vendor_id' => $vendor->id, 'slug' => strtolower($title), 'title' => $title, 'price' => 50, 'currency' => 'MVR',
            'tax_class' => 'standard', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    }

    $html = footerPage('public.home');
    preg_match('#data-testid="home-stats"(.*?)</section>#s', $html, $row);

    expect($row)->not->toBeEmpty()
        ->and($row[1])->toMatch('#data-testid="home-stat-books">\s*<span class="home-stat-n">1</span>#')
        ->and($row[1])->toMatch('#data-testid="home-stat-items">\s*<span class="home-stat-n">2</span>#')
        // No courses here, so no course count — not the old invented "12".
        ->and($row[1])->not->toContain('data-testid="home-stat-courses"')
        ->and($html)->not->toContain('Qualified teachers')
        ->and($html)->not->toContain('Courses offered');
});

it('leaves the stats row out when there is nothing to count', function () {
    expect(footerPage('public.home'))->not->toContain('data-testid="home-stats"');
});

it('speaks Dhivehi and Arabic in the footer', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['browse_books', 'write_for_akuru', 'delivery_returns', 'rights_reserved', 'stat_items'] as $key) {
            expect(__('site.'.$key, [], $locale))->not->toBe('site.'.$key)
                ->and(__('site.'.$key, [], $locale))->not->toBe(__('site.'.$key, [], 'en'));
        }
    }
});
