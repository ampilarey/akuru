<?php

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintUpload;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Support\Contracts\PdfRasterizerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The customer's own artwork (NAME_TAGS_PLAN §3.4, §5.5, §5.6, §11 NT5,
 * STATUS §5rs): uploaded privately onto an upload job, preflighted at the
 * printed size — warned under 300 dpi, refused under 150, a sheet held to
 * A4's shape, colour on black and white and transparency warned — the
 * declaration required and the warnings accepted before the proof, which
 * then goes on to approval and the basket as any job. A PDF only where the
 * host can rasterise one.
 */
uses(RefreshDatabase::class);

function nt5Desk(): PrintLayout
{
    PrintDesk::ensure();
    PrintDesk::product()->update(['status' => 'active']);
    PrintDesk::vendor()->update(['status' => 'active']);
    PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail()->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    printHostCanPrint();
    Storage::fake('local');

    return PrintLayout::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->firstOrFail();
}

/** A PNG of this many pixels: colour or grey, opaque or with a see-through corner. */
function nt5Png(int $w, int $h, bool $colour = false, bool $alpha = false): string
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, $alpha);
    imagealphablending($img, false);
    imagefill($img, 0, 0, $colour ? imagecolorallocate($img, 200, 30, 40) : imagecolorallocate($img, 128, 128, 128));
    if ($alpha) {
        imagefilledrectangle($img, 0, 0, intdiv($w, 2), intdiv($h, 2), imagecolorallocatealpha($img, 0, 0, 0, 127));
    }
    ob_start();
    imagepng($img);
    imagedestroy($img);

    return (string) ob_get_clean();
}

/** Pixels for a box of this many millimetres at this resolution. */
function nt5Px(float $mm, int $dpi): int
{
    return (int) ceil($mm / 25.4 * $dpi);
}

function nt5File(string $bytes, string $name = 'art.png', string $mime = 'image/png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes)->mimeType($mime);
}

function nt5Job(User $customer): PrintJob
{
    printWeb()->actingAs($customer)->post(route('public.shop.print.upload.start'))->assertRedirect();

    return PrintJob::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
}

function nt5Upload(User $customer, PrintJob $job, string $bytes, string $kind = 'tag', string $name = 'art.png', string $mime = 'image/png')
{
    return printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))
        ->post(route('public.shop.print.upload', $job->id), ['file' => nt5File($bytes, $name, $mime), 'upload_kind' => $kind]);
}

function nt5FakeRasterizer(int $pages): void
{
    app()->instance(PdfRasterizerInterface::class, new class($pages) implements PdfRasterizerInterface
    {
        public function __construct(private int $pages) {}

        public function enabled(): bool
        {
            return true;
        }

        public function pages(string $pdf): int
        {
            return $this->pages;
        }

        public function toPng(string $pdf, int $page, int $dpi = 96): string
        {
            return nt5Png(20, 28);
        }
    });
}

it('starts an upload job for a signed-in customer only, and shows it on its own page', function () {
    nt5Desk();
    printWeb()->post(route('public.shop.print.upload.start'))->assertRedirect();
    expect(PrintJob::query()->count())->toBe(0);

    $customer = User::factory()->create();
    $job = nt5Job($customer);
    expect($job->source)->toBe('upload')->and($job->upload_kind)->toBe('tag')->and($job->status)->toBe(PrintJobStatus::Draft)->and($job->isUpload())->toBeTrue();
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintUploadJob')->where('job.source', 'upload')->where('job.upload.has_file', false)->where('job.upload.pdf', false));

    // Nobody else uploads to it.
    nt5Upload(User::factory()->create(), $job, nt5Png(100, 100))->assertNotFound();
});

