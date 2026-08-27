<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Finance\Actions\RefundPaymentAction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\DecideWriterApplicationAction;
use App\Domains\Library\Actions\DecideWriterPayoutAction;
use App\Domains\Library\Actions\ListWriterEarningsSummaryAction;
use App\Domains\Library\Actions\RequestWriterPayoutAction;
use App\Domains\Library\Actions\SaveWriterBankDetailsAction;
use App\Domains\Library\Actions\StartLibraryCheckoutAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function fakeL6BmlProvider(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/l6');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-L6',
                status: 'completed',
                rawPayload: $request->all(),
                isConfirmed: true,
            );
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function seedEarningWriterItem(float $price = 100): array
{
    $writerUser = User::factory()->create();
    $application = app(ApplyAsWriterAction::class)->execute($writerUser->id, [
        'display_name' => 'Earning Writer',
        'agreement_accepted' => true,
    ]);
    app(DecideWriterApplicationAction::class)->execute($application->id, User::factory()->create()->id, true);
    $profile = WriterProfile::query()->where('user_id', $writerUser->id)->firstOrFail();
    $item = LibraryItem::query()->create([
        'title' => 'Earning Item '.$price,
        'slug' => 'earning-item-'.$price.'-'.uniqid(),
        'content_type' => 'book',
        'access_type' => 'paid',
        'price' => $price,
        'status' => 'published',
        'writer_id' => $profile->id,
    ]);

    return ['writerUser' => $writerUser, 'profile' => $profile, 'item' => $item];
}

it('accrues a shared-funded BML sale at 70/30 of the paid amount and matures after the window', function () {
    $ctx = seedEarningWriterItem(100);
    $buyer = User::factory()->create();
    fakeL6BmlProvider();
    app(SaveDiscountCodeAction::class)->execute([
        'code' => 'SHARE20',
        'discount_type' => 'fixed',
        'discount_value' => 20,
        'discount_funding_source' => 'shared',
    ]);

    $result = app(StartLibraryCheckoutAction::class)->execute($ctx['item']->slug, $buyer->id, null, 'SHARE20', false);
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $result['purchase']->fresh()->payment_id
            ? Payment::query()->find($result['purchase']->fresh()->payment_id)->merchant_reference
            : 'missing',
        'transactionId' => 'BML-L6',
        'status' => 'completed',
    ])->assertStatus(200);

    $earning = WriterEarning::query()->firstOrFail();
    expect((string) $earning->gross_amount)->toBe('100.00')
        ->and((string) $earning->discount_amount)->toBe('20.00')
        ->and($earning->discount_funding_source)->toBe('shared')
        ->and((string) $earning->writer_amount)->toBe('56.00') // 70% of 80 paid
        ->and((string) $earning->platform_commission)->toBe('24.00')
        ->and((string) $earning->bml_amount)->toBe('80.00')
        ->and($earning->status)->toBe('pending');

    // Refund window passes → matures to available.
    $this->travel(8)->days();
    $summary = app(ListWriterEarningsSummaryAction::class)->execute($ctx['writerUser']->id);
    expect($summary['available'])->toBe(56.0)
        ->and($earning->fresh()->status)->toBe('available');
});

it('accrues a wallet sale from the full paid value and honors per-item commission', function () {
    $ctx = seedEarningWriterItem(80);
    $ctx['item']->update(['commission_type' => 'percentage', 'commission_value' => 50]);
    $buyer = User::factory()->create();
    app(CreditWalletAction::class)->execute($buyer->id, 200, 'admin');

    app(StartLibraryCheckoutAction::class)->execute($ctx['item']->slug, $buyer->id, null, null, true);

    $earning = WriterEarning::query()->firstOrFail();
    expect((string) $earning->wallet_amount)->toBe('80.00')
        ->and((string) $earning->bml_amount)->toBe('0.00')
        ->and((string) $earning->writer_amount)->toBe('40.00') // per-item 50% of 80
        ->and($earning->status)->toBe('pending');
});

it('claws back the earning when the sale is fully refunded', function () {
    $ctx = seedEarningWriterItem(60);
    $buyer = User::factory()->create();
    fakeL6BmlProvider();

    $result = app(StartLibraryCheckoutAction::class)->execute($ctx['item']->slug, $buyer->id);
    $payment = Payment::query()->findOrFail($result['purchase']->fresh()->payment_id);
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-L6',
        'status' => 'completed',
    ])->assertStatus(200);
    expect(WriterEarning::query()->firstOrFail()->status)->toBe('pending');

    app(RefundPaymentAction::class)->execute($payment->id, 60, 'wallet');

    expect(WriterEarning::query()->firstOrFail()->status)->toBe('refunded');
});

it('gates payout requests behind the operator flag and pays through the admin decision', function () {
    $ctx = seedEarningWriterItem(200);
    $buyer = User::factory()->create();
    app(CreditWalletAction::class)->execute($buyer->id, 300, 'admin');
    app(StartLibraryCheckoutAction::class)->execute($ctx['item']->slug, $buyer->id, null, null, true);
    $this->travel(8)->days();

    // §9.4 gate: disabled by default.
    expect(fn () => app(RequestWriterPayoutAction::class)->execute($ctx['writerUser']->id))
        ->toThrow(ValidationException::class);

    config()->set('library.payouts_enabled', true);

    // Bank details required.
    expect(fn () => app(RequestWriterPayoutAction::class)->execute($ctx['writerUser']->id))
        ->toThrow(ValidationException::class);
    app(SaveWriterBankDetailsAction::class)->execute($ctx['writerUser']->id, [
        'bank_name' => 'BML',
        'account_name' => 'Earning Writer',
        'account_number' => '7701234567',
    ]);

    $payout = app(RequestWriterPayoutAction::class)->execute($ctx['writerUser']->id);
    expect((string) $payout->amount)->toBe('140.00') // 70% of 200
        ->and($payout->status)->toBe('requested');

    // A second request finds nothing available.
    expect(fn () => app(RequestWriterPayoutAction::class)->execute($ctx['writerUser']->id))
        ->toThrow(ValidationException::class);

    $admin = User::factory()->create();
    app(DecideWriterPayoutAction::class)->execute($payout->id, $admin->id, true, 'Transferred');

    $earning = WriterEarning::query()->firstOrFail();
    expect($earning->status)->toBe('paid')
        ->and($earning->paid_at)->not->toBeNull()
        ->and((int) $earning->writer_payout_id)->toBe($payout->id)
        ->and($payout->fresh()->status)->toBe('paid');
});
