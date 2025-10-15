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
        Schema::create('substitution_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('substitution_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('substitute_teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('assigned_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['substitution_request_id']); // One assignment per request
            $table->index(['substitute_teacher_id', 'assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('substitution_assignments');
    }
};