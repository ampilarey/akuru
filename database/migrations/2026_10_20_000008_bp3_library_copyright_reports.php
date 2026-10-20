<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BACKLOG B11, BP3 (STATUS §5ts): a report that a Library work copies
 * someone's own. Anyone may send one from the work's page, signed in or not;
 * the office upholds it (the work is archived, off the shelf, and its
 * writer told) or dismisses it, each with a note.
 *
 * Something that happens in time, so it carries the year (rule 10).
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_copyright_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('work_claimed', 300);
            $table->text('statement');
            $table->string('status', 20)->default('open');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_copyright_reports');
    }
};
