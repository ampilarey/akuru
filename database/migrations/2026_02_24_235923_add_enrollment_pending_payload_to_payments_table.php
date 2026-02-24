<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Stores full enrollment data for deferred enrollment creation.
            // For paid courses, enrollment records are NOT created until payment
            // is confirmed by BML. This column holds everything needed to create
            // the RegistrationStudent + CourseEnrollment after payment success.
            $table->json('enrollment_pending_payload')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('enrollment_pending_payload');
        });
    }
};
