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
        Schema::create('inquiry_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('email_to')->nullable(); // Specific email for this inquiry type
            $table->text('auto_response_template')->nullable();
            $table->boolean('requires_phone')->default(false);
            $table->boolean('requires_subject')->default(true);
            $table->json('custom_fields')->nullable(); // Additional form fields
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('response_time_hours')->default(24); // Expected response time
            $table->json('meta')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiry_types');
    }
};