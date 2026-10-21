<?php

use App\Domains\Bookshop\Actions\Checkout\PrepareCheckoutAction;
use App\Domains\Bookshop\Actions\Print\ApproveVendorPrintAction;
use App\Domains\Bookshop\Actions\Print\PrintProductionAction;
use App\Domains\Bookshop\Actions\Print\ProofPrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP7c (PD10d, STATUS §5uj): the customer chooses who
 * prints the job — Akuru first, then each approved shop with a priced paper
 * of the job's sheet size. The job moves to that printer's product and first
 * paper, its finishings cleared; the order is the shop's, delivered the
 * shop's way, and lands on the shop's queue.
 */
uses(RefreshDatabase::class);

/** The desk open, Akuru's first A4 paper at 3.00, and Fitrah approved to print with its own at 2.00. */
function pp7cDesk(): array
{
    PrintDesk::ensure();
    PrintDesk::product()->update(['status' => 'active']);
    PrintDesk::vendor()->update(['status' => 'active']);
    PrintPaperOption::query()->ofPrinter(null)->orderBy('sort_order')->orderBy('id')->firstOrFail()->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => [], 'print.desk.lead_days' => 3]);
    printHostCanPrint();
    Storage::fake('local');
    $shop = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'commission_rate' => 10]);
    $paper = PrintPaperOption::query()->create(['vendor_id' => $shop->id, 'name' => 'Fitrah plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 2, 'is_active' => true]);
    app(ApproveVendorPrintAction::class)->execute($shop, true);

    return [$shop->fresh(), $paper];
}

function pp7cJob(User $customer): PrintJob
{
    $template = PrintTemplate::query()->where('kind', 'name_tag')->orderBy('sort_order')->firstOrFail();
    $save = app(SavePrintJobAction::class);
    $job = $save->start($template, $customer->id, null, 'en');

    return $save->update($job, ['entry' => ['mode' => 'same', 'row' => ['name' => 'Aishath', 'class' => '3B']], 'sheets' => 3, 'colour' => true], 'en');
}

it('offers Akuru first and the approved shops with a paper of the size, and nobody else', function () {
    [$shop] = pp7cDesk();
    Vendor::query()->create(['name' => 'Noor', 'slug' => 'noor', 'code' => 'NOR', 'status' => 'active', 'commission_rate' => 10, 'print_enabled' => true]);
    $customer = User::factory()->create();
    $job = pp7cJob($customer);

    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/PrintJob')
            ->where('printers.0.id', null)->where('printers.0.slug', 'akuru')
            ->where('printers.1.id', $shop->id)->where('printers.1.sizes', ['a4'])
            ->has('printers', 2)->where('job.printer', null)->has('t.printer_heading'));
});

it('moves a job to the shop: its product, its first paper, no finishings, its price — and back', function () {
    [$shop, $paper] = pp7cDesk();
    $akuruCut = PrintFinishingOption::query()->create(['name' => 'Akuru cut', 'price' => 0.4, 'is_active' => true]);
    $customer = User::factory()->create();
    $job = pp7cJob($customer);
    app(SavePrintJobAction::class)->update($job, ['finishing' => [$akuruCut->id]], 'en');

    printWeb()->actingAs($customer)->post(route('public.shop.print.printer', $job->id), ['printer' => $shop->id])->assertSessionHasNoErrors();
    $job->refresh();
    expect($job->printerId())->toBe($shop->id)
        ->and($job->product_id)->toBe(PrintProducts::productFor('name_tags', $shop)->id)
        ->and($job->print_paper_option_id)->toBe($paper->id)
        ->and($job->finishing)->toBe([])
        ->and((float) $job->price_total)->toBe(6.0);
    printWeb()->actingAs($customer)->get(route('public.shop.print.job', $job->id))
        ->assertInertia(fn (Assert $page) => $page->where('job.printer', $shop->id)->where('papers', fn ($papers) => collect($papers)->pluck('id')->all() === [$paper->id]));

    printWeb()->actingAs($customer)->post(route('public.shop.print.printer', $job->id), ['printer' => null])->assertSessionHasNoErrors();
    expect($job->fresh()->printerId())->toBeNull()->and((float) $job->fresh()->price_total)->toBe(9.0);
});

