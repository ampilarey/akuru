<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds phone and email for the public admission form (table may have parent_phone/parent_email).
     */
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_applications', 'phone')) {
                $table->string('phone', 20)->nullable()->after('full_name');
            }
            if (!Schema::hasColumn('admission_applications', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            if (Schema::hasColumn('admission_applications', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('admission_applications', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
