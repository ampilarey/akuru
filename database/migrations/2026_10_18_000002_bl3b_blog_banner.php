<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL3b (STATUS §5sj): a blog page's banner. Like the picture
 * (`photo_media_file_id`), it is uploaded as private media into the waiting
 * design and copied to public media only when the office approves (§8).
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->foreignId('banner_media_file_id')->nullable()->after('photo_media_file_id')->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banner_media_file_id');
        });
    }
};
