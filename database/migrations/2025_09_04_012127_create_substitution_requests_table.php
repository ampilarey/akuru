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
        Schema::create('substitution_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_entry_id')->nullable()->constrained('timetables')->onDelete('cascade');
            $table->date('date');
            $table->foreignId('absent_teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('classroom_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('period_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['open', 'assigned', 'cancelled', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'date']);
            $table->index(['absent_teacher_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('substitution_requests');
    }
};