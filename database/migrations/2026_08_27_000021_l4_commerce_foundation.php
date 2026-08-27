<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * L-track slice L4 (LIBRARY_PLAN §39.4 MVP subset): platform-wide Commerce —
 * wallets, gift cards, discount codes. Rule 12: wallet/gift-card ledgers are
 * APPEND-ONLY (reversals, never updates/deletes); gift cards are a PAYMENT
 * method, discounts are a PRICE reduction; gift card codes are stored
 * HASHED (§43.19), shown in plain exactly once. Campaigns, coupons and
 * bundles stay post-MVP (§38). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('currency', 3)->default('MVR');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // Append-only ledger (§35.8, §43.20): rows are never updated or
        // deleted — a mistake is corrected by a reversal row.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10); // credit / debit
            $table->string('source_type', 30); // gift_card/refund/admin/promotion/purchase/reversal
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique(); // §43.19 — plain code shown once
            $table->decimal('original_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->foreignId('purchaser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_mobile', 20)->nullable();
            $table->string('message', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Append-only (§43.20 applies to money movement generally).
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10); // redeem / adjust
            $table->decimal('amount', 10, 2);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('discount_type', 20); // percentage / fixed
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->string('applies_to_type', 30)->default('all');
            $table->string('discount_funding_source', 20)->default('akuru');
            $table->boolean('can_combine')->default(false);
            $table->boolean('can_use_with_wallet')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained('discount_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purchase_type', 30); // library_purchase (course orders join in Phase 4)
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->decimal('amount_discounted', 10, 2);
            $table->string('status', 20)->default('pending'); // pending/confirmed/released
            $table->timestamps();

            $table->index(['discount_code_id', 'status']);
            $table->index(['user_id', 'discount_code_id']);
        });

        Permission::firstOrCreate(['name' => 'commerce.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('commerce.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
