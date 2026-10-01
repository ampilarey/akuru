<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P8d: pre-orders. A shop gives a product a release
 * date; until then it sells with no stock, paid in full, and each order
 * remembers the date it ships from. On that date the buyer and the shop are
 * told (`preorder_released_at` marks it done, once). Additive and nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('preorder_release_on')->nullable()->after('lead_days');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->date('ships_from')->nullable()->after('paid_at');
            $table->timestamp('preorder_released_at')->nullable()->after('ships_from');
            $table->index(['ships_from', 'preorder_released_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ships_from', 'preorder_released_at']);
            $table->dropColumn(['ships_from', 'preorder_released_at']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('preorder_release_on');
        });
    }
};
