<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2b-b — a message thread can carry a question.
 *
 * "Will your child attend the trip?" is the thing schools actually want from
 * messaging, and the plan puts simple polls here rather than in E6's form
 * builder: the audience, the delivery and the reply policy already exist on a
 * thread, so a poll is a question attached to one, not a second system.
 *
 * Additive only (rule 9). Both tables record something that happens in time, so
 * both carry `academic_year_id` (rule 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_polls', function (Blueprint $table) {
            $table->id();
            // One poll per thread: two questions in one conversation is how
            // answers get attributed to the wrong one.
            $table->foreignId('message_thread_id')->unique()->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('question');
            $table->json('options');
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_poll_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_poll_id')->constrained('message_polls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedTinyInteger('choice');
            $table->timestamp('responded_at');
            $table->timestamps();

            // One answer per person; changing your mind updates the row rather
            // than adding a second vote.
            $table->unique(['message_poll_id', 'user_id'], 'message_poll_one_per_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_poll_responses');
        Schema::dropIfExists('message_polls');
    }
};
