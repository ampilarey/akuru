<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B11 (LIBRARY_PLAN §41, STATUS §5iu): when a reader was last reminded to
 * pick a book back up, so the reminder goes once and not every night.
 * Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_reading_progress', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('library_reading_progress', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
