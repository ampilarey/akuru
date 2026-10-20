<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRINT_PRODUCTS_PLAN PP2 (STATUS §5tj): a layout and a template belong to
 * one print product — name tags, cards, stickers or photo prints. Both
 * default to name tags, so every row made before stays where it was. A job
 * already names its product (`print_jobs.product_id`).
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_layouts', function (Blueprint $table) {
            $table->string('print_kind', 20)->default('name_tags')->after('name');
        });
        Schema::table('print_templates', function (Blueprint $table) {
            $table->string('print_kind', 20)->default('name_tags')->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('print_layouts', fn (Blueprint $table) => $table->dropColumn('print_kind'));
        Schema::table('print_templates', fn (Blueprint $table) => $table->dropColumn('print_kind'));
    }
};
