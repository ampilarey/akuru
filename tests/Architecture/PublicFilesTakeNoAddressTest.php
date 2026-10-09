<?php

use Illuminate\Support\Facades\Route;

/**
 * Nothing in `public/` answers for one of the app's addresses (STATUS §5pm).
 *
 * The web server hands a request to `index.php` only when no file or folder
 * of that name exists: `public/.htaccess` skips both (`!-f`, `!-d`), and
 * `php artisan serve` does the same. So a folder named like a route's first
 * segment takes that address from the app.
 *
 * It happened. The ID-card scan (#670, 2026-10-03) put its reader in
 * `public/vendor/tesseract`. From then on a bare `/vendor` met the folder:
 * 404 under `artisan serve`, and a 403 under Apache. That bare address is the
 * portal's Home link, its product search and paging, the redirect after the
 * Vendor Agreement, and the link in every seller's notice.
 *
 * Laravel's default `public/robots.txt` ("allow everything") had likewise
 * been answering `/robots.txt`, in place of the route that names the
 * sitemap.
 *
 * A file or folder may share a route's name only when it is listed here, with
 * why.
 */
it('lets no file or folder in public/ take an address from the app', function () {
    $allowed = [
        // Laravel's own pair: the public disk's link, and the local disk's
        // signed `storage/{path}` route beneath it. A file that exists is the
        // public disk's; anything else reaches the route.
        'storage',
        // The PWA routes serve these very files, with their headers, for a
        // server that hands the request on. Either way the same file answers.
        'manifest.webmanifest',
        'sw.js',
        'offline.html',
    ];

    $segments = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => explode('/', $route->uri())[0])
        ->unique();

    $taken = collect(scandir(public_path()))
        ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
        ->filter(fn (string $entry): bool => $segments->contains($entry))
        ->reject(fn (string $entry): bool => in_array($entry, $allowed, true))
        ->values()
        ->all();

    expect($taken)->toBe([], "These sit in public/ under a route's name, so the web server answers for them instead of the app:\n".implode("\n", $taken));
});

it('answers /robots.txt from the app, at the root, with the sitemap', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertSee('Disallow: /admin/')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
    expect((string) $response->headers->get('Content-Type'))->toStartWith('text/plain');
});

it('keeps the ID-card reader out of every address the app uses', function () {
    $base = public_path('ocr/tesseract/'.config('registration.ocr_version'));

    expect(is_file($base.'/worker.min.js'))->toBeTrue()
        ->and(file_exists(public_path('vendor')))->toBeFalse();
});
