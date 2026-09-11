<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E8 — student pick-up.
 *
 * **This releases a child**, which is why the plan calls it "a protocol, not a
 * button" and says in terms: *do not ship steps 1–5 without step 2*. Step 2 is
 * the guardian's PIN — a second factor — and it is built here, not deferred.
 *
 * Three tables for the documented flow:
 *
 * - `pickup_pins` — the second factor, hashed. Deliberately its own table
 *   rather than a column on `parent_guardians`: that is a legacy table in the
 *   middle of a dual-write unification, and a credential should not be buried
 *   in something being migrated.
 * - `pickup_windows` — step 1, staff open pick-up for a date. Without it a
 *   guardian could request at 2am and the protocol would mean nothing.
 * - `pickup_notices` — the state machine itself.
 *
 * `pickup_notices` carries `academic_year_id` (rule 10): a child being
 * collected is an event in time, and it belongs to a school year.
 *
 * Nothing here is deleted on completion. Who collected a child, when, and who
 * released them is exactly the record a school needs to still have in a year's
 * time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Hashed, never stored or logged in the clear.
            $table->string('pin_hash');
            $table->timestamps();
        });

        Schema::create('pickup_windows', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pickup_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();

            $table->date('date');
            $table->string('status', 20)->default('requested');

            $table->timestamp('requested_at');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('note')->nullable();
            $table->timestamps();

            // The console reads "today, not yet gone" constantly.
            $table->index(['date', 'status']);
            $table->index(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_notices');
        Schema::dropIfExists('pickup_windows');
        Schema::dropIfExists('pickup_pins');
    }
};
