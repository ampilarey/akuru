<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRINT_PRODUCTS_PLAN PP7a (PD10, STATUS §5uh): a shop the office approves
 * may print, on its own papers and finishings at its own prices. Akuru's stay
 * unowned (`vendor_id` null), so nothing that exists changes. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['print_paper_options', 'print_finishing_options'] as $table) {
            if (! Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->foreignId('vendor_id')->nullable()->after('id')->constrained('vendors')->cascadeOnDelete();
                });
            }
        }
        if (! Schema::hasColumn('vendors', 'print_enabled')) {
            Schema::table('vendors', function (Blueprint $t): void {
                $t->boolean('print_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['print_paper_options', 'print_finishing_options'] as $table) {
            if (Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropConstrainedForeignId('vendor_id');
                });
            }
        }
        if (Schema::hasColumn('vendors', 'print_enabled')) {
            Schema::table('vendors', fn (Blueprint $t) => $t->dropColumn('print_enabled'));
        }
    }
};
