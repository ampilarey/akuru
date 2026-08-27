<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Arabic Module B (SPEC §51.9–§51.18, ROADMAP): the Pronunciation domain —
 * LOCAL/offline AI behind contracts and a feature flag (rule 8: everything
 * works with AI off; rule 4: no SDK/script calls outside the domain).
 * Attempts and predictions are stored (§51.12/§51.13); verified recordings
 * become human-approved training samples (§51.16/§51.17); model versions
 * are kept, never overwritten, and every activation/rollback is audited
 * (§51.18). Letter/haraka references are table-level FKs only — no
 * cross-domain model imports (F5P2 precedent). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_pronunciation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('course_offering_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->foreignId('expected_letter_id')->constrained('arabic_letters')->cascadeOnDelete();
            $table->foreignId('expected_haraka_id')->constrained('arabic_harakas')->cascadeOnDelete();
            $table->unsignedBigInteger('audio_media_file_id')->nullable();
            $table->string('mode', 10)->default('manual'); // live / manual
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 30)->default('submitted'); // submitted/ai_checked/teacher_reviewed/failed
            $table->unsignedBigInteger('ai_prediction_id')->nullable();
            $table->boolean('teacher_review_required')->default(true);
            $table->timestamps();

            // Explicit name: the auto-generated one exceeds MySQL's 64-char limit.
            $table->index(['status', 'teacher_review_required'], 'arabic_pron_attempts_status_review_index');
            $table->index('student_user_id');
        });

        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arabic_pronunciation_attempt_id')->nullable()
                ->constrained('arabic_pronunciation_attempts')->cascadeOnDelete();
            $table->unsignedBigInteger('predicted_letter_id')->nullable();
            $table->unsignedBigInteger('predicted_haraka_id')->nullable();
            $table->string('predicted_letter_label', 50)->nullable();
            $table->string('predicted_haraka_label', 50)->nullable();
            $table->decimal('letter_confidence', 5, 4)->nullable();
            $table->decimal('haraka_confidence', 5, 4)->nullable();
            $table->boolean('is_letter_match')->nullable();
            $table->boolean('is_haraka_match')->nullable();
            $table->string('final_status', 30); // correct/wrong_letter/wrong_haraka/low_confidence/needs_teacher_review/error
            $table->longText('raw_json')->nullable();
            $table->string('model_version', 100)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('training_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arabic_pronunciation_attempt_id')->nullable()
                ->constrained('arabic_pronunciation_attempts')->nullOnDelete();
            $table->unsignedBigInteger('audio_media_file_id');
            $table->foreignId('verified_letter_id')->constrained('arabic_letters')->cascadeOnDelete();
            $table->foreignId('verified_haraka_id')->constrained('arabic_harakas')->cascadeOnDelete();
            $table->unsignedBigInteger('original_predicted_letter_id')->nullable();
            $table->unsignedBigInteger('original_predicted_haraka_id')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending_review'); // pending_review/approved/rejected/used_for_training
            $table->string('rejection_reason', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // Explicit name: the auto-generated one exceeds MySQL's 64-char limit.
            $table->index(['status', 'verified_letter_id', 'verified_haraka_id'], 'training_samples_status_letter_haraka_index');
        });

        Schema::create('ai_model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 50)->default('arabic_pronunciation');
            $table->string('version_name', 100);
            $table->string('model_path', 500);
            $table->string('letter_labels_path', 500)->nullable();
            $table->string('haraka_labels_path', 500)->nullable();
            $table->unsignedInteger('training_sample_count')->default(0);
            $table->decimal('validation_letter_accuracy', 5, 4)->nullable();
            $table->decimal('validation_haraka_accuracy', 5, 4)->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('trained_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['model_type', 'version_name']);
        });

        // §51.16: activation and rollback must be audited — append-only log.
        Schema::create('ai_model_version_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_version_id')->constrained('ai_model_versions')->cascadeOnDelete();
            $table->string('action', 20); // registered/activated/rolled_back
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Permission::firstOrCreate(['name' => 'pronunciation.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('pronunciation.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_version_events');
        Schema::dropIfExists('ai_model_versions');
        Schema::dropIfExists('training_samples');
        Schema::dropIfExists('ai_predictions');
        Schema::dropIfExists('arabic_pronunciation_attempts');
    }
};
