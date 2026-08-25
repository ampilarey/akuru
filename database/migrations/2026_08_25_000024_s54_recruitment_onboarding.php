<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S5.4 — job postings, applications, onboarding/offboarding items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type', 32)->nullable();
            $table->date('closes_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('public')->default(false);
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('name');
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('cv_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('cover_note')->nullable();
            $table->string('status', 20)->default('received');
            $table->json('stage_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['job_posting_id', 'status'], 'job_app_posting_status_idx');
        });

        Schema::create('staff_onboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->string('kind', 20)->default('onboarding');
            $table->string('item');
            $table->boolean('done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['staff_profile_id', 'kind'], 'staff_onb_staff_kind_idx');
        });

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'hr.onboarding_items',
            'value' => json_encode(['Contract signed', 'Documents collected', 'Account roles assigned', 'Induction completed']),
            'type' => 'json',
            'group' => 'hr',
            'label' => 'Onboarding checklist template',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('settings')->insertOrIgnore([
            'key' => 'hr.offboarding_items',
            'value' => json_encode(['Revoke roles', 'Exit form signed', 'Final-pay flagged']),
            'type' => 'json',
            'group' => 'hr',
            'label' => 'Offboarding checklist template',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_onboarding_items');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
    }
};
