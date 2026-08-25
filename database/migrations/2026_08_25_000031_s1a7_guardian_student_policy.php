<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1A.7 — additive parent-child policy fields on guardian_student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_student', function (Blueprint $table) {
            $table->string('consent_status', 20)->default('unknown');
            $table->string('verification_status', 20)->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guardian_student', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'consent_status',
                'verification_status',
                'verified_at',
                'notes',
            ]);
        });
    }
};
