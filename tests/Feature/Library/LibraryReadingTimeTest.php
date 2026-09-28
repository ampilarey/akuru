<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListReaderLibraryForFamilyAction;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryReadingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §9.1 "reading time" (STATUS §5ju). `total_reading_seconds`,
 * the progress endpoint's `seconds` and the action's argument had existed
 * since L2 for "the beacon" — and no reader ever sent one. Now the reader
 * page carries it, it reports time only (a late beacon must not move the
 * reader back a page), My Library and a parent's view show the minutes,
 * and a preview carries no beacon because a sample is not reading (§9.4).
 */
function timedItem(array $overrides = [])
{
    $item = app(SaveLibraryItemAction::class)->execute($overrides + [
        'title' => 'Timed Primer',
        'content_type' => 'book',
        'access_type' => 'free_login',
        'body' => '<p>One.</p><!-- pagebreak --><p>Two.</p><!-- pagebreak --><p>Three.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('carries the reading-time beacon for a reader with access, and not on a preview', function () {
    $item = timedItem();
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('data-testid="reading-time"', false)
        ->assertSee('name="time_only" value="1"', false)
        ->assertSee('navigator.sendBeacon', false);

    $sampled = timedItem(['title' => 'Sampled Primer', 'access_type' => 'paid', 'price' => 40, 'preview_enabled' => true, 'preview_pages' => 1]);
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $sampled->slug]))
        ->assertOk()
        ->assertDontSee('data-testid="reading-time"', false);
});

it('banks the seconds a beacon reports without moving the page, and shows the minutes on My Library', function () {
    $item = timedItem();
    $reader = User::factory()->create();

    // Reading page two makes the progress row; the beacon then adds to it.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))->assertOk();
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.progress', $item->slug), ['page' => 2, 'seconds' => 50, 'time_only' => 1])
        ->assertRedirect();
    // A late beacon for page two, arriving after the reader moved on to page
    // one, must not drag `current_page` back with it.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))->assertOk();
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.progress', $item->slug), ['page' => 2, 'seconds' => 40, 'time_only' => 1])
        ->assertRedirect();

    $progress = LibraryReadingProgress::query()->where('user_id', $reader->id)->where('library_item_id', $item->id)->firstOrFail();
    expect((int) $progress->total_reading_seconds)->toBe(90)
        ->and((int) $progress->current_page)->toBe(1)
        ->and($progress->completed_at)->toBeNull();

    // Ninety seconds is two minutes' reading, rounded up, not one and a half.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('data-testid="reading-minutes"', false)
        ->assertSee('2 min read');

    // Without `time_only` the endpoint still does what it always did: moves
    // the page, and the last page completes.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.progress', $item->slug), ['page' => 3, 'seconds' => 30])
        ->assertRedirect();
    $progress->refresh();
    expect((int) $progress->current_page)->toBe(3)
        ->and((int) $progress->total_reading_seconds)->toBe(120)
        ->and($progress->completed_at)->not->toBeNull();

    // Validation still bounds a beacon: an hour is the most one page may claim.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.progress', $item->slug), ['page' => 3, 'seconds' => 3601, 'time_only' => 1])
        ->assertSessionHasErrors('seconds');
});

it('adds nothing for a reader with no progress row, and refuses a guest', function () {
    $item = timedItem();
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.progress', $item->slug), ['page' => 1, 'seconds' => 30, 'time_only' => 1])
        ->assertRedirect();
    expect(LibraryReadingProgress::query()->where('user_id', $reader->id)->exists())->toBeFalse();
});

it('refuses a guest beacon', function () {
    $item = timedItem();
    $this->withoutLocalizationMiddleware()
        ->post(route('public.library.progress', $item->slug), ['page' => 1, 'seconds' => 30, 'time_only' => 1])
        ->assertForbidden();
});

it('tells a parent the minutes read, as progress rather than private words', function () {
    $item = timedItem();
    $child = User::factory()->create();
    LibraryReadingProgress::query()->create([
        'user_id' => $child->id, 'library_item_id' => $item->id, 'current_page' => 2, 'progress_percent' => 67, 'last_read_at' => now(), 'total_reading_seconds' => 150,
    ]);

    $library = app(ListReaderLibraryForFamilyAction::class)->execute($child->id);
    expect($library['continue'][0]['reading_minutes'])->toBe(3);
});
