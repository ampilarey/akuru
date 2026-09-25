<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryReadingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §9.1: font size, light/sepia/dark, text direction, full
 * screen, and "mark as completed". The first four are the reader's own
 * browser's business (a toolbar the page carries); the last is a record.
 */
function polishedItem()
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Long Primer',
        'content_type' => 'book',
        'access_type' => 'free_login',
        'body' => '<p>One.</p><!-- pagebreak --><p>Two.</p><!-- pagebreak --><p>Three.</p><!-- pagebreak --><p>Four.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('carries the reading tools and lets a reader mark an item completed before the last page', function () {
    $item = polishedItem();
    $reader = User::factory()->create();

    $page = $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('data-testid="reader-tools"', false)
        ->assertSee('data-reader="font-up"', false)
        ->assertSee('data-value="sepia"', false)
        ->assertSee('data-reader="dir"', false)
        ->assertSee('data-reader="fullscreen"', false)
        ->assertSee('data-testid="mark-completed"', false)
        ->assertDontSee('data-testid="completed"', false);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.progress', $item->slug), ['page' => 4])
        ->assertRedirect();

    $progress = LibraryReadingProgress::query()->where('user_id', $reader->id)->where('library_item_id', $item->id)->firstOrFail();
    expect($progress->completed_at)->not->toBeNull()
        ->and((int) $progress->progress_percent)->toBe(100);

    // Back on any page: the finished note shows and the button is gone.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertOk()
        ->assertSee('data-testid="completed"', false)
        ->assertSee('You have finished this item.')
        ->assertDontSee('data-testid="mark-completed"', false);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('Completed');
});

it('offers no completion button on a preview, which is not reading', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Sampled Book',
        'content_type' => 'book',
        'access_type' => 'paid',
        'price' => 40,
        'preview_enabled' => true,
        'preview_pages' => 1,
        'body' => '<p>One.</p><!-- pagebreak --><p>Two.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $item->slug]))
        ->assertOk()
        ->assertSee('data-testid="reader-tools"', false)
        ->assertDontSee('data-testid="mark-completed"', false);
});
