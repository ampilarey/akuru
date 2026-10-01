<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P6a (the owner: "inventory and delivery handled by the
 * vendor or by Akuru, with an extra charge when Akuru does it"). Additive
 * (rule 9): every column has the old behaviour as its default — the shop
 * packs and delivers, and nothing is charged.
 *
 *  - `vendors.fulfilment` / `vendors.delivery_by`: `vendor` or `akuru`;
 *    `vendors.akuru_handling_fee`: the office's per-shop override of the
 *    handling fee (null = the setting).
 *  - `orders.fulfilled_by`, `orders.akuru_handling_fee`,
 *    `orders.delivery_revenue_to`: fixed on the order when it is placed, so
 *    a later change to the shop's arrangement never rewrites an order.
 *  - `vendor_earnings.akuru_handling_fee`: taken off the shop's earning (a
 *    line of its own, never an edit — rule 12); `vendor_commission_invoices.handling`
 *    its line on the monthly invoice.
 *  - `products.stock_at_akuru`: the part of `stock` the shop has handed to
 *    Akuru to pack.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('fulfilment', 10)->default('vendor')->after('trusted');
            $table->string('delivery_by', 10)->default('vendor')->after('fulfilment');
            $table->decimal('akuru_handling_fee', 10, 2)->nullable()->after('delivery_by');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfilled_by', 10)->default('vendor')->after('delivery_handling_days');
            $table->decimal('akuru_handling_fee', 10, 2)->default(0)->after('fulfilled_by');
            $table->string('delivery_revenue_to', 10)->default('vendor')->after('akuru_handling_fee');
            $table->index(['fulfilled_by', 'status']);
        });
        Schema::table('vendor_earnings', function (Blueprint $table) {
            $table->decimal('akuru_handling_fee', 10, 2)->default(0)->after('commission_tax');
        });
        Schema::table('vendor_commission_invoices', function (Blueprint $table) {
            $table->decimal('handling', 10, 2)->default(0)->after('tax');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock_at_akuru')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('stock_at_akuru'));
        Schema::table('vendor_commission_invoices', fn (Blueprint $table) => $table->dropColumn('handling'));
        Schema::table('vendor_earnings', fn (Blueprint $table) => $table->dropColumn('akuru_handling_fee'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['fulfilled_by', 'status']);
            $table->dropColumn(['fulfilled_by', 'akuru_handling_fee', 'delivery_revenue_to']);
        });
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn(['fulfilment', 'delivery_by', 'akuru_handling_fee']));
    }
};
