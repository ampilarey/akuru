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
        Schema::table('admission_applications', function (Blueprint $table) {
            // Add course_id column if it doesn't exist
            if (!Schema::hasColumn('admission_applications', 'course_id')) {
                $table->foreignId('course_id')->nullable()->after('id')->constrained('courses')->onDelete('set null');
                $table->index('course_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applications', 'course_id')) {
                $table->dropForeign(['course_id']);
                $table->dropIndex(['course_id']);
                $table->dropColumn('course_id');
            }
        });
    }
};