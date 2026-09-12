<?php

use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Actions\SaveResearchPostAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The authored-HTML surfaces #281 left declared UNSANITISED.
 *
 * Each is closed at its single write path, so both the admin and the
 * lower-privileged routes into the same table are covered at once:
 *
 *   - `SaveLibraryItemAction`  — admin upload AND the writer portal
 *   - `SaveResearchPostAction` — research, articles and news share `posts`
 *   - `SaveEventAction`        — events
 *   - `AnnouncementController` — three locale columns
 *
 * **Library items matter most of these.** They are written by approved
 * *writers*, and any authenticated user may apply to become one — every other
 * authored-HTML path in the application requires a staff role. Their body is
 * also chunked into the protected reader's pages, so one write path feeds two
 * public surfaces.
 */
uses(RefreshDatabase::class);

const XSS_BODY = '<p onclick="alert(1)">Chapter one</p>'
    .'<a href="javascript:alert(1)">note</a>'
    .'<script>alert(1)</script>'
    .'<p>Real <strong>content</strong></p>';

function expectCleaned(?string $stored): void
{
    expect($stored)->not->toBeNull()
        ->and($stored)->toContain('Real <strong>content</strong>')
        ->and($stored)->not->toContain('onclick')
        ->and($stored)->not->toContain('javascript')
        ->and($stored)->not->toContain('script>')
        ->and($stored)->not->toContain('alert(1)');
}

it('sanitises a library item body, whoever wrote it', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'A writer submission',
        'content_type' => 'article',
        'body' => XSS_BODY,
    ]);

    expectCleaned(LibraryItem::query()->findOrFail($item->id)->body);
});

it('carries the sanitised body into the protected reader pages', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'A long submission',
        'content_type' => 'book',
        'body' => XSS_BODY,
    ]);

    // The reader does not read `body`; it reads pages chunked from it. If the
    // chunking ran before the sanitising, the reader would still serve the
    // payload while the item page looked clean.
    $pages = LibraryItemPage::query()->where('library_item_id', $item->id)->pluck('content')->implode('');

    expect($pages)->not->toContain('onclick')
        ->and($pages)->not->toContain('javascript')
        ->and($pages)->not->toContain('alert(1)');
});

it('sanitises around the pagebreak marker rather than eating it', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'A three page book',
        'content_type' => 'book',
        'body' => '<p onclick="alert(1)">One</p><!-- pagebreak --><p>Two</p><!-- pagebreak --><p>Three</p>',
    ]);

    // `<!-- pagebreak -->` is an HTML comment, and the sanitiser strips
    // comments — sanitising the body whole silently collapsed a three-page book
    // into one page. The existing reader test caught it; this pins it.
    expect(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(3)
        ->and((string) LibraryItem::query()->findOrFail($item->id)->body)->not->toContain('onclick');
});

it('sanitises a post body, which research, articles and news all share', function () {
    $author = App\Domains\Identity\Models\User::factory()->create();
    $post = app(SaveResearchPostAction::class)->execute(
        ['title' => 'A research note', 'body' => XSS_BODY],
        null,
        (int) $author->id,
    );

    expectCleaned($post->body);
});

it('sanitises an event description', function () {
    $event = app(SaveEventAction::class)->execute([
        'title' => 'Open day',
        'description' => XSS_BODY,
        'location' => 'Main hall',
        'start_date' => now()->addWeek()->toDateString(),
    ]);

    expectCleaned($event->description);
});

it('keeps the markup an author legitimately used', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Formatted',
        'content_type' => 'article',
        'body' => '<h2>Chapter</h2><blockquote>A quotation</blockquote>'
            .'<a href="https://example.test">source</a><img src="/img/a.png" alt="Figure 1">',
    ]);

    // Sanitising must not make the library unusable for the people writing in
    // it: headings, quotations, links and figures are the point of the thing.
    $body = (string) LibraryItem::query()->findOrFail($item->id)->body;

    expect($body)->toContain('<h2>Chapter</h2>')
        ->and($body)->toContain('<blockquote>')
        ->and($body)->toContain('href="https://example.test"')
        ->and($body)->toContain('alt="Figure 1"');
});
