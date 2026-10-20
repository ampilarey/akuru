<?php

use App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP3 (STATUS §5tk): cards print with a 3 mm bleed —
 * each card drawn larger than its cut, what touches its edge running on into
 * the bleed, and the crop marks at the sheet's edges, clear of every card.
 */
uses(RefreshDatabase::class);

function pp3Business(): PrintTemplate
{
    return PrintTemplate::query()->where('title', 'Business — side band')->firstOrFail();
}

it('ships four card layouts with a bleed that fit their sheets, and five active card designs', function () {
    $layouts = PrintLayout::query()->where('print_kind', 'cards')->orderBy('sort_order')->get();
    expect($layouts)->toHaveCount(4)
        ->and($layouts->every(fn (PrintLayout $l) => (float) $l->bleed_mm === 3.0 && $l->fitsSheet() && $l->roomForBleed()))->toBeTrue()
        ->and($layouts->map(fn (PrintLayout $l) => $l->tagsPerSheet().' '.$l->sheet_size)->all())->toBe(['8 a4', '18 a3', '2 a4', '1 a4'])
        // Name tags keep no bleed.
        ->and(PrintLayout::query()->where('print_kind', 'name_tags')->where('bleed_mm', '>', 0)->exists())->toBeFalse();

    $cards = PrintTemplate::query()->where('print_kind', 'cards')->where('status', 'active')->get();
    expect($cards)->toHaveCount(5)
        ->and($cards->every(fn (PrintTemplate $t) => $t->layout?->print_kind === 'cards' && PrintDesign::fieldKeys((array) $t->design) !== [] && preg_match('/\p{Thaana}/u', (string) $t->title_dv)))->toBeTrue()
        ->and(PrintDesign::fieldKeys((array) pp3Business()->design))->toBe(['name', 'role', 'phone', 'email']);
    // Every shipped design passes the validator the office's own designs pass.
    $cards->each(fn (PrintTemplate $t) => PrintDesign::validate((array) $t->design));
});

it('runs what touches the cut on into the bleed, and moves everything else in by it', function () {
    $elements = [
        ['type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 22, 'h' => 55],
        ['type' => 'rect', 'x' => 0, 'y' => 52, 'w' => 85, 'h' => 3],
        ['type' => 'text', 'x' => 0, 'y' => 10, 'w' => 85, 'h' => 10],
        ['type' => 'rect', 'x' => 30, 'y' => 30, 'w' => 10, 'h' => 10],
    ];

    [$band, $foot, $text, $inner] = RenderPrintSheetAction::bleed($elements, 85, 55, 3);

    expect([$band['x'], $band['y'], $band['w'], $band['h']])->toBe([0.0, 0.0, 25.0, 61.0])
        ->and([$foot['x'], $foot['y'], $foot['w'], $foot['h']])->toBe([0.0, 55.0, 91.0, 6.0])
        // Text and a box inside the card are only moved in.
        ->and([$text['x'], $text['y'], $text['w']])->toBe([3.0, 13.0, 85.0])
        ->and([$inner['x'], $inner['y'], $inner['w'], $inner['h']])->toBe([33.0, 33.0, 10.0, 10.0])
        ->and(RenderPrintSheetAction::bleed($elements, 85, 55, 0))->toBe($elements);
});

it('draws each card larger by its bleed, with crop marks only at the sheet edges, clear of every card', function () {
    $template = pp3Business();
    $layout = $template->layout;
    $html = app(RenderPrintSheetAction::class)->document((array) $template->design, $layout, array_fill(0, 8, ['name' => 'Aishath', 'role' => 'Teacher', 'phone' => '7771234', 'email' => 'a@akuru.edu.mv']));

    // The first card: cut at 17 × 29.5 mm, drawn from 14 × 26.5 mm at 91 × 61.
    expect($html)->toContain('left: 14mm; top: 26.5mm; width: 91mm; height: 61mm;');
    $marks = RenderPrintSheetAction::outerMarks($layout);
    expect($marks)->not->toBeEmpty();
    $cards = [];
    for ($c = 0; $c < 2; $c++) {
        for ($r = 0; $r < 4; $r++) {
            $cards[] = [17 + $c * 91 - 3, 29.5 + $r * 61 - 3, 91, 61];
        }
    }
    foreach ($marks as $m) {
        foreach ($cards as [$x, $y, $w, $h]) {
            $overlaps = $m['x'] < $x + $w && $x < $m['x'] + $m['w'] && $m['y'] < $y + $h && $y < $m['y'] + $m['h'];
            expect($overlaps)->toBeFalse();
        }
        expect($m['x'] >= 0 && $m['y'] >= 0 && $m['x'] + $m['w'] <= 210 && $m['y'] + $m['h'] <= 297)->toBeTrue();
    }
    // A cut line at every card edge: two per column and two per row, each marked twice.
    expect(count($marks))->toBe((4 + 8) * 2);
});

it('refuses a bleed the gutters or margins cannot hold, in the office language', function () {
    PrintDesk::ensure();
    app()->setLocale('dv');
    $grid = ['name' => 'Tight cards', 'print_kind' => 'cards', 'columns' => 2, 'rows' => 4, 'tag_w_mm' => 85, 'tag_h_mm' => 55, 'margin_top_mm' => 29.5, 'margin_left_mm' => 17, 'gutter_x_mm' => 2, 'gutter_y_mm' => 6, 'bleed_mm' => 3];

    printWeb()->actingAs(printOffice())->post(route('admin.bookshop.print.layout'), $grid)
        ->assertSessionHasErrors(['bleed_mm' => __('print.error_layout_bleed', ['bleed' => 3, 'gutter' => 6], 'dv')]);
    printWeb()->actingAs(printOffice())->post(route('admin.bookshop.print.layout'), ['gutter_x_mm' => 6] + $grid)->assertSessionHasNoErrors();
    expect((float) PrintLayout::query()->where('name', 'Tight cards')->value('bleed_mm'))->toBe(3.0);
});

it('asks a business card customer for name, role, phone and email, named in their language', function () {
    PrintDesk::ensure();
    $customer = User::factory()->create();
    $job = app(SavePrintJobAction::class)->start(pp3Business(), $customer->id, null, 'dv');
    config(['print.desk.enabled' => true]);

    expect($job->printKind())->toBe('cards');
    app()->setLocale('dv');
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('job.fields.1.label', 'މަގާމު ނުވަތަ ލަޤަބު')->where('job.fields.2.label', 'ފޯނު'));
});
