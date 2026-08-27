<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L-track slice L2 (LIBRARY_PLAN §39.2): protected reader backbone —
 * page-at-a-time content, reading progress (§35.3), bookmarks. The reader
 * serves ONE page per request with a permission check and a per-user
 * watermark; the PDF original stays private (§36). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_item_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->longText('content');
            $table->timestamps();

            $table->unique(['library_item_id', 'page_number']);
        });

        Schema::create('library_reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('current_page')->default(1);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_reading_seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'library_item_id']);
        });

        Schema::create('library_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'library_item_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_bookmarks');
        Schema::dropIfExists('library_reading_progress');
        Schema::dropIfExists('library_item_pages');
    }
};
