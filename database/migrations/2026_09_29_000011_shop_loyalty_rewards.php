<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookstore rewards (STATUS §5lm): once an order's return window has
 * passed, a share of what was paid for the goods goes back into the
 * customer's wallet — off until the office turns it on. One row per order,
 * written once with the wallet credit it made (append-only, Rule 12).
 * Additive: one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->decimal('base_amount', 10, 2); // goods, less discount and refunds; never delivery
            $table->decimal('percent', 5, 2);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};
