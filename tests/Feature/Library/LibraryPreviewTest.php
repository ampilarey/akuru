<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryReadingProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §9.4 "Preview system: selected free preview pages; admin sets
 * preview percentage" — and §37, which lists "free preview" in the MVP's
 * public surface.
 *
 * `preview_enabled` and `preview_pages` shipped with the L1 foundation
 * migration in August 2026 and **nothing had ever read or written them.** Not
 * the access decision, not the reader, not the admin form. An administrator
 * could not turn a preview on, and if the column had somehow been set, no
 * reader would have seen one.
 *
 * The thing worth testing hardest is not that a preview appears. It is that
 * **it stops.** The page number arrives from the query string, so the cap has
 * to hold on the server against a reader who simply types a bigger number —
 * which is the whole reason the clamp lives in `PresentLibraryReaderAction`
 * and not in the view or the controller.
 */
function seedPreviewItem(bool $previewEnabled = true, ?int $previewPages = 2)
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Paid Primer',
        'content_type' => 'book',
        'access_type' => 'paid',
        'price' => 120,
        'preview_enabled' => $previewEnabled,
        'preview_pages' => $previewPages,
        'body' => '<p>Page one text.</p><!-- pagebreak --><p>Page two text.</p>'
            .'<!-- pagebreak --><p>Page three text.</p><!-- pagebreak --><p>Page four text.</p>',
    ]);
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('stores the preview allowance an admin sets', function () {
    // It was not merely unread — nothing wrote it either, so the first thing
    // to prove is that the control now reaches the column at all.
    $item = seedPreviewItem(true, 2);

    expect((bool) $item->preview_enabled)->toBeTrue()
        ->and((int) $item->preview_pages)->toBe(2)
        ->and((int) $item->page_count)->toBe(4);
});

it('lets a reader who has not bought it read the preview pages', function () {
    $item = seedPreviewItem(true, 2);
    $reader = User::factory()->create(['name' => 'Aishath Reader']);

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertOk()
        ->assertSee('Page one text', false)
        // §9.2 still applies to a sample: it carries the reader's own watermark.
        ->assertSee('Aishath Reader');
});

it('stops the preview at the allowance, however big a page number is asked for', function () {
    // The security boundary. A four-page book with a two-page preview must
    // never serve page three, and asking for page 400 must not be treated as
    // "the last page" of the item.
    $item = seedPreviewItem(true, 2);
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 3]))
        ->assertOk()
        ->assertSee('Page two text', false)
        ->assertDontSee('Page three text')
        ->assertDontSee('Page four text');

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 400]))
        ->assertOk()
        ->assertDontSee('Page three text')
        ->assertDontSee('Page four text');
});

it('says it is a preview, and how long it runs', function () {
    // A reader who does not know this is a sample reads to the cap and
    // concludes the book is broken.
    $item = seedPreviewItem(true, 2);

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertOk()
        ->assertSee('Free preview')
        ->assertSee('Get the full item');
});

it('records no reading progress for a sample', function () {
    // Progress is the record of reading something you have. Writing it would
    // put an unbought book into "continue reading" and count its pages toward
    // a completion the reader cannot reach.
    $item = seedPreviewItem(true, 2);
    $reader = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertOk();

    expect(LibraryReadingProgress::query()->where('user_id', $reader->id)->count())->toBe(0);
});

it('turns nothing on when the preview is off, or set to nothing', function () {
    // Fails closed, which is the property that matters: any path that forgets
    // the allowance shows no pages rather than the whole item.
    foreach ([[false, 2], [true, null], [true, 0]] as [$enabled, $pages]) {
        $item = seedPreviewItem($enabled, $pages);

        $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
            ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
            ->assertRedirect(route('public.library.show', $item->slug));
    }
});

it('still refuses a guest, who has no name to put in the watermark', function () {
    // §9.2 requires a per-reader watermark and a logged reading event on every
    // delivered page. An anonymous preview would be the one page in the system
    // handed out with nobody's name on it, so preview needs a signed-in reader
    // and a guest is sent to log in exactly as before.
    $item = seedPreviewItem(true, 2);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertRedirect(route('login'));
});
