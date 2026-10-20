<?php

use App\Domains\Bookshop\Actions\Print\RenderPrintSheetAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP4 (STATUS §5tl): stickers on sheets — round ones
 * printed and proofed as their circle, kiss-cut so the sheet stays whole.
 */
uses(RefreshDatabase::class);

it('ships sticker layouts that fit with their bleed, a kiss-cut finishing and an A3 sticker sheet, both off until priced', function () {
    $layouts = PrintLayout::query()->where('print_kind', 'stickers')->orderBy('sort_order')->get();
    expect($layouts->map(fn (PrintLayout $l) => $l->shape.' '.$l->tagsPerSheet().' '.$l->sheet_size)->all())->toBe(['circle 24 a4', 'circle 54 a3', 'circle 12 a4', 'rect 21 a4'])
        ->and($layouts->every(fn (PrintLayout $l) => $l->fitsSheet() && $l->roomForBleed()))->toBeTrue()
        // Everything before stays a rectangle.
        ->and(PrintLayout::query()->whereIn('print_kind', ['name_tags', 'cards'])->where('shape', '!=', 'rect')->exists())->toBeFalse();

    $kiss = PrintFinishingOption::query()->where('name', 'like', 'Kiss-cut%')->firstOrFail();
    expect($kiss->cuts_sheet)->toBeFalse()->and($kiss->is_active)->toBeFalse()->and($kiss->name_dv)->toMatch('/\p{Thaana}/u');
    $a3 = PrintPaperOption::query()->where('kind', 'sticker')->where('sheet_size', 'a3')->firstOrFail();
    expect($a3->is_active)->toBeFalse();

    $designs = PrintTemplate::query()->where('print_kind', 'stickers')->where('status', 'active')->get();
    expect($designs)->toHaveCount(3)
        ->and($designs->every(fn (PrintTemplate $t) => $t->layout?->print_kind === 'stickers' && $t->design['tag']['w'] == $t->layout->tag_w_mm))->toBeTrue();
    $designs->each(fn (PrintTemplate $t) => PrintDesign::validate((array) $t->design));
});

it('prints a round sticker as its circle, with no crop marks — the kiss-cut follows the outline', function () {
    $template = PrintTemplate::query()->where('title', 'Round name sticker')->firstOrFail();
    $html = app(RenderPrintSheetAction::class)->document((array) $template->design, $template->layout, array_fill(0, 24, ['name' => 'Ahmed', 'class' => '3B']));

    // 40 mm with a 2 mm bleed: drawn 44 mm wide, round.
    expect($html)->toContain('width: 44mm; height: 44mm;')->toContain('border-radius: 50%;')
        ->not->toContain('class="crop"');
    // A rectangle label still has its marks.
    $label = PrintTemplate::query()->where('title', 'Book label')->firstOrFail();
    expect(app(RenderPrintSheetAction::class)->document((array) $label->design, $label->layout, [['name' => 'Ahmed']]))
        ->toContain('class="crop"')->not->toContain('border-radius: 50%;');
});

it('lets the office make a round layout, and refuses a shape it does not know', function () {
    PrintDesk::ensure();
    $office = printOffice();
    $grid = ['name' => 'Round 30', 'print_kind' => 'stickers', 'shape' => 'circle', 'columns' => 5, 'rows' => 8, 'tag_w_mm' => 30, 'tag_h_mm' => 30,
        'margin_top_mm' => 10, 'margin_left_mm' => 10, 'gutter_x_mm' => 4, 'gutter_y_mm' => 4, 'bleed_mm' => 2];

    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), $grid)->assertSessionHasNoErrors();
    expect(PrintLayout::query()->where('name', 'Round 30')->value('shape'))->toBe('circle');
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.layout'), ['shape' => 'star', 'name' => 'Star'] + $grid)->assertSessionHasErrors('shape');
});

it('shows the customer a round sticker as round, on the door and on the job', function () {
    PrintDesk::ensure();
    config(['print.desk.enabled' => true]);
    \App\Domains\Bookshop\Support\PrintProducts::product('stickers')->update(['status' => 'active']);
    $customer = User::factory()->create();

    printWeb()->actingAs($customer)->get(route('public.shop.print.kind', 'stickers'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('templates', fn ($t) => collect($t)->firstWhere('title', 'Round name sticker')['shape'] === 'circle'
            && collect($t)->firstWhere('title', 'Book label')['shape'] === 'rect'));

    $job = app(SavePrintJobAction::class)->start(PrintTemplate::query()->where('title', 'Round name sticker')->firstOrFail(), $customer->id, null, 'en');
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('layouts', fn ($l) => collect($l)->firstWhere('id', $job->print_layout_id)['shape'] === 'circle'));
});
