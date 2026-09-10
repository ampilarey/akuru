<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E6a — sign-up sheets and surveys.
 *
 * A poll (E2b-b) answers one question inside a conversation. A form is the
 * thing a school actually sends for a trip: several questions, a window it is
 * open for, and a results table somebody has to work from.
 *
 * Audience targeting reuses the announcement shape (`target_audience` /
 * `target_classes`) rather than inventing a second vocabulary — one idea of
 * "who is this for", matched by one action.
 *
 * `form_responses` records something that happens in time, so it carries
 * `academic_year_id` (rule 10). The form itself carries one too: "the trip
 * sign-up" means a different sheet each year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('fields');
            $table->json('target_audience')->nullable();
            $table->json('target_classes')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            // An anonymous survey stores no person id at all — not a hidden
            // one. Anything else is a promise the schema cannot keep.
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'opens_at']);
        });

        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            // Nullable for anonymous forms, where there is deliberately nobody
            // to record.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['form_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('forms');
    }
};
