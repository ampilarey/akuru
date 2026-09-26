<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B7: shop polish and trust. Additive only (rule 9).
 *
 *  - `products`: the vendor's own badge text (three languages), and the
 *    published reviews' average and count kept alongside for the
 *    top-rated sort and the stars on every card.
 *  - `vendors.free_delivery_over`: a shop-wide "spend MVR X, get free
 *    delivery" rule (§6.5) on top of each method's own `free_over`.
 *  - `discount_codes.applies_to_id` (+ `created_by`): the code's scope
 *    beside the `applies_to_type` Commerce already had ('all'); a
 *    vendor-funded code is `applies_to_type = 'vendor'` with the vendor's
 *    id — a pseudo-polymorphic pair whose alias ('vendor') is registered in
 *    config/morph-map.php since B1a (ADR-005).
 *  - `product_reviews`: one per delivered order line (§4 "reviews only
 *    from customers who received the product"), a vendor reply, the
 *    office's moderation.
 *  - `wishlist_items`, `stock_alerts` ("out of stock — notify me").
 *  - `shop_home_features`: the office's merchandising of the shop home —
 *    hero slides, featured products, featured collections (§7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('badge', 40)->nullable()->after('featured');
            $table->string('badge_dv', 40)->nullable()->after('badge');
            $table->string('badge_ar', 40)->nullable()->after('badge_dv');
            $table->decimal('rating_avg', 3, 2)->nullable()->after('badge_ar');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->decimal('free_delivery_over', 10, 2)->nullable()->after('return_conditions');
        });

        Schema::table('discount_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('applies_to_id')->nullable()->after('applies_to_type');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->index(['applies_to_type', 'applies_to_id']);
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('published'); // published / pending / hidden
            $table->text('vendor_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderation_note', 500)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('notified_at')->nullable();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'notified_at']);
        });

        Schema::create('shop_home_features', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // hero / product / collection
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('vendor_collection_id')->nullable()->constrained('vendor_collections')->cascadeOnDelete();
            $table->string('heading', 160)->nullable();
            $table->string('heading_dv', 160)->nullable();
            $table->string('heading_ar', 160)->nullable();
            $table->string('subheading', 300)->nullable();
            $table->string('subheading_dv', 300)->nullable();
            $table->string('subheading_ar', 300)->nullable();
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->json('link')->nullable(); // {kind: vendor|category|product|collection, target}
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_home_features');
        Schema::dropIfExists('stock_alerts');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('product_reviews');
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropIndex(['applies_to_type', 'applies_to_id']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('applies_to_id');
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('free_delivery_over');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['badge', 'badge_dv', 'badge_ar', 'rating_avg', 'rating_count']);
        });
    }
};
