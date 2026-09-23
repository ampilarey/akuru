<?php

use App\Domains\Finance\Actions\AllocatePaymentAction;
use App\Domains\Finance\Actions\CreatePaymentPlanAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S4 spec, test 2: *"concurrent payment race (DB transaction + lock test)"*.
 * ADR-014's consequence: *"Concurrent allocations take a row lock on the
 * invoice (`lockForUpdate`)."* Neither had a test.
 *
 * The same shape as `SeatConcurrencyTest`, for the same reason: a
 * single-threaded test cannot hold one transaction open while another
 * blocks on it without deadlocking itself, and the action commits per call,
 * so any two calls here are sequential. What would actually break is the
 * lock going missing, so that is what is asserted — the invoice read and
 * the installment read are issued FOR UPDATE. Delete `lockForUpdate()`
 * from `AllocatePaymentAction` and this fails at once. True parallel
 * verification needs a second process and belongs in an integration
 * harness; recorded rather than pretended.
 */
uses(RefreshDatabase::class);

it('takes a row lock on the invoice and on its installments before allocating', function () {
    $admin = actingPeopleAdmin(['finance.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $invoice = makeSchoolInvoice($admin->id, makeStudent()->id, $year->id, 500);
    app(CreatePaymentPlanAction::class)->execute([
        'invoice_id' => $invoice->id,
        'created_by' => $admin->id,
        'installments' => [
            ['amount' => 200, 'due_date' => '2026-01-15'],
            ['amount' => 300, 'due_date' => '2026-02-15'],
        ],
    ]);

    DB::enableQueryLog();
    app(AllocatePaymentAction::class)->execute($invoice->fresh(), 150);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $invoiceRead = $queries->first(fn (string $q) => str_contains($q, 'from `invoices`') && str_contains($q, 'for update'));
    $planRead = $queries->first(fn (string $q) => str_contains($q, 'from `payment_plans`') && str_contains($q, 'for update'));
    $installmentsRead = $queries->first(fn (string $q) => str_contains($q, 'from `payment_plan_installments`') && str_contains($q, 'for update'));

    expect($invoiceRead)->not->toBeNull()
        ->and($planRead)->not->toBeNull()
        ->and($installmentsRead)->not->toBeNull();
});

it('gives two arrivals at the last of the balance exactly one success', function () {
    // Deliberately not called a concurrency test: the two arrivals are
    // sequential, because the action commits per call. It proves the balance
    // holds once the first is in; the lock is covered above.
    $admin = actingPeopleAdmin(['finance.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $invoice = makeSchoolInvoice($admin->id, makeStudent()->id, $year->id, 500);

    app(AllocatePaymentAction::class)->execute($invoice->fresh(), 400);
    app(AllocatePaymentAction::class)->execute($invoice->fresh(), 100);

    expect(fn () => app(AllocatePaymentAction::class)->execute($invoice->fresh(), 100))
        ->toThrow(ValidationException::class);
    expect((float) $invoice->fresh()->paid_amount)->toBe(500.0);
});
