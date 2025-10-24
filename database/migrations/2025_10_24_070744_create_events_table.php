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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('location');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('type', ['conference', 'workshop', 'seminar', 'competition', 'celebration', 'meeting', 'other'])->default('other');
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->enum('registration_type', ['none', 'required', 'optional'])->default('none');
            $table->integer('max_attendees')->nullable();
            $table->integer('current_attendees')->default(0);
            $table->decimal('registration_fee', 10, 2)->nullable();
            $table->datetime('registration_deadline')->nullable();
            $table->datetime('registration_start')->nullable();
            $table->text('registration_instructions')->nullable();
            $table->json('requirements')->nullable(); // What attendees need to bring/prepare
            $table->json('speakers')->nullable(); // Speaker information
            $table->json('schedule')->nullable(); // Event schedule/agenda
            $table->json('contact_info')->nullable(); // Contact details for the event
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('send_reminders')->default(true);
            $table->integer('reminder_days')->default(1); // Days before event to send reminder
            $table->text('cancellation_policy')->nullable();
            $table->text('refund_policy')->nullable();
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['status', 'start_date']);
            $table->index(['type', 'status']);
            $table->index(['is_featured', 'is_public']);
            $table->index('start_date');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};