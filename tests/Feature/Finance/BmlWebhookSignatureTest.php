<?php

use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Rule 12: "Access to paid anything depends on BML **webhook** confirmation."
 * `POST /webhooks/bml` is anonymous, and the IP allowlist is empty by default,
 * so the signature check is the only thing between the open internet and a
 * confirmed payment.
 *
 * It failed open twice over, and both were proven against the running app
 * before this was written:
 *
 *   - **No secret configured** — the whole check was skipped, and an unsigned
 *     POST confirmed a payment.
 *   - **Secret configured, header absent** — `if ($signature && …)`
 *     short-circuited, so an operator who had done the right thing was still
 *     defenceless against a request that simply omitted the header.
 *
 * The only case ever rejected was a *wrong* signature, which is the one thing
 * an attacker has no reason to send. `PaymentService::applyVerifiedResult`
 * still carries the comment "authoritative since signature was verified" —
 * that invariant is now actually true.
 *
 * Note `phpunit.xml` sets `BML_WEBHOOK_ALLOW_UNSIGNED=true`, because the suite
 * fakes BML and shares no secret with it. Every test here overrides that
 * explicitly, so the refusals are pinned rather than assumed.
 */
function signaturePayment(string $reference): Payment
{
    return Payment::create([
        'user_id' => User::factory()->create()->id,
        'amount' => 5000,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'merchant_reference' => $reference,
        'local_id' => $reference,
    ]);
}

function postWebhook(string $body, array $headers = []): \Illuminate\Testing\TestResponse
{
    return test()->call(
        'POST',
        url('/webhooks/bml'),
        [], [], [],
        array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
        $body,
    );
}

it('refuses an unsigned webhook when a secret is configured', function () {
    // The dangerous case: the operator set a secret, and the attacker simply
    // does not send the header.
    config(['bml.webhook_secret' => 'a-real-secret', 'bml.webhook_allow_unsigned' => true]);
    $payment = signaturePayment('SIG-MISSING-HEADER');

    postWebhook('{"localId":"SIG-MISSING-HEADER","state":"success"}')->assertStatus(400);

    // A configured secret always wins — allow_unsigned must not weaken it.
    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->paid_at)->toBeNull();
});

it('refuses an unsigned webhook when no secret is configured', function () {
    config(['bml.webhook_secret' => null, 'bml.webhook_allow_unsigned' => false]);
    $payment = signaturePayment('SIG-NO-SECRET');

    postWebhook('{"localId":"SIG-NO-SECRET","state":"success"}')->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending');
});

it('refuses a wrong signature', function () {
    config(['bml.webhook_secret' => 'a-real-secret', 'bml.webhook_allow_unsigned' => false]);
    $payment = signaturePayment('SIG-WRONG');

    postWebhook('{"localId":"SIG-WRONG","state":"success"}', ['HTTP_X-BML-Signature' => 'not-the-hash'])
        ->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending');
});

it('refuses a signature computed over a different body', function () {
    // Replay/tamper: a signature that is valid for some other payload must not
    // authorise this one. This is what verifying the *raw* body buys.
    config(['bml.webhook_secret' => 'a-real-secret', 'bml.webhook_allow_unsigned' => false]);
    $payment = signaturePayment('SIG-TAMPERED');

    $otherBody = '{"localId":"SOMETHING-ELSE","state":"success"}';
    $signatureForOtherBody = hash_hmac('sha256', $otherBody, 'a-real-secret');

    postWebhook('{"localId":"SIG-TAMPERED","state":"success"}', ['HTTP_X-BML-Signature' => $signatureForOtherBody])
        ->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending');
});

it('accepts a correctly signed webhook and confirms the payment', function () {
    // The control. Without this the tests above would pass on a webhook that
    // rejects everything, which would be its own defect.
    config(['bml.webhook_secret' => 'a-real-secret', 'bml.webhook_allow_unsigned' => false]);
    $payment = signaturePayment('SIG-VALID');

    $body = '{"localId":"SIG-VALID","state":"success"}';
    postWebhook($body, ['HTTP_X-BML-Signature' => hash_hmac('sha256', $body, 'a-real-secret')])
        ->assertStatus(200);

    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($payment->fresh()->paid_at)->not->toBeNull();
});

it('honours the sandbox opt-out only when no secret is set', function () {
    config(['bml.webhook_secret' => null, 'bml.webhook_allow_unsigned' => true]);
    $payment = signaturePayment('SIG-SANDBOX');

    postWebhook('{"localId":"SIG-SANDBOX","state":"success"}')->assertStatus(200);

    expect($payment->fresh()->status)->toBe('confirmed');
});

it('respects a custom signature header name', function () {
    config([
        'bml.webhook_secret' => 'a-real-secret',
        'bml.webhook_signature_header' => 'X-Custom-Sig',
        'bml.webhook_allow_unsigned' => false,
    ]);
    $payment = signaturePayment('SIG-CUSTOM-HEADER');

    $body = '{"localId":"SIG-CUSTOM-HEADER","state":"success"}';
    postWebhook($body, ['HTTP_X-Custom-Sig' => hash_hmac('sha256', $body, 'a-real-secret')])
        ->assertStatus(200);

    expect($payment->fresh()->status)->toBe('confirmed');
});
