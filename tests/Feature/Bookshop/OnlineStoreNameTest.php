<?php

use App\Domains\Website\Models\Page;
use Database\Seeders\BookshopPolicyPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The owner's rename (2026-09-26): "Akuru Online Bookshop" becomes the
 * **Akuru Online Store**, because it sells educational items, not only
 * books. Every label a person reads says so, in English, Dhivehi and
 * Arabic. `/shop`, `/admin/bookshop`, the domain's code names, tables and
 * the `bookshop.manage` permission are internal and unchanged.
 */
it('names the store in every language and in the menus', function () {
    foreach ([
        'en' => ['Akuru Online Store', 'Store', 'Online Store'],
        'dv' => ['އަކުރު އޮންލައިން ފިހާރަ', 'ފިހާރަ', 'އޮންލައިން ފިހާރަ'],
        'ar' => ['متجر أكورو الإلكتروني', 'المتجر', 'المتجر الإلكتروني'],
    ] as $locale => [$title, $short, $office]) {
        expect(__('shop.bookshop_title', [], $locale))->toBe($title)
            ->and(__('shop.nav_shop', [], $locale))->toBe($short)
            ->and(__('nav.shop', [], $locale))->toBe($short)
            ->and(__('nav.bookshop', [], $locale))->toBe($office);
    }

    $home = $this->withoutLocalizationMiddleware()->get(route('public.shop.index'))->assertOk();
    $home->assertSee('<title>Akuru Online Store', false)->assertDontSee('Bookshop');
    preg_match_all('#<a[^>]+href="[^"]*/shop"[^>]*>\s*Store\s*</a>#', $home->getContent(), $links);
    expect(count($links[0]))->toBeGreaterThanOrEqual(2);
});

it('renames the Vendor Agreement where nobody has edited it, and leaves an edited one alone', function () {
    $this->seed(BookshopPolicyPagesSeeder::class);
    expect(Page::query()->where('slug', 'vendor-agreement')->value('body'))->toContain('Akuru Online Store')->not->toContain('Bookshop');

    // A host seeded before the rename, its draft untouched.
    Page::query()->where('slug', 'vendor-agreement')->update([
        'body' => '<p><em>Last updated: 26 September 2026. First draft pending review by Akuru Institute.</em></p><h3>1. Selling in the bookshop</h3><p>The one Akuru Online Bookshop. The bookshop\'s header stays.</p>',
        'excerpt' => 'The terms on which a shop sells in the Akuru Online Bookshop.',
    ]);
    $this->seed(BookshopPolicyPagesSeeder::class);
    $page = Page::query()->where('slug', 'vendor-agreement')->first();
    expect($page->body)->toBe('<p><em>Last updated: 26 September 2026. First draft pending review by Akuru Institute.</em></p><h3>1. Selling in the store</h3><p>The one Akuru Online Store. The store\'s header stays.</p>')
        ->and($page->excerpt)->toBe('The terms on which a shop sells in the Akuru Online Store.');

    // One the office has signed off keeps its words.
    Page::query()->where('slug', 'vendor-agreement')->update(['body' => '<p>Signed off. Akuru Online Bookshop terms.</p>']);
    $this->seed(BookshopPolicyPagesSeeder::class);
    expect(Page::query()->where('slug', 'vendor-agreement')->value('body'))->toBe('<p>Signed off. Akuru Online Bookshop terms.</p>');
});

it('leaves the old name nowhere a person can read it', function () {
    $offenders = [];
    $paths = ['resources/views', 'resources/js', 'resources/lang', 'database/seeders/BookshopPolicyPagesSeeder.php', 'database/seeders/BookshopCatalogueSeeder.php'];
    foreach ($paths as $path) {
        $full = base_path($path);
        $files = is_file($full) ? [new SplFileInfo($full)] : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            // The seeder's rename step names the old wording on purpose.
            $source = preg_replace('/private function renameInUntouchedDraft.*?\n    }\n/s', '', $source);
            // Code identifiers (routes, props, the React page folder) are not words people read.
            $source = preg_replace('#admin/bookshop|admin\.bookshop|Bookshop/(Admin|Vendor)|Domains\\\\Bookshop|bookshop_title|\'bookshop\'|Bookshop(Catalogue|PolicyPages)Seeder|BOOKSHOP_PLAN#', '', $source);
            if (preg_match('/Online Bookshop|\bBookshop\b|ފޮތްފިހާރަ|متجر الكتب|للكتب على/u', $source)) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBeEmpty('Still named the bookshop: '.implode(', ', $offenders));
});
