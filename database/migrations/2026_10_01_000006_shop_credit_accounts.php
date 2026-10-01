<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P8c: credit accounts for schools (Bake & Grill's
 * CustomerCreditLedger). The office opens an account for a customer — a
 * school buying for its pupils — with a limit and terms; the customer pays
 * on account at checkout while the available credit covers the total; the
 * office records the school's payments.
 *
 *  - `shop_credit_accounts`: the limit, the terms, the status. Settings, so
 *    the office edits them.
 *  - `shop_credit_entries`: the ledger — a charge per checkout paid on
 *    account, a payment the office records, a refund when an order paid on
 *    account is refunded. Append-only (rule 12): never edited or removed;
 *    a mistake is put right by a new entry. Owed = charges − payments − refunds.
 *
 * Money tables: every key refuses a delete rather than cascade (as the
 * wallet ledger). Commerce, so no `academic_year_id` (as every bookstore table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('organisation', 160)->nullable();
            $table->decimal('credit_limit', 10, 2);
            $table->unsignedSmallInteger('terms_days')->default(30);
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('shop_credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_credit_account_id')->constrained('shop_credit_accounts')->restrictOnDelete();
            $table->string('kind', 20);
            $table->decimal('amount', 10, 2);
            $table->foreignId('bookshop_checkout_id')->nullable()->constrained('bookshop_checkouts')->restrictOnDelete();
            $table->foreignId('order_refund_id')->nullable()->constrained('order_refunds')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shop_credit_account_id', 'created_at'], 'shop_credit_entries_account_at');
            $table->unique(['shop_credit_account_id', 'kind', 'bookshop_checkout_id'], 'shop_credit_entries_one_charge');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_credit_entries');
        Schema::dropIfExists('shop_credit_accounts');
    }
};
