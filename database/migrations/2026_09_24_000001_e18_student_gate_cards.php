<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E18: the printed QR card a pupil shows at the gate (owner decision 11,
 * 2026-09-24).
 *
 * The card carries a random token, never the student's id: an id is a small
 * number anyone could print on a card of their own. A lost card is revoked
 * and a new one issued, so the old token stops working the same minute.
 * Revoked rows stay — they are the record of which card was used when.
 *
 * No `academic_year_id` (rule 10): a card is a credential, like a login, not
 * something that happens in time. The movements it produces carry the year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_gate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_gate_cards');
    }
};
