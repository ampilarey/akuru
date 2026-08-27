<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-track slice L6 (LIBRARY_PLAN §21–§23, §35.5): writer earnings and
 * payouts. Earnings accrue per paid sale with the funding-source rules
 * (§21 models A/B/C) and mature only after the refund window (§24 / rule
 * §43.7 — earnings are never payable while a refund is possible). Payout
 * REQUESTS stay behind the §9.4 operator gate (config
 * library.payouts_enabled, default off) until the tax/accounting
 * treatment is confirmed. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->constrained('writer_profiles')->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('library_purchase_id')->unique()->constrained('library_purchases')->cascadeOnDelete();
            $table->decimal('gross_amount', 10, 2); // original item price
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_funding_source', 20)->nullable(); // shared/akuru/writer
            $table->decimal('wallet_amount', 10, 2)->default(0);
            $table->decimal('bml_amount', 10, 2)->default(0);
            $table->decimal('platform_commission', 10, 2);
            $table->decimal('writer_amount', 10, 2);
            $table->string('status', 20)->default('pending'); // pending/available/paid/refunded
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('writer_payout_id')->nullable();
            $table->timestamps();

            $table->index(['writer_id', 'status']);
        });

        Schema::create('writer_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->constrained('writer_profiles')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('status', 20)->default('requested'); // requested/paid/rejected
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['writer_id', 'status']);
        });

        Schema::create('writer_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->unique()->constrained('writer_profiles')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number', 50);
            $table->string('currency', 3)->default('MVR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_bank_details');
        Schema::dropIfExists('writer_payouts');
        Schema::dropIfExists('writer_earnings');
    }
};
