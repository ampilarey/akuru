<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

/**
 * The public layout says a page's `error`, `success` and `status` flash
 * (STATUS §5dr), so a page that prints one too says it twice (slice FL1,
 * STATUS §5rg).
 *
 * WB1 found the website's forms doing it (the waiting list three times);
 * FL1 found the cart, a product, the saved list, the wallet, *My lending*,
 * the gift cards and the registration's first two pages doing it too. The
 * layout's copy carries the test id the walks read (`flash-success`).
 */
uses(RefreshDatabase::class);

/** @return list<string> the public site's views and partials, relative to resources/views */
function fl1PublicViews(): array
{
    $views = [];
    foreach (File::allFiles(resource_path('views')) as $file) {
        $relative = str_replace('\\', '/', $file->getRelativePathname());
        if (! str_ends_with($relative, '.blade.php') || $relative === 'public/layouts/public.blade.php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (str_starts_with($relative, 'public/') || str_starts_with($relative, 'courses/') || str_starts_with($relative, 'components/public/')
            || str_contains($source, "@extends('public.layouts.public')")) {
            $views[] = $relative;
        }
    }

    return $views;
}

it('leaves the flash to the layout on every public page', function () {
    $views = fl1PublicViews();
    expect(count($views))->toBeGreaterThan(50);

    $printing = array_values(array_filter($views, fn (string $view) => preg_match(
        "/session\\(\\s*'(success|error|status)'\\s*\\)/",
        (string) file_get_contents(resource_path('views/'.$view)),
    ) === 1));

    expect($printing)->toBe([]);
});

it('says a cart\'s thanks once, in the layout\'s flash', function () {
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $book = Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => 'arabic-workbook', 'title' => 'Arabic Workbook', 'price' => 85,
        'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop',
    ]);

    $this->withoutLocalizationMiddleware()->post(route('public.shop.cart.add'), ['product' => $book->slug])->assertSessionHas('success');
    $thanks = (string) session('success');
    $page = $this->get(route('public.shop.cart'))->assertOk()->getContent();

    expect($thanks)->not->toBe('')
        ->and(substr_count($page, e($thanks)))->toBe(1)
        ->and($page)->toContain('data-testid="flash-success"');
});
