<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E18 — student arrivals and departures.
 *
 * The plan's warning is the design constraint: *"Do not build the software
 * until the hardware question is answered, or it will be a manual log nobody
 * fills."* The hardware question decides **what presses the button**, not what
 * the row looks like — a card reader and a member of staff both record the
 * same fact: this child was at the gate, going this way, at this time.
 *
 * So the schema is built now and `source` is carried from the first row.
 * `RecordStudentMovementAction` is the single writer (rule 11); a card or QR
 * adapter, if one is ever bought, calls that action with a different source
 * and needs no new table and no migration. What is deliberately **not** built
 * is an empty `GateReader` interface with no implementation — speculative
 * generality for a device nobody has purchased is its own kind of waste.
 *
 * Carries `academic_year_id` (rule 10). `recorded_by` is nullable precisely so
 * a future unattended reader has somewhere to be: a turnstile has no user id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->string('direction', 8);
            $table->timestamp('at');

            // Null when a device recorded it — a turnstile has no user id.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 16)->default('manual');

            $table->string('note')->nullable();

            // A mis-tap must be correctable, but the row does not vanish:
            // "somebody logged this and took it back" is itself the record a
            // parent may need. Soft, not deleted.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The two reads: one gate console for a day, and one child's day
            // for their family.
            $table->index(['academic_year_id', 'at']);
            $table->index(['student_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_movements');
    }
};
