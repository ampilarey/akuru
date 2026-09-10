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

it('breaks sales down per book with the writer share and refunds', function () {
    $ctx = seedEarningWriterItem(100);
    $book2 = LibraryItem::query()->create([
        'title' => 'Second Book',
        'slug' => 'second-book-'.uniqid(),
        'content_type' => 'book',
        'access_type' => 'paid',
        'price' => 50,
        'status' => 'published',
        'writer_id' => $ctx['profile']->id,
    ]);

    $buyEarning = function (LibraryItem $item, float $gross, float $writerAmount, string $status) use ($ctx) {
        $purchase = \App\Domains\Library\Models\LibraryPurchase::query()->create([
            'user_id' => User::factory()->create()->id,
            'library_item_id' => $item->id,
            'amount' => $gross,
            'currency' => 'MVR',
            'status' => 'paid',
            'purchased_at' => now(),
        ]);
        WriterEarning::query()->create([
            'writer_id' => $ctx['profile']->id,
            'library_item_id' => $item->id,
            'library_purchase_id' => $purchase->id,
            'gross_amount' => $gross,
            'platform_commission' => round($gross - $writerAmount, 2),
            'writer_amount' => $writerAmount,
            'status' => $status,
        ]);
    };

    $buyEarning($ctx['item'], 100, 70, 'available');
    $buyEarning($ctx['item'], 100, 70, 'pending');
    $buyEarning($book2, 50, 35, 'paid');
    $buyEarning($book2, 50, 35, 'refunded');

    $rows = app(\App\Domains\Library\Actions\ListWriterItemSalesAction::class)->execute($ctx['writerUser']->id);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]['title'])->toBe('Earning Item 100')
        ->and($rows[0]['sold'])->toBe(2)
        ->and($rows[0]['gross'])->toBe(200.0)
        ->and($rows[0]['earned'])->toBe(140.0)
        ->and($rows[0]['refunded'])->toBe(0)
        ->and($rows[1]['title'])->toBe('Second Book')
        ->and($rows[1]['sold'])->toBe(1)
        ->and($rows[1]['earned'])->toBe(35.0)
        ->and($rows[1]['refunded'])->toBe(1);

    // A user with no writer profile sees nothing.
    expect(app(\App\Domains\Library\Actions\ListWriterItemSalesAction::class)->execute(User::factory()->create()->id))->toBe([]);

    // The portal page carries the table's data.
    $this->withoutLocalizationMiddleware()->actingAs($ctx['writerUser'])
        ->get(route('write.index'))
        ->assertOk()
        ->assertSee('item_sales')
        ->assertSee('Earning Item 100');
});

/**
 * Route-level authorization for the library money endpoints.
 *
 * The action above is covered thoroughly — the payout gate, bank details, the
 * amount, the second-request refusal, the paid outcome. What nothing tested is
 * **who may call the endpoint**, and the obvious abuse is a writer approving
 * their own payout.
 *
 * `admin/library/*` requires `role:super_admin|admin|headmaster` **and**
 * `can:library.manage`. These pin that.
 */
function requestedPayout(): array
{
    $ctx = seedEarningWriterItem(200);
    $buyer = User::factory()->create();
    app(CreditWalletAction::class)->execute($buyer->id, 300, 'admin');
    app(StartLibraryCheckoutAction::class)->execute($ctx['item']->slug, $buyer->id, null, null, true);
    test()->travel(8)->days();

    config()->set('library.payouts_enabled', true);
    app(SaveWriterBankDetailsAction::class)->execute($ctx['writerUser']->id, [
        'bank_name' => 'BML',
        'account_name' => 'Earning Writer',
        'account_number' => '7701234567',
    ]);

    return ['payout' => app(RequestWriterPayoutAction::class)->execute($ctx['writerUser']->id), 'ctx' => $ctx];
}

function libraryAdmin(): User
{
    $user = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('library.manage', 'web');
    $user->assignRole('admin');
    $user->givePermissionTo('library.manage');

    return $user;
}

it('refuses a writer approving their own payout', function () {
    ['payout' => $payout, 'ctx' => $ctx] = requestedPayout();

    // Self-dealing is the whole reason this endpoint needs a guard.
    test()->withoutLocalizationMiddleware()
        ->actingAs($ctx['writerUser'])
        ->post(route('admin.library.payouts.decide', $payout->id), ['paid' => true])
        ->assertForbidden();

    expect($payout->fresh()->status)->toBe('requested');
});

it('refuses a signed-in account with no library permission', function () {
    ['payout' => $payout] = requestedPayout();

    test()->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->post(route('admin.library.payouts.decide', $payout->id), ['paid' => true])
        ->assertForbidden();

    expect($payout->fresh()->status)->toBe('requested');
});

it('refuses an anonymous visitor deciding a payout', function () {
    ['payout' => $payout] = requestedPayout();

    test()->withoutLocalizationMiddleware()
        ->post(route('admin.library.payouts.decide', $payout->id), ['paid' => true])
        ->assertRedirect();

    expect($payout->fresh()->status)->toBe('requested');
});

it('lets a library admin mark a payout paid through the route', function () {
    ['payout' => $payout] = requestedPayout();

    test()->withoutLocalizationMiddleware()
        ->actingAs(libraryAdmin())
        ->post(route('admin.library.payouts.decide', $payout->id), ['paid' => true, 'note' => 'Transferred'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($payout->fresh()->status)->toBe('paid')
        ->and(WriterEarning::query()->firstOrFail()->status)->toBe('paid');
});

it('requires an explicit decision rather than defaulting to paid', function () {
    ['payout' => $payout] = requestedPayout();

    // `paid` is required:boolean — an empty post must not quietly pay somebody.
    test()->withoutLocalizationMiddleware()
        ->actingAs(libraryAdmin())
        ->post(route('admin.library.payouts.decide', $payout->id), [])
        ->assertSessionHasErrors('paid');

    expect($payout->fresh()->status)->toBe('requested');
});
