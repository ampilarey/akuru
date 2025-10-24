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
        Schema::table('courses', function (Blueprint $table) {
            // Course duration and scheduling
            $table->integer('duration_weeks')->nullable()->after('seats');
            $table->date('start_date')->nullable()->after('duration_weeks');
            $table->date('end_date')->nullable()->after('start_date');
            $table->date('enrollment_deadline')->nullable()->after('end_date');
            
            // Course content and structure
            $table->json('prerequisites')->nullable()->after('enrollment_deadline');
            $table->json('learning_objectives')->nullable()->after('prerequisites');
            $table->text('instructor_notes')->nullable()->after('learning_objectives');
            
            // Course display and ordering
            $table->boolean('is_featured')->default(false)->after('instructor_notes');
            $table->integer('sort_order')->default(0)->after('is_featured');
            
            // Add indexes for better performance
            $table->index(['is_featured', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'status']);
            $table->dropIndex(['start_date', 'end_date']);
            $table->dropIndex('sort_order');
            
            $table->dropColumn([
                'duration_weeks',
                'start_date',
                'end_date',
                'enrollment_deadline',
                'prerequisites',
                'learning_objectives',
                'instructor_notes',
                'is_featured',
                'sort_order',
            ]);
        });
    }
};