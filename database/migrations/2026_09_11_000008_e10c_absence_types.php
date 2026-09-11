<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E10c — custom absence types.
 *
 * The five reasons a family could give were a **MySQL enum**:
 * `illness, medical_appointment, family_emergency, religious, other` — hardcoded
 * in the column, again in `PortalAbsenceNoteController`'s list, and a third time
 * in its validation rule. A school that wanted "sports fixture" or "bereavement"
 * needed a migration and a deploy.
 *
 * More than naming, though, each type now carries **what it does**:
 *
 *  - `excuses_absence` — does approving a note of this type turn the day's
 *    absences into *excused*? Until now that was `absence_notes.affects_attendance`,
 *    a per-note boolean defaulting to true, which meant the policy was decided
 *    one note at a time by whoever filled the form. It belongs to the type.
 *  - `requires_evidence` — must the family attach something? A school that
 *    wants a doctor's note for illness can now say so, which is the honest
 *    half of the plan's *"can't they be falsified?"* question: you cannot stop
 *    a parent writing what they like, but you can require a document and
 *    record who approved it.
 *
 * Additive (rule 9). `absence_notes.type` is **kept and still written**: this
 * is the deploy that stops reading it, not the one that drops it. The new
 * `absence_type_id` is backfilled from it, so no existing note changes meaning.
 *
 * That column is **widened from `enum(...)` to `varchar`**, which the tests
 * forced and which is right: while it is still being written, it has to be
 * able to hold the code of a reason the school invented. Left as an enum it
 * silently truncated `unauthorised_holiday` to nothing, so the legacy column
 * would have described the note as a reason nobody chose. Widening loses no
 * data and drops nothing — every existing value is still valid.
 *
 * The five existing values are seeded as rows with `excuses_absence = true`,
 * which is exactly what `affects_attendance`'s default did — the school wakes
 * up to the behaviour it already had.
 */
return new class extends Migration
{
    /** @var list<array{code: string, name: string, evidence: bool}> */
    private array $seeded = [
        ['code' => 'illness', 'name' => 'Illness', 'evidence' => false],
        ['code' => 'medical_appointment', 'name' => 'Medical appointment', 'evidence' => false],
        ['code' => 'family_emergency', 'name' => 'Family emergency', 'evidence' => false],
        ['code' => 'religious', 'name' => 'Religious observance', 'evidence' => false],
        ['code' => 'other', 'name' => 'Other', 'evidence' => false],
    ];

    public function up(): void
    {
        Schema::create('absence_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();

            $table->string('name');
            $table->string('name_dhivehi')->nullable();
            $table->string('name_arabic')->nullable();

            // What approving a note of this type does to the register.
            $table->boolean('excuses_absence')->default(true);

            // Whether the family must attach something.
            $table->boolean('requires_evidence')->default(false);

            // Deactivated rather than deleted: a type that has been used is
            // part of old notes' meaning, and deleting it would leave them
            // describing a reason nobody can name.
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        foreach ($this->seeded as $i => $row) {
            DB::table('absence_types')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'excuses_absence' => true,
                'requires_evidence' => $row['evidence'],
                'is_active' => true,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('absence_notes', function (Blueprint $table) {
            // See the class comment: it must be able to hold a school's own
            // code for as long as it is still written.
            $table->string('type', 40)->default('other')->change();

            $table->foreignId('absence_type_id')->nullable()->after('type')
                ->constrained('absence_types')->nullOnDelete();
        });

        // Backfill, so no existing note loses its reason.
        foreach (DB::table('absence_types')->pluck('id', 'code') as $code => $id) {
            DB::table('absence_notes')->where('type', $code)->update(['absence_type_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('absence_notes', function (Blueprint $table) {
            $table->dropForeign(['absence_type_id']);
            $table->dropColumn('absence_type_id');
        });

        Schema::dropIfExists('absence_types');
    }
};
