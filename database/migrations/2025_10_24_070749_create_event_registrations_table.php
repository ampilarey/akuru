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
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'attended', 'no_show'])->default('pending');
            $table->enum('registration_source', ['website', 'phone', 'email', 'walk_in', 'admin'])->default('website');
            $table->json('additional_info')->nullable(); // Custom fields based on event requirements
            $table->boolean('dietary_requirements')->default(false);
            $table->text('dietary_notes')->nullable();
            $table->boolean('transportation_needed')->default(false);
            $table->text('transportation_notes')->nullable();
            $table->boolean('accommodation_needed')->default(false);
            $table->text('accommodation_notes')->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->datetime('payment_date')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->datetime('confirmed_at')->nullable();
            $table->datetime('checked_in_at')->nullable();
            $table->string('qr_code')->nullable(); // For check-in purposes
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['event_id', 'status']);
            $table->index(['email', 'event_id']);
            $table->index('status');
            $table->index('registration_source');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};