<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether leave was paid decided a salary deduction by travelling through an
 * English sentence.
 *
 * `ApproveStaffLeaveAction` knows the answer — `leave_types.paid` is a boolean
 * it has in hand — and wrote it into `staff_attendance.remarks` as one of four
 * strings ("Approved leave", "Approved unpaid leave", and the two half-day
 * variants). `CountUnpaidLeaveDaysAction` then read it back with
 * `where('remarks', 'like', '%unpaid%')` and `count()`, and that count
 * multiplied `basic_salary / working_days` into a payslip.
 *
 * Rule 11, with money attached. These two columns carry the fact itself, so
 * the sentence goes back to being a note a human reads.
 *
 * **Additive, then backfilled, then the reader switches** (rule 9). The
 * backfill is exact for rows this app wrote, because it wrote exactly those
 * four strings; a hand-typed `on_leave` remark that merely contains "unpaid"
 * is backfilled to a full unpaid day, which is what it counted as yesterday.
 * Nothing is dropped here — `remarks` keeps every word it had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            // Null means "this row is not a leave row, or nobody recorded
            // which kind" — deliberately three-state rather than defaulting to
            // paid, so an unbackfilled row is visible as unknown.
            $table->boolean('leave_paid')->nullable()->after('status');
            $table->decimal('leave_day_fraction', 2, 1)->nullable()->after('leave_paid');
        });

        // Machine-written rows: exact, including the half-days the old counter
        // charged as whole days.
        DB::table('staff_attendance')->where('status', 'on_leave')
            ->where('remarks', 'Approved leave')
            ->update(['leave_paid' => true, 'leave_day_fraction' => 1.0]);

        DB::table('staff_attendance')->where('status', 'on_leave')
            ->where('remarks', 'Half-day leave')
            ->update(['leave_paid' => true, 'leave_day_fraction' => 0.5]);

        DB::table('staff_attendance')->where('status', 'on_leave')
            ->where('remarks', 'Approved unpaid leave')
            ->update(['leave_paid' => false, 'leave_day_fraction' => 1.0]);

        DB::table('staff_attendance')->where('status', 'on_leave')
            ->where('remarks', 'Half-day unpaid leave')
            ->update(['leave_paid' => false, 'leave_day_fraction' => 0.5]);

        // Anything else on leave that the old `LIKE '%unpaid%'` would have
        // caught. Preserved rather than corrected: changing what a past
        // payslip was based on is not this migration's call.
        DB::table('staff_attendance')->where('status', 'on_leave')
            ->whereNull('leave_paid')
            ->where('remarks', 'like', '%unpaid%')
            ->update(['leave_paid' => false, 'leave_day_fraction' => 1.0]);

        // Remaining `on_leave` rows are paid leave by the same reading.
        DB::table('staff_attendance')->where('status', 'on_leave')
            ->whereNull('leave_paid')
            ->update(['leave_paid' => true, 'leave_day_fraction' => 1.0]);
    }

    public function down(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->dropColumn(['leave_paid', 'leave_day_fraction']);
        });
    }
};
