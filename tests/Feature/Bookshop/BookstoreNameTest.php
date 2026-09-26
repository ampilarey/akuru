<?php

use App\Domains\Website\Models\Page;
use Database\Seeders\BookshopPolicyPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The owner's name (2026-09-26): **Akuru Bookstore**. It was planned as the
 * "Akuru Online Bookshop" and was briefly the "Akuru Online Store" the same
 * day. Every label a person reads says Akuru Bookstore, in English, Dhivehi
 * and Arabic. `/shop`, `/admin/bookshop`, the domain's code names, tables
 * and the `bookshop.manage` permission are internal and unchanged.
 */
it('names the bookstore in every language and in the menus', function () {
    foreach ([
        'en' => ['Akuru Bookstore', 'Bookstore'],
        'dv' => ['އަކުރު ފޮތްފިހާރަ', 'ފޮތްފިހާރަ'],
        'ar' => ['متجر أكورو للكتب', 'متجر الكتب'],
    ] as $locale => [$title, $short]) {
        expect(__('shop.bookshop_title', [], $locale))->toBe($title)
            ->and(__('shop.nav_shop', [], $locale))->toBe($short)
            ->and(__('nav.shop', [], $locale))->toBe($short)
            ->and(__('nav.bookshop', [], $locale))->toBe($title);
    }

    $home = $this->withoutLocalizationMiddleware()->get(route('public.shop.index'))->assertOk();
    $home->assertSee('<title>Akuru Bookstore', false)
        ->assertDontSee('Online Store')
        ->assertDontSee('Bookshop');
    preg_match_all('#<a[^>]+href="[^"]*/shop"[^>]*>\s*Bookstore\s*</a>#', $home->getContent(), $links);
    expect(count($links[0]))->toBeGreaterThanOrEqual(2);
});

it('renames the Vendor Agreement from either earlier name where nobody has edited it, and leaves an edited one alone', function () {
    $this->seed(BookshopPolicyPagesSeeder::class);
    expect(Page::query()->where('slug', 'vendor-agreement')->value('body'))->toContain('Akuru Bookstore')->not->toContain('Online Store');

    $draft = '<p><em>Last updated: 26 September 2026. First draft pending review by Akuru Institute.</em></p>';
    foreach ([
        ['Online Bookshop', 'bookshop', "bookshop's"],
        ['Online Store', 'store', "store's"],
    ] as [$name, $noun, $possessive]) {
        Page::query()->where('slug', 'vendor-agreement')->update([
            'body' => $draft."<h3>1. Selling in the {$noun}</h3><p>The one Akuru {$name}. The {$possessive} header stays.</p>",
            'excerpt' => "The terms on which a shop sells in the Akuru {$name}.",
        ]);
        $this->seed(BookshopPolicyPagesSeeder::class);
        $page = Page::query()->where('slug', 'vendor-agreement')->first();
        expect($page->body)->toBe($draft.'<h3>1. Selling in the bookstore</h3><p>The one Akuru Bookstore. The bookstore\'s header stays.</p>')
            ->and($page->excerpt)->toBe('The terms on which a shop sells in the Akuru Bookstore.');
    }

    Page::query()->where('slug', 'vendor-agreement')->update(['body' => '<p>Signed off. Akuru Online Store terms.</p>']);
    $this->seed(BookshopPolicyPagesSeeder::class);
    expect(Page::query()->where('slug', 'vendor-agreement')->value('body'))->toBe('<p>Signed off. Akuru Online Store terms.</p>');
});

it('leaves neither earlier name anywhere a person can read it', function () {
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
            if (preg_match('/Online Bookshop|Online Store|\bBookshop\b|އޮންލައިން ފިހާރަ|متجر أكورو الإلكتروني|المتجر الإلكتروني/u', $source)) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBeEmpty('Still carrying an earlier name: '.implode(', ', $offenders));
});
