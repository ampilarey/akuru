<?php

use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentService;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * CLAUDE.md rule 12: *"Access to paid anything depends on BML **webhook**
 * confirmation, never the return URL."*
 *
 * `GET payments/bml/return` carries the middleware `['web']` and nothing else:
 * no auth, no signature, no ownership check, every value from the query
 * string. It did not set the payment's status directly — but it wrote
 * `bml_transaction_id` from `?transactionId=`, and `finalizeByReference` then
 * asked BML about **that** id and confirmed the payment if the answer was
 * "completed".
 *
 * `queryStatus` echoed its own argument back as the result's
 * `merchantReference`, so the answer always agreed with the question and
 * nothing ever compared the two. The verification was server-side and
 * verified the wrong transaction.
 *
 * ## The attack it allowed
 *
 * 1. Start a real checkout and abandon it. A pending payment now exists with
 *    `bml_transaction_id` null.
 * 2. Request `/payments/bml/return?ref=<own ref>&transactionId=<T>`, where `T`
 *    is any completed BML transaction — **most easily one of the payer's own
 *    earlier purchases**.
 * 3. The controller stores `T`, `finalizeByReference` queries `T`, BML says
 *    "completed", the payment is confirmed, `PaymentConfirmed` fires, the
 *    enrolment activates and the family is sent a receipt.
 *
 * Pay once, then confirm everything afterwards for free. The return URL was
 * not the answer, but it chose the question, which is the same thing with one
 * step in it.
 */
function bmlReturnPayment(array $overrides = []): Payment
{
    return Payment::query()->create(array_merge([
        'user_id' => User::factory()->create()->id,
        'amount' => 1500,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-RET-'.uniqueFixtureSuffix(),
    ], $overrides));
}

/**
 * A provider whose `queryStatus` answers about **whatever transaction was
 * asked about**, which is what a real gateway does and what the old fake could
 * not express: it reported the question back as the answer.
 *
 * @param  array<string, string>  $transactions  transaction id => the merchant reference that transaction belongs to
 */
function fakeBmlReturningOtherPayments(array $transactions): void
{
    app()->instance(PaymentProviderInterface::class, new class($transactions) implements PaymentProviderInterface
    {
        /** @param array<string, string> $transactions */
        public function __construct(private array $transactions) {}

        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-HOOK',
                status: 'completed',
                rawPayload: $request->all(),
                isConfirmed: true,
            );
        }

        public function queryStatus(string $reference): ?PaymentVerificationResult
        {
            // BML answers about the transaction you named, and tells you which
            // merchant reference it belongs to. It does not repeat your
            // question back at you.
            $belongsTo = $this->transactions[$reference] ?? null;

            if ($belongsTo === null) {
                return null;
            }

            return new PaymentVerificationResult(
                verified: true,
                merchantReference: $belongsTo,
                providerReference: $reference,
                status: 'completed',
                rawPayload: ['localId' => $belongsTo, 'state' => 'completed'],
                isConfirmed: true,
            );
        }
    });
}

it('refuses to confirm a payment with somebody else\'s completed transaction', function () {
    $paid = bmlReturnPayment(['status' => 'confirmed', 'confirmed_at' => now()]);
    $unpaid = bmlReturnPayment();

    // The one real transaction in the world belongs to the payment that was
    // actually paid for.
    fakeBmlReturningOtherPayments(['BML-TXN-REAL' => $paid->merchant_reference]);

    // The attack, through the public route, exactly as it would be typed.
    $this->get('/payments/bml/return?ref='.$unpaid->merchant_reference.'&transactionId=BML-TXN-REAL')
        ->assertOk();

    expect($unpaid->fresh()->status)->toBe('pending')
        ->and($unpaid->fresh()->confirmed_at)->toBeNull();
});

it('still confirms a payment from its own transaction, which is the whole point of the return url', function () {
    $payment = bmlReturnPayment();

    fakeBmlReturningOtherPayments(['BML-TXN-MINE' => $payment->merchant_reference]);

    $this->get('/payments/bml/return?ref='.$payment->merchant_reference.'&transactionId=BML-TXN-MINE')
        ->assertOk();

    // Without this case the fix could be "refuse everything", which passes the
    // case above and breaks every real payment.
    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($payment->fresh()->confirmed_at)->not->toBeNull();
});

it('refuses a provider result that names no payment at all', function () {
    $payment = bmlReturnPayment();

    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(false);
        }

        public function queryStatus(string $reference): ?PaymentVerificationResult
        {
            // Confirmed, but silent about which payment it confirms.
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: null,
                providerReference: $reference,
                status: 'completed',
                isConfirmed: true,
            );
        }
    });

    app(PaymentService::class)->finalizeByReference($payment->merchant_reference);

    // The cautious direction, and the shape of the bug: the field used to be
    // whatever we passed in, so "missing" and "matching" looked identical.
    expect($payment->fresh()->status)->toBe('pending');
});

it('matches on local_id as well, since finalizeByReference accepts either key', function () {
    $payment = bmlReturnPayment(['local_id' => 'LOCAL-'.uniqueFixtureSuffix()]);

    fakeBmlReturningOtherPayments(['BML-TXN-LOCAL' => $payment->local_id]);

    $payment->update(['bml_transaction_id' => 'BML-TXN-LOCAL']);
    app(PaymentService::class)->finalizeByReference($payment->local_id);

    expect($payment->fresh()->status)->toBe('confirmed');
});

it('leaves a transaction id already on the payment alone', function () {
    // The guard that stops a second visit overwriting a real id with a
    // replayed one.
    $payment = bmlReturnPayment(['bml_transaction_id' => 'BML-TXN-MINE']);

    fakeBmlReturningOtherPayments(['BML-TXN-MINE' => $payment->merchant_reference]);

    $this->get('/payments/bml/return?ref='.$payment->merchant_reference.'&transactionId=BML-TXN-INJECTED')
        ->assertOk();

    expect($payment->fresh()->bml_transaction_id)->toBe('BML-TXN-MINE');
});
