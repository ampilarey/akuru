<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Actions\SearchLibraryItemPagesAction;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B7 (LIBRARY_PLAN §9.1, STATUS §5iq): search inside an item, over the
 * pages the reader may open and no further.
 */
function searchableItem(string $access = 'free_login', array $extra = []): LibraryItem
{
    $admin = User::factory()->create();
    $item = app(SaveLibraryItemAction::class)->execute(array_merge([
        'title' => 'Sun and Moon Letters',
        'content_type' => 'book',
        'access_type' => $access,
        'price' => $access === 'paid' ? 50 : null,
        'created_by' => $admin->id,
        'body' => '<p>The sun letters assimilate the lam.</p><!-- pagebreak -->'
            .'<p>The <strong>moon</strong> letters keep it, &amp; the lam is heard.</p><!-- pagebreak -->'
            .'<p>Shadda marks the doubled consonant.</p><!-- pagebreak -->'
            .'<p data-note="moon">Nothing here says it.</p>',
    ], $extra));
    app(PublishLibraryItemAction::class)->execute($item->id, $admin->id);

    return $item->refresh();
}

it('finds the pages that contain the words, with a plain-text snippet, and links to each', function () {
    $item = searchableItem();
    $reader = User::factory()->create();

    $result = app(SearchLibraryItemPagesAction::class)->execute($item, $reader->id, 'moon');
    expect($result['hits'])->toHaveCount(1)
        ->and($result['hits'][0]['page'])->toBe(2)
        ->and($result['hits'][0]['snippet'])->toContain('moon letters keep it, & the lam')
        ->and($result['hits'][0]['snippet'])->not->toContain('<strong>')
        ->and($result['readable_pages'])->toBe(4);

    // Page 4 mentions "moon" only inside an attribute: not a match a reader can see.
    expect(array_column($result['hits'], 'page'))->not->toContain(4);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1, 'q' => 'lam']))
        ->assertOk()
        ->assertSee('data-testid="reader-search"', false)
        ->assertSee('2 pages match &quot;lam&quot;.', false)
        // Blade escapes the `&` between the query keys.
        ->assertSee(str_replace('&', '&amp;', route('public.library.read', ['slug' => $item->slug, 'page' => 2, 'q' => 'lam'])), false)
        // The reader stays on page 1: searching lists, it does not turn the page.
        ->assertSee('assimilate the lam', false)
        ->assertDontSee('Shadda marks', false)
        ->assertSee('data-testid="reader-search-hits"', false);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1, 'q' => 'zebra']))
        ->assertOk()
        ->assertSee('No pages match &quot;zebra&quot;.', false);

    // Too short a term searches nothing.
    expect(app(SearchLibraryItemPagesAction::class)->execute($item, $reader->id, 'm')['hits'])->toBe([]);
});

it('searches only the pages a previewer may open, and nothing for somebody with no access', function () {
    $item = searchableItem('paid', ['preview_enabled' => true, 'preview_pages' => 2]);
    $previewer = User::factory()->create();

    $result = app(SearchLibraryItemPagesAction::class)->execute($item, $previewer->id, 'Shadda');
    expect($result['hits'])->toBe([])
        ->and($result['readable_pages'])->toBe(2);
    expect(array_column(app(SearchLibraryItemPagesAction::class)->execute($item, $previewer->id, 'letters')['hits'], 'page'))->toBe([1, 2]);

    // The reader page carries the same answer.
    $this->withoutLocalizationMiddleware()->actingAs($previewer)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1, 'q' => 'Shadda']))
        ->assertOk()
        ->assertSee('No pages match &quot;Shadda&quot;.', false);

    // No preview, no access: the search finds nothing, and the reader itself sends them away.
    $locked = searchableItem('paid');
    $stranger = User::factory()->create();
    expect(app(SearchLibraryItemPagesAction::class)->execute($locked, $stranger->id, 'letters'))->toMatchArray(['hits' => [], 'readable_pages' => 0]);
    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->get(route('public.library.read', ['slug' => $locked->slug, 'q' => 'letters']))
        ->assertRedirect(route('public.library.show', $locked->slug));
});
