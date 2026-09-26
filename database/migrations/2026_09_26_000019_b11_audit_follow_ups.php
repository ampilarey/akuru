<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B11: the plan items the audit (BOOKSHOP_PLAN §15) found unbuilt.
 * Additive only (rule 9): a gift message on the checkout, copied to each
 * shop's order so the packing slip carries it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookshop_checkouts', fn (Blueprint $table) => $table->string('gift_message', 300)->nullable()->after('notes'));
        Schema::table('orders', fn (Blueprint $table) => $table->string('gift_message', 300)->nullable()->after('notes'));
    }

    public function down(): void
    {
        Schema::table('bookshop_checkouts', fn (Blueprint $table) => $table->dropColumn('gift_message'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('gift_message'));
    }
};
