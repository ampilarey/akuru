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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->text('description_arabic')->nullable();
            $table->text('description_dhivehi')->nullable();
            $table->string('address');
            $table->string('phone');
            $table->string('email');
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('principal_name');
            $table->string('principal_name_arabic')->nullable();
            $table->string('principal_name_dhivehi')->nullable();
            $table->string('established_year');
            $table->json('settings')->nullable(); // For storing school-specific settings
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
