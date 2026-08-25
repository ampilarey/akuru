<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1B.2 — offering content pins and enrollment seat linkage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->json('pinned_revision_json')->nullable()->after('pin_mode');
            $table->timestamp('pinned_at')->nullable()->after('pinned_revision_json');
            $table->foreignId('pinned_by')->nullable()->after('pinned_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('course_offering_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_offerings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_offering_id');
        });
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn(['pinned_revision_json', 'pinned_at']);
        });
    }
};
