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
        // Check if admission_applications table exists and enhance it
        if (Schema::hasTable('admission_applications')) {
            Schema::table('admission_applications', function (Blueprint $table) {
                if (!Schema::hasColumn('admission_applications', 'timeline')) {
                    $table->json('timeline')->nullable()->after('status'); // History of status changes
                }
                if (!Schema::hasColumn('admission_applications', 'assigned_to')) {
                    $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->after('timeline');
                }
                if (!Schema::hasColumn('admission_applications', 'expected_start')) {
                    $table->date('expected_start')->nullable()->after('assigned_to');
                }
                if (!Schema::hasColumn('admission_applications', 'tags')) {
                    $table->json('tags')->nullable()->after('expected_start');
                }
                if (!Schema::hasColumn('admission_applications', 'priority')) {
                    $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('tags');
                }
                if (!Schema::hasColumn('admission_applications', 'source')) {
                    $table->string('source')->nullable()->after('priority'); // website, referral, walk-in, etc.
                }
            });
        } else {
            // Create the table if it doesn't exist
            Schema::create('admission_applications', function (Blueprint $table) {
                $table->id();
                $table->string('application_number')->unique();
                $table->string('student_name');
                $table->date('date_of_birth');
                $table->enum('gender', ['male', 'female']);
                $table->string('nationality')->default('Maldivian');
                $table->string('parent_name');
                $table->string('parent_phone');
                $table->string('parent_email');
                $table->text('address');
                $table->string('previous_school')->nullable();
                $table->string('grade_applying_for');
                $table->enum('status', ['new', 'reviewed', 'interview_scheduled', 'interviewed', 'accepted', 'rejected', 'waitlisted'])->default('new');
                $table->json('timeline')->nullable(); // History of status changes
                $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
                $table->date('expected_start')->nullable();
                $table->json('tags')->nullable();
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
                $table->string('source')->nullable(); // website, referral, walk-in, etc.
                $table->text('notes')->nullable();
                $table->json('documents')->nullable(); // Uploaded documents
                $table->timestamps();
                
                $table->index(['status', 'created_at']);
                $table->index(['assigned_to', 'status']);
                $table->index(['grade_applying_for', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('admission_applications')) {
            Schema::table('admission_applications', function (Blueprint $table) {
                $table->dropColumn(['timeline', 'assigned_to', 'expected_start', 'tags', 'priority', 'source']);
            });
        }
    }
};