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
        Schema::create('tajweed_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recitation_practice_id')->constrained()->onDelete('cascade');
            $table->string('rule_name'); // e.g., "Madd", "Qalqalah", "Ikhfa", etc.
            $table->string('rule_name_arabic')->nullable();
            $table->text('comment');
            $table->text('comment_arabic')->nullable();
            $table->text('comment_dhivehi')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->unsignedSmallInteger('ayah_number')->nullable(); // Specific ayah if applicable
            $table->string('word_position')->nullable(); // Position in the ayah
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tajweed_feedback');
    }
};