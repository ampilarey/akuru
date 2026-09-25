<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBRARY_PLAN §36 "cover image upload". `library_items.cover_image` was a
 * free-text URL only the office could type; nothing uploaded a file. The
 * cover is PUBLIC media, like a writer's portrait — it is published on the
 * shelf — unlike the PDF original the reader protects. Additive: the URL
 * column stays as a fallback for the covers already typed in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->foreignId('cover_media_file_id')->nullable()->after('cover_image')->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_media_file_id');
        });
    }
};
