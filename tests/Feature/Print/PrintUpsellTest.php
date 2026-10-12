<?php

use App\Domains\Bookshop\Actions\Print\ProofPrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintSubjectSet;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintSalesReport;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Name tags beside the books and again later (NAME_TAGS_PLAN §3.9, §11 NT7,
 * STATUS §5ru): the card on a school book list and after *Add all*, which
 * starts a design with the grade's subjects chosen; *Order again* as a copy
 * at today's price; the office's print sales by paper and month, with CSV.
 */
uses(RefreshDatabase::class);

function nt7Desk(bool $on = true): PrintPaperOption
{
    PrintDesk::ensure();
    PrintDesk::product()->update(['status' => 'active']);
    PrintDesk::vendor()->update(['status' => 'active']);
    $paper = PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $paper->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    config(['print.desk.enabled' => $on, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => []]);
    printHostCanPrint();
    Storage::fake('local');

    return $paper;
}

function nt7BookList(string $grade = 'Grade 3'): VendorCollection
{
    $shop = Vendor::query()->create(['name' => 'List Shop', 'slug' => 'nt7-shop', 'code' => 'NT7', 'status' => 'active']);
    $book = Product::query()->create(['vendor_id' => $shop->id, 'slug' => 'nt7-maths', 'title' => 'Maths 3', 'price' => 50, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 20, 'status' => 'active', 'visibility' => 'shop']);
    $list = VendorCollection::query()->create(['vendor_id' => $shop->id, 'slug' => 'grade-3', 'name' => 'Grade 3 list', 'book_list' => true, 'school' => 'Ghiyasuddin School', 'grade' => $grade, 'is_active' => true]);
    $list->products()->attach($book->id, ['sort_order' => 0, 'quantity' => 1]);

    return $list;
}

it('offers name tags on a school book list and after Add all, only while the desk sells them', function () {
    nt7Desk(on: false);
    nt7BookList();
    printWeb()->get(route('public.shop.vendor.collection', ['nt7-shop', 'grade-3']))->assertOk()->assertDontSee('data-testid="print-upsell"', false);

    config(['print.desk.enabled' => true]);
    printWeb()->get(route('public.shop.vendor.collection', ['nt7-shop', 'grade-3']))->assertOk()
        ->assertSee('data-testid="print-upsell"', false)->assertSee(__('print.upsell_body_grade', ['grade' => 'Grade 3']));

    printWeb()->post(route('public.shop.book-list.add', ['nt7-shop', 'grade-3']))->assertRedirect(route('public.shop.cart'))->assertSessionHas('print_upsell', 'Grade 3');
    printWeb()->get(route('public.shop.cart'))->assertOk()->assertSee('data-testid="print-upsell-start"', false);
});

it('starts a design from the card with the grade\'s subject set chosen', function () {
    nt7Desk();
    $set = PrintSubjectSet::query()->create(['label' => 'Grade 3', 'label_dv' => 'ގްރޭޑް 3', 'subjects' => [['en' => 'Maths', 'dv' => 'ހިސާބު', 'ar' => 'رياضيات'], ['en' => 'English', 'dv' => 'އިނގިރޭސި', 'ar' => 'إنجليزي']], 'is_active' => true, 'sort_order' => 0]);
    $template = PrintTemplate::query()->where('status', 'active')->get()->first(fn (PrintTemplate $t) => in_array('subject', \App\Domains\Bookshop\Support\PrintDesign::fieldKeys((array) $t->design), true));
    expect($template)->not->toBeNull();

    printWeb()->post(route('public.shop.print.start'), ['template' => $template->id, 'grade' => 'grade 3'])->assertRedirect();
    $job = PrintJob::query()->latest('id')->firstOrFail();
    expect($job->print_subject_set_id)->toBe($set->id)->and($job->entry['subjects']['items'])->toHaveCount(2);

    // A grade no set names starts the design without subjects.
    printWeb()->post(route('public.shop.print.start'), ['template' => $template->id, 'grade' => 'Grade 9'])->assertRedirect();
    expect(PrintJob::query()->latest('id')->firstOrFail()->print_subject_set_id)->toBeNull();
    expect(SavePrintJobAction::subjectSetFor('ގްރޭޑް 3')?->id)->toBe($set->id);
});

