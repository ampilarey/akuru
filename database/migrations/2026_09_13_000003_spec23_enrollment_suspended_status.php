<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC §23 "Enrollment Statuses" lists six:
 *
 *   > Active · Pending payment · Pending approval · **Suspended** · Completed ·
 *   > Cancelled
 *
 * The column held five of them — `pending`, `approved`, `rejected`, `active`,
 * `completed`, `cancelled` — with the two "pending" cases folded into one
 * `pending` plus a separate `payment_status` column, which is a reasonable
 * reading. **`suspended` was simply absent**, and appears nowhere in the
 * codebase at all.
 *
 * That matters more than a missing enum value usually would, because §23's own
 * seat rule names it:
 *
 *   > Cancelled/**suspended** enrollments should not count as active seats.
 *
 * A rule written about a status the database cannot hold is a rule that has
 * never once been exercised.
 *
 * Purely additive, which is what rule 9 asks for: widening an enum neither
 * drops nor renames anything, every existing row keeps the value it has, and
 * nothing is read differently until something writes the new value. The `down`
 * narrows it again and is safe only because no row can hold `suspended` unless
 * this migration ran.
 */
return new class extends Migration
{
    private const WITH_SUSPENDED = "'pending','approved','rejected','active','completed','cancelled','suspended'";

    private const WITHOUT_SUSPENDED = "'pending','approved','rejected','active','completed','cancelled'";

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE course_enrollments MODIFY status ENUM('.self::WITH_SUSPENDED.") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        // Any row actually suspended goes back to cancelled rather than being
        // silently truncated to an empty string, which is what MySQL does to a
        // value that no longer fits its enum.
        DB::table('course_enrollments')->where('status', 'suspended')->update(['status' => 'cancelled']);

        DB::statement(
            'ALTER TABLE course_enrollments MODIFY status ENUM('.self::WITHOUT_SUSPENDED.") NOT NULL DEFAULT 'pending'"
        );
    }
};
