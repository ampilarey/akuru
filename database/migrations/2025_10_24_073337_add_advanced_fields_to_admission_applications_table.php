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
            // Add new fields
            $table->string('application_number')->unique()->after('course_id');
            $table->date('date_of_birth')->after('full_name');
            $table->enum('gender', ['male', 'female', 'other'])->default('male')->after('date_of_birth');
            $table->text('address')->after('email');
            $table->string('guardian_email')->nullable()->after('guardian_phone');
            $table->string('guardian_relationship')->after('guardian_email');
            $table->string('emergency_contact_name')->after('guardian_relationship');
            $table->string('emergency_contact_phone')->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->after('emergency_contact_phone');
            $table->text('previous_education')->nullable()->after('emergency_contact_relationship');
            $table->text('previous_islamic_education')->nullable()->after('previous_education');
            $table->enum('quran_knowledge_level', ['none', 'basic', 'intermediate', 'advanced'])->default('none')->after('previous_islamic_education');
            $table->enum('arabic_knowledge_level', ['none', 'basic', 'intermediate', 'advanced'])->default('none')->after('quran_knowledge_level');
            $table->text('learning_goals')->nullable()->after('arabic_knowledge_level');
            $table->text('special_needs')->nullable()->after('learning_goals');
            $table->text('medical_conditions')->nullable()->after('special_needs');
            $table->text('allergies')->nullable()->after('medical_conditions');
            $table->enum('status', ['new', 'under_review', 'interview_scheduled', 'accepted', 'rejected', 'enrolled', 'withdrawn'])->default('new')->after('user_agent');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('status');
            $table->boolean('application_fee_paid')->default(false)->after('priority');
            $table->decimal('application_fee_amount', 10, 2)->nullable()->after('application_fee_paid');
            $table->string('application_fee_payment_method')->nullable()->after('application_fee_amount');
            $table->string('application_fee_payment_reference')->nullable()->after('application_fee_payment_method');
            $table->datetime('application_fee_payment_date')->nullable()->after('application_fee_payment_reference');
            $table->json('documents_submitted')->nullable()->after('application_fee_payment_date');
            $table->boolean('documents_verified')->default(false)->after('documents_submitted');
            $table->boolean('interview_scheduled')->default(false)->after('documents_verified');
            $table->datetime('interview_date')->nullable()->after('interview_scheduled');
            $table->text('interview_notes')->nullable()->after('interview_date');
            $table->integer('interview_score')->nullable()->after('interview_notes');
            $table->text('recommendation_notes')->nullable()->after('interview_score');
            $table->enum('admission_decision', ['pending', 'accepted', 'rejected', 'waitlisted'])->default('pending')->after('recommendation_notes');
            $table->date('admission_decision_date')->nullable()->after('admission_decision');
            $table->text('admission_decision_notes')->nullable()->after('admission_decision_date');
            $table->date('enrollment_date')->nullable()->after('admission_decision_notes');
            $table->foreignId('assigned_to')->nullable()->after('enrollment_date')->constrained('users')->onDelete('set null');
            $table->text('admin_notes')->nullable()->after('assigned_to');
            $table->json('custom_fields')->nullable()->after('admin_notes');
            $table->json('meta')->nullable()->after('custom_fields');
            
            // Add indexes
            $table->index(['status', 'priority']);
            $table->index(['course_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('created_at');
            $table->index('email');
            $table->index('application_number');
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