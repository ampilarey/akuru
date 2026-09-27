<?php

use App\Domains\Commerce\Actions\DeactivateGiftCardAction;
use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\ListGiftCardsAction;
use App\Domains\Commerce\Actions\RedeemGiftCardAction;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\GiftCardTransaction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\SaveLibrarySettingsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * B10 (LIBRARY_PLAN §15.2, STATUS §5is): bought cards take the office's
 * expiry rule; the office can take a card out of circulation, with the
 * reason on the card's own ledger.
 */
function fakeBmlForGiftExpiry(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/gift');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: (string) $request->input('reference'), providerReference: 'BML-GIFT', status: 'completed', rawPayload: $request->all(), isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function buyGiftCard(User $buyer): GiftCard
{
    test()->withoutLocalizationMiddleware()->actingAs($buyer)
        ->post(route('public.gift-cards.purchase'), ['amount' => 100, 'recipient_name' => 'Hawwa', 'recipient_email' => 'hawwa@example.test'])
        ->assertRedirect('https://bml.test/pay/gift');
    $payment = Payment::query()->latest('id')->firstOrFail();
    test()->postJson(url('/webhooks/bml'), ['reference' => $payment->merchant_reference, 'transactionId' => 'BML-GIFT', 'status' => 'completed'])->assertStatus(200);

    return GiftCard::query()->latest('id')->firstOrFail();
}

it('gives a bought card the office\'s expiry, and none while the rule is zero', function () {
    Mail::fake();
    fakeBmlForGiftExpiry();
    $buyer = User::factory()->create();

    config(['library.gift_cards.expiry_months' => 0]);
    expect(buyGiftCard($buyer)->expires_at)->toBeNull();

    app(SaveLibrarySettingsAction::class)->execute(['gift_card_expiry_months' => 6]);
    $card = buyGiftCard($buyer);
    expect($card->expires_at)->not->toBeNull()
        ->and($card->expires_at->toDateString())->toBe(now()->addMonths(6)->toDateString());

    // Not more than ten years, and never negative.
    expect(fn () => app(SaveLibrarySettingsAction::class)->execute(['gift_card_expiry_months' => 121]))->toThrow(ValidationException::class);
});

it('lets the office deactivate a spendable card with a reason on the ledger, after which it cannot be redeemed', function () {
    $office = actingSystemAdmin(['commerce.manage']);
    $issued = app(IssueGiftCardAction::class)->execute(['amount' => 200, 'recipient_name' => 'Hawwa', 'created_by' => $office->id]);
    $card = $issued['gift_card'];

    // A reason is required.
    test()->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.commerce.gift-cards.deactivate', $card->id), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    test()->withoutLocalizationMiddleware()->actingAs($office)->from(route('admin.commerce.index'))
        ->post(route('admin.commerce.gift-cards.deactivate', $card->id), ['reason' => 'Code was posted publicly'])
        ->assertRedirect(route('admin.commerce.index'))
        ->assertSessionHas('success');

    $card->refresh();
    $row = GiftCardTransaction::query()->where('gift_card_id', $card->id)->where('type', 'deactivate')->firstOrFail();
    expect($card->status->value)->toBe('deactivated')
        ->and((string) $card->balance_amount)->toBe('200.00')
        ->and((string) $row->amount)->toBe('200.00')
        ->and($row->note)->toBe('Code was posted publicly')
        ->and((int) $row->user_id)->toBe($office->id);

    // The till refuses it, the list says why, and it cannot be deactivated twice.
    $recipient = User::factory()->create();
    expect(fn () => app(RedeemGiftCardAction::class)->execute($recipient->id, $issued['plain_code']))->toThrow(ValidationException::class);
    $listed = collect(app(ListGiftCardsAction::class)->execute())->firstWhere('id', $card->id);
    expect($listed['status'])->toBe('deactivated')->and($listed['deactivated_reason'])->toBe('Code was posted publicly');
    expect(fn () => app(DeactivateGiftCardAction::class)->execute($card->id, $office->id, 'again'))->toThrow(ValidationException::class);

    // A spent card has nothing to take away; the office's gate holds for everyone else.
    $spent = app(IssueGiftCardAction::class)->execute(['amount' => 50, 'created_by' => $office->id]);
    app(RedeemGiftCardAction::class)->execute($recipient->id, $spent['plain_code']);
    expect(fn () => app(DeactivateGiftCardAction::class)->execute($spent['gift_card']->id, $office->id, 'late'))->toThrow(ValidationException::class);

    $another = app(IssueGiftCardAction::class)->execute(['amount' => 50, 'created_by' => $office->id])['gift_card'];
    test()->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin(['commerce.manage']))
        ->post(route('admin.commerce.gift-cards.deactivate', $another->id), ['reason' => 'x'])->assertForbidden();
    test()->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->post(route('admin.commerce.gift-cards.deactivate', $another->id), ['reason' => 'x'])->assertForbidden();
    expect($another->fresh()->status->value)->toBe('active');
});
