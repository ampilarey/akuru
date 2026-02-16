<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the Laravel-standard notifications table for Notification::send() database channel.
     * The existing 'notifications' table (custom schema) is renamed to 'app_notifications'.
     */
    public function up(): void
    {
        // Rename existing custom notifications table
        Schema::rename('notifications', 'app_notifications');

        // Create Laravel's standard notifications table for Notifiable trait
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::rename('app_notifications', 'notifications');
    }
};