it('refuses a shop not approved, one without a paper of the size, and a job no longer open', function () {
    [$shop] = pp7cDesk();
    $customer = User::factory()->create();
    $job = pp7cJob($customer);
    $noor = Vendor::query()->create(['name' => 'Noor', 'slug' => 'noor', 'code' => 'NOR', 'status' => 'active', 'commission_rate' => 10]);
    PrintPaperOption::query()->create(['vendor_id' => $noor->id, 'name' => 'Noor plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 2, 'is_active' => true]);
    $save = app(SavePrintJobAction::class);

    expect(fn () => $save->printer($job, $noor->id, 'en'))->toThrow(ValidationException::class);
    PrintPaperOption::query()->where('vendor_id', $shop->id)->update(['sheet_size' => 'a3']);
    expect(fn () => $save->printer($job->fresh(), $shop->id, 'en'))->toThrow(ValidationException::class, __('print.error_printer_no_paper'));
    expect($job->fresh()->printerId())->toBeNull();

    PrintPaperOption::query()->where('vendor_id', $shop->id)->update(['sheet_size' => 'a4']);
    $proof = app(ProofPrintJobAction::class);
    $approved = $proof->approve($proof->proof($job->fresh(), $customer->id, 'en'), 'en');
    expect(fn () => $save->printer($approved, $shop->id, 'en'))->toThrow(ValidationException::class);
});

it('orders from the shop: the order is the shop\'s, delivered its way, and on its queue', function () {
    [$shop] = pp7cDesk();
    $customer = User::factory()->create();
    $save = app(SavePrintJobAction::class);
    $job = $save->printer(pp7cJob($customer), $shop->id, 'en');
    $proof = app(ProofPrintJobAction::class);
    $job = $proof->approve($proof->proof($job, $customer->id, 'en'), 'en');

    printWeb()->actingAs($customer)->post(route('public.shop.print.cart', $job->id))->assertSessionHasNoErrors();
    $options = app(PrepareCheckoutAction::class)->execute($customer->id, Cart::query()->where('user_id', $customer->id)->first())['basket']['groups'][0]['delivery_options'];
    // The shop's own deliveries, not Akuru's, each ready in the desk's lead time.
    $own = app(\App\Domains\Bookshop\Actions\Checkout\ResolveDeliveryOptionsAction::class)->execute($shop, 6.0);
    expect(collect($options)->pluck('key')->all())->toBe(collect($own)->pluck('key')->all())
        ->and(collect($options)->pluck('key')->intersect(['akuru', 'akuru-collect'])->all())->toBe([])
        ->and(collect($options)->every(fn ($o) => $o['handling_days'] >= 3))->toBeTrue();
    $chosen = collect($options)->firstWhere('offered', true) ?? $options[0];
    app(CreditWalletAction::class)->execute($customer->id, 100, 'admin', null, 'Top-up');
    printWeb()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$shop->slug => (string) $chosen['key']], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->vendor_id)->toBe($shop->id)->and($job->fresh()->status->value)->toBe('ordered');
    expect(collect(app(PrintProductionAction::class)->queue(false, 'en', $shop->id))->pluck('id')->all())->toBe([$job->id])
        ->and(app(PrintProductionAction::class)->queue())->toBe([]);
});

it('sends a quote for a shop\'s print job to the shop, not the office (BACKLOG C26)', function () {
    [$shop] = pp7cDesk();
    $owner = User::factory()->create();
    \App\Domains\Bookshop\Models\VendorMember::query()->create(['vendor_id' => $shop->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    $school = User::factory()->create();
    $save = app(SavePrintJobAction::class);
    $job = $save->printer(pp7cJob($school), $shop->id, 'en');
    $job = $save->update($job, ['sheets' => 12], 'en');
    $proof = app(ProofPrintJobAction::class);
    $job = $proof->approve($proof->proof($job, $school->id, 'en'), 'en');
    printWeb()->actingAs($school)->post(route('public.shop.print.cart', $job->id))->assertSessionHasNoErrors();

    $quote = app(\App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction::class)->request($school->id, $shop->slug, ['organisation' => 'Hiriya School']);
    $line = $quote->items()->sole();
    expect($quote->vendor_id)->toBe($shop->id)->and($line->print_job_id)->toBe($job->id);

    // Not on the office's print quotes; on the shop's own.
    printWeb()->actingAs(printOffice())->get(route('admin.bookshop.print.quotes'))->assertInertia(fn (Assert $page) => $page->has('quotes', 0));
    printWeb()->actingAs($owner)->get(route('vendor.quotes.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('quotes.0.items.0.print_job_id', $job->id));
    printWeb()->actingAs($owner)->post(route('vendor.quotes.quote', $quote->id), ['prices' => [$line->id => 1.50], 'valid_days' => 14])->assertSessionHasNoErrors();
    expect((float) $quote->fresh()->quoted_total)->toBe(18.0);

    app(\App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction::class)->accept($school->id, $quote->number);
    $item = \App\Domains\Bookshop\Models\CartItem::query()->where('print_job_id', $job->id)->sole();
    expect($item->quote_item_id)->toBe($line->id)->and(\App\Domains\Bookshop\Support\PrintLine::quoted($item, $item->product))->toBe(1.5);
});
