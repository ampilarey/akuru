<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One `teachers` row per user, enforced by the database.
 *
 * `teachers.user_id` carried a foreign key but **no unique index**, so nothing
 * below the application prevented a user having two teacher profiles.
 * Idempotence rested entirely on `EnsureTeacherRowAction` checking first — a
 * read-then-write that two concurrent requests can both pass.
 *
 * Duplicates are collapsed before the index is added rather than letting the
 * migration fail on them. The **lowest id wins**: it is the row other tables
 * have had longest to point at, so keeping it is the choice least likely to
 * orphan a reference. Any row actually removed is reported, because silently
 * deleting a teacher profile would be worse than the duplicate.
 *
 * Rule 9: nothing is dropped or renamed, and the column keeps its data. On a
 * database with no duplicates — every one checked so far — this migration only
 * adds an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('teachers')
            ->select('user_id', DB::raw('COUNT(*) as total'), DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $removed = DB::table('teachers')
                ->where('user_id', $row->user_id)
                ->where('id', '!=', $row->keep_id)
                ->pluck('id')
                ->all();

            DB::table('teachers')->whereIn('id', $removed)->delete();

            // Loud on purpose: a deleted teacher profile is worth a line in the
            // deploy log, even when it was a duplicate.
            logger()->warning('teachers.user_id de-duplicated before adding a unique index', [
                'user_id' => $row->user_id,
                'kept' => $row->keep_id,
                'removed' => $removed,
            ]);
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->unique('user_id', 'teachers_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique('teachers_user_id_unique');
        });
    }
};
