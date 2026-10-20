<?php

use App\Domains\Library\Actions\RecogniseLibraryItemPagesAction;
use App\Domains\Library\Actions\SyncLibraryItemPagesAction;
use App\Domains\Library\Contracts\PageImageTextReader;
use App\Domains\Library\Contracts\PdfPageTextExtractor;
use App\Domains\Library\Jobs\RecogniseLibraryItemPagesJob;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Domains\Media\Models\MediaFile;
use App\Support\Contracts\PdfRasterizerInterface;
use App\Support\Services\TesseractPageImageTextReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
 * BACKLOG B1 (STATUS §5tg): a scanned Library PDF read by text recognition,
 * behind a switch that is off until the host has Tesseract. Fakes stand in
 * for the PDF, Ghostscript and Tesseract; the queue runs the jobs inline.
 */
uses(RefreshDatabase::class);

/** Binds a PDF whose per-page text is `$texts`, a rasteriser and an OCR engine that read "scan N". */
function fakeScannedPdf(array $texts, bool $engine = true): void
{
    app()->instance(ReadPrivateMediaAction::class, new class extends ReadPrivateMediaAction
    {
        public function __construct() {}

        public function execute(int $mediaId): ?array
        {
            return ['id' => $mediaId, 'contents' => '%PDF-fake', 'mime' => 'application/pdf', 'original_name' => 'scan.pdf'];
        }
    });
    app()->instance(PdfPageTextExtractor::class, new class($texts) implements PdfPageTextExtractor
    {
        public function __construct(private array $texts) {}

        public function pages(string $pdf): array
        {
            return $this->texts;
        }
    });
    app()->instance(PdfRasterizerInterface::class, new class(count($texts)) implements PdfRasterizerInterface
    {
        public function __construct(private int $count) {}

        public function enabled(): bool
        {
            return true;
        }

        public function pages(string $pdf): int
        {
            return $this->count;
        }

        public function toPng(string $pdf, int $page, int $dpi = 96): string
        {
            return 'png:'.$page;
        }
    });
    app()->instance(PageImageTextReader::class, new class($engine) implements PageImageTextReader
    {
        public int $reads = 0;

        public function __construct(private bool $engine) {}

        public function enabled(): bool
        {
            return $this->engine;
        }

        public function text(string $png): string
        {
            $this->reads++;

            return 'scan '.substr($png, 4);
        }
    });
}

/** A private PDF original's record; its bytes come from the faked reader. */
function scanMedia(): int
{
    return (int) MediaFile::query()->create([
        'disk' => 'local', 'path' => 'library/scan-'.Str::random(8).'.pdf', 'mime' => 'application/pdf',
        'original_name' => 'scan.pdf', 'size' => 9, 'visibility' => 'private', 'process_status' => 'processed',
    ])->id;
}

function scannedItem(): LibraryItem
{
    return LibraryItem::query()->create([
        'title' => 'Old Thaana Primer', 'slug' => 'old-thaana-primer', 'content_type' => 'book',
        'access_type' => 'free_public', 'language' => 'dv', 'status' => 'draft', 'pdf_media_file_id' => scanMedia(),
    ]);
}

it('leaves a scan without pages, and starts nothing, while the switch is off', function () {
    Queue::fake();
    config(['library.ocr.enabled' => false]);
    fakeScannedPdf(['', '']);

    $item = scannedItem();
    expect(app(SyncLibraryItemPagesAction::class)->execute($item))->toBe(0);

    Queue::assertNothingPushed();
    expect($item->refresh()->ocr_media_file_id)->toBeNull()
        ->and(SyncLibraryItemPagesAction::recognising($item))->toBeFalse();
});

it('starts nothing where the host has no OCR engine, even with the switch on', function () {
    Queue::fake();
    config(['library.ocr.enabled' => true]);
    fakeScannedPdf(['', ''], engine: false);

    app(SyncLibraryItemPagesAction::class)->execute(scannedItem());

    Queue::assertNothingPushed();
});

it('queues recognition for a scan and says so while it reads', function () {
    Queue::fake();
    config(['library.ocr.enabled' => true]);
    fakeScannedPdf(['', '', '']);

    $item = scannedItem();
    app(SyncLibraryItemPagesAction::class)->execute($item);

    Queue::assertPushed(RecogniseLibraryItemPagesJob::class, fn ($job) => $job->itemId === $item->id && $job->mediaId === $item->pdf_media_file_id && $job->fromPage === 1);
    expect(SyncLibraryItemPagesAction::recognising($item->refresh()))->toBeTrue()
        ->and(__('common.library_pages_reading'))->toContain('text recognition');
});

