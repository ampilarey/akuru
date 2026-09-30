<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5lw: BML Connect as it really is. The owner's BML merchant app shows
 * an Application ID, an API key and a public key — no webhook secret. BML
 * signs a webhook with the API key (X-Signature = sha256(X-Signature-Nonce .
 * X-Signature-Timestamp . apiKey)), which covers who sent it but not what it
 * says. So the site checks the signature and then asks BML's API about the
 * payment; the payload's own "CONFIRMED" is never believed.
 *
 * Production-shaped throughout: no webhook secret, unsigned callbacks refused
 * (phpunit.xml allows them for the older tests; these turn that off).
 */
const BML_TEST_KEY = 'eyJhbGciOiJIUzI1NiJ9.test-key.sig';

function bmlConnectLive(): void
{
    config([
        'bml.api_key' => BML_TEST_KEY,
        'bml.app_id' => 'app-1',
        'bml.base_url' => 'https://bml.test/public',
        'bml.webhook_secret' => null,
        'bml.webhook_allow_unsigned' => false,
    ]);
}

function bmlPendingCoursePayment(?string $bmlTransactionId = null): array
{
    $user = User::factory()->create();
    $pupil = makeStudent(['first_name' => 'Paying', 'last_name' => 'Family']);
    $pupil->forceFill(['user_id' => $user->id])->save();
    $course = Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Paid', 'slug' => 'paid-'.Str::random(6), 'order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Paid course', 'slug' => 'paid-course-'.Str::random(6), 'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'status' => 'open', 'registration_fee_amount' => 500, 'requires_admin_approval' => false,
    ]);
    $enrollment = CourseEnrollment::query()->create([
        'unified_student_id' => $pupil->id, 'course_id' => $course->id, 'status' => 'pending', 'payment_status' => 'pending',
    ]);
    $reference = 'AKURU-BML-'.Str::upper(Str::random(6));
    $payment = Payment::query()->create([
        'user_id' => $user->id, 'unified_student_id' => $pupil->id, 'course_id' => $course->id, 'amount' => 500, 'currency' => 'MVR',
        'status' => 'pending', 'provider' => 'bml', 'merchant_reference' => $reference, 'local_id' => $reference,
        'bml_transaction_id' => $bmlTransactionId,
    ]);
    PaymentItem::query()->create(['payment_id' => $payment->id, 'enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'amount' => 500]);
    $enrollment->update(['payment_id' => $payment->id]);

    return [$payment, $enrollment, $reference];
}

/** A webhook as BML sends one, signed with the key (or with a wrong one). */
function bmlWebhook(array $body, ?string $key = BML_TEST_KEY)
{
    $raw = json_encode($body);
    $server = ['CONTENT_TYPE' => 'application/json'];
    if ($key !== null) {
        $nonce = 'nonce-'.Str::random(8);
        $timestamp = (string) now()->timestamp;
        $server += [
            'HTTP_X-Signature-Nonce' => $nonce,
            'HTTP_X-Signature-Timestamp' => $timestamp,
            'HTTP_X-Signature' => hash('sha256', $nonce.$timestamp.$key),
        ];
    }

    return test()->call('POST', url('/webhooks/bml'), [], [], [], $server, $raw);
}

/** BML's API answering "Get transaction" with this state and merchant reference. */
function bmlApiSays(string $state, ?string $localId, string $id = 'bml-txn-1'): void
{
    Http::fake(['bml.test/*' => Http::response(['id' => $id, 'localId' => $localId, 'state' => $state, 'amount' => 50000, 'currency' => 'MVR'])]);
}

it('confirms a payment when BML signs the webhook with the API key and BML\'s API agrees', function () {
    bmlConnectLive();
    [$payment, $enrollment, $reference] = bmlPendingCoursePayment('bml-txn-1');
    bmlApiSays('CONFIRMED', $reference);

    bmlWebhook(['eventType' => 'NOTIFY_TRANSACTION_CHANGE', 'transactionId' => 'bml-txn-1', 'localId' => $reference, 'state' => 'CONFIRMED'])->assertOk();

    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($enrollment->fresh()->status)->toBe('active');
    // It asked BML, by BML's transaction id, sending the key as BML's own SDK does.
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v2/transactions/bml-txn-1') && $r->hasHeader('Authorization', BML_TEST_KEY));
});

it('believes BML\'s API, not the webhook body: a "CONFIRMED" body for a pending payment confirms nothing', function () {
    bmlConnectLive();
    [$payment, $enrollment, $reference] = bmlPendingCoursePayment('bml-txn-1');
    bmlApiSays('QR_CODE_GENERATED', $reference);

    bmlWebhook(['transactionId' => 'bml-txn-1', 'localId' => $reference, 'state' => 'CONFIRMED'])->assertOk();

    expect($payment->fresh()->status)->toBe('pending')->and($enrollment->fresh()->status)->toBe('pending');
});

it('finds the payment from BML\'s transaction id when the webhook carries no local id', function () {
    bmlConnectLive();
    [$payment, , $reference] = bmlPendingCoursePayment('bml-txn-9');
    bmlApiSays('CONFIRMED', $reference, 'bml-txn-9');

    bmlWebhook(['eventType' => 'NOTIFY_TRANSACTION_CHANGE', 'transactionId' => 'bml-txn-9', 'state' => 'CONFIRMED'])->assertOk();

    expect($payment->fresh()->status)->toBe('confirmed');
});

it('refuses a webhook signed with the wrong key, or not signed at all, and never asks BML', function () {
    bmlConnectLive();
    [$payment, , $reference] = bmlPendingCoursePayment('bml-txn-1');
    bmlApiSays('CONFIRMED', $reference);

    bmlWebhook(['transactionId' => 'bml-txn-1', 'localId' => $reference, 'state' => 'CONFIRMED'], 'not-the-key')->assertStatus(400);
    bmlWebhook(['transactionId' => 'bml-txn-1', 'localId' => $reference, 'state' => 'CONFIRMED'], null)->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending');
    Http::assertNothingSent();
});

it('refuses BML\'s answer when it is about another payment', function () {
    bmlConnectLive();
    [$payment, , $reference] = bmlPendingCoursePayment('bml-txn-1');
    bmlApiSays('CONFIRMED', 'AKURU-SOMEONE-ELSE');

    bmlWebhook(['transactionId' => 'bml-txn-1', 'localId' => $reference, 'state' => 'CONFIRMED'])->assertOk();

    expect($payment->fresh()->status)->toBe('pending');
});

it('keeps a configured shared secret authoritative: BML-style headers do not bypass it', function () {
    bmlConnectLive();
    config(['bml.webhook_secret' => 'a-shared-secret']);
    [$payment, , $reference] = bmlPendingCoursePayment('bml-txn-1');
    bmlApiSays('CONFIRMED', $reference);

    bmlWebhook(['transactionId' => 'bml-txn-1', 'localId' => $reference, 'state' => 'CONFIRMED'])->assertStatus(400);

    expect($payment->fresh()->status)->toBe('pending');
});
