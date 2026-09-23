<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S4.1 said: *"replace `applicable_grades` json with explicit assignment via
 * fee structures (kept during transition)"*. The structures shipped in S4.2;
 * the transition never closed. A month on the column was null on every
 * row of every deployment, written by an action the screen never sent it
 * to, and read by one listing that no screen displayed (S4 audit D6,
 * STATUS §5fc).
 *
 * Refused if a row carries a value — that would mean somebody is using it
 * after all, and the answer is to look, not to drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fee_items', 'applicable_grades')) {
            return;
        }

        $rows = (int) DB::table('fee_items')->whereNotNull('applicable_grades')->count();
        if ($rows > 0) {
            throw new RuntimeException(
                "fee_items.applicable_grades holds a value on {$rows} row(s); it was believed unused. Look before dropping."
            );
        }

        Schema::table('fee_items', function (Blueprint $table) {
            $table->dropColumn('applicable_grades');
        });
    }

    public function down(): void
    {
        // Forward-only (rule 9). The column was null everywhere.
    }
};
