<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('media_galleries')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type'); // image/jpeg, video/mp4, etc.
            $table->enum('media_type', ['image', 'video', 'audio', 'document'])->default('image');
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('mime_type');
            $table->string('title')->nullable();
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->text('description_arabic')->nullable();
            $table->text('description_dhivehi')->nullable();
            $table->json('metadata')->nullable(); // EXIF data, video duration, etc.
            $table->string('thumbnail_path')->nullable(); // For videos and large images
            $table->unsignedInteger('width')->nullable(); // For images/videos
            $table->unsignedInteger('height')->nullable(); // For images/videos
            $table->unsignedInteger('duration')->nullable(); // For videos/audio in seconds
            $table->unsignedInteger('order')->default(0); // For ordering within gallery
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};