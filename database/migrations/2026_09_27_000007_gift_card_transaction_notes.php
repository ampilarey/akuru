<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B10 (LIBRARY_PLAN §15.2, STATUS §5is): the gift-card ledger can carry a
 * reason. A deactivation is a row in the append-only history (§43.20) —
 * type `deactivate`, the balance it froze, and the office's words for why —
 * so the fraud log is the ledger itself, not a second table. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
