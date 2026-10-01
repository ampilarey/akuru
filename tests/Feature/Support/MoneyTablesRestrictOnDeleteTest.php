<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Actions\DeleteUserAccountAction;
use App\Domains\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Money records are kept, not dropped (rule 12; the Bookstore audit's
 * finding 7, STATUS §5ii). Every money-table foreign key used to cascade on
 * delete, so deleting a person, a vendor or a writer would have taken their
 * payments, ledger, orders, earnings and payouts with them. They now
 * restrict: the database refuses, whatever code asks, and the account
 * delete degrades to a deactivation because it counts these tables first.
 */
it('makes every money-table key refuse a delete rather than cascade', function () {
    $expected = [
        'payments' => ['user_id'],
        'wallets' => ['user_id'],
        'wallet_transactions' => ['user_id', 'wallet_id'],
        'gift_card_orders' => ['user_id'],
        'gift_card_transactions' => ['user_id', 'gift_card_id'],
        'payment_refunds' => ['payment_id'],
        'bookshop_checkouts' => ['user_id'],
        'orders' => ['user_id', 'vendor_id', 'bookshop_checkout_id'],
        'vendor_earnings' => ['vendor_id', 'order_id'],
        'vendor_payouts' => ['vendor_id'],
        'library_purchases' => ['user_id', 'library_item_id'],
        'writer_earnings' => ['writer_id', 'library_purchase_id', 'library_item_id'],
        'writer_payouts' => ['writer_id'],
        // COMMERCE_PARITY_PLAN P8c: credit accounts and their ledger.
        'shop_credit_accounts' => ['user_id'],
        'shop_credit_entries' => ['shop_credit_account_id', 'bookshop_checkout_id'],
    ];

    $cascading = [];
    foreach ($expected as $table => $columns) {
        $keys = collect(Schema::getForeignKeys($table));
        foreach ($columns as $column) {
            $key = $keys->first(fn (array $fk) => $fk['columns'] === [$column]);
            expect($key)->not->toBeNull("{$table}.{$column} has no foreign key");
            if (! in_array(strtoupper((string) $key['on_delete']), ['RESTRICT', 'NO ACTION'], true)) {
                $cascading[] = "{$table}.{$column} on delete {$key['on_delete']}";
            }
        }
    }

    expect($cascading)->toBe([], 'Money keys that still cascade: '.implode(', ', $cascading));
});

it('refuses to delete a person with a wallet ledger, and the account delete deactivates instead', function () {
    $user = User::factory()->create(['is_active' => true]);
    app(CreditWalletAction::class)->execute($user->id, 50, 'admin', null, 'Opening credit');

    // The raw delete the old cascade would have allowed is refused by the database.
    expect(fn () => DB::table('users')->where('id', $user->id)->delete())->toThrow(QueryException::class);
    expect(User::query()->find($user->id))->not->toBeNull()
        ->and(DB::table('wallet_transactions')->where('user_id', $user->id)->count())->toBe(1);

    // The office's path never meets that refusal: it counts the ledger and deactivates.
    $result = app(DeleteUserAccountAction::class)->execute($user, null);
    expect($result['deleted'])->toBeFalse()
        ->and($result['deactivated'])->toBeTrue()
        ->and($result['blocked_by'])->toHaveKeys(['wallets', 'wallet_transactions'])
        ->and((bool) $user->fresh()->is_active)->toBeFalse();
});
