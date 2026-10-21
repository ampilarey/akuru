<?php

use App\Domains\Bookshop\Actions\Print\ApproveVendorPrintAction;
use App\Domains\Bookshop\Actions\Print\PrintProductionAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\PrintFinishingOption;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PRINT_PRODUCTS_PLAN PP7b (PD10b, PD10e, STATUS §5ui): a printing shop's
 * own desk on /vendor/print — its own papers and finishings, and the paid
 * print orders placed with it. Nobody else's, and the office's queue keeps
 * to Akuru's.
 */
uses(RefreshDatabase::class);

function pp7bShop(string $slug = 'fitrah'): array
{
    $shop = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active', 'commission_rate' => 10]);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $shop->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);

    return [$shop, $owner];
}

/** A paid order at `$vendor` carrying one print job, ready for its queue. */
function pp7bPaidJob(Vendor $vendor, ?int $paperId = null): PrintJob
{
    $customer = User::factory()->create();
    $paperId ??= PrintPaperOption::query()->ofPrinter($vendor->id === PrintDesk::vendor()?->id ? null : $vendor->id)->value('id');
    $job = PrintJob::query()->create(['user_id' => $customer->id, 'print_paper_option_id' => $paperId, 'design' => ['tag' => ['w' => 95, 'h' => 60], 'elements' => []], 'sheets' => 2, 'status' => 'ordered', 'source' => 'template']);
    $n = 'AK-2026-'.str_pad((string) $job->id, 6, '0', STR_PAD_LEFT);
    $checkout = BookshopCheckout::query()->create(['number' => $n, 'user_id' => $customer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['name' => 'A'], 'subtotal' => 6, 'discount' => 0, 'delivery_total' => 0, 'total' => 6, 'currency' => 'MVR', 'paid_at' => now()]);
    $order = Order::query()->create(['number' => $n.'-'.$vendor->code, 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $customer->id, 'status' => 'paid', 'delivery_kind' => 'collect_vendor', 'delivery_name' => 'Collect', 'address_snapshot' => ['name' => 'A'], 'subtotal' => 6, 'total' => 6, 'currency' => 'MVR', 'paid_at' => now()]);
    $product = PrintProducts::productFor('name_tags', $vendor);
    OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'print_job_id' => $job->id, 'title' => 'Name tags', 'unit_price' => 6, 'quantity' => 1, 'line_total' => 6, 'tax_class' => 'zero_rated', 'tax_amount' => 0]);

    return $job;
}

it('gives a shop its own papers and finishings, and keeps them its own', function () {
    PrintDesk::ensure();
    [$shop, $owner] = pp7bShop();
    [$other] = pp7bShop('noor');
    $theirs = PrintPaperOption::query()->create(['vendor_id' => $other->id, 'name' => 'Noor gloss', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 2, 'is_active' => true]);
    $akuru = PrintPaperOption::query()->ofPrinter(null)->firstOrFail();

    printWeb()->actingAs(User::factory()->create())->get(route('vendor.print.index'))->assertForbidden();
    printWeb()->actingAs($owner)->post(route('vendor.print.paper'), ['name' => 'Fitrah plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 2.5, 'is_active' => true])->assertSessionHasNoErrors();
    printWeb()->actingAs($owner)->post(route('vendor.print.finishing'), ['name' => 'Fitrah cut', 'price' => 0.5, 'is_active' => true])->assertSessionHasNoErrors();
    $mine = PrintPaperOption::query()->where('name', 'Fitrah plain')->sole();
    expect($mine->vendor_id)->toBe($shop->id)
        ->and(PrintFinishingOption::query()->where('name', 'Fitrah cut')->sole()->vendor_id)->toBe($shop->id);

    // Neither Akuru's paper nor another shop's is this shop's to change.
    printWeb()->actingAs($owner)->post(route('vendor.print.paper', $akuru->id), ['name' => 'Taken', 'kind' => 'plain', 'price_bw' => 1, 'price_colour' => 1])->assertNotFound();
    printWeb()->actingAs($owner)->post(route('vendor.print.paper', $theirs->id), ['name' => 'Taken', 'kind' => 'plain', 'price_bw' => 1, 'price_colour' => 1])->assertNotFound();
    expect($theirs->fresh()->name)->toBe('Noor gloss')->and($akuru->fresh()->name)->not->toBe('Taken');

    // Not yet approved: the desk says so, and the shell offers no link.
    printWeb()->actingAs($owner)->get(route('vendor.print.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Bookshop/VendorPrint')->where('prints', false)->where('vendor_prints', false)
            ->where('papers', fn ($papers) => collect($papers)->pluck('name')->all() === ['Fitrah plain'])
            ->where('finishing.0.name', 'Fitrah cut')->has('t.vendor_print_not_approved')->has('shop.print_desk_title'));
    app(ApproveVendorPrintAction::class)->execute($shop, true);
    printWeb()->actingAs($owner)->get(route('vendor.print.index'))->assertInertia(fn (Assert $page) => $page->where('prints', true)->where('vendor_prints', true));

    $csv = printWeb()->actingAs($owner)->get(route('vendor.print.export', ['list' => 'papers']))->assertOk()->streamedContent();
    expect($csv)->toContain('Fitrah plain')->not->toContain('Noor gloss')->not->toContain($akuru->name);
});

it('queues each printer\'s own orders: the shop sees its jobs, the office Akuru\'s', function () {
    [$akuru] = PrintDesk::ensure();
    [$shop, $owner] = pp7bShop();
    [$other, $otherOwner] = pp7bShop('noor');
    foreach ([$shop, $other] as $v) {
        PrintPaperOption::query()->create(['vendor_id' => $v->id, 'name' => $v->name.' plain', 'kind' => 'plain', 'sheet_size' => 'a4', 'price_bw' => 1, 'price_colour' => 3, 'is_active' => true]);
        app(ApproveVendorPrintAction::class)->execute($v, true);
    }
    $shopJob = pp7bPaidJob($shop);
    $otherJob = pp7bPaidJob($other);
    $akuruJob = pp7bPaidJob($akuru);
    $desk = app(PrintProductionAction::class);

    expect(collect($desk->queue())->pluck('id')->all())->toBe([$akuruJob->id])
        ->and(collect($desk->queue(false, 'en', $shop->id))->pluck('id')->all())->toBe([$shopJob->id]);
    printWeb()->actingAs($owner)->get(route('vendor.print.index'))->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.id', $shopJob->id));

    // A step on its own job works; on another's, nothing is there.
    printWeb()->actingAs($owner)->post(route('vendor.print.jobs.step', [$shopJob->id, 'start']))->assertSessionHasNoErrors();
    expect($shopJob->fresh()->status->value)->toBe('printing');
    printWeb()->actingAs($owner)->post(route('vendor.print.jobs.step', [$otherJob->id, 'start']))->assertNotFound();
    printWeb()->actingAs($owner)->post(route('vendor.print.jobs.step', [$akuruJob->id, 'start']))->assertNotFound();
    printWeb()->actingAs($owner)->get(route('vendor.print.jobs.file', $otherJob->id))->assertNotFound();
    expect($otherJob->fresh()->status->value)->toBe('ordered')->and($akuruJob->fresh()->status->value)->toBe('ordered');

    $csv = printWeb()->actingAs($otherOwner)->get(route('vendor.print.export'))->assertOk()->streamedContent();
    expect($csv)->toContain((string) $otherJob->id)->not->toContain('Fitrah');
});
