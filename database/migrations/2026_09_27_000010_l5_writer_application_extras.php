<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B9 (LIBRARY_PLAN §11.1, STATUS §5iv): what an applicant may add to a
 * writer application — a portrait (public media, carried onto the author
 * page at approval), previous publications, and an identity document
 * (private media, opened only by the office from the queue). Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_applications', function (Blueprint $table) {
            $table->text('previous_publications')->nullable()->after('motivation');
            $table->foreignId('photo_media_file_id')->nullable()->after('previous_publications')->constrained('media_files')->nullOnDelete();
            $table->foreignId('id_document_media_file_id')->nullable()->after('photo_media_file_id')->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('writer_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_document_media_file_id');
            $table->dropConstrainedForeignId('photo_media_file_id');
            $table->dropColumn('previous_publications');
        });
    }
};
