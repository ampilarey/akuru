<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B8: bulk and operations. Additive only (rule 9).
 *
 *  - `stock_movements` (§9 "append-only: in, sale, return, adjustment, by
 *    whom"): one row per change to a counted product's or variant's stock —
 *    a sale, a cancellation or return put back, a vendor's edit, a CSV
 *    import, stock received — with the stock it left and who did it. Never
 *    updated or deleted by the app. No `academic_year_id`: commerce, as
 *    every bookshop table (B1a).
 *  - `products.low_stock_notified_at`, `product_variants.low_stock_notified_at`:
 *    the shop is told once when stock falls to its low-stock level, and
 *    again only after it has been back above it.
 *  - `vendors.notice_settings`: which shop notices also go by email or SMS
 *    (§5 "Notifications: which events email or SMS them").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('kind', 20); // in / sale / cancel / return / adjustment / import
            $table->integer('quantity'); // signed: + onto the shelf, − off it
            $table->integer('stock_after');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['vendor_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('low_stock_notified_at')->nullable()->after('low_stock_at');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->timestamp('low_stock_notified_at')->nullable()->after('stock');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->json('notice_settings')->nullable()->after('free_delivery_over');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn('notice_settings'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('low_stock_notified_at'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('low_stock_notified_at'));
        Schema::dropIfExists('stock_movements');
    }
};
