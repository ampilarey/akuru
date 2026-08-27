<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-track slice L3 (LIBRARY_PLAN §39.3): paid content. Access grants
 * (§35.4) are the single answer to "may this user read this item" for
 * non-free access types; purchases record the buy itself. Access is
 * granted by the BML WEBHOOK, never the return URL (§43.5). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->string('access_type', 20)->default('full');
            $table->string('source_type', 20); // purchase/course/admin/free/gift_card/wallet/coupon
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'library_item_id', 'status']);
        });

        Schema::create('library_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->string('status', 20)->default('pending'); // pending/paid/failed/refunded
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['library_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_purchases');
        Schema::dropIfExists('library_access_grants');
    }
};
