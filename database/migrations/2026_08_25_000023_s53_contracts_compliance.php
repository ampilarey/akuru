<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S5.3 — staff contracts and once-per-horizon expiry notices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->string('contract_type', 32);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_until')->nullable();
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->json('allowances')->nullable();
            $table->unsignedSmallInteger('working_hours_per_week')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['staff_profile_id', 'status'], 'staff_con_staff_status_idx');
        });

        Schema::create('document_expiry_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('horizon_days');
            $table->timestamp('notified_at');
            $table->timestamps();

            $table->unique(['document_id', 'horizon_days'], 'doc_exp_notice_doc_horizon_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_expiry_notices');
        Schema::dropIfExists('staff_contracts');
    }
};
