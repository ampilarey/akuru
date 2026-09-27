<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who decided an enrolment, and when (admin-panel audit finding 6,
 * KNOWN_ISSUES "Enrolment decisions record no actor", STATUS §5ih).
 *
 * Activate, reject, suspend, reinstate and the access window wrote the
 * status and nothing about who did it; refunds, manual payments and wallet
 * credits do record the actor. Additive (rule 9): three nullable columns
 * that every existing row leaves empty — "no decision recorded" — and the
 * webhook's activation keeps leaving empty, because the system decided.
 * The last decision is kept, not a log; a platform-wide activity log is
 * its own decision (finding 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('decided_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by_user_id');
            $table->string('decision', 32)->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by_user_id');
            $table->dropColumn(['decided_at', 'decision']);
        });
    }
};
