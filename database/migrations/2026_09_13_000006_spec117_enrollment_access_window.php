<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §11.7 "Enrollment and Offering Relationship" lists ten fields
 * `course_enrollments` should include:
 *
 *   > Course ID · Course offering ID nullable · Student ID · Enrollment type ·
 *   > Status · **Access starts at nullable** · **Access ends at nullable** ·
 *   > Completed at nullable · Progress percentage · Certificate issued at
 *   > nullable
 *
 * Eight were there. **"Access starts at" and "Access ends at" existed
 * nowhere**, so a student's access to a course had no time dimension at all:
 * `AuthorizeLessonAccessAction` gates on enrolment status and §26's unlock
 * rules and nothing else. An enrolment could be granted early, or run out, and
 * the product had no way to say so.
 *
 * The offering's own `starts_at` / `ends_at` are not the same thing and cannot
 * stand in. Those are the cohort's dates. §11.7 puts the window on the
 * *enrolment* because a student who joins late, transfers in, or whose paid
 * access is for a fixed term has a window of their own.
 *
 * **The tenth field is deliberately not added.** "Certificate issued at"
 * already lives on `issued_certificates`, which carries `enrollment_id`,
 * `issued_at` and `revoked_at`. Copying it here would be a second source of
 * truth for the same fact (rule 11), and the one that would rot is this one —
 * a revoked certificate would leave a stale issue date on the enrolment.
 *
 * Additive (rule 9): two nullable columns, nothing dropped or renamed, and
 * **null means unbounded at that end** — so every row that exists keeps
 * exactly the access it has today and no backfill is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table): void {
            if (! Schema::hasColumn('course_enrollments', 'access_starts_at')) {
                $table->timestamp('access_starts_at')->nullable()->after('enrolled_at');
            }
            if (! Schema::hasColumn('course_enrollments', 'access_ends_at')) {
                $table->timestamp('access_ends_at')->nullable()->after('access_starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['access_starts_at', 'access_ends_at']);
        });
    }
};
