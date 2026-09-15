<?php

use App\Domains\Finance\Actions\VerifyPendingPayloadDrainAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The Phase 4 cleanup gate (rule 9, deploy 3).
 *
 * P4.2 stopped writing `enrollment_pending_payload`; the webhook still reads it
 * so payments started before that deploy can finalize. Deploy 3 deletes the
 * read branch and drops the column, and may only run once nothing depends on
 * it.
 *
 * The gate had no command — it was a line of SQL in STATUS §5h — and that SQL
 * has a trap: it returns `0` on a database that has drained *and* on one that
 * never had a pre-P4.2 payment at all. Those are opposite answers. The tests
 * below pin all three outcomes, including the difference between the two kinds
 * of zero.
 */
function payloadPayment(array $overrides = []): int
{
    // `payments.user_id` is NOT NULL — a payment belongs to whoever is paying.
    $payer = \App\Domains\Identity\Models\User::factory()->create();

    return (int) DB::table('payments')->insertGetId(array_merge([
        'user_id' => $payer->id,
        // NOT NULL and unique — the gateway's own handle for the payment.
        'merchant_reference' => 'SMOKE-'.str()->random(10),
        'amount' => 250,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'enrollment_pending_payload' => json_encode(['course_id' => 1]),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('says nothing was ever at stake when no payment carried a payload', function () {
    payloadPayment(['enrollment_pending_payload' => null, 'status' => 'confirmed']);

    $report = app(VerifyPendingPayloadDrainAction::class)->execute();

    // Green — but flagged as vacuous, which is the whole point. A fresh
    // install answers exactly like a drained one, and reporting them the same
    // way is how a gate turns into decoration (STATUS §5en).
    expect($report['ok'])->toBeTrue()
        ->and($report['vacuous'])->toBeTrue()
        ->and($report['with_payload'])->toBe(0)
        ->and($report['payments'])->toBe(1);
});

it('is green and meaningful once every payload payment has settled', function () {
    foreach (VerifyPendingPayloadDrainAction::SETTLED as $status) {
        payloadPayment(['status' => $status]);
    }

    $report = app(VerifyPendingPayloadDrainAction::class)->execute();

    expect($report['ok'])->toBeTrue()
        // Not vacuous: these payments really did carry the payload, and really
        // are finished with it.
        ->and($report['vacuous'])->toBeFalse()
        ->and($report['with_payload'])->toBe(count(VerifyPendingPayloadDrainAction::SETTLED))
        ->and($report['blocking'])->toBe(0);
});

it('refuses while a payment could still arrive at the webhook', function () {
    payloadPayment(['status' => 'confirmed']);
    $pending = payloadPayment(['status' => 'pending', 'merchant_reference' => 'SMOKE-REF-1']);

    $report = app(VerifyPendingPayloadDrainAction::class)->execute();

    expect($report['ok'])->toBeFalse()
        ->and($report['blocking'])->toBe(1)
        ->and($report['blocking_rows'][0]['id'])->toBe($pending)
        // Listed, never guessed — an operator has to be able to go and look at
        // the row that is holding the deploy up.
        ->and($report['blocking_rows'][0]['reference'])->toBe('SMOKE-REF-1')
        ->and($report['blocking_rows'][0]['status'])->toBe('pending');
});

it('does not treat a refunded payment as holding the deploy open', function () {
    // The list STATUS §5h recorded omitted `refunded`, which would have kept
    // this gate red forever on a payment that has already been through
    // confirmation and can never be confirmed again. It also named `paid`, a
    // status the enum cannot hold at all.
    payloadPayment(['status' => 'refunded']);

    $report = app(VerifyPendingPayloadDrainAction::class)->execute();

    expect($report['ok'])->toBeTrue()
        ->and($report['blocking'])->toBe(0)
        ->and($report['with_payload'])->toBe(1)
        ->and($report['vacuous'])->toBeFalse()
        ->and(VerifyPendingPayloadDrainAction::SETTLED)->not->toContain('paid');
});

it('fails the command while something still depends on the payload', function () {
    payloadPayment(['status' => 'pending']);

    $this->artisan('payments:verify-payload-drain')
        ->expectsOutputToContain('payments=1 with_payload=1 blocking=1')
        ->expectsOutputToContain('FAILED')
        ->assertExitCode(1);
});

it('warns rather than congratulating when the run proves nothing', function () {
    $this->artisan('payments:verify-payload-drain')
        ->expectsOutputToContain('proves nothing')
        ->assertExitCode(0);
});
