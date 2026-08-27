<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'learning_outcomes')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->json('learning_outcomes')->nullable()->after('learning_objectives');
            });
        }

        if (! Schema::hasColumn('testimonials', 'course_id')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->foreignId('course_id')->nullable()->after('id')->constrained('courses')->nullOnDelete();
                $table->index(['course_id', 'is_public', 'order']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('testimonials', 'course_id')) {
            Schema::table('testimonials', function (Blueprint $table) {
                $table->dropConstrainedForeignId('course_id');
            });
        }

        if (Schema::hasColumn('courses', 'learning_outcomes')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('learning_outcomes');
            });
        }
    }
};
