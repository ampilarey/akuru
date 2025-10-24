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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'user_analytics', 'course_performance', 'financial', 'system_health', etc.
            $table->string('category'); // 'analytics', 'performance', 'financial', 'academic', etc.
            $table->text('description')->nullable();
            $table->json('parameters')->nullable(); // Report parameters and filters
            $table->json('data')->nullable(); // Cached report data
            $table->string('status')->default('pending'); // 'pending', 'generating', 'completed', 'failed'
            $table->string('format')->default('json'); // 'json', 'csv', 'pdf', 'excel'
            $table->string('file_path')->nullable(); // Path to generated file
            $table->integer('file_size')->nullable(); // File size in bytes
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For auto-cleanup
            $table->timestamps();
            
            $table->index(['type', 'status', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};