<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRINT_PRODUCTS_PLAN PP1 (STATUS §5ti): sheet sizes. A layout is laid out
 * on a sheet size, and a paper is stock of one size with its own prices.
 * Both default to A4, so every layout, paper and job reads as it did.
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_layouts', function (Blueprint $table) {
            $table->string('sheet_size', 4)->default('a4')->after('name');
        });
        Schema::table('print_paper_options', function (Blueprint $table) {
            $table->string('sheet_size', 4)->default('a4')->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('print_layouts', fn (Blueprint $table) => $table->dropColumn('sheet_size'));
        Schema::table('print_paper_options', fn (Blueprint $table) => $table->dropColumn('sheet_size'));
    }
};
