<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2a — threading over the existing single-recipient `messages` table.
 *
 * Additive only (rule 9): `messages.thread_id` is nullable, so the rows that
 * exist keep working untouched and nothing has to be backfilled before reads
 * switch over.
 *
 * `context_type` takes a morph alias, never an FQCN (ADR-005) — registered in
 * config/morph-map.php in this same slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // What the thread is about (a class, a course, a student…), so a
            // reply can be filed against the thing it concerns. Nullable
            // because a plain person-to-person thread is about nothing.
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            // Reply policy, copying EduPage's defaults because they were earned
            // against real schools: a broadcast to every parent with reply-all
            // on turns one message into hundreds.
            $table->string('reply_policy', 16)->default('all');

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
            $table->index('last_message_at');
        });

        Schema::create('message_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Who they are *in this thread* — the author can always reply even
            // when the policy silences everyone else.
            $table->string('role', 16)->default('participant');

            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_thread_id', 'user_id'], 'msg_participant_unique');
            $table->index(['user_id', 'last_read_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('thread_id')->nullable()->after('id')
                ->constrained('message_threads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('thread_id');
        });
        Schema::dropIfExists('message_participants');
        Schema::dropIfExists('message_threads');
    }
};
