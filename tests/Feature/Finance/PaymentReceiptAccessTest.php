<?php

use App\Domains\Courses\Actions\StartCourseCheckoutAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Finance\Actions\BuildPaymentReceiptAction;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §45 "Policies and Permissions" lists **Manage payments** among the
 * areas the backend must enforce. This is the screen where a family reads
 * back what they paid, and it never worked.
 *
 * The gate read:
 *
 *     if (! in_array($payment->status, ['paid', 'completed'])) { abort(404); }
 *
 * and **neither value exists**. `payments.status` is an enum of
 * `initiated, pending, confirmed, failed, cancelled, expired, refunded`, and
 * the only status written for money received is `confirmed` — by
 * `PaymentService` on webhook confirmation and by `RecordManualPaymentAction`
 * for money taken at the office. So every receipt for every payment ever made
 * returned 404, to the payer and to staff alike, and the ownership rule above
 * it never ran.
 *
 * Nothing in the product links to the route, which is why no walk found it: the
 * page could only be reached by typing the URL. That is also what makes the
 * §45 audit worth doing route by route — an access rule nobody can reach is
 * indistinguishable from one that works.
 *
 * Two further defects were hidden behind the 404, and are fixed here because
 * they are what the page does the moment it opens:
 *
 *   - the line-item table loops `$payment->items`, and an engine payment has
 *     none, so the receipt rendered an **empty table above a total**;
 *   - the method line read `BML {{ $payment->provider }}`, printing "BML bml"
 *     for a gateway payment and "BML manual" for cash — the one case where it
 *     is certainly not BML.
 */
uses(RefreshDatabase::class);

function receiptPayment(array $overrides = []): Payment
{
    return Payment::query()->create(array_merge([
        'user_id' => User::factory()->create()->id,
        'amount' => 250,
        'currency' => 'MVR',
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-RCPT-'.uniqueFixtureSuffix(),
        'paid_at' => now(),
        'confirmed_at' => now(),
    ], $overrides));
}

it('lets the payer read their own receipt', function () {
    // The whole feature, in one assertion. This returned 404 for every payment
    // in the system before.
    $payment = receiptPayment();
    $payer = User::query()->find($payment->user_id);

    $this->actingAs($payer)
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertSee('RECEIPT', false);
});

it('refuses a stranger', function () {
    $payment = receiptPayment();

    $this->actingAs(User::factory()->create())
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertForbidden();
});

it('lets finance staff read any receipt', function () {
    // §45 asks for permissions, not roles. This used to be a `hasAnyRole`
    // list that included `supervisor`, who does not hold `finance.manage` —
    // a deliberate narrowing, since an academic supervisor has no reason to
    // read a family's payment receipts.
    $payment = receiptPayment();

    $this->actingAs(actingPeopleAdmin(['finance.manage']))
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertOk();
});

it('refuses staff who only hold an academic role', function () {
    $payment = receiptPayment();

    $this->actingAs(actingPeopleAdmin(['courses.manage']))
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertForbidden();
});

it('gives no receipt for money that has not arrived', function () {
    foreach (['initiated', 'pending', 'failed', 'cancelled', 'expired'] as $status) {
        $payment = receiptPayment(['status' => $status]);
        $payer = User::query()->find($payment->user_id);

        $this->actingAs($payer)
            ->withoutLocalizationMiddleware()
            ->get("/payments/{$payment->id}/receipt")
            ->assertNotFound();
    }
});

it('gives no receipt for money that was given back', function () {
    // A receipt asserts money received and kept. Handing one out for a refund
    // produces a document someone can later wave at an office.
    $payment = receiptPayment(['status' => 'refunded']);
    $payer = User::query()->find($payment->user_id);

    $this->actingAs($payer)
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertNotFound();
});

it('gates on the vocabulary the system actually writes', function () {
    // The defect in one line: the old gate named two statuses the column
    // cannot hold.
    $build = app(BuildPaymentReceiptAction::class);

    expect($build->isReceiptable(receiptPayment(['status' => 'confirmed'])))->toBeTrue()
        ->and($build->isReceiptable(receiptPayment(['status' => 'pending'])))->toBeFalse();
});

it('names what was bought even when the payment has no line items', function () {
    // An engine payment creates no `payment_items`, so the receipt table was
    // empty above its total. SPEC §38's `course_id` is what lets the payment
    // answer for itself.
    $payer = User::factory()->create();
    makeStudent(['user_id' => $payer->id, 'first_name' => 'Receipt', 'last_name' => 'Reader']);
    makeRegistrationStudent(['user_id' => $payer->id]);
    $course = Course::factory()->create([
        'title' => 'Tajweed Foundations',
        'registration_fee_amount' => 250,
        'requires_admin_approval' => false,
        'workflow_status' => 'published',
        'status' => 'open',
    ]);

    app(StartCourseCheckoutAction::class)->execute($payer->id, $course->id);
    $payment = Payment::query()->latest('id')->first();
    $payment->update(['status' => 'confirmed', 'confirmed_at' => now(), 'paid_at' => now()]);

    expect($payment->items)->toHaveCount(0);

    $this->actingAs($payer)
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertOk()
        ->assertSee('Tajweed Foundations', false);
});

it('does not call cash at the office a BML payment', function () {
    $payment = receiptPayment(['provider' => 'manual', 'payment_method' => 'cash']);
    $payer = User::query()->find($payment->user_id);

    $response = $this->actingAs($payer)
        ->withoutLocalizationMiddleware()
        ->get("/payments/{$payment->id}/receipt")
        ->assertOk();

    $response->assertSee('Recorded at the institute', false)
        ->assertSee('Cash', false)
        ->assertDontSee('BML manual', false);
});

it('separates the gateway from the instrument, as §38 does', function () {
    $build = app(BuildPaymentReceiptAction::class);

    expect($build->execute(receiptPayment())['gateway'])->toBe('BML Connect')
        ->and($build->execute(receiptPayment())['method'])->toBeNull()
        ->and($build->execute(receiptPayment(['provider' => 'manual', 'payment_method' => 'bank_transfer']))['method'])
        ->toBe('Bank transfer');
});
