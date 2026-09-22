<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two S2 leftovers found by the 2026-09-22 audit (STATUS §5eu).
 *
 * 1. The pre-S2 `attendance` table. S2.4 replaced it with `class_attendance`
 *    (one writer, `AttendanceWriterInterface`, rule 11) and nothing has read
 *    or written the old table since — the only reference left was a
 *    `Student::attendance()` relation with no caller. Rule 9's "stop using,
 *    then drop" has had a month of nothing using it. Refused if a row has
 *    appeared in the meantime rather than deleting anything on a guess.
 *
 * 2. `course_plans.academic_year`, the string the S2.3 backbone migration
 *    kept beside `academic_year_id`. `SaveCoursePlanAction` was still writing
 *    both and the plans screen still read the string. This is deploy 2 of
 *    that switch: the column becomes nullable so the action can stop writing
 *    it; the drop is a later cleanup once no row still needs it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance')) {
            $rows = (int) DB::table('attendance')->count();
            if ($rows > 0) {
                throw new RuntimeException(
                    "attendance has {$rows} row(s); it was believed unused. Look before dropping."
                );
            }

            Schema::drop('attendance');
        }

        if (Schema::hasColumn('course_plans', 'academic_year')) {
            Schema::table('course_plans', function (Blueprint $table) {
                $table->string('academic_year')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Forward-only (rule 9). The table was empty; the column is only widened.
    }
};
