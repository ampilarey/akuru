<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S4.2 — fee structures and structure items (one active per class per year).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_year_id');
            $table->string('name');
            $table->string('applies_to', 32);
            $table->json('class_ids')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['academic_year_id', 'status'], 'fee_struct_year_status_idx');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
        });

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_structure_id');
            $table->unsignedBigInteger('fee_item_id');
            $table->decimal('amount', 10, 2);
            $table->string('frequency', 32);
            $table->smallInteger('due_day')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->index(['fee_structure_id'], 'fee_struct_item_struct_idx');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->cascadeOnDelete();
            $table->foreign('fee_item_id')->references('id')->on('fee_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
    }
};
