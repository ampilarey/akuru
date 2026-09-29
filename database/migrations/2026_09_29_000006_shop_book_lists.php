<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * School book lists (STATUS §5lc): a shop's hand-picked collection marked
 * as a school's list for a grade, with how many of each item the list asks
 * for, so a parent can put the whole list in the cart in one tap.
 * Additive: three columns on `vendor_collections`, one on its pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_collections', function (Blueprint $table) {
            $table->boolean('book_list')->default(false)->after('rule');
            $table->string('school', 120)->nullable()->after('book_list');
            $table->string('grade', 40)->nullable()->after('school');
        });

        Schema::table('vendor_collection_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('quantity')->default(1)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_collection_products', fn (Blueprint $table) => $table->dropColumn('quantity'));
        Schema::table('vendor_collections', fn (Blueprint $table) => $table->dropColumn(['book_list', 'school', 'grade']));
    }
};
