<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E22c — which categories of notification reach me.
 *
 * With E22b's digest firing nightly to every family, the only control a school
 * had was the global on/off switch. A parent who wants trip notices but not a
 * nightly summary had no way to say so, and the usual outcome is muting
 * everything — which kills the notices that mattered.
 *
 * **Absence of a row means opted in.** Storing only the choices people actually
 * make keeps the table small and means a category added later reaches everyone
 * by default rather than silently reaching nobody — the same inversion E4's
 * blank-audience rule had to avoid.
 *
 * No `academic_year_id` (rule 10 does not apply): a preference is standing
 * state, not something that happens in time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 32);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category'], 'notification_pref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
