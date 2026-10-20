<?php

use App\Domains\Bookshop\Actions\Print\QuotePrintJobAction;
use App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintSheet;
use App\Domains\Bookshop\Support\PrintUpload;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * PRINT_PRODUCTS_PLAN PP1 (STATUS §5ti): sheet sizes. A layout is laid out
 * on A4, A3 or A5; a paper is stock of one size with its own prices; a job
 * prints on its layout's size, and every layout and paper made before reads
 * as A4.
 */
uses(RefreshDatabase::class);

/** The desk open with its A4 paper priced, plus an A3 paper and an A3 layout of 24 tags. */
function pp1Desk(): array
{
    PrintDesk::ensure();
    $a4 = PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $a4->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    $a3 = PrintPaperOption::query()->create(['name' => 'Gloss A3', 'name_dv' => 'ގްލޯސް A3', 'kind' => 'glossy', 'sheet_size' => 'a3', 'price_bw' => 4.00, 'price_colour' => 7.50, 'is_active' => true, 'sort_order' => 90]);
    $layout = PrintLayout::query()->create(['name' => 'A3 — 24 tags', 'sheet_size' => 'a3', 'columns' => 4, 'rows' => 6, 'tag_w_mm' => 70, 'tag_h_mm' => 65,
        'margin_top_mm' => 10, 'margin_left_mm' => 8, 'gutter_x_mm' => 0, 'gutter_y_mm' => 0, 'is_active' => true, 'sort_order' => 90]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    printHostCanPrint();
    Storage::fake('local');

    return [$a4, $a3, $layout];
}

it('reads every layout and paper made before as A4', function () {
    PrintDesk::ensure();

    $tags = PrintLayout::query()->where('print_kind', 'name_tags');
    expect((clone $tags)->pluck('sheet_size')->unique()->all())->toBe(['a4'])
        ->and(PrintPaperOption::query()->pluck('sheet_size')->unique()->all())->toBe(['a4'])
        ->and((clone $tags)->get()->every(fn (PrintLayout $l) => $l->fitsSheet() && $l->sheetMm() === [210.0, 297.0]))->toBeTrue();
});

it('lets the office lay out an A3 sheet, and refuses the same grid on A4 in its language', function () {
    PrintDesk::ensure();
    $office = printOffice();
    $grid = ['name' => 'Big sheet', 'columns' => 4, 'rows' => 6, 'tag_w_mm' => 70, 'tag_h_mm' => 65, 'margin_top_mm' => 10, 'margin_left_mm' => 8, 'is_active' => true];

    app()->setLocale('dv');
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), $grid + ['sheet_size' => 'a4'])
        ->assertSessionHasErrors(['columns' => __('print.error_layout_fits', ['width' => 288, 'height' => 400, 'size' => 'A4', 'page_w' => 210, 'page_h' => 297], 'dv')]);
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), $grid + ['sheet_size' => 'a3'])->assertSessionHasNoErrors();
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), $grid + ['sheet_size' => 'b2'])->assertSessionHasErrors('sheet_size');

    $layout = PrintLayout::query()->where('name', 'Big sheet')->firstOrFail();
    expect($layout->sheet_size)->toBe('a3')->and($layout->tagsPerSheet())->toBe(24)->and($layout->sheetMm())->toBe([297.0, 420.0]);
});

it('keeps a paper to its own size, with its own prices', function () {
    [$a4, $a3] = pp1Desk();
    $office = printOffice();
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.paper', $a3->id), ['name' => 'Gloss A3', 'kind' => 'glossy', 'sheet_size' => 'a3', 'price_bw' => 4, 'price_colour' => 8, 'is_active' => true])->assertSessionHasNoErrors();

    $quote = app(QuotePrintJobAction::class);
    expect($quote->execute($a3->id, true, [], 2, 'en', 'a3')['total'])->toBe(16.0)
        ->and($quote->execute($a4->id, true, [], 2, 'en', 'a4')['total'])->toBe(6.0);
    try {
        $quote->execute($a4->id, true, [], 2, 'dv', 'a3');
        $this->fail('An A4 paper priced an A3 job.');
    } catch (ValidationException $e) {
        expect($e->errors()['paper'][0])->toBe(__('print.error_job_paper_size', ['size' => 'A3']));
    }
});

it('moves a job to the A3 paper when its layout becomes A3, and prices it there', function () {
    [$a4, $a3, $layout] = pp1Desk();
    $customer = User::factory()->create();
    $save = app(SavePrintJobAction::class);
    $job = $save->start(PrintTemplate::query()->where('status', 'active')->orderBy('sort_order')->firstOrFail(), $customer->id, null, 'en');
    expect($job->print_paper_option_id)->toBe($a4->id);

    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['layout_id' => $layout->id, 'sheets' => 1])->assertOk();

    $job->refresh();
    expect($job->print_layout_id)->toBe($layout->id)->and($job->print_paper_option_id)->toBe($a3->id)
        ->and((float) $job->price_total)->toBe(7.5)
        ->and((float) $job->design['tag']['w'])->toBe(70.0)->and((float) $job->design['tag']['h'])->toBe(65.0);

    // The page knows each paper's size, to offer the layout's only.
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn ($page) => $page->where('papers', fn ($papers) => collect($papers)->firstWhere('id', $a3->id)['sheet_size'] === 'a3')
            ->where('layouts', fn ($layouts) => collect($layouts)->firstWhere('id', $layout->id)['sheet_label'] === 'A3'));
});

it('prints an A3 layout on A3 pages, and proofs at A3 size', function () {
    [, , $layout] = pp1Desk();
    $design = ['tag' => ['w' => 70, 'h' => 65], 'background' => ['color' => '#FFFFFF'], 'elements' => []];

    $html = app(RenderPrintSheetAction::class)->document($design, $layout, [[]]);

    expect($html)->toContain('size: 297mm 420mm')
        ->and(PrintSheet::px('a3'))->toBe([1123, 1587])
        ->and(PrintSheet::px('a4'))->toBe([794, 1123])
        ->and(PrintUpload::sheetLayout('a3')->sheetMm())->toBe([297.0, 420.0]);
});

it('takes a whole-sheet upload of the layout sheet, and names that sheet when the shape is wrong', function () {
    [, , $layout] = pp1Desk();
    $upright = ['type' => 'png', 'w' => 3508, 'h' => 4961, 'alpha' => false, 'colour' => true];
    $square = ['type' => 'png', 'w' => 4000, 'h' => 4000, 'alpha' => false, 'colour' => true];

    expect(PrintUpload::preflight($upright, 'sheet', $layout, true, 1)['refusals'])->toBe([])
        ->and(PrintUpload::preflight($upright, 'sheet', $layout, true, 1)['dpi'])->toBe(300)
        ->and(PrintUpload::preflight($square, 'sheet', $layout, true, 1)['refusals'])->toBe([['key' => 'print.upload_not_a4', 'vars' => ['size' => 'A3']]]);
});