it('preflights a tag picture at the printed size: sharp passes, soft is warned, too small is refused', function () {
    $layout = nt5Desk();
    $customer = User::factory()->create();
    $job = nt5Job($customer);
    [$w, $h] = [(float) $layout->tag_w_mm, (float) $layout->tag_h_mm];

    nt5Upload($customer, $job, nt5Png(nt5Px($w, 300), nt5Px($h, 300)))->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_uploaded'));
    $job->refresh();
    expect($job->preflight['dpi'])->toBeGreaterThanOrEqual(300)->and($job->preflight['warnings'])->toBe([])->and($job->preflight['refusals'])->toBe([])
        ->and($job->upload_meta['type'])->toBe('png')->and($job->upload_document_id)->not->toBeNull();

    nt5Upload($customer, $job, nt5Png(nt5Px($w, 200), nt5Px($h, 200)))->assertSessionHasNoErrors();
    expect(collect($job->fresh()->preflight['warnings'])->pluck('key')->all())->toBe(['print.upload_dpi_low']);
    // Said to the customer, in words (and in Thaana under /dv: the walk reads it there).
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('job.warnings.0', __('print.upload_dpi_low', ['dpi' => $job->fresh()->preflight['dpi'], 'good' => 300])));
    expect(__('print.upload_dpi_low', ['dpi' => 200, 'good' => 300], 'dv'))->toMatch('/\p{Thaana}/u');

    nt5Upload($customer, $job, nt5Png(nt5Px($w, 100), nt5Px($h, 100)))->assertSessionHasNoErrors();
    $refusal = $job->fresh()->preflight['refusals'][0];
    expect($refusal['key'])->toBe('print.upload_dpi_refused')->and($refusal['vars']['min'])->toBe(150);
    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))
        ->post(route('public.shop.print.proof.make', $job->id), ['declared' => true, 'accept_warnings' => true])
        ->assertSessionHasErrors(['file' => __('print.upload_dpi_refused', $refusal['vars'])]);
    expect($job->fresh()->status)->toBe(PrintJobStatus::Draft);
});

it('holds a whole-sheet upload to A4\'s shape, and warns colour on black and white and see-through parts', function () {
    nt5Desk();
    $customer = User::factory()->create();
    $job = nt5Job($customer);

    nt5Upload($customer, $job, nt5Png(2480, 3508), 'sheet')->assertSessionHasNoErrors();
    expect($job->fresh()->preflight['refusals'])->toBe([])->and($job->fresh()->upload_kind)->toBe('sheet');
    nt5Upload($customer, $job, nt5Png(3000, 3000), 'sheet')->assertSessionHasNoErrors();
    expect(collect($job->fresh()->preflight['refusals'])->pluck('key')->all())->toBe(['print.upload_not_a4']);

    // A colour picture on a black-and-white job, with a transparent corner.
    $job = nt5Job($customer);
    nt5Upload($customer, $job, nt5Png(2480, 3508, colour: true, alpha: true), 'sheet')->assertSessionHasNoErrors();
    expect(collect($job->fresh()->preflight['warnings'])->pluck('key')->all())->toBe(['print.upload_transparent']);
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['colour' => false])->assertOk();
    expect(collect($job->fresh()->preflight['warnings'])->pluck('key')->all())->toBe(['print.upload_transparent', 'print.upload_colour_on_bw']);

    // Switching what the file is re-checks it.
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['upload_kind' => 'tag'])->assertOk()->assertJsonPath('job.upload.kind', 'tag');
});

it('refuses what is not a picture, and a file over the desk\'s size', function () {
    nt5Desk();
    $customer = User::factory()->create();
    $job = nt5Job($customer);
    nt5Upload($customer, $job, 'just some text', name: 'notes.txt', mime: 'text/plain')->assertSessionHasErrors(['file' => __('print.upload_error_type')]);
    config(['print.desk.max_upload_kb' => 256]);
    nt5Upload($customer, $job, str_repeat('x', 300 * 1024), name: 'big.png')->assertSessionHasErrors('file');
    expect($job->fresh()->upload_document_id)->toBeNull();
});

