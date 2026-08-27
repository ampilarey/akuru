<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryBookmark;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Library\Models\LibraryReadingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedReadableItem(string $accessType = 'free_public')
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Paged Primer',
        'content_type' => 'book',
        'access_type' => $accessType,
        'body' => '<p>Page one text.</p><!-- pagebreak --><p>Page two text.</p><!-- pagebreak --><p>Page three text.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('splits the body into pages and reads one page at a time with watermark and progress', function () {
    $item = seedReadableItem();
    expect((int) $item->page_count)->toBe(3)
        ->and(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(3);

    $reader = User::factory()->create(['name' => 'Aishath Reader']);
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('Page two text', false)
        ->assertDontSee('Page one text')
        ->assertDontSee('Page three text')
        ->assertSee('Aishath Reader');

    $progress = LibraryReadingProgress::query()
        ->where('user_id', $reader->id)
        ->where('library_item_id', $item->id)
        ->firstOrFail();
    expect((int) $progress->current_page)->toBe(2)
        ->and((int) $progress->progress_percent)->toBe(67)
        ->and($progress->completed_at)->toBeNull();

    // Reaching the last page completes; re-reading keeps the completion stamp.
    $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.read', ['slug' => $item->slug, 'page' => 3]))->assertOk();
    $progress->refresh();
    $completedAt = $progress->completed_at;
    expect($completedAt)->not->toBeNull();
    $this->withoutLocalizationMiddleware()->actingAs($reader)->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))->assertOk();
    expect($progress->refresh()->completed_at?->toDateTimeString())->toBe($completedAt->toDateTimeString());

    // My library continues from the current page.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('Paged Primer')
        ->assertSee('Completed');
});

it('gates the reader per access type', function () {
    $free = seedReadableItem();
    $this->withoutLocalizationMiddleware()->get(route('public.library.read', ['slug' => $free->slug]))
        ->assertOk()
        ->assertSee('Page one text', false)
        ->assertSee('Akuru Institute'); // guest watermark

    $loginOnly = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Members Book',
        'content_type' => 'book',
        'access_type' => 'free_login',
        'body' => '<p>Members-only pages.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($loginOnly->id, User::factory()->create()->id);
    $this->withoutLocalizationMiddleware()->get(route('public.library.read', ['slug' => $loginOnly->slug]))->assertRedirect(route('login'));
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $loginOnly->slug]))
        ->assertOk()
        ->assertSee('Members-only pages', false);

    $paid = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Paid Book',
        'content_type' => 'book',
        'access_type' => 'paid',
        'body' => '<p>Paid pages.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($paid->id, User::factory()->create()->id);
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $paid->slug]))
        ->assertRedirect(route('public.library.show', $paid->slug));
});

it('toggles bookmarks, lists them privately, and re-syncs pages on body edits', function () {
    $item = seedReadableItem();
    $reader = User::factory()->create();

    // Guests have no my-library. (First request — actingAs persists for the
    // rest of the test case once used, so the guest check must come first.)
    $this->withoutLocalizationMiddleware()->get(route('public.library.my'))->assertForbidden();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.bookmark', $item->slug), ['page' => 2, 'note' => 'Great section'])
        ->assertRedirect();
    expect(LibraryBookmark::query()->count())->toBe(1);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('Great section');

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.bookmark', $item->slug), ['page' => 2])
        ->assertRedirect();
    expect(LibraryBookmark::query()->count())->toBe(0);

    // Editing the body re-syncs the page set.
    app(SaveLibraryItemAction::class)->execute([
        'title' => 'Paged Primer',
        'content_type' => 'book',
        'access_type' => 'free_public',
        'body' => '<p>Only page now.</p>',
    ], $item);
    expect(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(1)
        ->and((int) $item->refresh()->page_count)->toBe(1);
});
