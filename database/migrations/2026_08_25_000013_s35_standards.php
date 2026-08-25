<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3.5 — curriculum standards and polymorphic tagging (exams, plan_topics).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('code', 64);
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('standards')->nullOnDelete();
        });

        Schema::create('standard_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_id')->constrained('standards')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->unique(['standard_id', 'taggable_type', 'taggable_id'], 'std_tag_unique');
            $table->index(['taggable_type', 'taggable_id'], 'std_tag_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_taggables');
        Schema::dropIfExists('standards');
    }
};
