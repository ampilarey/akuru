<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maldivian names have a first name, one or more middle names and a last
 * name (C17 slice R4, STATUS §5og). The owner, 2026-10-03: "Now name has only
 * 2 columns ... we use in Maldives 1st name, 2 names and last name". A middle
 * name is optional, so the column is nullable. Additive (rule 9); every
 * existing row keeps its two parts as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('middle_name', 150)->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('middle_name');
        });
    }
};
