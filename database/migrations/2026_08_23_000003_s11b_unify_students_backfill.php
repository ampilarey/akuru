<?php

use Illuminate\Database\Migrations\Migration;

/**
 * S1.1b — was the thin caller for the registration_students → students
 * backfill (`UnifyStudentsAction`, ADR-007).
 *
 * Emptied by S1 Deploy 3, slice 3 (STATUS §5gh), which archived
 * `registration_students` and retired the backfill with it. Every
 * environment that existed before then ran the real backfill when this
 * migration first applied; a database created afterwards has no legacy rows,
 * so there is nothing for it to do. Kept as a record so migration history
 * stays identical everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
