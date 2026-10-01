<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P8: receipts by SMS link. A paid checkout gets a
 * short private token the first time its receipt link is made; the link
 * (`/shop/r/{token}`) opens the receipt without signing in. Additive and
 * nullable — a checkout gets its token when it is paid, never before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookshop_checkouts', function (Blueprint $table) {
            $table->string('receipt_token', 16)->nullable()->unique()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookshop_checkouts', function (Blueprint $table) {
            $table->dropUnique(['receipt_token']);
            $table->dropColumn('receipt_token');
        });
    }
};
