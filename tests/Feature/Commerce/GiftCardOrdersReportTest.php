<?php

use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Models\GiftCardOrder;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * LIBRARY_PLAN §7.7 "gift card usage" and §41 admin "gift card purchase":
 * the office sees what was bought at /gift-cards — buyer, recipient,
 * amount, whether the bank confirmed it, where the code went — and can
 * export it. The plain code appears nowhere (§43.19).
 */
function giftOrdersOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('commerce.manage');

    return $admin;
}

it('lists gift card purchases for the office, with the code masked, and exports them', function () {
    $buyer = User::factory()->create(['name' => 'Mariyam Buyer']);
    $issued = app(IssueGiftCardAction::class)->execute(['amount' => 250, 'purchaser_user_id' => $buyer->id, 'recipient_name' => 'Hawwa']);
    GiftCardOrder::query()->create([
        'user_id' => $buyer->id, 'amount' => 250, 'currency' => 'MVR', 'recipient_name' => 'Hawwa', 'recipient_email' => 'hawwa@example.test',
        'status' => 'paid', 'gift_card_id' => $issued['gift_card']->id, 'delivered_via' => 'email', 'delivered_to' => 'h***@example.test', 'delivered_at' => now(), 'paid_at' => now(),
    ]);
    GiftCardOrder::query()->create([
        'user_id' => $buyer->id, 'amount' => 100, 'currency' => 'MVR', 'recipient_name' => 'Nobody', 'recipient_email' => 'n@example.test', 'status' => 'failed',
    ]);
    // An office-issued card, for the source column.
    app(IssueGiftCardAction::class)->execute(['amount' => 50, 'recipient_name' => 'Office Gift']);

    $admin = giftOrdersOffice();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.commerce.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('gift_card_orders.0.recipient_name', 'Nobody')
            ->where('gift_card_orders.0.status', 'failed')
            ->where('gift_card_orders.1.buyer', 'Mariyam Buyer')
            ->where('gift_card_orders.1.delivered_to', 'h***@example.test')
            ->where('gift_card_orders.1.gift_card_id', $issued['gift_card']->id)
            ->where('gift_cards.0.source', 'office')
            ->where('gift_cards.1.source', 'purchased'));

    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.commerce.gift-card-orders.export'))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Mariyam Buyer')->toContain('Hawwa')->toContain('h***@example.test')
        ->and($csv)->not->toContain($issued['plain_code']);

    // Not for everyone.
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->get(route('admin.commerce.gift-card-orders.export'))
        ->assertForbidden();
});
