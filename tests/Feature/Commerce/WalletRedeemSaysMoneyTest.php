<?php

use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The wallet screen prints money as `MVR 25.00` everywhere — the balance, the
 * ledger — and the one line that did not was the flash after redeeming a
 * gift card, which said "25 added to your wallet". The reader walk (STATUS
 * §5fo) caught it; this keeps it caught.
 */
it('tells the reader what was added as money, with its currency', function () {
    $office = User::factory()->create();
    $reader = User::factory()->create();
    $issued = app(IssueGiftCardAction::class)->execute([
        'amount' => 25,
        'recipient_name' => 'Reader',
        'created_by' => $office->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($reader)
        ->from(route('public.wallet'))
        ->post(route('public.wallet.redeem'), ['code' => $issued['plain_code']])
        ->assertRedirect(route('public.wallet'))
        ->assertSessionHas('success', 'Gift card redeemed: MVR 25.00 added to your wallet.');
});
