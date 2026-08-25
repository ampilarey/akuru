<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S5.6 — payroll periods, payslips, finance posting receipt.
 * payroll.enabled stays off until two parallel cycles match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('open');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month'], 'payroll_period_year_month_uq');
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->json('allowances')->nullable();
            $table->json('deductions')->nullable();
            $table->decimal('gross', 10, 2)->default(0);
            $table->decimal('employee_pension', 10, 2)->default(0);
            $table->decimal('employer_pension', 10, 2)->default(0);
            $table->decimal('tax_withheld', 10, 2)->default(0);
            $table->decimal('unpaid_leave_deduction', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->json('inputs')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['payroll_period_id', 'staff_profile_id'], 'payslip_period_staff_uq');
        });

        Schema::create('payroll_postings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_net', 12, 2);
            $table->unsignedInteger('staff_count');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['year', 'month'], 'payroll_post_year_month_uq');
        });

        Permission::firstOrCreate(['name' => 'payroll.run', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'payroll.approve', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo(['payroll.run', 'payroll.approve']);
            }
        }
        if (Role::query()->where('name', 'headmaster')->exists()) {
            Role::findByName('headmaster')->givePermissionTo(['payroll.run']);
        }

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'payroll.enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'hr',
            'label' => 'Enable payroll (off until two parallel cycles match)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('settings')->insertOrIgnore([
            'key' => 'payroll.rules',
            'value' => json_encode([
                'employee_pension_rate' => 0.07,
                'employer_pension_rate' => 0.07,
                'working_days' => 22,
                'rounding' => 'half_up_2',
                'tax_brackets' => [
                    ['up_to' => 60000, 'rate' => 0],
                    ['up_to' => 100000, 'rate' => 0.08],
                    ['up_to' => null, 'rate' => 0.15],
                ],
            ]),
            'type' => 'json',
            'group' => 'hr',
            'label' => 'Payroll rates and tax brackets (never hardcoded)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_postings');
        Schema::dropIfExists('payroll_periods');
    }
};
