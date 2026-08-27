<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * L-track slice L7 (LIBRARY_PLAN §12.2): research workflow — a submitted
 * research item gets a PEER REVIEWER assigned by the editor; the reviewer
 * recommends (accept / revise / reject) with comments that land in the
 * same append-only editorial trail the writer already sees; the editor
 * still owns the final decision (§43.3 — admin publishes). Approving
 * research without a reviewer recommendation is blocked while
 * library.research_review_required is on. DOI / journals / subscriptions
 * stay post-L7 backlog (§38). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('assigned'); // assigned/done
            $table->string('recommendation', 20)->nullable(); // accept/revise/reject
            $table->timestamps();

            // Explicit name: the auto-generated one is 66 chars, past MySQL's
            // 64-char identifier limit, and fails the whole migrate:fresh.
            $table->unique(['library_item_id', 'reviewer_user_id'], 'lib_review_assignments_item_reviewer_unique');
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->text('citations')->nullable()->after('body');
        });

        Role::firstOrCreate(['name' => 'reviewer', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('citations');
        });
        Schema::dropIfExists('library_review_assignments');
    }
};
