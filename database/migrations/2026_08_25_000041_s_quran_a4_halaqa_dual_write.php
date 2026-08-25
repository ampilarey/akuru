<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qur’an A.4 — additive dual-write flags. No switch, no Hifz column drops.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offering_halaqa_links', function (Blueprint $table) {
            $table->boolean('dual_write')->default(false);
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('offering_halaqa_links', function (Blueprint $table) {
            $table->dropColumn(['dual_write', 'last_synced_at']);
        });
    }
};
