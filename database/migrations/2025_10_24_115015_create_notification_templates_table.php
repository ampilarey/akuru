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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // 'welcome_email', 'course_enrollment', 'assignment_due', etc.
            $table->string('type'); // 'email', 'sms', 'push', 'in_app'
            $table->string('category'); // 'system', 'course', 'assignment', 'event', 'payment', etc.
            $table->string('subject')->nullable(); // For email notifications
            $table->text('body'); // Template body with placeholders
            $table->json('variables')->nullable(); // Available template variables
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System templates can't be deleted
            $table->string('language')->default('en');
            $table->timestamps();
            
            $table->index(['type', 'category', 'is_active']);
            $table->index(['language', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};