<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds columns for the simplified public admission form.
     */
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applications', 'full_name')) {
                $table->string('full_name')->nullable()->after('course_id');
            }
            if (!Schema::hasColumn('admission_applications', 'message')) {
                $table->text('message')->nullable()->after('meta');
            }
            if (!Schema::hasColumn('admission_applications', 'locale')) {
                $table->string('locale', 10)->nullable()->after('source');
            }
            if (!Schema::hasColumn('admission_applications', 'ip')) {
                $table->string('ip', 45)->nullable()->after('locale');
            }
            if (!Schema::hasColumn('admission_applications', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $columns = ['full_name', 'message', 'locale', 'ip', 'user_agent'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('admission_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
