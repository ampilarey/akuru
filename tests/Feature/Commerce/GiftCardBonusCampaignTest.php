<?php

use App\Domains\Commerce\Actions\ListPromotionCampaignsAction;
use App\Domains\Commerce\Actions\ResolveStoredValueLiabilityAction;
use App\Domains\Commerce\Actions\SavePromotionCampaignAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\GiftCardOrder;
use App\Domains\Commerce\Models\GiftCardTransaction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Mail\GiftCardCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * B4b (LIBRARY_PLAN §18 "gift card bonus buy-500-get-50", STATUS §5iy): a
 * campaign may add value to gift cards bought while it runs. The buyer pays
 * the full amount (§15.4 stands: nothing discounts a gift card); the card is
 * issued for more, the bonus is on its ledger, and the office sees the use.
 */
function fakeBmlForBonus(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/bonus');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: (string) $request->input('reference'), providerReference: 'BML-BONUS', status: 'completed', rawPayload: $request->all(), isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function buyCardForBonus(User $buyer, int $amount): GiftCardOrder
{
    test()->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.gift-cards.purchase'), ['amount' => $amount, 'recipient_name' => 'Hawwa', 'recipient_email' => 'hawwa@example.test'])
        ->assertRedirect('https://bml.test/pay/bonus');
    $payment = Payment::query()->latest('id')->firstOrFail();
    expect((string) $payment->amount)->toBe(number_format($amount, 2, '.', ''));
    test()->postJson(url('/webhooks/bml'), ['reference' => $payment->merchant_reference, 'transactionId' => 'BML-BONUS', 'status' => 'completed'])->assertStatus(200);

    return GiftCardOrder::query()->latest('id')->firstOrFail();
}

it('issues a bought card for more when a bonus campaign covers the amount, charging the full price and keeping the bonus on the ledger', function () {
    Mail::fake();
    fakeBmlForBonus();
    $buyer = User::factory()->create();
    $campaign = app(SavePromotionCampaignAction::class)->execute([
        'name' => 'Ramadan gift bonus', 'discount_type' => 'percentage', 'discount_value' => 10, 'max_discount_amount' => 50, 'minimum_amount' => 500,
        'funding_source' => 'akuru', 'targets' => [['type' => 'gift_card']],
    ]);

    // Below the minimum: a plain card.
    $small = buyCardForBonus($buyer, 200);
    expect((float) $small->bonus_amount)->toBe(0.0)
        ->and((string) GiftCard::query()->findOrFail($small->gift_card_id)->original_amount)->toBe('200.00');

    // At the minimum: paid 500, card worth 550, bonus capped at 50 on the ledger.
    $big = buyCardForBonus($buyer, 1000);
    $card = GiftCard::query()->findOrFail($big->gift_card_id);
    expect((string) $big->bonus_amount)->toBe('50.00')
        ->and((int) $big->promotion_campaign_id)->toBe($campaign->id)
        ->and((string) $card->original_amount)->toBe('1050.00')
        ->and((string) $card->balance_amount)->toBe('1050.00');
    $ledger = GiftCardTransaction::query()->where('gift_card_id', $card->id)->where('type', 'bonus')->firstOrFail();
    expect((string) $ledger->amount)->toBe('50.00')->and($ledger->note)->toContain('Campaign bonus');
    $redemption = DiscountRedemption::query()->where('purchase_type', 'gift_card_order')->where('purchase_id', $big->id)->firstOrFail();
    expect($redemption->status)->toBe('confirmed')->and((string) $redemption->amount_discounted)->toBe('50.00');

    // The recipient is told what the card is worth, bonus included; the liability counts it.
    Mail::assertQueued(GiftCardCodeMail::class, fn (GiftCardCodeMail $mail) => $mail->amount === '1050.00');
    expect(app(ResolveStoredValueLiabilityAction::class)->execute()['gift_cards'])->toBe(1250.0);

    // The office's list counts the use; the shelf's resolver ignores a gift-card campaign.
    $listed = collect(app(ListPromotionCampaignsAction::class)->execute())->firstWhere('slug', $campaign->slug);
    expect($listed['uses'])->toBe(1)->and($listed['given'])->toBe(50.0)->and($listed['is_gift_card_bonus'])->toBeTrue()
        ->and(app(ListLibraryItemsAction::class)->execute(['discounted' => 1]))->toBe([]);
});

it('shows the bonus offer where cards are bought and on the shelf strip, pointing at the gift card page', function () {
    app(SavePromotionCampaignAction::class)->execute([
        'name' => 'Eid gift bonus', 'discount_type' => 'fixed', 'discount_value' => 25, 'minimum_amount' => 250, 'targets' => [['type' => 'gift_card']],
    ]);

    $this->withoutLocalizationMiddleware()->get(route('public.gift-cards.index'))
        ->assertOk()->assertSee('data-testid="gift-card-bonuses"', false)->assertSee('Eid gift bonus')->assertSee('Buy MVR 250 or more and get MVR 25.00 extra on the card');
    $this->withoutLocalizationMiddleware()->get(route('public.library.index'))
        ->assertOk()->assertSee('MVR 25.00 bonus on gift cards')->assertSee(route('public.gift-cards.index'));
    $this->withoutLocalizationMiddleware()->get(route('public.library.promotions'))
        ->assertOk()->assertSee('Eid gift bonus')->assertSee('Buy a gift card');
});
