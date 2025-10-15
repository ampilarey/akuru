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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('content');
            $table->text('content_arabic')->nullable();
            $table->text('content_dhivehi')->nullable();
            $table->enum('type', ['general', 'academic', 'quran', 'event', 'holiday', 'emergency']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->json('target_audience')->nullable(); // ['students', 'parents', 'teachers', 'all']
            $table->json('target_classes')->nullable(); // Specific classes if applicable
            $table->date('publish_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('attachment')->nullable(); // File attachment
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
