<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LENDING_AND_USED_BOOKS_PLAN U1 (STATUS §5mr): old and used books.
 *
 *  - `products.condition`: new (the default, so every product so far is
 *    new), like_new, good, fair, worn — and a note on what is marked or
 *    missing. Indexed: the Used shelf and filter read it.
 *  - `vendors.kind`: `shop` (every vendor so far) or `personal` — a person
 *    selling their own books, with everything else a shop has.
 *  - `vendor_applications.kind`: what the applicant asked to be.
 *
 * All additive with defaults (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('condition', 20)->default('new')->after('preorder_release_on')->index();
            $table->string('condition_note', 500)->nullable()->after('condition');
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('kind', 20)->default('shop')->after('status')->index();
        });
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->string('kind', 20)->default('shop')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_applications', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['condition', 'condition_note']));
    }
};
