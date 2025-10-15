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
        Schema::create('parent_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('first_name_arabic')->nullable();
            $table->string('first_name_dhivehi')->nullable();
            $table->string('last_name');
            $table->string('last_name_arabic')->nullable();
            $table->string('last_name_dhivehi')->nullable();
            $table->string('phone');
            $table->string('email');
            $table->string('address');
            $table->string('occupation')->nullable();
            $table->string('occupation_arabic')->nullable();
            $table->string('occupation_dhivehi')->nullable();
            $table->string('national_id')->nullable();
            $table->enum('relationship', ['father', 'mother', 'guardian', 'grandfather', 'grandmother', 'uncle', 'aunt', 'other']);
            $table->string('photo')->nullable();
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_guardians');
    }
};
