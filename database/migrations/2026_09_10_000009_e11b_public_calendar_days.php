<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E11b — which calendar entries families and teachers may see.
 *
 * The staff calendar already records five types. The portal read returns only
 * `holiday` and `closure`, so a sports day, an exam week or a half-day
 * timetable is entered by the office and **seen by nobody else**. That is the
 * same defect as E4's noticeboard and E22's notifications: data captured, never
 * read.
 *
 * Broadening the read alone would have been wrong. `calendar_days` has no
 * audience, so publishing every type would push internal entries — a staff
 * meeting, a note to the office — to every parent in one deploy. So the
 * audience becomes explicit.
 *
 * Backfilled rather than defaulted (rule 9): `is_public` is true exactly where
 * the portal already showed the row, so **today's behaviour is preserved to the
 * row** and nothing new appears until somebody ticks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_days', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('affects_timetable');
        });

        // Exactly what ListCalendarHolidaysAction already published.
        DB::table('calendar_days')
            ->whereIn('type', ['holiday', 'closure'])
            ->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('calendar_days', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
