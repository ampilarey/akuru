<?php

use App\Domains\Bookshop\Actions\Print\PrintProductionAction;
use App\Domains\Bookshop\Actions\Print\ProofPrintJobAction;
use App\Domains\Bookshop\Actions\Print\SavePrintJobAction;
use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Vendor\FulfilVendorOrderAction;
use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\PrintJobReprint;
use App\Domains\Bookshop\Models\PrintPaperOption;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A design bought and printed (NAME_TAGS_PLAN §3.8, §4.8, §11 NT4, STATUS
 * §5rr): the approved job into the basket at its held price, checked out
 * with Akuru's two deliveries only, put on the desk's queue only when the
 * money is confirmed (rule 12), and worked through printing, finished,
 * reprint and problem — each written on the order for the customer.
 */
uses(RefreshDatabase::class);

/** The desk on, its product on sale, one paper priced, fakes for Chrome. */
function nt4Desk(): PrintPaperOption
{
    PrintDesk::ensure();
    PrintDesk::product()->update(['status' => 'active']);
    PrintDesk::vendor()->update(['status' => 'active']);
    $paper = PrintPaperOption::query()->orderBy('sort_order')->orderBy('id')->firstOrFail();
    $paper->update(['price_colour' => 3.00, 'price_bw' => 1.50, 'is_active' => true]);
    config(['print.desk.enabled' => true, 'print.desk.min_sheets' => 1, 'print.desk.tiers' => [], 'print.desk.lead_days' => 3]);
    printHostCanPrint();
    Storage::fake('local');

    return $paper;
}

/** A customer's job, filled, proofed and approved: 3 sheets at 3.00 = 9.00. */
function nt4ApprovedJob(User $customer, int $sheets = 3): PrintJob
{
    $template = PrintTemplate::query()->where('kind', 'name_tag')->orderBy('sort_order')->firstOrFail();
    $save = app(SavePrintJobAction::class);
    $job = $save->start($template, $customer->id, null, 'en');
    $job = $save->update($job, ['entry' => ['mode' => 'same', 'row' => ['name' => 'Aishath', 'class' => '3B']], 'sheets' => $sheets, 'colour' => true], 'en');
    $proof = app(ProofPrintJobAction::class);
    $job = $proof->proof($job, $customer->id, 'en');

    return $proof->approve($job, 'en');
}

function nt4Web(?User $user = null)
{
    $web = test()->withoutLocalizationMiddleware();

    return $user === null ? $web : $web->actingAs($user);
}

