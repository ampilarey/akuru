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
        Schema::create('media_galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->text('description_arabic')->nullable();
            $table->text('description_dhivehi')->nullable();
            $table->enum('type', ['photo', 'video', 'mixed'])->default('photo');
            $table->enum('visibility', ['public', 'private', 'restricted'])->default('public');
            $table->json('tags')->nullable(); // For categorization
            $table->string('cover_image')->nullable(); // Main image for the gallery
            $table->date('event_date')->nullable(); // Date of the event if applicable
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
        Schema::dropIfExists('media_galleries');
    }
};