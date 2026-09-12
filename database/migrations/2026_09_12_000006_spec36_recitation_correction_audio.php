<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §36 lists "Upload correction audio" among what a teacher/reviewer must
 * be able to do. There was nowhere to put one: a recitation submission held the
 * student's audio, a text `review_note`, and per-mistake timestamps into the
 * student's own recording — but nothing the teacher recorded themselves.
 *
 * For a Qur'an institute that is the weakest possible form of the feature.
 * Tajweed is a sound. "Your madd is short on ayah 4" is a description of a
 * sound; three seconds of the teacher reciting it is the correction.
 *
 * Additive and nullable, per rule 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_recitation_submissions', function (Blueprint $table) {
            $table->foreignId('correction_audio_media_file_id')
                ->nullable()
                ->after('audio_media_file_id');

            // Named explicitly: the generated name
            // `quran_recitation_submissions_correction_audio_media_file_id_foreign`
            // is 67 characters, and MariaDB's identifier limit is 64.
            // `MigrationIdentifierLengthTest` guards this.
            $table->foreign('correction_audio_media_file_id', 'qrs_correction_audio_fk')
                ->references('id')->on('media_files')
                // The correction losing its file leaves a dangling id otherwise;
                // null it, because a review that loses its audio is still a review.
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quran_recitation_submissions', function (Blueprint $table) {
            $table->dropForeign('qrs_correction_audio_fk');
            $table->dropColumn('correction_audio_media_file_id');
        });
    }
};
