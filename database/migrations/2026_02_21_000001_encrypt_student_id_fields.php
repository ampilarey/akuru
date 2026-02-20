<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            // Widen columns to text so encrypted values (which are long) fit
            $table->text('national_id')->nullable()->change();
            $table->text('passport')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('registration_students', function (Blueprint $table) {
            $table->string('national_id', 50)->nullable()->change();
            $table->string('passport', 50)->nullable()->change();
        });
    }
};
