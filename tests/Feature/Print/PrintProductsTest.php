<?php

use App\Domains\Bookshop\Actions\Cart\SaveCartItemAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintSettingsAction;
use App\Domains\Bookshop\Actions\Print\SavePrintTemplateAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP2 (STATUS §5tj): the print desk sells name tags,
 * cards, stickers and photo prints. Each is a Bookstore product with its own
 * door, layouts and templates; name tags is on with the desk, the others are
 * put on sale one by one.
 */
uses(RefreshDatabase::class);

/** The desk switched on (the host's Chrome faked), a paper priced, and a cards layout and template. */
function pp2Desk(): array
{
    PrintDesk::ensure();
    printHostCanPrint();
    Storage::fake('local');
    PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail()->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    app(SavePrintSettingsAction::class)->execute(['enabled' => true, 'lead_days' => 2, 'min_sheets' => 1]);
    $layout = PrintLayout::query()->create(['name' => 'Business cards — 10', 'print_kind' => 'cards', 'sheet_size' => 'a4', 'columns' => 2, 'rows' => 5, 'tag_w_mm' => 85, 'tag_h_mm' => 55,
        'margin_top_mm' => 10, 'margin_left_mm' => 15, 'gutter_x_mm' => 0, 'gutter_y_mm' => 0, 'is_active' => true, 'sort_order' => 1]);
    $nameTag = PrintTemplate::query()->where('status', 'active')->orderBy('sort_order')->firstOrFail();
    $template = PrintTemplate::query()->create(['title' => 'Plain card', 'kind' => 'name_tag', 'print_kind' => 'cards', 'print_layout_id' => $layout->id,
        'design' => $nameTag->design, 'status' => 'active', 'sort_order' => 1]);

    return [$layout, $template, $nameTag];
}

it('makes the four print products once, each a draft until it is put on sale', function () {
    PrintDesk::ensure();
    PrintDesk::ensure();

    $products = Product::query()->whereIn('slug', array_column(PrintProducts::KINDS, 'slug'))->get();
    expect($products)->toHaveCount(4)
        ->and($products->every(fn (Product $p) => $p->status->value === 'draft' && ! $p->track_stock && $p->title_dv !== null))->toBeTrue()
        ->and(PrintProducts::title('photos', 'dv'))->toBe('ފޮޓޯ ޕްރިންޓް');
});