function nt4Bml(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/'.$payment->id);
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: (string) $request->input('reference'), providerReference: 'BML-NT4', status: 'completed', rawPayload: $request->all(), isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function nt4Address(array $overrides = []): array
{
    return $overrides + ['recipient_name' => 'Aishath', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example'];
}

/** The checkout's key for one of the print basket's deliveries. */
function nt4Delivery(User $customer, string $kind): array
{
    $options = app(\App\Domains\Bookshop\Actions\Checkout\PrepareCheckoutAction::class)->execute($customer->id, Cart::query()->where('user_id', $customer->id)->first())['basket']['groups'][0]['delivery_options'];

    return [PrintDesk::vendor()->slug => (string) collect($options)->firstWhere('kind', $kind)['key']];
}

/** The job in the customer's basket, checked out by wallet for collection: a paid order. */
function nt4PaidOrder(User $customer, PrintJob $job): Order
{
    nt4Web($customer)->post(route('public.shop.print.cart', $job->id))->assertSessionHasNoErrors();
    app(CreditWalletAction::class)->execute($customer->id, 100, 'admin', null, 'Top-up');
    nt4Web($customer)->post(route('public.shop.checkout.store'), nt4Address(['delivery' => nt4Delivery($customer, 'collect_akuru'), 'payment_method' => 'wallet']))
        ->assertSessionHasNoErrors();

    return Order::query()->latest('id')->firstOrFail();
}

it('puts an approved design in the basket at its held price, once, and nothing else', function () {
    nt4Desk();
    $customer = User::factory()->create();
    $job = nt4ApprovedJob($customer);

    // Someone else's design is not theirs to buy; a draft is not for sale.
    nt4Web(User::factory()->create())->post(route('public.shop.print.cart', $job->id))->assertNotFound();
    $draft = app(SavePrintJobAction::class)->copy($job, $customer->id, null, 'en');
    nt4Web($customer)->from(route('public.shop.print.job', $draft->id))->post(route('public.shop.print.cart', $draft->id))
        ->assertSessionHasErrors(['job' => __('print.error_cart_reapprove')]);

    nt4Web($customer)->post(route('public.shop.print.cart', $job->id))->assertRedirect(route('public.shop.cart'))->assertSessionHas('success', __('print.flash_added_to_cart'));
    nt4Web($customer)->post(route('public.shop.print.cart', $job->id))->assertRedirect();
    $line = CartItem::query()->where('print_job_id', $job->id)->sole();
    expect($line->quantity)->toBe(3)->and($line->product_id)->toBe(PrintDesk::product()->id);

    // The basket shows the design, its sheets and the held price — and no way to change the count or save it for later.
    nt4Web($customer)->get(route('public.shop.cart'))->assertOk()
        ->assertSee('data-testid="cart-print-design"', false)->assertSee(__('print.cart_sheets_note', ['sheets' => 3]))
        ->assertSee('MVR 9.00')->assertDontSee('data-testid="cart-save-later"', false);
    nt4Web($customer)->from(route('public.shop.cart'))->post(route('public.shop.cart.update', $line->id), ['quantity' => 4])
        ->assertSessionHasErrors(['quantity' => __('print.error_cart_quantity')]);
    nt4Web($customer)->from(route('public.shop.cart'))->post(route('public.shop.cart.save', $line->id))
        ->assertSessionHasErrors(['product' => __('print.error_cart_save_later')]);
    expect($line->fresh()->quantity)->toBe(3)->and($line->fresh()->saved_at)->toBeNull();

    // The design's page says it can be bought.
    nt4Web($customer)->get(route('public.shop.print.job', $job->id))->assertInertia(fn (Assert $page) => $page->where('job.buyable', true)->where('job.order', null));

    // Removing it is allowed.
    nt4Web($customer)->post(route('public.shop.cart.update', $line->id), ['quantity' => 0])->assertSessionHasNoErrors();
    expect(CartItem::query()->where('print_job_id', $job->id)->exists())->toBeFalse();
});

it('offers Akuru\'s two deliveries only, ready in the lead time, and copies the approved price onto the order', function () {
    nt4Desk();
    $customer = User::factory()->create();
    $job = nt4ApprovedJob($customer);
    nt4Web($customer)->post(route('public.shop.print.cart', $job->id));

    nt4Web($customer)->get(route('public.shop.checkout'))->assertOk()->assertSee(__('print.ready_in_days', ['days' => 3]));
    $options = app(\App\Domains\Bookshop\Actions\Checkout\PrepareCheckoutAction::class)->execute($customer->id, Cart::query()->where('user_id', $customer->id)->first())['basket']['groups'][0]['delivery_options'];
    expect(collect($options)->pluck('kind')->sort()->values()->all())->toBe(['akuru_courier', 'collect_akuru'])
        ->and(collect($options)->pluck('handling_days')->unique()->all())->toBe([3]);

    // Another delivery is refused; cash is refused while the desk takes no cash.
    nt4Web($customer)->post(route('public.shop.checkout.store'), nt4Address(['delivery' => [PrintDesk::vendor()->slug => 't0'], 'payment_method' => 'wallet']))->assertSessionHasErrors('delivery');

    $order = nt4PaidOrder($customer, $job);
    $line = OrderItem::query()->where('order_id', $order->id)->sole();
    expect($line->print_job_id)->toBe($job->id)->and((int) $line->quantity)->toBe(3)
        ->and((float) $line->line_total)->toBe(9.0)->and((float) $line->unit_price)->toBe(3.0)
        ->and($order->status->value)->toBe('paid')->and($order->delivery_kind->value)->toBe('collect_akuru')
        ->and($job->fresh()->status)->toBe(PrintJobStatus::Ordered);

    // Bought once: it cannot go in the basket again.
    nt4Web($customer)->from(route('public.shop.print.job', $job->id))->post(route('public.shop.print.cart', $job->id))->assertSessionHasErrors('job');
    nt4Web($customer)->get(route('public.shop.print.job', $job->id))->assertInertia(fn (Assert $page) => $page->where('job.order.number', $order->number)->where('job.buyable', false));
});

it('queues a card-paid job only when the bank\'s webhook confirms it (rule 12)', function () {
    nt4Desk();
    nt4Bml();
    $customer = User::factory()->create();
    $job = nt4ApprovedJob($customer);
    nt4Web($customer)->post(route('public.shop.print.cart', $job->id));
    nt4Web($customer)->post(route('public.shop.checkout.store'), nt4Address(['delivery' => nt4Delivery($customer, 'akuru_courier'), 'payment_method' => 'card']))->assertRedirect();

    // Back from the bank, not yet confirmed: not on the desk.
    expect($job->fresh()->status)->toBe(PrintJobStatus::Approved)->and(app(PrintProductionAction::class)->queue())->toBe([]);

    $payment = Payment::query()->firstOrFail();
    $payment->update(['status' => 'confirmed']);
    event(new PaymentConfirmed($payment->fresh()));
    event(new PaymentConfirmed($payment->fresh()));
    expect($job->fresh()->status)->toBe(PrintJobStatus::Ordered)->and(BookshopCheckout::query()->firstOrFail()->status->value)->toBe('paid')
        ->and(app(PrintProductionAction::class)->queue())->toHaveCount(1);
});

it('refuses cash for printing by default, and a lapsed or un-approved design at checkout', function () {
    nt4Desk();
    \App\Domains\Bookshop\Models\Vendor::query()->whereKey(PrintDesk::vendor()->id)->update(['cod_enabled' => true]);
    $customer = User::factory()->create();
    $job = nt4ApprovedJob($customer);
    nt4Web($customer)->post(route('public.shop.print.cart', $job->id));

    nt4Web($customer)->post(route('public.shop.checkout.store'), nt4Address(['delivery' => nt4Delivery($customer, 'collect_akuru'), 'payment_method' => 'cash_on_delivery']))
        ->assertSessionHasErrors(['payment_method' => __('print.error_cod_print')]);

    // The held price lapses: the basket says so and checkout refuses.
    $snapshot = $job->approved_price_snapshot;
    $snapshot['held_until'] = now()->subMinute()->toIso8601String();
    $job->forceFill(['approved_price_snapshot' => $snapshot])->save();
    nt4Web($customer)->get(route('public.shop.cart'))->assertSee(__('print.error_cart_price_lapsed'));
    app(CreditWalletAction::class)->execute($customer->id, 100, 'admin', null, 'Top-up');
    nt4Web($customer)->post(route('public.shop.checkout.store'), nt4Address(['delivery' => nt4Delivery($customer, 'collect_akuru'), 'payment_method' => 'wallet']))
        ->assertSessionHasErrors(['cart' => __('print.error_cart_price_lapsed')]);
    expect(Order::query()->count())->toBe(0);
});

it('works a job through the queue: printing moves the order, finished makes a collection ready, a reprint costs nothing, a problem needs attention', function () {
    nt4Desk();
    $customer = User::factory()->create();
    $office = printOffice();
    $job = nt4ApprovedJob($customer);
    $order = nt4PaidOrder($customer, $job);

    // The queue is the office's.
    nt4Web($customer)->get(route('admin.bookshop.print.queue'))->assertForbidden();
    nt4Web($customer)->post(route('admin.bookshop.print.jobs.step', [$job->id, 'start']))->assertForbidden();
    nt4Web($office)->get(route('admin.bookshop.print.queue'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Bookshop/PrintQueue')
        ->has('rows', 1)->where('rows.0.id', $job->id)->where('rows.0.status', 'ordered')->where('rows.0.order', $order->number)->where('rows.0.sheets', 3)->where('rows.0.file', true)
        ->where('rows.0.due', PrintProductionAction::due($job->fresh()->ordered_at, 3)->toDateString()));
    $csv = nt4Web($office)->get(route('admin.bookshop.print.queue.export'))->assertOk()->streamedContent();
    expect($csv)->toContain($order->number);

    // The order cannot be made ready while its printing is not finished.
    $scope = app(ResolveVendorScopeAction::class)->forOffice((int) $order->vendor_id, $office->id);
    expect(fn () => app(FulfilVendorOrderAction::class)->advance($scope, $order->id, 'ready'))->toThrow(ValidationException::class);

    // Finishing before printing is refused.
    nt4Web($office)->from(route('admin.bookshop.print.queue'))->post(route('admin.bookshop.print.jobs.step', [$job->id, 'finish']))->assertSessionHasErrors(['job' => __('print.error_queue_step')]);

    nt4Web($office)->post(route('admin.bookshop.print.jobs.step', [$job->id, 'start']))->assertSessionHasNoErrors()->assertSessionHas('success', __('print.flash_queue_start'));
    expect($job->fresh()->status)->toBe(PrintJobStatus::Printing)->and($job->fresh()->printed_by)->toBe($office->id)->and($order->fresh()->status->value)->toBe('processing');

    nt4Web($office)->post(route('admin.bookshop.print.jobs.step', [$job->id, 'finish']))->assertSessionHasNoErrors();
    expect($job->fresh()->status)->toBe(PrintJobStatus::Finished)->and($order->fresh()->status->value)->toBe('ready');

    // A reprint needs a reason, is recorded, and charges nothing.
    $total = (string) $order->fresh()->total;
    nt4Web($office)->from(route('admin.bookshop.print.queue'))->post(route('admin.bookshop.print.jobs.step', [$job->id, 'reprint']), ['reason' => ''])->assertSessionHasErrors('reason');
    nt4Web($office)->post(route('admin.bookshop.print.jobs.step', [$job->id, 'reprint']), ['reason' => 'Smudged ink'])->assertSessionHasNoErrors();
    expect($job->fresh()->status)->toBe(PrintJobStatus::Printing)->and(PrintJobReprint::query()->where('print_job_id', $job->id)->value('reason'))->toBe('Smudged ink')
        ->and((string) $order->fresh()->total)->toBe($total)->and(\App\Domains\Bookshop\Models\OrderRefund::query()->count())->toBe(0);
    nt4Web($office)->post(route('admin.bookshop.print.jobs.step', [$job->id, 'finish']))->assertSessionHasNoErrors();

    // A problem puts the order in front of the office.
    nt4Web($office)->post(route('admin.bookshop.print.jobs.step', [$job->id, 'problem']), ['note' => 'Paper ran out'])->assertSessionHasNoErrors();
    expect($order->fresh()->status->value)->toBe('needs_attention');

    // Every step is on the order, and the customer's My orders reads it (in Arabic in the walk).
    expect(OrderEvent::query()->where('order_id', $order->id)->pluck('type')->all())->toContain('print_printing', 'print_finished', 'print_reprint', 'print_problem');
    nt4Web($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()
        ->assertSee(__('shop.event_print_finished'))->assertSee('data-testid="order-print-design"', false);
    foreach (['print_printing', 'print_finished', 'print_reprint', 'print_problem'] as $type) {
        expect(__('shop.event_'.$type, [], 'ar'))->not->toBe(__('shop.event_'.$type, [], 'en'))->and(__('shop.event_'.$type, [], 'dv'))->not->toBe('shop.event_'.$type);
    }
});

it('cancels an order\'s job with it, before printing', function () {
    nt4Desk();
    $customer = User::factory()->create();
    $job = nt4ApprovedJob($customer);
    $order = nt4PaidOrder($customer, $job);

    app(\App\Domains\Bookshop\Actions\Orders\CancelOrderAction::class)->execute($order, $customer->id, 'Wrong class names', true);
    expect($job->fresh()->status)->toBe(PrintJobStatus::Cancelled)->and(app(PrintProductionAction::class)->queue())->toBe([]);
});

it('counts the due date in working days, skipping Friday and Saturday', function () {
    // Thursday + 2 working days = Monday.
    expect(PrintProductionAction::due(\Illuminate\Support\Carbon::parse('2026-10-15 09:00', 'Indian/Maldives'), 2)->toDateString())->toBe('2026-10-19')
        ->and(PrintProductionAction::due(\Illuminate\Support\Carbon::parse('2026-10-12 09:00', 'Indian/Maldives'), 0)->toDateString())->toBe('2026-10-12');
});
