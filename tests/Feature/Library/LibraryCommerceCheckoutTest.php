<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedWalletPaidItem(float $price = 100)
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Wallet Book',
        'content_type' => 'book',
        'access_type' => 'paid',
        'body' => '<p>Wallet book content.</p>',
    ]);
    $item->price = $price;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

it('pays with wallet: immediate grant, append-only debit, no BML round-trip', function () {
    $item = seedWalletPaidItem(100);
    $buyer = User::factory()->create();
    app(CreditWalletAction::class)->execute($buyer->id, 150, 'admin', null, 'Top-up');

    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $item->slug), ['pay_with_wallet' => 1])
        ->assertRedirect(route('public.library.read', ['slug' => $item->slug]));

    $purchase = LibraryPurchase::query()->firstOrFail();
    expect($purchase->status)->toBe('paid')
        ->and($purchase->payment_id)->toBeNull()
        ->and((string) Wallet::query()->where('user_id', $buyer->id)->value('balance'))->toBe('50.00');
    $grant = LibraryAccessGrant::query()->firstOrFail();
    expect($grant->source_type)->toBe('wallet');
    expect(Payment::query()->count())->toBe(0);

    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->get(route('public.library.read', ['slug' => $item->slug]))
        ->assertOk()
        ->assertSee('Wallet book content', false);

    // Insufficient balance refuses and grants nothing.
    $poor = User::factory()->create();
    $second = seedWalletPaidItem(999);
    $this->withoutLocalizationMiddleware()->actingAs($poor)
        ->post(route('public.library.checkout', $second->slug), ['pay_with_wallet' => 1])
        ->assertSessionHasErrors('amount');
    expect(LibraryAccessGrant::query()->count())->toBe(1);
});

it('applies a discount on the BML path and confirms the redemption via webhook', function () {
    $item = seedWalletPaidItem(200);
    $buyer = User::factory()->create();
    app(SaveDiscountCodeAction::class)->execute([
        'code' => 'BOOKS25',
        'discount_type' => 'fixed',
        'discount_value' => 25,
        'per_user_limit' => 1,
    ]);
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/discounted');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-L4',
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

    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $item->slug), ['discount_code' => 'BOOKS25'])
        ->assertRedirect('https://bml.test/pay/discounted');

    $purchase = LibraryPurchase::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    $redemption = DiscountRedemption::query()->firstOrFail();
    expect((string) $purchase->amount)->toBe('175.00')
        ->and((string) $payment->amount)->toBe('175.00')
        ->and($redemption->status)->toBe('pending');

    // Webhook confirms payment AND the discount redemption together.
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-L4',
        'status' => 'completed',
    ])->assertStatus(200);

    expect($purchase->refresh()->status)->toBe('paid')
        ->and($redemption->refresh()->status)->toBe('confirmed')
        ->and(LibraryAccessGrant::query()->count())->toBe(1);

    // The per-user limit now blocks a second use at checkout.
    $again = seedWalletPaidItem(100);
    $this->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.library.checkout', $again->slug), ['discount_code' => 'BOOKS25'])
        ->assertSessionHasErrors('discount_code');
});
