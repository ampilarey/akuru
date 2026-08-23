<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1.1c — additive payments.unified_student_id so payment reads use students.
 * Existing student_id (→ registration_students) stays for dual-write. See ADR-008.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('unified_student_id')
                ->nullable()
                ->after('student_id')
                ->constrained('students')
                ->nullOnDelete();
        });

        DB::statement('
            UPDATE payments
            INNER JOIN students ON students.legacy_registration_student_id = payments.student_id
            SET payments.unified_student_id = students.id
            WHERE payments.student_id IS NOT NULL
              AND payments.unified_student_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unified_student_id');
        });
    }
};
