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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Quran Memorization", "Arabic Language", "Islamic Studies"
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->string('code')->unique(); // e.g., "QUR101", "ARB201"
            $table->text('description')->nullable();
            $table->text('description_arabic')->nullable();
            $table->text('description_dhivehi')->nullable();
            $table->string('type'); // e.g., "Quran", "Arabic", "Islamic Studies", "General"
            $table->integer('credits')->default(1);
            $table->boolean('is_quran_subject')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
