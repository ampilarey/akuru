<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BACKLOG C16 slice N4 (STATUS §5nz; OWNER_ACTIONS 15, decided 2026-10-03:
 * "Add \"withdrawn\""). A fifth value for a pupil who has left the
 * Institute, and the three columns an ending needs — when, why, by whom.
 * Widening an enum and adding nullable columns is additive (rule 9); the
 * four existing values and every row keep their meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `hifz_enrollments` MODIFY `status` ENUM('active', 'paused', 'completed', 'transferred', 'withdrawn') NOT NULL DEFAULT 'active'");
        } else {
            Schema::table('hifz_enrollments', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->change();
            });
        }

        Schema::table('hifz_enrollments', function (Blueprint $table) {
            $table->date('ended_at')->nullable()->after('status');
            $table->string('end_reason', 500)->nullable()->after('ended_at');
            $table->foreignId('ended_by')->nullable()->after('end_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hifz_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ended_by');
            $table->dropColumn(['ended_at', 'end_reason']);
        });
        // The enum is left widened: narrowing it would fail on any row that
        // reads `withdrawn`, and a rollback must not lose who has left.
    }
};
