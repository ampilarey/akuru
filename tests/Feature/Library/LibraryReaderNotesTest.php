<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryBookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §9.1 "private notes", §10 "Notes & Bookmarks", and §29's rule
 * that "private notes never exposed to writers".
 *
 * **The whole chain existed except a way to type one.**
 * `library_bookmarks.note` is a column, it is on the model's `$fillable`,
 * `ToggleLibraryBookmarkAction` has always taken a `$note` argument, the reader
 * controller has always validated `note` at 500 characters, `ListMyLibraryAction`
 * reads it back, and `my.blade.php` **renders it when present**. The reader's
 * bookmark form posted a page number and nothing else.
 *
 * So My Library had a place to display something no reader could ever write —
 * the "missing place rather than a missing calculation" shape, inverted: here
 * every calculation was in place and the input was missing.
 *
 * The tests worth having are the two that would fail silently: that saving a
 * note does **not** delete the bookmark (the existing action toggles), and that
 * a note is private.
 */
function notedItem()
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Annotated Primer',
        'content_type' => 'book',
        'access_type' => 'free_login',
        'body' => '<p>Page one text.</p><!-- pagebreak --><p>Page two text.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('lets a reader write a note and shows it back on the page', function () {
    $item = notedItem();
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.note', $item->slug), ['page' => 2, 'note' => 'Check this against the tafsir.'])
        ->assertRedirect();

    // The box comes back holding what was written.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('Check this against the tafsir.', false);
});

it('shows the note in My Library, which could already render one', function () {
    $item = notedItem();
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.note', $item->slug), ['page' => 1, 'note' => 'Start here next time.']);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertSee('Start here next time.', false);
});

it('saves a note without removing an existing bookmark', function () {
    // The trap. `ToggleLibraryBookmarkAction` deletes the row when one exists,
    // which is right for a button labelled "Remove bookmark" and wrong for
    // typing: a reader annotating a page they had bookmarked would lose the
    // bookmark. That is why the note has its own verb.
    $item = notedItem();
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.bookmark', $item->slug), ['page' => 1]);

    expect(LibraryBookmark::query()->where('user_id', $reader->id)->count())->toBe(1);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.note', $item->slug), ['page' => 1, 'note' => 'Still bookmarked.']);

    $row = LibraryBookmark::query()->where('user_id', $reader->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->note)->toBe('Still bookmarked.');
});

it('clears the text without un-bookmarking the page', function () {
    // Clearing what you wrote is not the same as un-bookmarking, and the button
    // beside it already does that.
    $item = notedItem();
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.note', $item->slug), ['page' => 1, 'note' => 'Temporary.']);
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->post(route('public.library.note', $item->slug), ['page' => 1, 'note' => '']);

    $row = LibraryBookmark::query()->where('user_id', $reader->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->note)->toBeNull();
});

it('keeps one reader\'s note away from another reader', function () {
    // §29: "Private notes never exposed to writers." The same rule, tested at
    // its weakest point — two readers of the same item.
    $item = notedItem();
    $author = User::factory()->create();
    $stranger = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($author)
        ->post(route('public.library.note', $item->slug), ['page' => 1, 'note' => 'My own thinking.']);

    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertOk()
        ->assertDontSee('My own thinking.');

    $this->withoutLocalizationMiddleware()->actingAs($stranger)
        ->get(route('public.library.my'))
        ->assertOk()
        ->assertDontSee('My own thinking.');
});

it('refuses a note from somebody who cannot read the page', function () {
    // A note is reading. Without this, an account with no access could
    // annotate a paid item it cannot open.
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Paid Primer',
        'content_type' => 'book',
        'access_type' => 'paid',
        'price' => 90,
        'body' => '<p>Page one text.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->post(route('public.library.note', $item->refresh()->slug), ['page' => 1, 'note' => 'Should not stick.'])
        ->assertForbidden();

    expect(LibraryBookmark::query()->count())->toBe(0);
});

it('refuses a guest outright', function () {
    $item = notedItem();

    $this->withoutLocalizationMiddleware()
        ->post(route('public.library.note', $item->slug), ['page' => 1, 'note' => 'Anonymous.'])
        ->assertForbidden();
});
