<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMERCE_PARITY_PLAN P2/P3: the front and back of an identity card, and the
 * office's verdict on it — one table for shops, writers and learners (rule
 * 11). A row per submission, so a rejected card and the one sent after it
 * both stay on record; the latest row for (user, purpose, student) is the
 * current state. The images are private media, kept for the life of the
 * account (owner decision D1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 20); // vendor | writer | learner
            // P3: a child's own card, uploaded by the parent — the learner it is for.
            $table->unsignedBigInteger('student_id')->nullable();
            $table->foreignId('front_media_file_id')->constrained('media_files')->restrictOnDelete();
            $table->foreignId('back_media_file_id')->constrained('media_files')->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // pending | verified | rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'student_id']);
            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
