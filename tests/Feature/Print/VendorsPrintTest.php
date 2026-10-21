<?php

use App\Domains\Bookshop\Actions\Print\ApproveVendorPrintAction;
use App\Domains\Bookshop\Actions\Print\QuotePrintJobAction;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP7a (PD10, STATUS §5uh): the office approves a shop to
 * print; the shop has its own copy of each print product, on sale while it
 * prints; and a job is priced from its printer's own papers and finishings
 * only — Akuru's stay unowned, and the office's desk keeps only those.
 */
uses(RefreshDatabase::class);

function pp7Shop(string $status = 'active'): Vendor
{
    return Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => $status, 'commission_rate' => 10]);
}

it('makes an approved shop its own print products, on sale while it prints', function () {
    PrintDesk::ensure();
    $shop = pp7Shop();

    printWeb()->actingAs(\App\Domains\Identity\Models\User::factory()->create())->post(route('admin.bookshop.print.shop', $shop->id), ['print_enabled' => true])->assertForbidden();
    printWeb()->actingAs(printOffice())->post(route('admin.bookshop.print.shop', $shop->id), ['print_enabled' => true])->assertSessionHasNoErrors();

    expect($shop->fresh()->print_enabled)->toBeTrue();
    foreach (PrintProducts::kinds() as $kind) {
        $product = Product::query()->where('slug', PrintProducts::KINDS[$kind]['slug'].'--fitrah')->sole();
        expect($product->vendor_id)->toBe($shop->id)
            ->and($product->status->value)->toBe('active')
            ->and(PrintProducts::kindOfSlug($product->slug))->toBe($kind)
            ->and(PrintProducts::printerOfProduct($product->id))->toBe($shop->id);
    }
    // Akuru's own products are Akuru's: no printer.
    expect(PrintProducts::printerOfProduct(PrintProducts::product('name_tags')->id))->toBeNull();

    app(ApproveVendorPrintAction::class)->execute($shop->fresh(), false);
    expect($shop->fresh()->print_enabled)->toBeFalse()
        ->and(Product::query()->where('vendor_id', $shop->id)->where('status', 'active')->count())->toBe(0);
});

it('refuses to approve Akuru itself or a shop that is not open', function () {
    [$akuru] = PrintDesk::ensure();
    expect(fn () => app(ApproveVendorPrintAction::class)->execute($akuru, true))->toThrow(ValidationException::class);
    expect(fn () => app(ApproveVendorPrintAction::class)->execute(pp7Shop('suspended'), true))->toThrow(ValidationException::class);
});

it('prices a shop\'s job from its own papers and finishings only, and Akuru\'s from Akuru\'s', function () {
    PrintDesk::ensure();
    $shop = pp7Shop();
    $akuruPaper = PrintPaperOption::query()->create(['name' => 'Akuru plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 3, 'is_active' => true]);
    $shopPaper = PrintPaperOption::query()->create(['vendor_id' => $shop->id, 'name' => 'Fitrah plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 2.5, 'is_active' => true]);
    $shopCut = PrintFinishingOption::query()->create(['vendor_id' => $shop->id, 'name' => 'Cut', 'price' => 0.5, 'is_active' => true]);
    $akuruCut = PrintFinishingOption::query()->create(['name' => 'Akuru cut', 'price' => 0.4, 'is_active' => true]);
    config(['print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    $quote = app(QuotePrintJobAction::class);

    expect($quote->execute($shopPaper->id, true, [$shopCut->id], 4, 'en', 'a4', $shop->id)['total'])->toBe(12.0);
    expect(fn () => $quote->execute($akuruPaper->id, true, [], 4, 'en', 'a4', $shop->id))->toThrow(ValidationException::class);
    expect(fn () => $quote->execute($shopPaper->id, true, [$akuruCut->id], 4, 'en', 'a4', $shop->id))->toThrow(ValidationException::class);
    expect(fn () => $quote->execute($shopPaper->id, true, [], 4, 'en', 'a4'))->toThrow(ValidationException::class);
    expect($quote->execute($akuruPaper->id, true, [], 4, 'en', 'a4')['total'])->toBe(12.0);
    expect(PrintPaperOption::firstFor('a4', $shop->id)?->id)->toBe($shopPaper->id)
        ->and(PrintPaperOption::firstFor('a4')?->id)->toBe($akuruPaper->id);
});

it('keeps the office\'s desk to Akuru\'s papers, and lists the shops it may approve', function () {
    PrintDesk::ensure();
    $shop = pp7Shop();
    $shopPaper = PrintPaperOption::query()->create(['vendor_id' => $shop->id, 'name' => 'Fitrah plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 2.5, 'is_active' => true]);
    PrintPaperOption::query()->create(['name' => 'Akuru plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 3, 'is_active' => true]);
    $office = printOffice();

    printWeb()->actingAs($office)->get(route('admin.bookshop.print'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Bookshop/Print')
            ->where('papers', fn ($papers) => collect($papers)->pluck('name')->contains('Akuru plain') && ! collect($papers)->pluck('name')->contains('Fitrah plain'))
            ->where('shops.0.slug', 'fitrah')->where('shops.0.print_enabled', false)->where('shops.0.papers', 1));
    // A shop's paper is not the office's to change from its desk.
    printWeb()->actingAs($office)->post(route('admin.bookshop.print.paper', $shopPaper->id), ['name' => 'Taken', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 1])->assertNotFound();
    expect($shopPaper->fresh()->name)->toBe('Fitrah plain');
});
