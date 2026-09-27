<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money records are kept, not dropped (CLAUDE.md rule 12; the Bookstore
 * audit's finding 7, KNOWN_ISSUES "Deleting a customer or a vendor would
 * take their orders and money records with them", STATUS §5ii).
 *
 * `payments.user_id`, the wallet ledger, gift-card orders and redemptions,
 * refunds, the Bookstore's checkouts, orders, earnings and payouts, and the
 * Library's purchases and writer earnings all cascaded on delete: deleting
 * a person, a vendor or a writer would have taken every money record that
 * hung off them. Nothing deletes those rows today — accounts are
 * deactivated, shops suspended — so the cascade was latent, and the
 * platform's own precedent (`payments` cascaded from the start).
 *
 * The same keys now **restrict**: the database refuses the delete while
 * money hangs off the row, whatever code asks. `DeleteUserAccountAction`
 * already deactivates instead of deleting when anything depends on the
 * account, and now counts these tables too, so the office never meets the
 * refusal as an error. Additive in effect (rule 9): no column changes, no
 * data touched; each key is dropped and re-created with the new rule.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: string}> table, column, referenced table */
    private array $keys = [
        ['payments', 'user_id', 'users'],
        ['wallets', 'user_id', 'users'],
        ['wallet_transactions', 'user_id', 'users'],
        ['wallet_transactions', 'wallet_id', 'wallets'],
        ['gift_card_orders', 'user_id', 'users'],
        ['gift_card_transactions', 'user_id', 'users'],
        ['gift_card_transactions', 'gift_card_id', 'gift_cards'],
        ['payment_refunds', 'payment_id', 'payments'],
        ['bookshop_checkouts', 'user_id', 'users'],
        ['orders', 'user_id', 'users'],
        ['orders', 'vendor_id', 'vendors'],
        ['orders', 'bookshop_checkout_id', 'bookshop_checkouts'],
        ['vendor_earnings', 'vendor_id', 'vendors'],
        ['vendor_earnings', 'order_id', 'orders'],
        ['vendor_payouts', 'vendor_id', 'vendors'],
        ['library_purchases', 'user_id', 'users'],
        ['library_purchases', 'library_item_id', 'library_items'],
        ['writer_earnings', 'writer_id', 'writer_profiles'],
        ['writer_earnings', 'library_purchase_id', 'library_purchases'],
        ['writer_earnings', 'library_item_id', 'library_items'],
        ['writer_payouts', 'writer_id', 'writer_profiles'],
    ];

    public function up(): void
    {
        $this->rebuild('restrict');
    }

    public function down(): void
    {
        $this->rebuild('cascade');
    }

    private function rebuild(string $onDelete): void
    {
        foreach ($this->keys as [$table, $column, $references]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $column, $references, $onDelete): void {
                $t->dropForeign("{$table}_{$column}_foreign");
                $t->foreign($column)->references('id')->on($references)->onDelete($onDelete);
            });
        }
    }
};
