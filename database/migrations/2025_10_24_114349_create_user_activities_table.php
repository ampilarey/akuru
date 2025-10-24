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
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity_type'); // 'login', 'page_view', 'course_enrolled', 'assignment_submitted', etc.
            $table->string('activity_name'); // 'User Login', 'Viewed Quran Progress', 'Enrolled in Arabic Course', etc.
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('performed_at');
            $table->timestamps();
            
            $table->index(['user_id', 'activity_type', 'performed_at']);
            $table->index(['activity_type', 'performed_at']);
            $table->index('performed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};