it('lists what is on sale: name tags with the desk, the others once put on sale, and the office sees them all', function () {
    pp2Desk();
    $customer = User::factory()->create();

    printWeb()->actingAs($customer)->get(route('public.shop.print.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintIndex')->has('products', 1)->where('products.0.kind', 'name_tags'));
    printWeb()->actingAs($customer)->get(route('public.shop.print.kind', 'cards'))->assertNotFound();
    printWeb()->actingAs(printOffice())->get(route('public.shop.print.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 4)->where('products.1.kind', 'cards')->where('products.1.on_sale', false));

    printWeb()->actingAs(printOffice())->post(route('admin.bookshop.print.product', 'cards'), ['on_sale' => true])->assertSessionHasNoErrors();
    expect(PrintProducts::onSale('cards'))->toBeTrue();

    printWeb()->actingAs($customer)->get(route('public.shop.print.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 2)->where('products.1.kind', 'cards'));
    printWeb()->actingAs($customer)->get(route('public.shop.print.kind', 'cards'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintDoor')->where('kind', 'cards')->where('templates', fn ($templates) => collect($templates)->contains('title', 'Plain card') && collect($templates)->doesntContain('title', 'Plain')));
    // The name-tag door keeps its own designs.
    printWeb()->actingAs($customer)->get(route('public.shop.print'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('templates', fn ($templates) => collect($templates)->doesntContain('title', 'Plain card')));
});

it('puts a product on sale only while the desk is on and it has a layout, and the desk off takes them all off', function () {
    pp2Desk();
    $office = printOffice();
    app()->setLocale('dv');
    // Stickers' own layouts (PP4) retired, so it has none to print on.
    PrintLayout::query()->where('print_kind', 'stickers')->update(['is_active' => false]);

    printWeb()->actingAs($office)->post(route('admin.bookshop.print.product', 'stickers'), ['on_sale' => true])
        ->assertSessionHasErrors(['product' => __('print.error_product_no_layout', ['product' => 'ސްޓިކަރު'], 'dv')]);
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.product', 'cards'), ['on_sale' => true])->assertSessionHasNoErrors();

    app(SavePrintSettingsAction::class)->execute(['enabled' => false, 'lead_days' => 2, 'min_sheets' => 1]);
    expect(PrintProducts::onSale('cards'))->toBeFalse()->and(PrintProducts::onSale('name_tags'))->toBeFalse();

    printWeb()->actingAs($office)->post(route('admin.bookshop.print.product', 'cards'), ['on_sale' => true])
        ->assertSessionHasErrors(['product' => __('print.error_product_desk_off', [], 'dv')]);
    printWeb()->actingAs($office)->post('/admin/bookshop/print/products/posters', ['on_sale' => true])->assertNotFound();
    // Name tags is the desk's own switch.
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.product', 'name_tags'), ['on_sale' => false])
        ->assertSessionHasErrors(['product' => __('print.error_product_name_tags', [], 'dv')]);
});

it('starts a job from a cards design as a cards job, on cards layouts only', function () {
    [$layout, $template, $nameTag] = pp2Desk();
    printWeb()->actingAs(printOffice())->post(route('admin.bookshop.print.product', 'cards'), ['on_sale' => true]);
    $customer = User::factory()->create();

    printWeb()->actingAs($customer)->post(route('public.shop.print.start'), ['template' => $template->id])->assertRedirect();
    $job = \App\Domains\Bookshop\Models\PrintJob::query()->latest('id')->firstOrFail();
    expect($job->printKind())->toBe('cards')->and($job->product_id)->toBe(PrintProducts::product('cards')->id)
        ->and($job->print_layout_id)->toBe($layout->id);

    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('layouts', fn ($layouts) => collect($layouts)->contains('id', $layout->id) && collect($layouts)->doesntContain('name', 'A4 2 × 4 (8 tags)'))->where('door', 'cards')->where('product_title', 'Cards'));
    // A name-tag layout is not this job's to choose.
    $tagLayout = PrintLayout::query()->where('print_kind', 'name_tags')->where('is_active', true)->firstOrFail();
    printWeb()->actingAs($customer)->postJson(route('public.shop.print.save', $job->id), ['layout_id' => $tagLayout->id])->assertStatus(422);

    // A blank design and an upload start on the product they name.
    $free = app(SavePrintJobAction::class)->startFreeform($customer->id, null, 'en', 'cards');
    expect($free->printKind())->toBe('cards')->and($free->print_layout_id)->toBe($layout->id);
    printWeb()->actingAs($customer)->post(route('public.shop.print.freeform'), ['kind' => 'posters'])->assertNotFound();

    // Name tags carry on as they were.
    expect(app(SavePrintJobAction::class)->start($nameTag, $customer->id, null, 'en')->printKind())->toBe('name_tags');
});

it('keeps a template to its product, and a print product out of the basket without a design', function () {
    [$layout, , $nameTag] = pp2Desk();
    $tagLayout = PrintLayout::query()->where('print_kind', 'name_tags')->where('is_active', true)->firstOrFail();
    $save = app(SavePrintTemplateAction::class);

    expect(fn () => $save->execute(['title' => 'Mixed', 'kind' => 'name_tag', 'print_kind' => 'cards', 'print_layout_id' => $tagLayout->id, 'design' => $nameTag->design], null, printOffice()->id))
        ->toThrow(ValidationException::class);
    $card = $save->execute(['title' => 'Card two', 'kind' => 'name_tag', 'print_kind' => 'cards', 'print_layout_id' => $layout->id, 'design' => $nameTag->design], null, printOffice()->id);
    expect($card->print_kind)->toBe('cards');

    $cart = Cart::query()->create(['user_id' => User::factory()->create()->id]);
    expect(fn () => app(SaveCartItemAction::class)->add($cart, 'print-cards', null, 1))->toThrow(ValidationException::class, __('print.error_cart_needs_design'));
});
