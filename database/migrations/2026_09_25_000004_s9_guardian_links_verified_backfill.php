<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * OWNER_ACTIONS item 13, decided 2026-09-25: the parent↔child link's
 * verification status becomes a **gate**. From this deploy a parent sees a
 * child only through a link the office has verified.
 *
 * Every link that exists today was created by the office on the student's
 * profile, or by a seeder — never by a stranger through the public form — so
 * marking them all verified records what is true rather than granting
 * anything. Without this backfill the gate would hide every child from every
 * parent overnight, which is the outcome `GuardianVerificationStatus` warned
 * about for a year.
 *
 * `verified_at` is stamped with the migration's own time, and `notes` records
 * why, so a link verified by backfill is distinguishable from one a person
 * checked. Rejected links are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('guardian_student')
            ->where('verification_status', 'unverified')
            ->update([
                'verification_status' => 'verified',
                'verified_at' => now(),
                'notes' => DB::raw("CONCAT_WS('\n', notes, 'Verified by the 2026-09-25 backfill: every link then existing was created by the office or a seeder, before self-registration could create one.')"),
            ]);
    }

    public function down(): void
    {
        // Only the rows this migration verified: they carry its note.
        DB::table('guardian_student')
            ->where('verification_status', 'verified')
            ->where('notes', 'like', '%Verified by the 2026-09-25 backfill%')
            ->update([
                'verification_status' => 'unverified',
                'verified_at' => null,
                'notes' => DB::raw("NULLIF(TRIM(BOTH '\n' FROM REPLACE(notes, 'Verified by the 2026-09-25 backfill: every link then existing was created by the office or a seeder, before self-registration could create one.', '')), '')"),
            ]);
    }
};
