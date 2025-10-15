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
        Schema::create('absence_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Guardian or student
            $table->date('date');
            $table->foreignId('period_id')->nullable()->constrained()->onDelete('set null');
            $table->text('reason');
            $table->enum('type', ['illness', 'medical_appointment', 'family_emergency', 'religious', 'other'])->default('illness');
            $table->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('attachment_path')->nullable(); // Medical certificate, etc.
            $table->boolean('affects_attendance')->default(true); // Whether this should excuse the absence
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index(['date', 'status']);
            $table->index(['created_by', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absence_notes');
    }
};