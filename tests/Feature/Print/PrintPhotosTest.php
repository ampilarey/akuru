<?php

use App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Bookshop\Support\PrintUpload;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP5 (STATUS §5tm): photo prints — the customer's own
 * picture, a size, fill or fit, judged at photo resolution (warned under
 * 150 dpi, refused under 72), a PNG or a JPEG only.
 */
uses(RefreshDatabase::class);

function pp5Desk(): User
{
    PrintDesk::ensure();
    PrintPaperOption::query()->where('kind', 'glossy')->where('sheet_size', 'a4')->firstOrFail()->update(['price_colour' => 6.00, 'price_bw' => 3.00, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    PrintProducts::product('photos')->update(['status' => 'active']);
    printHostCanPrint();
    Storage::fake('local');

    return User::factory()->create();
}

function pp5Png(int $w, int $h): UploadedFile
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 200));
    ob_start();
    imagepng($img);
    imagedestroy($img);

    return UploadedFile::fake()->createWithContent('photo.png', (string) ob_get_clean())->mimeType('image/png');
}

it('ships five photo sizes that fit their sheets, and an A3 photo paper off until priced', function () {
    $sizes = PrintLayout::query()->where('print_kind', 'photos')->orderBy('sort_order')->get();
    expect($sizes->map(fn (PrintLayout $l) => $l->tagsPerSheet().' '.$l->sheet_size)->all())->toBe(['2 a4', '4 a3', '1 a4', '1 a4', '1 a3'])
        ->and($sizes->every(fn (PrintLayout $l) => $l->fitsSheet() && $l->shape === 'rect' && (float) $l->bleed_mm === 0.0))->toBeTrue()
        ->and(PrintPaperOption::query()->where('name', 'Glossy photo paper A3')->value('is_active'))->toBeFalse();
});

it('starts a photo print on the photo sizes, filling the print, and offers the upload alone at its door', function () {
    $customer = pp5Desk();

    printWeb()->actingAs($customer)->get(route('public.shop.print.kind', 'photos'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('kind', 'photos')->has('templates', 0));
    printWeb()->actingAs($customer)->post(route('public.shop.print.upload.start'), ['kind' => 'photos'])->assertRedirect();
    $job = \App\Domains\Bookshop\Models\PrintJob::query()->latest('id')->firstOrFail();

    expect($job->printKind())->toBe('photos')->and($job->layout->print_kind)->toBe('photos')
        ->and(PrintUpload::fitOf((array) $job->design))->toBe('cover');
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintUploadJob')->where('job.fit', 'cover')->where('door', 'photos')->has('layouts', 5));
});

it('judges a photo at photo resolution: warned under 150 dpi, refused under 72, and by the side that fills the print', function () {
    $landscape = PrintLayout::query()->where('name', '4 × 6 in landscape — 2 per A4')->firstOrFail();
    $tagLayout = PrintLayout::query()->where('print_kind', 'name_tags')->orderBy('sort_order')->firstOrFail();
    $png = fn (int $w, int $h) => ['type' => 'png', 'w' => $w, 'h' => $h, 'alpha' => false, 'colour' => true];
    $keys = fn (array $p) => [array_column($p['warnings'], 'key'), array_column($p['refusals'], 'key')];

    expect(PrintUpload::preflight($png(1800, 1200), 'tag', $landscape, true, 1, 'cover')['dpi'])->toBe(300)
        ->and($keys(PrintUpload::preflight($png(900, 600), 'tag', $landscape, true, 1, 'cover')))->toBe([[], []])
        ->and($keys(PrintUpload::preflight($png(600, 400), 'tag', $landscape, true, 1, 'cover')))->toBe([['print.upload_dpi_low'], []])
        ->and($keys(PrintUpload::preflight($png(400, 267), 'tag', $landscape, true, 1, 'cover')))->toBe([[], ['print.upload_dpi_refused']])
        // A name-tag upload keeps its own, stricter line.
        ->and($keys(PrintUpload::preflight($png(900, 600), 'tag', $tagLayout, true, 1)))->toBe([['print.upload_dpi_low'], []]);

    // An upright photo in a landscape print: fitted whole, its height sets 450 dpi; filling the width, 200.
    expect(PrintUpload::preflight($png(1200, 1800), 'tag', $landscape, true, 1, 'contain')['dpi'])->toBe(450)
        ->and(PrintUpload::preflight($png(1200, 1800), 'tag', $landscape, true, 1, 'cover')['dpi'])->toBe(200);
});

it('takes a PNG or a JPEG only, one picture per print, and lets the customer choose fill or fit', function () {
    $customer = pp5Desk();
    $job = app(SavePrintJobAction::class)->startUpload($customer->id, 'en', 'photos');
    app()->setLocale('dv');

    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))
        ->post(route('public.shop.print.upload', $job->id), ['file' => UploadedFile::fake()->createWithContent('photo.pdf', "%PDF-1.4\n%fake")->mimeType('application/pdf'), 'upload_kind' => 'sheet'])
        ->assertSessionHasErrors(['file' => __('print.upload_error_photo_type', [], 'dv')]);
    printWeb()->actingAs($customer)->from(route('public.shop.print.job', $job->id))
        ->post(route('public.shop.print.upload', $job->id), ['file' => pp5Png(1800, 1200), 'upload_kind' => 'sheet'])->assertSessionHasNoErrors();
    expect($job->fresh()->upload_kind)->toBe('tag');

    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['fit' => 'contain'])->assertOk();
    expect(PrintUpload::fitOf((array) $job->fresh()->design))->toBe('contain');
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['fit' => 'stretch'])
        ->assertStatus(422)->assertJsonPath('errors.fit.0', __('print.upload_error_fit', [], 'dv'));
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['upload_kind' => 'sheet'])->assertStatus(422);
});

it('prints a filled photo cropped to its box, and a fitted one whole', function () {
    $layout = PrintLayout::query()->where('name', '5 × 7 in — 1 per A4')->firstOrFail();
    $render = app(RenderPrintSheetAction::class);
    $src = 'data:image/png;base64,'.base64_encode((string) pp5Png(20, 30)->getContent());

    expect($render->document(PrintUpload::design(127, 177.8, 'cover'), $layout, [[]], ['upload' => $src]))->toContain('<img class="cover"')
        ->and($render->document(PrintUpload::design(127, 177.8, 'contain'), $layout, [[]], ['upload' => $src]))->toContain('<img class=""');
});
