<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral credit (STATUS §5ln): a customer's share link; a friend whose
 * first Bookstore order came through it; once that order is delivered and
 * its return window has passed, wallet credit for both — off until the
 * office turns it on. The money is in the wallet ledger; a referral row
 * records who asked whom and what each was paid. Additive: two tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('bookshop_checkout_id')->constrained('bookshop_checkouts')->restrictOnDelete();
            $table->string('code', 16);
            $table->string('status', 12)->default('pending'); // pending | paid | void
            $table->decimal('base_amount', 10, 2)->nullable();
            $table->decimal('referrer_amount', 10, 2)->nullable();
            $table->decimal('friend_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('MVR');
            $table->unsignedBigInteger('referrer_wallet_transaction_id')->nullable();
            $table->unsignedBigInteger('friend_wallet_transaction_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('referrer_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
    }
};
