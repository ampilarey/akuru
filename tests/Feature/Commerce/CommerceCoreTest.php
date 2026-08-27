<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\RedeemGiftCardAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('keeps the wallet ledger append-only with before/after balances and refuses overdrafts', function () {
    $user = User::factory()->create();

    app(CreditWalletAction::class)->execute($user->id, 100, 'admin', null, 'Opening credit');
    app(DebitWalletAction::class)->execute($user->id, 30, 'purchase', null, 'Test buy');
    // A mistake is corrected by a reversal row — never by editing a row (§43.20).
    app(CreditWalletAction::class)->execute($user->id, 30, 'reversal', null, 'Reversal of test buy');

    $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
    expect((string) $wallet->balance)->toBe('100.00');

    $ledger = WalletTransaction::query()->orderBy('id')->get();
    expect($ledger)->toHaveCount(3)
        ->and((string) $ledger[0]->balance_before)->toBe('0.00')
        ->and((string) $ledger[0]->balance_after)->toBe('100.00')
        ->and((string) $ledger[1]->balance_after)->toBe('70.00')
        ->and((string) $ledger[2]->balance_after)->toBe('100.00')
        ->and($ledger[0]->updated_at ?? null)->toBeNull();

    expect(fn () => app(DebitWalletAction::class)->execute($user->id, 500, 'purchase'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreditWalletAction::class)->execute($user->id, -5, 'admin'))
        ->toThrow(ValidationException::class);
});

it('issues gift cards hashed, redeems onto the wallet once, and rejects expired cards', function () {
    $admin = User::factory()->create();
    $redeemer = User::factory()->create();

    $issued = app(IssueGiftCardAction::class)->execute([
        'amount' => 250,
        'recipient_name' => 'Aminath',
        'created_by' => $admin->id,
    ]);
    $plain = $issued['plain_code'];
    expect($plain)->toStartWith('AKG-');
    // §43.19: only the hash is stored.
    $card = GiftCard::query()->firstOrFail();
    expect($card->code_hash)->toBe(hash('sha256', $plain))
        ->and(str_contains(json_encode($card->getAttributes()), substr($plain, 4)))->toBeFalse();

    $result = app(RedeemGiftCardAction::class)->execute($redeemer->id, $plain);
    expect($result['credited'])->toBe(250.0);
    $wallet = Wallet::query()->where('user_id', $redeemer->id)->firstOrFail();
    expect((string) $wallet->balance)->toBe('250.00')
        ->and($card->refresh()->status->value)->toBe('redeemed');

    // Double redemption fails; the wallet is unchanged.
    expect(fn () => app(RedeemGiftCardAction::class)->execute($redeemer->id, $plain))
        ->toThrow(ValidationException::class);
    expect((string) $wallet->refresh()->balance)->toBe('250.00');

    // Expired cards refuse and flip to expired.
    $expired = app(IssueGiftCardAction::class)->execute([
        'amount' => 50,
        'expires_at' => now()->subDay(),
        'created_by' => $admin->id,
    ]);
    expect(fn () => app(RedeemGiftCardAction::class)->execute($redeemer->id, $expired['plain_code']))
        ->toThrow(ValidationException::class);
    expect($expired['gift_card']->refresh()->status->value)->toBe('expired');
});

it('resolves discounts with caps, windows, minimums, and usage limits', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    app(SaveDiscountCodeAction::class)->execute([
        'code' => 'RAMADAN10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'max_discount_amount' => 20,
        'usage_limit' => 2,
        'per_user_limit' => 1,
        'minimum_order_amount' => 50,
    ]);

    // 10% of 300 capped at 20.
    $resolved = app(ResolveDiscountAction::class)->execute('RAMADAN10', $user->id, 300);
    expect($resolved['amount_discounted'])->toBe(20.0)
        ->and($resolved['final_amount'])->toBe(280.0);

    // Below minimum refuses.
    expect(fn () => app(ResolveDiscountAction::class)->execute('RAMADAN10', $user->id, 40))
        ->toThrow(ValidationException::class);

    // Per-user limit: one pending redemption blocks the same user, not others.
    DiscountRedemption::query()->create([
        'discount_code_id' => $resolved['discount_code']->id,
        'user_id' => $user->id,
        'purchase_type' => 'library_purchase',
        'purchase_id' => null,
        'amount_discounted' => 20,
        'status' => 'pending',
    ]);
    expect(fn () => app(ResolveDiscountAction::class)->execute('RAMADAN10', $user->id, 300))
        ->toThrow(ValidationException::class);
    expect(app(ResolveDiscountAction::class)->execute('RAMADAN10', $other->id, 300)['final_amount'])->toBe(280.0);

    // Global limit: a second consumed slot exhausts the code for everyone.
    DiscountRedemption::query()->create([
        'discount_code_id' => $resolved['discount_code']->id,
        'user_id' => $other->id,
        'purchase_type' => 'library_purchase',
        'purchase_id' => null,
        'amount_discounted' => 20,
        'status' => 'confirmed',
    ]);
    $third = User::factory()->create();
    expect(fn () => app(ResolveDiscountAction::class)->execute('RAMADAN10', $third->id, 300))
        ->toThrow(ValidationException::class);

    // A released redemption frees its slot.
    DiscountRedemption::query()->where('user_id', $user->id)->update(['status' => 'released']);
    expect(app(ResolveDiscountAction::class)->execute('RAMADAN10', $third->id, 300)['final_amount'])->toBe(280.0);
});
