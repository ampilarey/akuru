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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('teacher_id')->unique(); // School-specific teacher ID
            $table->string('first_name');
            $table->string('first_name_arabic')->nullable();
            $table->string('first_name_dhivehi')->nullable();
            $table->string('last_name');
            $table->string('last_name_arabic')->nullable();
            $table->string('last_name_dhivehi')->nullable();
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female']);
            $table->string('national_id')->nullable();
            $table->string('phone');
            $table->string('address');
            $table->string('email');
            $table->string('qualification');
            $table->string('qualification_arabic')->nullable();
            $table->string('qualification_dhivehi')->nullable();
            $table->text('specialization'); // e.g., "Quran Memorization", "Arabic Language"
            $table->text('specialization_arabic')->nullable();
            $table->text('specialization_dhivehi')->nullable();
            $table->date('joining_date');
            $table->decimal('salary', 10, 2)->nullable();
            $table->string('photo')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
