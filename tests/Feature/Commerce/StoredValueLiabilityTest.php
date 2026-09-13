<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\RedeemGiftCardAction;
use App\Domains\Commerce\Actions\ResolveStoredValueLiabilityAction;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §13.7 lists "gift card liabilities, wallet balance liabilities"
 * among the admin reports. **Neither was computed anywhere** — `grep -i
 * liabilit` across `app/` and `resources/js/` found two unrelated code
 * comments and nothing else.
 *
 * It is an accounting figure, not a nicety: every unspent gift card and every
 * laari of wallet credit is an obligation to hand over goods later, against
 * money either already taken or never taken at all (an admin-issued card is a
 * gift the Institute funds itself). A school that cannot say what it owes
 * cannot close its books.
 *
 * **The one way this could be wrong is double counting**, and it is the case
 * worth testing hardest. `RedeemGiftCardAction` *moves* value: it zeroes the
 * card and credits the wallet inside one transaction. If the report summed
 * face values, or counted redeemed cards, redemption would appear to conjure
 * money the Institute does not owe twice over.
 */
function liability(): array
{
    return app(ResolveStoredValueLiabilityAction::class)->execute();
}

it('reports nothing owed when nothing has been issued', function () {
    expect(liability())
        ->toMatchArray(['gift_cards' => 0.0, 'wallets' => 0.0, 'total' => 0.0]);
});

it('counts an issued gift card as money owed', function () {
    app(IssueGiftCardAction::class)->execute(['amount' => 500.0, 'created_by' => User::factory()->create()->id]);

    expect(liability())
        ->toMatchArray(['gift_cards' => 500.0, 'wallets' => 0.0, 'total' => 500.0]);
});

it('does not count the same money twice when a card is redeemed into a wallet', function () {
    // The heart of it. Redemption moves value; it does not create any.
    $issued = app(IssueGiftCardAction::class)->execute(['amount' => 500.0, 'created_by' => User::factory()->create()->id]);

    $before = liability();
    expect($before['total'])->toBe(500.0);

    $reader = User::factory()->create();
    app(RedeemGiftCardAction::class)->execute($reader->id, $issued['plain_code']);

    $after = liability();

    expect($after['total'])->toBe(500.0)          // unchanged — that is the point
        ->and($after['gift_cards'])->toBe(0.0)    // the card gave its balance up
        ->and($after['wallets'])->toBe(500.0);    // the wallet took it on
});

it('adds admin wallet credit to what is owed', function () {
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 120.0, 'admin', null, 'Goodwill');

    expect(liability())
        ->toMatchArray(['wallets' => 120.0, 'total' => 120.0]);
});

it('stops counting a card that can no longer be spent', function () {
    // Deactivated, expired, empty: not claimable, so not owed. Whether an
    // expired card should still be honoured is a §24 policy question the owner
    // has not answered; until it is, an unclaimable card is not a liability.
    $issued = app(IssueGiftCardAction::class)->execute(['amount' => 300.0, 'created_by' => User::factory()->create()->id]);

    expect(liability()['total'])->toBe(300.0);

    GiftCard::query()->whereKey($issued['gift_card']->id)->update(['status' => 'deactivated']);

    expect(liability()['total'])->toBe(0.0);
});

it('stops counting a card whose expiry has passed, before anyone flips its status', function () {
    // The status flip happens lazily, on the next redemption attempt. A report
    // that waited for it would overstate what the Institute owes.
    $issued = app(IssueGiftCardAction::class)->execute(['amount' => 250.0, 'created_by' => User::factory()->create()->id]);

    GiftCard::query()->whereKey($issued['gift_card']->id)->update([
        'status' => 'active',
        'expires_at' => now()->subDay(),
    ]);

    expect(liability())
        ->toMatchArray(['gift_cards' => 0.0, 'total' => 0.0]);
});

it('shows the figures to an admin on the commerce screen', function () {
    $issued = app(IssueGiftCardAction::class)->execute(['amount' => 500.0, 'created_by' => User::factory()->create()->id]);
    app(RedeemGiftCardAction::class)->execute(User::factory()->create()->id, $issued['plain_code']);

    $admin = actingPeopleAdmin(['commerce.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('admin.commerce.index'))
        ->assertOk()
        // Compared as numbers: JSON renders 0.0 as 0 and 500.0 as 500, which
        // is right for the browser and would fail an identical-type assertion.
        ->assertInertia(fn ($page) => $page
            ->where('liability.gift_cards', fn ($v) => (float) $v === 0.0)
            ->where('liability.wallets', fn ($v) => (float) $v === 500.0)
            ->where('liability.total', fn ($v) => (float) $v === 500.0)
            ->etc()
        );
});
