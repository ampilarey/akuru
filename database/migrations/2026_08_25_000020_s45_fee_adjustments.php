<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S4.5 — school-fee adjustments (not Commerce discount codes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->string('type', 32);
            $table->string('basis', 16);
            $table->decimal('value', 10, 2);
            $table->string('applies_to', 32)->default('all_items');
            $table->json('item_types')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id', 'status'], 'fee_adj_stu_year_status_idx');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_adjustments');
    }
};
