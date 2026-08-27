<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * L-track slice L5 (LIBRARY_PLAN §7.4, §11, §35.2 — ROADMAP §9 overrides):
 * writer portal. A writer is a ROLE on the unified People/Identity record,
 * never a separate identity. Writers apply; admin approves (business rule
 * §43.2); writers upload drafts and submit; admin reviews with a comment
 * trail (§43.3 — admin must approve content; writers can never publish
 * directly). Review rows are an APPEND-ONLY editorial log. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('expertise')->nullable();
            $table->text('motivation')->nullable();
            $table->timestamp('agreement_accepted_at'); // §31 writer agreement
            $table->string('status', 20)->default('pending'); // pending/approved/rejected
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('writer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('expertise')->nullable();
            $table->string('status', 20)->default('active'); // active/suspended
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('default_commission', 5, 2)->nullable(); // §22: null = platform default
            $table->timestamps();
        });

        // Append-only editorial log: submitted / changes_requested /
        // approved / rejected, each with the editor's comment.
        Schema::create('library_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 30);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['library_item_id', 'created_at']);
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('published_at');
        });

        Role::firstOrCreate(['name' => 'writer', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
        Schema::dropIfExists('library_item_reviews');
        Schema::dropIfExists('writer_profiles');
        Schema::dropIfExists('writer_applications');
    }
};
