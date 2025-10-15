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
        Schema::create('plan_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_plan_id')->constrained()->onDelete('cascade');
            $table->smallInteger('order')->default(1);
            $table->string('title');
            $table->text('objective')->nullable();
            $table->text('resources')->nullable();
            $table->integer('estimated_minutes')->default(45);
            $table->json('materials')->nullable(); // Books, videos, worksheets, etc.
            $table->text('assessment_notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            
            $table->index(['course_plan_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_topics');
    }
};