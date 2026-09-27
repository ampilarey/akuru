<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A registrant who left gender empty was recorded as male (KNOWN_ISSUES,
 * BOOKSHOP_PLAN §15 finding 7): `students.gender` and `teachers.gender` were
 * required enums, the public registration forms let the field be left
 * empty, and the writers filled the gap with a value nobody gave. Rule 9,
 * additive: both columns become nullable so an empty answer is stored as
 * one. The forms stay optional; requiring the field is the owner's call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->enum('gender', ['male', 'female'])->nullable()->change();
        });
        Schema::table('teachers', function (Blueprint $table): void {
            $table->enum('gender', ['male', 'female'])->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rolling back reintroduces the very default this migration retires;
        // there is no other value a NOT NULL column can take.
        DB::table('students')->whereNull('gender')->update(['gender' => 'male']);
        DB::table('teachers')->whereNull('gender')->update(['gender' => 'male']);
        Schema::table('students', function (Blueprint $table): void {
            $table->enum('gender', ['male', 'female'])->nullable(false)->change();
        });
        Schema::table('teachers', function (Blueprint $table): void {
            $table->enum('gender', ['male', 'female'])->nullable(false)->change();
        });
    }
};
