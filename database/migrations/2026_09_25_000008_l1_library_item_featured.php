<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBRARY_PLAN §8.1 "featured books/articles" and §7.8 "featuring": the
 * office picks what sits at the top of the shelf. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('status');
            $table->timestamp('featured_at')->nullable()->after('featured');
            $table->index(['status', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropIndex(['status', 'featured']);
            $table->dropColumn(['featured', 'featured_at']);
        });
    }
};
