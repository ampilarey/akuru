<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRINT_PRODUCTS_PLAN PP6b (STATUS §5to): a print job in a school's bulk
 * quote. `quote_items.print_job_id` is the approved job a line prices, so
 * an accepted quote puts that job back into the basket at the quoted price.
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('print_job_id')->nullable()->after('product_variant_id')->constrained('print_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('print_job_id');
        });
    }
};
