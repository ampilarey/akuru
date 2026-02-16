<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('dob');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('passport', 50)->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_students');
    }
};
