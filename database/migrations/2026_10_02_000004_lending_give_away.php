<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LENDING_AND_USED_BOOKS_PLAN L3: a book may be offered to keep, not only to
 * borrow. `offer` is `lend` (every book so far) or `give`; a given book's
 * final status is `given`. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lending_books', function (Blueprint $table) {
            $table->string('offer', 10)->default('lend')->after('lender_id')->index(); // lend / give
        });
    }

    public function down(): void
    {
        Schema::table('lending_books', fn (Blueprint $table) => $table->dropColumn('offer'));
    }
};
