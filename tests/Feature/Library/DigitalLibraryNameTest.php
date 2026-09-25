<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\ResolveNotificationPreferencesAction;
use App\Domains\Website\Models\Page;
use Database\Seeders\LibraryPolicyPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B0: the Knowledge Library is renamed the Akuru
 * Digital Library in every label, in English, Dhivehi and Arabic. `/library`
 * stays. The staff menu's "Library" group is the school's printed-book
 * circulation — a different library — and keeps its name.
 */
it('heads the shelf with the full name and links it by the short one', function () {
    $shelf = $this->withoutLocalizationMiddleware()->get(route('public.library.index'))->assertOk();

    $shelf->assertSee('<h1 class="text-4xl font-bold text-brandMaroon-900 mb-3">Akuru Digital Library</h1>', false)
        ->assertSee('<title>Akuru Digital Library', false)
        ->assertDontSee('Knowledge Library');

    // The public header (desktop and mobile) and the footer.
    preg_match_all('#<a[^>]+href="[^"]*/library"[^>]*>\s*Digital Library\s*</a>#', $shelf->getContent(), $links);
    expect(count($links[0]))->toBeGreaterThanOrEqual(3);

    $this->withoutLocalizationMiddleware()->get(route('public.gift-cards.index'))
        ->assertOk()
        ->assertSee('in the Akuru Digital Library.', false)
        ->assertDontSee('Knowledge Library');

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.wallet'))
        ->assertOk()
        ->assertSee('Digital Library');
});

it('has its own name in Dhivehi and Arabic', function () {
    expect(__('public.Akuru Digital Library', [], 'dv'))->toBe('އަކުރު ޑިޖިޓަލް ލައިބްރަރީ')
        ->and(__('public.Akuru Digital Library', [], 'ar'))->toBe('مكتبة أكورو الرقمية')
        ->and(__('public.Digital Library', [], 'dv'))->toBe('ޑިޖިޓަލް ލައިބްރަރީ')
        ->and(__('public.Digital Library', [], 'ar'))->toBe('المكتبة الرقمية');

    App::setLocale('ar');
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()
        ->assertSee('مكتبة أكورو الرقمية');
});

it('renames the app-shell link and the notification category, not the circulation group', function () {
    foreach (['en' => 'Digital Library', 'dv' => 'ޑިޖިޓަލް ލައިބްރަރީ', 'ar' => 'المكتبة الرقمية'] as $locale => $name) {
        expect(__('nav.library', [], $locale))->toBe($name);
    }
    expect(__('nav.library_group', [], 'en'))->toBe('Library');

    expect(ResolveNotificationPreferencesAction::CATEGORIES['library'] ?? null)
        ->toBe('Digital Library, writing and wallet');
});

it('seeds the policy pages under the new name and renames only untouched drafts', function () {
    $this->seed(LibraryPolicyPagesSeeder::class);
    expect(Page::query()->where('body', 'like', '%Knowledge Library%')->count())->toBe(0)
        ->and(Page::query()->where('slug', 'publishing-terms')->value('body'))->toContain('Akuru Digital Library');

    // A host seeded before B0: one page still the untouched draft, one the
    // office has edited (its words, the old name included, are kept).
    Page::query()->where('slug', 'copyright-policy')->update([
        'body' => '<p><em>Last updated: 25 September 2026. First draft pending review by Akuru Institute.</em></p><p>Every work in the Knowledge Library is published with consent.</p>',
        'excerpt' => 'How Akuru Institute handles copyright in the Knowledge Library.',
    ]);
    Page::query()->where('slug', 'publishing-terms')->update([
        'body' => '<p>Signed off by the office. The Akuru Knowledge Library terms apply.</p>',
    ]);

    $this->seed(LibraryPolicyPagesSeeder::class);

    $copyright = Page::query()->where('slug', 'copyright-policy')->first();
    expect($copyright->body)->toContain('Every work in the Akuru Digital Library is published')
        ->not->toContain('Knowledge Library')
        ->and($copyright->excerpt)->toBe('How Akuru Institute handles copyright in the Akuru Digital Library.')
        ->and(Page::query()->where('slug', 'publishing-terms')->value('body'))
        ->toBe('<p>Signed off by the office. The Akuru Knowledge Library terms apply.</p>');
});

it('leaves the old name nowhere a person can read it', function () {
    $offenders = [];
    $paths = ['resources/views', 'resources/js', 'resources/lang', 'app', 'database/seeders'];
    foreach ($paths as $path) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($path), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            // The seeder's rename step names the old wording on purpose.
            if (str_ends_with($file->getPathname(), 'LibraryPolicyPagesSeeder.php')) {
                $source = preg_replace('/private function renameInUntouchedDraft.*?\n    }\n/s', '', $source);
                $source = preg_replace('#/\*\*\s*\n\s*\* B0 \(2026-09-25\).*?\*/#s', '', $source);
            }
            if (str_contains($source, 'Knowledge Library')) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBeEmpty();
});
