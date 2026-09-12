<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §28.4 "Offering Pinning":
 *
 *   > Admins may explicitly re-pin an offering to a newer course content
 *   > version, but this must be a deliberate action.
 *   >
 *   > Offering re-pinning must never happen automatically.
 *   >
 *   > If an offering is re-pinned, the system should record:
 *   > Old pinned version · New pinned version · Admin who changed it ·
 *   > Timestamp · Reason/comment nullable
 *
 * `PinOfferingContentAction` overwrote `pinned_revision_json` in place. The
 * offering kept `pinned_by` and `pinned_at` for the *latest* pin only, so the
 * old version was gone the moment it was replaced — and with it the answer to
 * "what were enrolled students seeing last week, and who changed it, and why".
 *
 * Re-pinning changes content under students mid-offering, which is exactly why
 * §28.4 calls for it to be deliberate. An audit trail is how that is checked
 * after the fact, so this table is append-only: rows are written, never
 * updated or deleted (rule 12's ledger discipline, applied to a record that
 * exists to be trusted).
 *
 * Rule 10: a re-pin happens in time, so it carries `academic_year_id`.
 * Additive migration, no existing column touched (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_repin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();

            // Both sides of the transition, kept whole. Storing the pin maps
            // rather than a version number means the record still answers
            // "what did this student see" even if a lesson is later deleted.
            $table->json('old_pinned_revision_json')->nullable();
            $table->json('new_pinned_revision_json')->nullable();
            $table->string('old_pin_mode', 20)->nullable();
            $table->string('new_pin_mode', 20);

            $table->foreignId('changed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // §28.4 marks the reason nullable, so the pin is never blocked for
            // want of a comment — but the field exists to be asked for.
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['course_offering_id', 'id'], 'offering_repin_offering_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_repin_events');
    }
};
