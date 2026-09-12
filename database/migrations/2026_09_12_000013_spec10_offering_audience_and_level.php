<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §10.5 and §10.6 say where audience and level live, in as many words:
 *
 *   > Audience is stored on **`course_offerings`** (§11), so the same course
 *   > template can run for different audiences without duplicating content.
 *
 *   > **Offerings:** `level_id` on `course_offerings` combines with
 *   > `audience_id` (§10.5) to describe *who* and *how advanced* a batch is —
 *   > e.g. Nahw Level 1 for Kids vs Nahw Level 2 for Adults on the same course
 *   > template.
 *
 * Neither column existed. Both taxonomies were built anyway: `audiences` and
 * `course_levels` are real admin-managed trilingual tables, seeded with
 * exactly §10.5's and §10.6's example values, with List/Save Actions, admin
 * screens, CSV export and a nav link.
 *
 * They had nowhere to attach. `level_id` is referenced by exactly one model in
 * the codebase (`GlossaryItem`), and **`audience_id` by nothing at all** — a
 * whole admin-managed dimension with zero referencing rows anywhere.
 *
 * What stood in for them is `courses.level`, an
 * `enum('kids','youth','adult','all')`. Three things are wrong with it and
 * only the third is fixed here:
 *
 *   1. Those are **audience** values wearing the name "level". §10.6's levels
 *      are Foundation / Beginner / A1 — a different dimension entirely.
 *   2. It is **hardcoded**, which §10.6 forbids outright ("These must not be
 *      hardcoded").
 *   3. It is **on the course**, which §10.2 forbids ("Audience and Level are
 *      **not** duplicated on every course row"), defeating the one-template-
 *      many-offerings design §10.1 opens with.
 *
 * `courses.level` is **left exactly as it is**. Rule 9: it is populated, and
 * the public site filters on it live (`PublicSite\CourseController`), so it
 * cannot be dropped or repurposed in the deploy that stops depending on it.
 * This migration is additive only; retiring that column is a later deploy with
 * its own backfill, and a decision about the public filter that is not this
 * slice's to make.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unsignedBigInteger('audience_id')->nullable()->after('delivery_mode');
            $table->unsignedBigInteger('level_id')->nullable()->after('audience_id');

            $table->index('audience_id');
            $table->index('level_id');
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropIndex(['audience_id']);
            $table->dropIndex(['level_id']);
            $table->dropColumn(['audience_id', 'level_id']);
        });
    }
};
