<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3.6's last line, a month late: *"Regeneration allowed until published;
 * after, new version with audit."* The code refused regeneration after
 * publish and recorded nothing (S3 audit D3, STATUS §5ez, ADR-038).
 *
 * One row per regeneration of a published card: which document it replaced,
 * which one it produced, who asked and why. The card row itself stays one
 * per student and term (`rc_student_term_uq`); the version history hangs off
 * it. Carries the backbone (rule 10) because a correction happens in time.
 * Append-only: a revision is never edited or deleted from the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained('report_cards')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignId('superseded_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->timestamps();

            $table->index(['report_card_id', 'created_at'], 'rc_revisions_card_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_revisions');
    }
};