it('needs the declaration and the warnings accepted, then proofs, approves and goes in the basket', function () {
    $layout = nt5Desk();
    $customer = User::factory()->create();
    $job = nt5Job($customer);
    $web = fn () => printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id));

    // No file yet.
    $web()->post(route('public.shop.print.proof.make', $job->id), ['declared' => true])->assertSessionHasErrors(['file' => __('print.upload_error_none')]);

    nt5Upload($customer, $job, nt5Png(nt5Px((float) $layout->tag_w_mm, 200), nt5Px((float) $layout->tag_h_mm, 200)))->assertSessionHasNoErrors();
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['sheets' => 2])->assertOk();

    $web()->post(route('public.shop.print.proof.make', $job->id), ['accept_warnings' => true])->assertSessionHasErrors(['declared' => __('print.upload_error_declaration')]);
    $web()->post(route('public.shop.print.proof.make', $job->id), ['declared' => true])->assertSessionHasErrors(['accept_warnings' => __('print.upload_error_accept_warnings')]);
    $web()->post(route('public.shop.print.proof.make', $job->id), ['declared' => true, 'accept_warnings' => true])->assertSessionHasNoErrors();

    $job->refresh();
    expect($job->status)->toBe(PrintJobStatus::Proofed)->and($job->declared_at)->not->toBeNull()->and($job->warnings_accepted_at)->not->toBeNull()
        ->and($job->proof_document_ids)->toHaveCount(2)->and($job->print_document_id)->not->toBeNull();
    expect(app(ReadGeneratedDocumentAction::class)->execute((int) $job->print_document_id)['contents'])->toStartWith('%PDF');

    $web()->post(route('public.shop.print.approve', $job->id))->assertSessionHasNoErrors();
    printWeb()->actingAs($customer)->post(route('public.shop.print.cart', $job->id))->assertRedirect(route('public.shop.cart'));
    expect(CartItem::query()->where('print_job_id', $job->id)->value('quantity'))->toBe(2);

    // A copy needs the declaration again.
    printWeb()->actingAs($customer)->post(route('public.shop.print.copy', $job->id))->assertRedirect();
    $copy = PrintJob::query()->latest('id')->firstOrFail();
    expect($copy->isUpload())->toBeTrue()->and($copy->declared_at)->toBeNull()->and($copy->upload_document_id)->toBe($job->upload_document_id);
});

it('takes a PDF only where the host can rasterise it: whole sheets, one per page, the PDF itself the print file', function () {
    nt5Desk();
    $customer = User::factory()->create();
    $job = nt5Job($customer);
    $pdf = "%PDF-1.4\n% a customer's sheets\n";

    nt5Upload($customer, $job, $pdf, name: 'sheets.pdf', mime: 'application/pdf')->assertSessionHasErrors(['file' => __('print.upload_error_type')]);

    nt5FakeRasterizer(3);
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertInertia(fn (Assert $page) => $page->where('job.upload.pdf', true));
    nt5Upload($customer, $job, $pdf, name: 'sheets.pdf', mime: 'application/pdf')->assertSessionHasNoErrors();
    $job->refresh();
    expect($job->upload_meta['type'])->toBe('pdf')->and($job->upload_meta['pages'])->toBe(3)->and($job->upload_kind)->toBe('sheet')
        ->and((int) $job->sheets)->toBe(3)->and($job->preflight['refusals'])->toBe([]);

    // Pages and sheets must agree; a PDF is never one tag.
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['sheets' => 4])->assertOk();
    expect($job->fresh()->preflight['refusals'][0])->toBe(['key' => 'print.upload_pdf_pages', 'vars' => ['pages' => 3, 'sheets' => 4]]);
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['sheets' => 3, 'upload_kind' => 'tag'])->assertOk();
    expect($job->fresh()->preflight['refusals'][0]['key'])->toBe('print.upload_pdf_sheet_only');
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['upload_kind' => 'sheet'])->assertOk();

    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))->post(route('public.shop.print.proof.make', $job->id), ['declared' => true])->assertSessionHasNoErrors();
    $job->refresh();
    expect($job->proof_document_ids)->toHaveCount(3)
        ->and(app(ReadGeneratedDocumentAction::class)->execute((int) $job->print_document_id)['contents'])->toBe($pdf);
});

it('works out the resolution of a picture fitted inside the box', function () {
    $layout = new PrintLayout(['tag_w_mm' => 95, 'tag_h_mm' => 60]);
    // 1122 × 709 px fills 95 × 60 mm at 300 dpi; a picture twice as wide fits by its width, at twice the resolution.
    expect(PrintUpload::preflight(['type' => 'png', 'w' => 1122, 'h' => 709, 'alpha' => false, 'colour' => false], 'tag', $layout, true, 1)['dpi'])->toBe(300)
        ->and(PrintUpload::preflight(['type' => 'png', 'w' => 2244, 'h' => 709, 'alpha' => false, 'colour' => false], 'tag', $layout, true, 1)['dpi'])->toBe(599);
    expect(PrintUpload::inspect(nt5Png(40, 30, colour: true))['colour'])->toBeTrue()
        ->and(PrintUpload::inspect(nt5Png(40, 30))['colour'])->toBeFalse()
        ->and(PrintUpload::inspect(nt5Png(40, 30, alpha: true))['alpha'])->toBeTrue()
        ->and(PrintUpload::inspect('nope'))->toBeNull();
});
