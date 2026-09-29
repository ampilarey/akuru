<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helpful review votes (STATUS §5lg): a signed-in customer marks another
 * customer's review helpful, once; the product page shows the most helpful
 * first. Additive: a count on `product_reviews` and one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->unsignedInteger('helpful_count')->default(0)->after('body');
        });

        Schema::create('review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_review_id')->constrained('product_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_votes');
        Schema::table('product_reviews', fn (Blueprint $table) => $table->dropColumn('helpful_count'));
    }
};
