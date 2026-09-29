<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * RESEARCH_ARTICLES_PLAN R5 (STATUS §5kt): the site search finds what is in
 * the Digital Library — books, articles and research — alongside courses,
 * news and events, and only what is published; the home page's library row
 * says what each card is; the shelf's front lists its authors.
 */
function searchFor(string $q, string $locale = 'en'): string
{
    app()->setLocale($locale);

    return test()->withoutLocalizationMiddleware()->get(route('public.search', ['q' => $q]))->assertOk()->getContent();
}

it('finds published books, articles and research by title, with their type, authors and price', function () {
    $research = LibraryItem::query()->create(['title' => 'Coral Reef Findings', 'slug' => 'coral-reef-findings', 'content_type' => 'research', 'access_type' => 'free_public', 'status' => 'published', 'published_at' => now()]);
    $research->authors()->create(['name' => 'Dr Aishath Coral', 'sort_order' => 0]);
    LibraryItem::query()->create(['title' => 'Coral Tales', 'slug' => 'coral-tales', 'content_type' => 'book', 'access_type' => 'paid', 'price' => 45, 'status' => 'published', 'published_at' => now()]);
    LibraryItem::query()->create(['title' => 'Coral Draft', 'slug' => 'coral-draft', 'content_type' => 'article', 'access_type' => 'free_public', 'status' => 'draft']);

    $html = searchFor('coral');

    preg_match('#data-testid="search-library"(.*?)</section>#s', $html, $group);
    expect($group)->not->toBeEmpty()
        ->and(substr_count($group[1], 'data-testid="search-library-item"'))->toBe(2)
        ->and($group[1])->toContain('href="'.route('public.library.show', 'coral-reef-findings').'"')
        ->and($group[1])->toContain('Research')
        ->and($group[1])->toContain('Dr Aishath Coral')
        ->and($group[1])->toContain('MVR 45.00')
        ->and($group[1])->toContain('Free')
        // A draft is never found.
        ->and($html)->not->toContain('Coral Draft')
        ->and($html)->toContain('2 results for “coral”');
});

it('says so when nothing is found, and points at the courses and the library', function () {
    $html = searchFor('zzqqxx');

    expect($html)->toContain('Nothing found for “zzqqxx”')
        ->and($html)->toContain('href="'.route('public.library.index').'"')
        ->and($html)->not->toContain('data-testid="search-library"');
});

it('speaks Dhivehi and Arabic on the search page', function () {
    LibraryItem::query()->create(['title' => 'Coral Tales', 'slug' => 'coral-tales', 'content_type' => 'book', 'access_type' => 'free_public', 'status' => 'published', 'published_at' => now()]);

    expect(searchFor('coral', 'dv'))->toContain(__('site.type_book', [], 'dv'))->toContain(__('site.digital_library', [], 'dv'));
    expect(searchFor('coral', 'ar'))->toContain('كتاب')->toContain('نتيجة واحدة');
});

it('labels each card in the home page\'s library row as a book, article or research', function () {
    Cache::flush();
    LibraryItem::query()->create(['title' => 'Home Research', 'slug' => 'home-research', 'content_type' => 'research', 'access_type' => 'free_public', 'status' => 'published', 'published_at' => now()]);

    $html = test()->withoutLocalizationMiddleware()->get(route('public.home'))->assertOk()->getContent();

    expect($html)->toMatch('#data-testid="home-book-type">Research</span>#');
});

it('lists the writers on the shelf\'s front, where the Authors link lands', function () {
    $writer = WriterProfile::query()->create(['user_id' => User::factory()->create()->id, 'display_name' => 'Ustadha Shelf', 'slug' => 'ustadha-shelf', 'status' => 'active']);
    WriterProfile::query()->create(['user_id' => User::factory()->create()->id, 'display_name' => 'Nothing Yet', 'slug' => 'nothing-yet', 'status' => 'active']);
    LibraryItem::query()->create(['title' => 'Her Book', 'slug' => 'her-book', 'content_type' => 'book', 'access_type' => 'free_public', 'status' => 'published', 'published_at' => now(), 'writer_id' => $writer->id]);

    $html = test()->withoutLocalizationMiddleware()->get(route('public.library.index'))->assertOk()->getContent();

    preg_match('#id="authors"(.*?)data-testid="library-policies"#s', $html, $strip);
    expect($strip)->not->toBeEmpty()
        ->and($strip[1])->toContain('href="'.route('public.library.author', 'ustadha-shelf').'"')
        ->and($strip[1])->toContain('1 published')
        // A writer with nothing published has no place on it yet.
        ->and($strip[1])->not->toContain('Nothing Yet');

    // A filtered shelf is an answer to a question; the strip is for the front.
    expect(test()->withoutLocalizationMiddleware()->get(route('public.library.index', ['content_type' => 'book']))->getContent())
        ->not->toContain('data-testid="library-authors"');
});
