<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S4.4 — payment plans + installment allocation. Adds invoices.payment_plan_id FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->unique();
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_plan_id');
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->unique(['payment_plan_id', 'sequence'], 'pay_plan_inst_seq_uq');
            $table->foreign('payment_plan_id')->references('id')->on('payment_plans')->cascadeOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('payment_plan_id')->references('id')->on('payment_plans')->nullOnDelete();
        });

        DB::table('settings')->insertOrIgnore([
            'key' => 'finance.plan_default_days',
            'value' => '14',
            'type' => 'string',
            'group' => 'finance',
            'label' => 'Days an installment may be overdue before the plan is defaulted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_plan_id']);
        });
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
    }
};
