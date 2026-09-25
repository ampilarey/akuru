<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Actions\SaveReadingProgressAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Website\Models\Page;
use Database\Seeders\LibraryPolicyPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §8.1–§8.3: the shelf can be narrowed (free/paid, language,
 * price) and ordered (newest, most read, most purchased, price, title);
 * the office's featured picks and a reader's half-read books sit on the
 * front of it; and the plan's required pages exist and are linked from
 * where a reader would look.
 */
function shelfItem(string $title, array $overrides = []): LibraryItem
{
    $item = app(SaveLibraryItemAction::class)->execute($overrides + [
        'title' => $title,
        'content_type' => 'book',
        'access_type' => 'free_public',
        'language' => 'en',
        'body' => '<p>One.</p><!-- pagebreak --><p>Two.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

function shelfOrder($response): array
{
    preg_match_all('/data-item="([^"]+)"/', $response->getContent(), $m);

    return $m[1];
}

it('narrows the shelf by access, language and price, and orders it six ways', function () {
    $free = shelfItem('Free English');
    $paidCheap = shelfItem('Cheap Dhivehi', ['access_type' => 'paid', 'price' => 30, 'language' => 'dv']);
    $paidDear = shelfItem('Dear Arabic', ['access_type' => 'paid', 'price' => 300, 'language' => 'ar']);

    // Two readers opened the cheap one; one bought the dear one.
    foreach (User::factory()->count(2)->create() as $reader) {
        app(SaveReadingProgressAction::class)->execute($reader->id, $paidCheap->id, 1, 2);
    }
    LibraryPurchase::query()->create(['user_id' => User::factory()->create()->id, 'library_item_id' => $paidDear->id, 'amount' => 300, 'currency' => 'MVR', 'status' => 'paid', 'purchased_at' => now()]);

    $get = fn (array $query) => test()->withoutLocalizationMiddleware()->get(route('public.library.index', $query))->assertOk();

    expect(shelfOrder($get(['access' => 'free'])))->toBe([$free->slug])
        ->and(shelfOrder($get(['access' => 'paid', 'sort' => 'title'])))->toBe([$paidCheap->slug, $paidDear->slug])
        ->and(shelfOrder($get(['language' => 'dv'])))->toBe([$paidCheap->slug])
        ->and(shelfOrder($get(['price_min' => 100])))->toBe([$paidDear->slug])
        ->and(shelfOrder($get(['price_max' => 100, 'access' => 'paid'])))->toBe([$paidCheap->slug])
        ->and(shelfOrder($get(['sort' => 'most_read']))[0])->toBe($paidCheap->slug)
        ->and(shelfOrder($get(['sort' => 'most_purchased']))[0])->toBe($paidDear->slug)
        ->and(shelfOrder($get(['sort' => 'price_desc']))[0])->toBe($paidDear->slug)
        ->and(shelfOrder($get(['sort' => 'price_asc']))[0])->toBe($free->slug)
        ->and(shelfOrder($get(['sort' => 'title'])))->toBe([$paidCheap->slug, $paidDear->slug, $free->slug])
        // An unknown sort is newest, not an error.
        ->and($get(['sort' => 'nonsense'])->getContent())->toContain('data-item=');

    // The paid card says its price; the free one says free.
    $get([])->assertSee('MVR 300.00')->assertSee('MVR 30.00');
});

it('puts the office\'s featured picks and the reader\'s half-read books on the front of the shelf only', function () {
    $plain = shelfItem('Plain Book');
    $starred = shelfItem('Starred Book');
    $admin = actingPeopleAdmin(['library.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.feature', $starred->id), ['featured' => 1])
        ->assertRedirect();
    expect($starred->refresh()->featured)->toBeTrue()->and($starred->featured_at)->not->toBeNull();

    $reader = User::factory()->create();
    app(SaveReadingProgressAction::class)->execute($reader->id, $plain->id, 1, 2);

    $front = $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.index'))->assertOk();
    $front->assertSee('data-testid="featured"', false)
        ->assertSee('data-testid="continue-reading"', false)
        ->assertSee('Starred Book')
        ->assertSee('Plain Book');
    // The featured strip names only the starred one; the shelf marks it.
    expect(substr_count($front->getContent(), 'Starred Book'))->toBeGreaterThanOrEqual(2)
        ->and($front->getContent())->toContain('★');

    // A filtered shelf is an answer, not a shop window.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.index', ['q' => 'Plain']))
        ->assertOk()
        ->assertDontSee('data-testid="featured"', false)
        ->assertDontSee('data-testid="continue-reading"', false);

    // Unfeature.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.library.items.feature', $starred->id), ['featured' => 0]);
    expect($starred->refresh()->featured)->toBeFalse();
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertDontSee('data-testid="featured"', false);

    // The admin page carries the toggle's state.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.library.index'))
        ->assertInertia(fn ($page) => $page->has('items'));
});

it('seeds the plan\'s required pages, idempotently, and links them where a reader looks', function () {
    $this->seed(LibraryPolicyPagesSeeder::class);
    $slugs = ['publishing-terms', 'writer-agreement', 'reader-terms', 'gift-card-terms', 'wallet-terms', 'copyright-policy', 'promotion-policy'];
    foreach ($slugs as $slug) {
        $this->withoutLocalizationMiddleware()->get(route('public.page.show', $slug))->assertOk();
    }

    // An office edit survives a reseed.
    Page::query()->where('slug', 'reader-terms')->update(['body' => '<p>Edited by the office.</p>']);
    $this->seed(LibraryPolicyPagesSeeder::class);
    expect(Page::query()->where('slug', 'reader-terms')->value('body'))->toBe('<p>Edited by the office.</p>')
        ->and(Page::query()->whereIn('slug', $slugs)->count())->toBe(7);

    $item = shelfItem('Linked Book');
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertSee(route('public.page.show', 'reader-terms', false), false)
        ->assertSee(route('public.page.show', 'copyright-policy', false), false);
    $this->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))
        ->assertSee(route('public.page.show', 'copyright-policy', false), false);
    $this->withoutLocalizationMiddleware()->get(route('public.gift-cards.index'))
        ->assertSee(route('public.page.show', 'gift-card-terms', false), false);
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get(route('public.wallet'))
        ->assertSee(route('public.page.show', 'wallet-terms', false), false);
});
