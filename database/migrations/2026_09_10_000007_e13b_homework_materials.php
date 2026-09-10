<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E13b — a material can go home with the homework.
 *
 * E13a lets a teacher tick which materials a lesson used. That is not the same
 * fact as which materials a pupil needs at home: "whiteboard" and "mushaf" are
 * used in the room, "Alphabet worksheet — print double sided" goes home. Showing
 * a family everything the lesson touched would bury the one thing they need.
 *
 * One flag on the existing pivot rather than a second table (rule 11): the
 * homework lives on the lesson log, so its materials belong on the same link.
 * A material can be both — used in the lesson *and* sent home — which a boolean
 * expresses and two tables would not.
 *
 * Additive and defaulted false (rule 9): every existing attachment stays exactly
 * what it was, a lesson material that nobody has sent home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_log_material', function (Blueprint $table) {
            $table->boolean('for_homework')->default(false)->after('teaching_material_id');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_log_material', function (Blueprint $table) {
            $table->dropColumn('for_homework');
        });
    }
};
