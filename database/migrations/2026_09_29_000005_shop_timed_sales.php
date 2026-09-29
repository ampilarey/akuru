<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timed sales (STATUS §5lb): a shop puts a product on sale at a percentage
 * off, from an optional start until an end it must give. While the sale runs
 * the cart and the checkout charge the lower price; after it ends, the list
 * price. Additive: three nullable columns, nothing backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('sale_percent')->nullable()->after('compare_at_price');
            $table->timestamp('sale_starts_at')->nullable()->after('sale_percent');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
            $table->index('sale_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sale_ends_at']);
            $table->dropColumn(['sale_percent', 'sale_starts_at', 'sale_ends_at']);
        });
    }
};
