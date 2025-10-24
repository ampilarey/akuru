<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists($table, $index)
    {
        $connection = \DB::connection();
        $driver = $connection->getDriverName();
        
        if ($driver === 'sqlite') {
            $indexes = \DB::select("PRAGMA index_list({$table})");
            foreach ($indexes as $idx) {
                if ($idx->name === $index) {
                    return true;
                }
            }
        } else {
            $indexes = \DB::select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === $index) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            // Add new fields only if they don't exist
            if (!Schema::hasColumn('admission_applications', 'application_number')) {
                $table->string('application_number')->unique()->after('course_id');
            }
            if (!Schema::hasColumn('admission_applications', 'date_of_birth')) {
                $table->date('date_of_birth')->after('student_name');
            }
            if (!Schema::hasColumn('admission_applications', 'gender')) {
                $table->enum('gender', ['male', 'female', 'other'])->default('male')->after('date_of_birth');
            }
            if (!Schema::hasColumn('admission_applications', 'address')) {
                $table->text('address')->after('parent_email');
            }
            if (!Schema::hasColumn('admission_applications', 'guardian_email')) {
                $table->string('guardian_email')->nullable()->after('parent_phone');
            }
            if (!Schema::hasColumn('admission_applications', 'guardian_relationship')) {
                $table->string('guardian_relationship')->after('guardian_email');
            }
            if (!Schema::hasColumn('admission_applications', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->after('guardian_relationship');
            }
            if (!Schema::hasColumn('admission_applications', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone')->after('emergency_contact_name');
            }
            if (!Schema::hasColumn('admission_applications', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship')->after('emergency_contact_phone');
            }
            if (!Schema::hasColumn('admission_applications', 'previous_education')) {
                $table->text('previous_education')->nullable()->after('emergency_contact_relationship');
            }
            if (!Schema::hasColumn('admission_applications', 'previous_islamic_education')) {
                $table->text('previous_islamic_education')->nullable()->after('previous_education');
            }
            if (!Schema::hasColumn('admission_applications', 'quran_knowledge_level')) {
                $table->enum('quran_knowledge_level', ['none', 'basic', 'intermediate', 'advanced'])->default('none')->after('previous_islamic_education');
            }
            if (!Schema::hasColumn('admission_applications', 'arabic_knowledge_level')) {
                $table->enum('arabic_knowledge_level', ['none', 'basic', 'intermediate', 'advanced'])->default('none')->after('quran_knowledge_level');
            }
            if (!Schema::hasColumn('admission_applications', 'learning_goals')) {
                $table->text('learning_goals')->nullable()->after('arabic_knowledge_level');
            }
            if (!Schema::hasColumn('admission_applications', 'special_needs')) {
                $table->text('special_needs')->nullable()->after('learning_goals');
            }
            if (!Schema::hasColumn('admission_applications', 'medical_conditions')) {
                $table->text('medical_conditions')->nullable()->after('special_needs');
            }
            if (!Schema::hasColumn('admission_applications', 'allergies')) {
                $table->text('allergies')->nullable()->after('medical_conditions');
            }
            if (!Schema::hasColumn('admission_applications', 'application_fee_paid')) {
                $table->boolean('application_fee_paid')->default(false)->after('priority');
            }
            if (!Schema::hasColumn('admission_applications', 'application_fee_amount')) {
                $table->decimal('application_fee_amount', 10, 2)->nullable()->after('application_fee_paid');
            }
            if (!Schema::hasColumn('admission_applications', 'application_fee_payment_method')) {
                $table->string('application_fee_payment_method')->nullable()->after('application_fee_amount');
            }
            if (!Schema::hasColumn('admission_applications', 'application_fee_payment_reference')) {
                $table->string('application_fee_payment_reference')->nullable()->after('application_fee_payment_method');
            }
            if (!Schema::hasColumn('admission_applications', 'application_fee_payment_date')) {
                $table->datetime('application_fee_payment_date')->nullable()->after('application_fee_payment_reference');
            }
            if (!Schema::hasColumn('admission_applications', 'documents_submitted')) {
                $table->json('documents_submitted')->nullable()->after('application_fee_payment_date');
            }
            if (!Schema::hasColumn('admission_applications', 'documents_verified')) {
                $table->boolean('documents_verified')->default(false)->after('documents_submitted');
            }
            if (!Schema::hasColumn('admission_applications', 'interview_scheduled')) {
                $table->boolean('interview_scheduled')->default(false)->after('documents_verified');
            }
            if (!Schema::hasColumn('admission_applications', 'interview_date')) {
                $table->datetime('interview_date')->nullable()->after('interview_scheduled');
            }
            if (!Schema::hasColumn('admission_applications', 'interview_notes')) {
                $table->text('interview_notes')->nullable()->after('interview_date');
            }
            if (!Schema::hasColumn('admission_applications', 'interview_score')) {
                $table->integer('interview_score')->nullable()->after('interview_notes');
            }
            if (!Schema::hasColumn('admission_applications', 'recommendation_notes')) {
                $table->text('recommendation_notes')->nullable()->after('interview_score');
            }
            if (!Schema::hasColumn('admission_applications', 'admission_decision')) {
                $table->enum('admission_decision', ['pending', 'accepted', 'rejected', 'waitlisted'])->default('pending')->after('recommendation_notes');
            }
            if (!Schema::hasColumn('admission_applications', 'admission_decision_date')) {
                $table->date('admission_decision_date')->nullable()->after('admission_decision');
            }
            if (!Schema::hasColumn('admission_applications', 'admission_decision_notes')) {
                $table->text('admission_decision_notes')->nullable()->after('admission_decision_date');
            }
            if (!Schema::hasColumn('admission_applications', 'enrollment_date')) {
                $table->date('enrollment_date')->nullable()->after('admission_decision_notes');
            }
            if (!Schema::hasColumn('admission_applications', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('admission_applications', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('admin_notes');
            }
            if (!Schema::hasColumn('admission_applications', 'meta')) {
                $table->json('meta')->nullable()->after('custom_fields');
            }
            
            // Add indexes only if they don't exist
            if (!$this->indexExists('admission_applications', 'admission_applications_status_priority_index')) {
                $table->index(['status', 'priority']);
            }
            if (!$this->indexExists('admission_applications', 'admission_applications_course_id_status_index')) {
                $table->index(['course_id', 'status']);
            }
            if (!$this->indexExists('admission_applications', 'admission_applications_assigned_to_status_index')) {
                $table->index(['assigned_to', 'status']);
            }
            if (!$this->indexExists('admission_applications', 'admission_applications_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('admission_applications', 'admission_applications_parent_email_index')) {
                $table->index('parent_email');
            }
            if (!$this->indexExists('admission_applications', 'admission_applications_application_number_index')) {
                $table->index('application_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['course_id', 'status']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex('created_at');
            $table->dropIndex('email');
            $table->dropIndex('application_number');
            
            $table->dropColumn([
                'application_number',
                'date_of_birth',
                'gender',
                'address',
                'guardian_email',
                'guardian_relationship',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'previous_education',
                'previous_islamic_education',
                'quran_knowledge_level',
                'arabic_knowledge_level',
                'learning_goals',
                'special_needs',
                'medical_conditions',
                'allergies',
                'status',
                'priority',
                'application_fee_paid',
                'application_fee_amount',
                'application_fee_payment_method',
                'application_fee_payment_reference',
                'application_fee_payment_date',
                'documents_submitted',
                'documents_verified',
                'interview_scheduled',
                'interview_date',
                'interview_notes',
                'interview_score',
                'recommendation_notes',
                'admission_decision',
                'admission_decision_date',
                'admission_decision_notes',
                'enrollment_date',
                'assigned_to',
                'admin_notes',
                'custom_fields',
                'meta',
            ]);
        });
    }
};