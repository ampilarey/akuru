<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B3 (LIBRARY_PLAN §36, STATUS §5iw): the article body comes from a rich
 * text editor. The editor cannot hold the `<!-- pagebreak -->` comment, so
 * its page break is `<hr data-pagebreak>`; the client converts it back and
 * the server accepts either, so a body from the editor always paginates.
 */
it('paginates a body that still carries the editor page-break node', function () {
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'From the editor',
        'content_type' => 'article',
        'body' => '<p>One.</p><hr data-pagebreak="true" class="akuru-pagebreak"><p>Two.</p><hr class="akuru-pagebreak" data-pagebreak="true"><p>Three.</p>',
    ]);

    $body = (string) LibraryItem::query()->findOrFail($item->id)->body;
    expect(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(3)
        ->and(substr_count($body, '<!-- pagebreak -->'))->toBe(2)
        ->and($body)->not->toContain('<hr');
});

it('keeps everything the toolbar can produce, and a plain rule that is not a page break', function () {
    $writer = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('writer', 'web');
    $profile = \App\Domains\Library\Models\WriterProfile::query()->create([
        'user_id' => $writer->id, 'display_name' => 'Ustadha Aminath', 'slug' => 'ustadha-aminath', 'status' => 'active', 'approved_at' => now(),
    ]);

    $html = '<h2>Sun letters</h2><p>The <strong>lam</strong> is <em>assimilated</em>, <u>always</u>, <s>never</s> <code>heard</code>.</p>'
        .'<ul><li>alif</li><li>ba</li></ul><ol><li>one</li></ol><blockquote><p>A saying.</p></blockquote>'
        .'<p><a href="https://example.org/sun">Read more</a></p><hr><pre><code>x = 1</code></pre>';

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), ['title' => 'Sun letters', 'content_type' => 'article', 'body' => $html])
        ->assertSessionHasNoErrors();

    $item = LibraryItem::query()->where('writer_id', $profile->id)->firstOrFail();
    foreach (['<h2>', '<strong>', '<em>', '<u>', '<s>', '<code>', '<ul>', '<ol>', '<blockquote>', '<a href="https://example.org/sun">', '<hr>', '<pre>'] as $tag) {
        expect($item->body)->toContain($tag);
    }
    // A bare rule is a rule, not a page break: still one page.
    expect($item->page_count)->toBe(1);
});
