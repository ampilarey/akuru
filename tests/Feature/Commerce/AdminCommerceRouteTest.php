<?php

use App\Domains\Commerce\Models\DiscountCode;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The money routes had no route-level test.
 *
 * `CommerceCoreTest` covers the actions well — append-only ledger, hashed gift
 * cards, discount resolution — but nothing exercised the three admin endpoints
 * that create money. They are guarded twice, by `role:super_admin|admin` **and**
 * `can:commerce.manage`, which is right; these tests exist so that stays true.
 *
 * Written after finding a real hole where `/students` and `/teachers` were
 * guarded by `auth` alone (#218). A money endpoint is the last place to assume
 * the guard is holding because it looks like it should be.
 */
function commerceAdmin(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $user->assignRole('admin');
    $user->givePermissionTo('commerce.manage');

    return $user;
}

/** The role without the permission — the half-privileged case. */
function commerceAdminWithoutPermission(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $user->assignRole('admin');

    return $user;
}

it('refuses a signed-in account with no role', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->post(route('admin.commerce.gift-cards.store'), ['amount' => 500])
        ->assertForbidden();

    expect(GiftCard::query()->count())->toBe(0);
});

it('refuses a parent issuing a gift card', function () {
    $parent = User::factory()->create();
    Role::findOrCreate('parent', 'web');
    $parent->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('admin.commerce.gift-cards.store'), ['amount' => 500])
        ->assertForbidden();

    expect(GiftCard::query()->count())->toBe(0);
});

it('refuses an admin who lacks commerce.manage', function () {
    // Both guards matter: the role alone must not be enough to mint money.
    $this->withoutLocalizationMiddleware()
        ->actingAs(commerceAdminWithoutPermission())
        ->post(route('admin.commerce.wallet-credits.store'), [
            'user_id' => User::factory()->create()->id,
            'amount' => 250,
        ])
        ->assertForbidden();

    expect(WalletTransaction::query()->count())->toBe(0);
});

it('refuses an anonymous visitor', function () {
    $this->withoutLocalizationMiddleware()
        ->post(route('admin.commerce.wallet-credits.store'), ['user_id' => 1, 'amount' => 10])
        ->assertRedirect();

    expect(WalletTransaction::query()->count())->toBe(0);
});

it('issues a gift card and shows the plain code exactly once', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(commerceAdmin())
        ->post(route('admin.commerce.gift-cards.store'), [
            'amount' => 500,
            'recipient_name' => 'Aminath',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('gift_card_code');

    $card = GiftCard::query()->sole();

    // §43.19: the code is stored **hashed** and the plain value is flashed
    // exactly once. There is no `code` column at all — only `code_hash` — so a
    // leak of the table does not leak spendable cards.
    expect((float) $card->original_amount)->toBe(500.0)
        ->and((float) $card->balance_amount)->toBe(500.0)
        ->and($card->code_hash)->not->toBe(session('gift_card_code'))
        ->and(session('gift_card_code'))->not->toBeEmpty();
});

it('credits a wallet through the ledger rather than a balance write', function () {
    $target = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs(commerceAdmin())
        ->post(route('admin.commerce.wallet-credits.store'), [
            'user_id' => $target->id,
            'amount' => 250,
            'description' => 'Goodwill',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $wallet = Wallet::query()->where('user_id', $target->id)->sole();
    $entry = WalletTransaction::query()->sole();

    // Rule 12: the ledger is the record, and it is append-only.
    expect((float) $wallet->balance)->toBe(250.0)
        ->and((float) $entry->amount)->toBe(250.0)
        ->and((float) $entry->balance_before)->toBe(0.0)
        ->and((float) $entry->balance_after)->toBe(250.0);
});

it('refuses a wallet credit for a user that does not exist', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(commerceAdmin())
        ->post(route('admin.commerce.wallet-credits.store'), [
            'user_id' => 999999,
            'amount' => 250,
        ])
        ->assertSessionHasErrors('user_id');

    expect(WalletTransaction::query()->count())->toBe(0);
});

it('refuses a zero or negative credit', function () {
    $target = User::factory()->create();

    foreach ([0, -50] as $amount) {
        $this->withoutLocalizationMiddleware()
            ->actingAs(commerceAdmin())
            ->post(route('admin.commerce.wallet-credits.store'), [
                'user_id' => $target->id,
                'amount' => $amount,
            ])
            ->assertSessionHasErrors('amount');
    }

    // A "credit" that removes money would be a reversal, and reversals are not
    // this endpoint's job.
    expect(WalletTransaction::query()->count())->toBe(0);
});

it('saves a discount code', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(commerceAdmin())
        ->post(route('admin.commerce.discount-codes.store'), [
            'code' => 'RAMADAN25',
            'discount_type' => 'percentage',
            'discount_value' => 25,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(DiscountCode::query()->sole()->code)->toBe('RAMADAN25');
});

it('refuses a discount type it does not understand', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(commerceAdmin())
        ->post(route('admin.commerce.discount-codes.store'), [
            'code' => 'ODD',
            'discount_type' => 'buy_one_get_one',
            'discount_value' => 1,
        ])
        ->assertSessionHasErrors('discount_type');

    expect(DiscountCode::query()->count())->toBe(0);
});
