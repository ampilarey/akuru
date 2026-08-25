<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S5.2 — leave types, entitlements, and an append-only ledger.
 * Requests stay on S2.10 `requests` with type staff_leave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->decimal('days_per_year', 5, 1)->default(0);
            $table->decimal('carry_over_max', 5, 1)->default(0);
            $table->boolean('requires_document')->default(false);
            $table->boolean('paid')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->decimal('entitled_days', 5, 1)->default(0);
            $table->decimal('carried_over_days', 5, 1)->default(0);
            $table->decimal('adjusted_days', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['staff_profile_id', 'leave_type_id', 'academic_year_id'], 'leave_ent_staff_type_year_uq');
        });

        Schema::create('leave_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitlement_id')->constrained('leave_entitlements')->restrictOnDelete();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->decimal('days', 5, 1);
            $table->string('reason');
            $table->timestamps();

            $table->index(['entitlement_id', 'created_at'], 'leave_led_ent_created_idx');
            $table->foreign('request_id')->references('id')->on('requests')->nullOnDelete();
        });

        $now = now();
        $types = [
            ['code' => 'annual', 'name' => 'Annual', 'days_per_year' => 20, 'carry_over_max' => 5, 'requires_document' => 0, 'paid' => 1],
            ['code' => 'sick', 'name' => 'Sick', 'days_per_year' => 10, 'carry_over_max' => 0, 'requires_document' => 1, 'paid' => 1],
            ['code' => 'family', 'name' => 'Family', 'days_per_year' => 5, 'carry_over_max' => 0, 'requires_document' => 0, 'paid' => 1],
            ['code' => 'hajj_umrah', 'name' => 'Hajj / Umrah', 'days_per_year' => 15, 'carry_over_max' => 0, 'requires_document' => 0, 'paid' => 1],
            ['code' => 'maternity', 'name' => 'Maternity', 'days_per_year' => 60, 'carry_over_max' => 0, 'requires_document' => 0, 'paid' => 1],
            ['code' => 'paternity', 'name' => 'Paternity', 'days_per_year' => 7, 'carry_over_max' => 0, 'requires_document' => 0, 'paid' => 1],
            ['code' => 'unpaid', 'name' => 'Unpaid', 'days_per_year' => 0, 'carry_over_max' => 0, 'requires_document' => 0, 'paid' => 0],
            ['code' => 'other', 'name' => 'Other', 'days_per_year' => 0, 'carry_over_max' => 0, 'requires_document' => 0, 'paid' => 1],
        ];

        foreach ($types as $type) {
            DB::table('leave_types')->insert(array_merge($type, [
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_ledger');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_types');
    }
};
