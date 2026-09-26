<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B2: cart and checkout. Every table is additive.
 *
 * Money is decimal(10,2) MVR like the wallet (B1a). Status columns are
 * strings, not DB enums, backed by string PHP enums that hold only the
 * values this slice writes; B3 adds the fulfilment statuses when it writes
 * them. No `academic_year_id`: commerce records (rule 10 precedent).
 *
 * `bookshop_checkouts` is a Finance payable (`bookshop_checkout` in
 * config/morph-map.php, ADR-005): one BML payment per checkout, one order
 * per vendor under it (plan §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('name');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('free_over', 10, 2)->nullable();
            $table->decimal('minimum_order', 10, 2)->nullable();
            // Plan audit finding 5: a boat fee is paid to the carrier on
            // arrival — shown at checkout, never charged.
            $table->boolean('carrier_paid_on_arrival')->default(false);
            $table->unsignedSmallInteger('handling_days')->default(1);
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vendor_id', 'is_active']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('recipient_name');
            $table->string('phone', 40);
            $table->string('atoll', 80);
            $table->string('island', 120);
            $table->string('street');
            $table->string('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // A guest's cart: a random token kept in the session, merged into
            // the person's cart when they sign in (plan §4).
            $table->string('session_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'product_variant_id']);
        });

        // Order numbers: AK-YYYY-NNNNNN per checkout, sequential per year,
        // never reused (plan audit finding 16). One row per year, locked.
        Schema::create('bookshop_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next')->default(1);
        });

        Schema::create('bookshop_checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending_payment');
            $table->string('payment_method', 20);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('delivery_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->unsignedBigInteger('discount_code_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->json('address_snapshot');
            $table->string('notes', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('payment_id');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookshop_checkout_id')->constrained('bookshop_checkouts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['product_id', 'expires_at']);
            $table->index(['product_variant_id', 'expires_at']);
        });

        Schema::create('bank_transfer_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookshop_checkout_id')->constrained('bookshop_checkouts')->cascadeOnDelete();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('reference', 80)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->foreignId('bookshop_checkout_id')->constrained('bookshop_checkouts')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending_payment');
            $table->string('delivery_kind', 30);
            $table->string('delivery_name');
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->boolean('delivery_carrier_paid')->default(false);
            $table->unsignedSmallInteger('delivery_handling_days')->default(1);
            $table->json('address_snapshot');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->boolean('tax_shown')->default(false);
            $table->string('vendor_tin', 40)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('title');
            $table->string('variant_name')->nullable();
            $table->string('sku', 64)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->string('tax_class', 20)->default('standard');
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        // Append-only trail (plan §9): what happened to an order, by whom.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('bank_transfer_slips');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('bookshop_checkouts');
        Schema::dropIfExists('bookshop_sequences');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('vendor_delivery_methods');
    }
};
