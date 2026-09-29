<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save for later (STATUS §5lf): a cart line set aside stays with the basket
 * but out of it — not counted, not charged, not checked out — until the
 * customer moves it back. Additive: one nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->timestamp('saved_at')->nullable()->after('quote_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropColumn('saved_at'));
    }
};
