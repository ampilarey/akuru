<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B4c (LIBRARY_PLAN §18 "banner", STATUS §5iz): a picture for a campaign,
 * shown on the public offers page. Public media, like a cover: an offer's
 * banner is published on purpose. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_campaigns', function (Blueprint $table) {
            $table->foreignId('banner_media_file_id')->nullable()->after('description')->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promotion_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banner_media_file_id');
        });
    }
};
