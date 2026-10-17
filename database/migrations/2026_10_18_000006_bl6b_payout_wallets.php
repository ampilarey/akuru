<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL6b (STATUS §5sn, §7.3): how a writer payout is paid. `bank`
 * (every existing row) as today; `wallet` into an Akuru wallet — a student's
 * earnings go to the consenting parent's wallet while the student is under
 * 18 (`wallet_user_id`), never to a bank. Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_payouts', function (Blueprint $table) {
            $table->string('method', 10)->default('bank')->after('currency');
            $table->foreignId('wallet_user_id')->nullable()->after('method')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('writer_payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_user_id');
            $table->dropColumn('method');
        });
    }
};
