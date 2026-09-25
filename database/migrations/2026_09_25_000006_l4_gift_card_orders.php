<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBRARY_PLAN §15.3, the gift card purchase flow: "select amount →
 * recipient details → message → BML → webhook → generate code". Until now
 * gift cards were only ever issued by the office (§15.4's rule held by
 * accident: no purchase existed to discount).
 *
 * An order is what the buyer asked for and what the bank confirmed; the
 * card itself is still a `gift_cards` row, issued by the same
 * `IssueGiftCardAction` the office uses, only on the webhook. The plain
 * code is never stored (§43.19): it goes to the recipient once, and this
 * row records where and when.
 *
 * No `academic_year_id` (rule 10): a gift card order is commerce, like
 * `gift_cards`, `wallet_transactions` and `library_purchases`, none of
 * which carry the backbone — the money is not a term's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_mobile', 20)->nullable();
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending | paid | failed
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('gift_card_id')->nullable()->constrained('gift_cards')->nullOnDelete();
            $table->string('delivered_via', 20)->nullable(); // email | sms | email+sms
            $table->string('delivered_to')->nullable(); // masked
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_orders');
    }
};
