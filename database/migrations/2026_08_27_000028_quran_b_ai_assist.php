<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qur'an Module B (SPEC §52.3, ROADMAP): the SAME Pronunciation service
 * gains its second consumer — isolated letter+haraka checking on Qur'an
 * recitation submissions. One model family, two consumers; predictions
 * for recitation audio link to the submission. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_predictions', function (Blueprint $table) {
            $table->foreignId('quran_recitation_submission_id')->nullable()
                ->after('arabic_pronunciation_attempt_id')
                ->constrained('quran_recitation_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_predictions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quran_recitation_submission_id');
        });
    }
};
