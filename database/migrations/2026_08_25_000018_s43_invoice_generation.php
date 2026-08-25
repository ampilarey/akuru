<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S4.3 — invoice generation log (idempotent per student/structure/period).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('fee_structure_id');
            $table->string('period_key', 64);
            $table->unsignedBigInteger('invoice_id');
            $table->timestamps();

            $table->unique(['student_id', 'fee_structure_id', 'period_key'], 'inv_gen_stu_struct_period_uq');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });

        foreach ([
            [
                'key' => 'finance.invoice_monthly_mode',
                'value' => 'per_month',
                'label' => 'Monthly invoice mode (per_month or consolidated)',
            ],
            [
                'key' => 'finance.invoice_reminder_days',
                'value' => '3',
                'label' => 'Days after due date before a reminder SMS',
            ],
        ] as $row) {
            DB::table('settings')->insertOrIgnore([
                'key' => $row['key'],
                'value' => $row['value'],
                'type' => 'string',
                'group' => 'finance',
                'label' => $row['label'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_generation_logs');
    }
};
