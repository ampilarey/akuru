<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Fixtures/pdf/make.php';

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §36: a PDF original goes to private storage and the reader
 * serves its pages, one at a time, watermarked — the file itself never.
 * Before 2026-09-25 the upload was stored and never read again, so a
 * PDF-only book had no pages: the shelf said "Read online" and the reader
 * said "no reader pages yet".
 */
function uploadPdfItem(string $bytes, array $overrides = []): LibraryItem
{
    Storage::fake('local');
    $admin = actingPeopleAdmin(['library.manage']);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, $bytes);

    $response = test()->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('admin.library.items.store'), $overrides + [
            'title' => 'Printed Primer',
            'content_type' => 'book',
            'access_type' => 'free_public',
            'pdf' => new UploadedFile($path, 'primer.pdf', 'application/pdf', null, true),
        ]);
    $response->assertRedirect();

    $item = LibraryItem::query()->where('title', $overrides['title'] ?? 'Printed Primer')->firstOrFail();
    app(PublishLibraryItemAction::class)->execute($item->id, $admin->id);

    return $item->refresh();
}

it('makes reader pages from an uploaded PDF and serves them one at a time, watermarked', function () {
    $item = uploadPdfItem((string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'));

    expect((int) $item->page_count)->toBe(3)
        ->and(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(3)
        ->and(session('success'))->toContain('3 reader pages ready');

    $reader = User::factory()->create(['name' => 'Mariyam Reader']);
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 1]))
        ->assertOk()
        ->assertSee('Chapter One')
        ->assertSee('The quick brown fox jumps over the lazy dog.')
        ->assertDontSee('Chapter Two')
        ->assertSee('Mariyam Reader')
        ->assertSee('Page 1 / 3', false);

    // Page two carries the Arabic and Dhivehi in logical order, and a
    // right-to-left paragraph is marked so the browser lays it out that way.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('Chapter Two begins here on page two.')
        ->assertSee('بسم الله الرحمن الرحيم')
        ->assertSee('ދިވެހި ބަސް')
        ->assertSee('<p dir="auto">', false)
        ->assertDontSee('Chapter One');

    // §43.6: no path from the reader to the file.
    $this->withoutLocalizationMiddleware()->actingAs($reader)
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 3]))
        ->assertOk()
        ->assertDontSee('download')
        ->assertDontSee('.pdf');
});

it('keeps the preview clamp on PDF pages', function () {
    $item = uploadPdfItem((string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'), [
        'title' => 'Sampled Primer',
        'access_type' => 'paid',
        'price' => '50',
        'preview_enabled' => 1,
        'preview_pages' => 1,
    ]);

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 3]))
        ->assertOk()
        ->assertSee('Chapter One')
        ->assertDontSee('Page three is short');
});

it('escapes the PDF text: markup inside a document is shown, not rendered', function () {
    $pdf = pdfFromObjects([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => pdfStream('', 'BT /F1 12 Tf 72 700 Td (<script>alert\(1\)</script> and <b>bold</b>) Tj ET'),
    ], 1);
    $item = uploadPdfItem($pdf, ['title' => 'Hostile Primer']);

    $page = LibraryItemPage::query()->where('library_item_id', $item->id)->firstOrFail();
    expect($page->content)->not->toContain('<script>')
        ->and($page->content)->toContain('&lt;script&gt;');
});

it('tells the office when a PDF has no readable text, and the item stays without pages', function () {
    $item = uploadPdfItem(pdfNoText(), ['title' => 'Scanned Primer']);

    expect($item->page_count)->toBeNull()
        ->and(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(0)
        ->and(session('success'))->toContain('no readable text');

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $item->slug]))
        ->assertOk()
        ->assertSee('This item has no reader pages yet.');
});

it('keeps a textless page in its place and says so on that page', function () {
    $item = uploadPdfItem(pdfTextThenBlank(), ['title' => 'Holed Primer']);

    expect((int) $item->page_count)->toBe(2);

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('public.library.read', ['slug' => $item->slug, 'page' => 2]))
        ->assertOk()
        ->assertSee('This page has no text')
        ->assertDontSee('Words on one');
});

it('lets the body win when an item has both a body and a PDF', function () {
    $item = uploadPdfItem((string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'), [
        'title' => 'Edited Primer',
        'body' => '<p>Curated one.</p><!-- pagebreak --><p>Curated two.</p>',
    ]);

    expect((int) $item->page_count)->toBe(2)
        ->and(LibraryItemPage::query()->where('library_item_id', $item->id)->orderBy('page_number')->pluck('content')->all())
        ->toBe(['<p>Curated one.</p>', '<p>Curated two.</p>']);
});

it('backfills pages for items uploaded before PDFs made pages', function () {
    $item = uploadPdfItem((string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'), ['title' => 'Old Upload']);
    // What such an item looks like today: a private PDF and no pages.
    LibraryItemPage::query()->where('library_item_id', $item->id)->delete();
    $item->forceFill(['page_count' => null])->save();

    $this->artisan('library:sync-pages')
        ->expectsOutputToContain('3 pages from pdf')
        ->assertSuccessful();

    expect((int) $item->refresh()->page_count)->toBe(3)
        ->and(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(3);
});

it('is the writer portal too: a writer sees how many pages their PDF made', function () {
    Storage::fake('local');
    $writer = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('writer', 'web');
    \App\Domains\Library\Models\WriterProfile::query()->create([
        'user_id' => $writer->id,
        'display_name' => 'Aminath Writer',
        'status' => 'active',
        'slug' => 'aminath-writer',
    ]);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, (string) file_get_contents(__DIR__.'/../../Fixtures/pdf/three-pages-chromium.pdf'));

    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), [
            'title' => 'My Printed Draft',
            'content_type' => 'book',
            'access_type' => 'paid',
            'pdf' => new UploadedFile($path, 'draft.pdf', 'application/pdf', null, true),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($message) => str_contains($message, '3 reader pages ready'));

    $item = LibraryItem::query()->where('title', 'My Printed Draft')->firstOrFail();
    expect((int) $item->page_count)->toBe(3);

    // Same action, no PDF: the writer is told there is nothing to read yet.
    $this->withoutLocalizationMiddleware()->actingAs($writer)
        ->post(route('write.items.store'), [
            'title' => 'My Empty Draft',
            'content_type' => 'article',
            'access_type' => 'free_login',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'No reader pages yet'));
});