it('orders a design again as a copy at today\'s price, to proof again', function () {
    $paper = nt7Desk();
    $customer = User::factory()->create();
    $template = PrintTemplate::query()->where('kind', 'name_tag')->orderBy('sort_order')->firstOrFail();
    $save = app(SavePrintJobAction::class);
    $job = $save->update($save->start($template, $customer->id, null, 'en'), ['entry' => ['mode' => 'same', 'row' => ['name' => 'Ali']], 'sheets' => 2, 'colour' => true, 'paper_id' => $paper->id], 'en');
    $proof = app(ProofPrintJobAction::class);
    $job = $proof->approve($proof->proof($job, $customer->id, 'en'), 'en');
    expect((float) $job->approved_price_snapshot['total'])->toBe(6.0);

    $paper->update(['price_colour' => 4.00]);
    printWeb()->actingAs($customer)->post(route('public.shop.print.copy', $job->id), ['again' => 1])->assertSessionHas('success', __('print.flash_order_again'));
    $again = PrintJob::query()->latest('id')->firstOrFail();
    expect($again->id)->not->toBe($job->id)->and($again->status->value)->toBe('draft')->and((float) $again->price_total)->toBe(8.0)
        ->and($again->entry['row']['name'])->toBe('Ali')->and((float) $job->fresh()->approved_price_snapshot['total'])->toBe(6.0);

    printWeb()->actingAs($customer)->get(route('public.shop.print.designs'))->assertInertia(fn (Assert $page) => $page->where('designs', fn ($d) => collect($d)->pluck('status')->sort()->values()->all() === ['approved', 'draft']));
});

it('reports print sales by paper and month for the office, with a CSV', function () {
    $paper = nt7Desk();
    $customer = User::factory()->create();
    $vendor = PrintDesk::vendor();
    $job = PrintJob::query()->create(['user_id' => $customer->id, 'print_paper_option_id' => $paper->id, 'design' => ['tag' => ['w' => 95, 'h' => 60], 'elements' => []], 'sheets' => 3, 'status' => 'finished', 'source' => 'template']);
    $make = function (string $status, ?string $paidAt, float $total) use ($vendor, $customer, $job) {
        $order = Order::query()->forceCreate(['bookshop_checkout_id' => \App\Domains\Bookshop\Models\BookshopCheckout::query()->forceCreate(['number' => 'AK-T-'.uniqid(), 'user_id' => $customer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'subtotal' => $total, 'discount' => 0, 'delivery_total' => 0, 'total' => $total, 'currency' => 'MVR', 'address_snapshot' => ['recipient_name' => 'A', 'phone' => '7700000']])->id,
            'vendor_id' => $vendor->id, 'user_id' => $customer->id, 'number' => 'AK-'.uniqid(), 'status' => $status, 'subtotal' => $total, 'discount' => 0, 'delivery_fee' => 0, 'total' => $total, 'currency' => 'MVR', 'paid_at' => $paidAt, 'address_snapshot' => ['recipient_name' => 'A', 'phone' => '7700000'], 'delivery_kind' => 'collect_akuru', 'delivery_name' => 'Collect']);
        OrderItem::query()->forceCreate(['order_id' => $order->id, 'product_id' => PrintDesk::product()->id, 'title' => 'Name tags', 'quantity' => 3, 'unit_price' => $total / 3, 'line_total' => $total, 'tax_class' => 'standard', 'tax_amount' => 0, 'print_job_id' => $job->id]);
    };
    $make('ready', now()->toDateTimeString(), 9.00);
    $make('delivered', now()->toDateTimeString(), 12.00);
    $make('cancelled', now()->toDateTimeString(), 50.00);
    $make('pending_payment', null, 70.00);

    $rows = PrintSalesReport::byPaperAndMonth();
    expect($rows)->toHaveCount(1)->and($rows[0]['month'])->toBe(now('Indian/Maldives')->format('Y-m'))->and($rows[0]['jobs'])->toBe(2)
        ->and($rows[0]['sheets'])->toBe(6)->and($rows[0]['revenue'])->toBe('21.00')->and($rows[0]['paper'])->toBe($paper->name);

    $office = printOffice();
    printWeb()->actingAs($office)->get(route('admin.bookshop.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('insights.print_sales', 1)->where('insights.print_sales.0.revenue', '21.00'));
    $csv = printWeb()->actingAs($office)->get(route('admin.bookshop.insights.print-sales.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('month,paper,jobs,sheets,revenue')->toContain('21.00');
    printWeb()->actingAs($customer)->get(route('admin.bookshop.insights.print-sales.export'))->assertForbidden();
});