it('reads a scan a few pages per run, keeping the pages that had text', function () {
    config(['library.ocr.enabled' => true, 'library.ocr.pages_per_run' => 2]);
    fakeScannedPdf(['', 'Typed preface', '', '', '']);

    $item = scannedItem();
    app(SyncLibraryItemPagesAction::class)->execute($item);

    $item->refresh();
    $pages = LibraryItemPage::query()->where('library_item_id', $item->id)->orderBy('page_number')->pluck('content', 'page_number')->all();
    expect($pages)->toHaveCount(5)
        ->and($pages[1])->toBe('<p dir="auto">scan 1</p>')
        ->and($pages[2])->toBe('<p dir="auto">Typed preface</p>')
        ->and($pages[5])->toBe('<p dir="auto">scan 5</p>')
        ->and($item->page_count)->toBe(5)
        ->and($item->pages_recognised_at)->not->toBeNull()
        // Only the four empty pages were pictured and read.
        ->and(app(PageImageTextReader::class)->reads)->toBe(4);
});

it('keeps the recognised pages on a later save, and reads again when the PDF changes', function () {
    config(['library.ocr.enabled' => true]);
    fakeScannedPdf(['', '']);

    $item = scannedItem();
    app(SyncLibraryItemPagesAction::class)->execute($item);
    expect(app(PageImageTextReader::class)->reads)->toBe(2);

    // A title edit re-syncs: the pages stay and nothing is read again.
    $item->refresh()->update(['title' => 'Old Thaana Primer (1962)']);
    expect(app(SyncLibraryItemPagesAction::class)->execute($item))->toBe(2)
        ->and(app(PageImageTextReader::class)->reads)->toBe(2);

    // A new PDF original is a new book to read.
    $newer = scanMedia();
    $item->update(['pdf_media_file_id' => $newer]);
    app(SyncLibraryItemPagesAction::class)->execute($item->refresh());
    expect(app(PageImageTextReader::class)->reads)->toBe(4)
        ->and($item->refresh()->ocr_media_file_id)->toBe($newer);
});

it('stops a run whose PDF was replaced meanwhile', function () {
    config(['library.ocr.enabled' => true]);
    fakeScannedPdf(['', '']);
    $item = scannedItem();
    $started = (int) $item->pdf_media_file_id;
    $item->forceFill(['ocr_media_file_id' => $started, 'pdf_media_file_id' => scanMedia()])->save();

    (new RecogniseLibraryItemPagesJob($item->id, $started))->handle(app(RecogniseLibraryItemPagesAction::class));

    expect(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(0)
        ->and(app(PageImageTextReader::class)->reads)->toBe(0);
});

it('lets the body win over a recognised scan', function () {
    config(['library.ocr.enabled' => true]);
    fakeScannedPdf(['']);
    $item = scannedItem();
    app(SyncLibraryItemPagesAction::class)->execute($item);

    $item->refresh()->update(['body' => '<p>Typed out by the office.</p>']);
    app(SyncLibraryItemPagesAction::class)->execute($item);

    expect(LibraryItemPage::query()->where('library_item_id', $item->id)->pluck('content')->all())->toBe(['<p>Typed out by the office.</p>'])
        ->and($item->refresh()->ocr_media_file_id)->toBeNull();
});

it('keeps no pages when recognition finds no text on any', function () {
    config(['library.ocr.enabled' => true]);
    fakeScannedPdf(['', '']);
    app()->instance(PageImageTextReader::class, new class implements PageImageTextReader
    {
        public function enabled(): bool
        {
            return true;
        }

        public function text(string $png): string
        {
            return '';
        }
    });

    $item = scannedItem();
    app(SyncLibraryItemPagesAction::class)->execute($item);

    expect($item->refresh()->page_count)->toBeNull()
        ->and(LibraryItemPage::query()->where('library_item_id', $item->id)->count())->toBe(0)
        ->and(SyncLibraryItemPagesAction::recognising($item))->toBeFalse();
});

it('binds Tesseract, off where no binary is configured', function () {
    expect(app(PageImageTextReader::class))->toBeInstanceOf(TesseractPageImageTextReader::class)
        ->and((new TesseractPageImageTextReader(null))->enabled())->toBeFalse()
        ->and((new TesseractPageImageTextReader('/no/such/tesseract'))->enabled())->toBeFalse()
        ->and(config('library.ocr.enabled'))->toBeFalse();
});
