<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B10a: the owner does not want prices in dollars (2026-09-26), so B9f's
 * dollar guide is gone and its two office settings with it. Nothing was
 * ever charged in dollars; no order, price or payment changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', ['bookshop_usd_display', 'bookshop_usd_rate'])->delete();
    }

    public function down(): void
    {
        // Nothing to restore: the feature is gone.
    }
};
