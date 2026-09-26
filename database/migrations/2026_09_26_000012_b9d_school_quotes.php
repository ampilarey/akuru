<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B9d: bulk quotes for schools (B9 "later, on
 * request"; audit finding 22). Additive only (rule 9).
 *
 * A customer asks one shop to price a basket of its goods for a school or
 * group; the shop prices each line and says how long the price holds; the
 * customer accepts, and those lines go into the cart at the quoted price
 * — the checkout charges it while the quote is valid. No `academic_year_id`:
 * commerce, as every bookshop table.
 *
 *  - `quote_requests`, `quote_items` (the lines as asked, with the list
 *    price then and the quoted price).
 *  - `cart_items.quote_item_id`, `order_items.quote_item_id`: which quoted
 *    line a cart line or an order line came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('organisation', 160);
            $table->string('contact_phone', 40)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('requested'); // requested / quoted / accepted / declined / ordered / withdrawn
            $table->decimal('list_total', 10, 2)->default(0);
            $table->decimal('quoted_total', 10, 2)->nullable();
            $table->string('currency', 3)->default('MVR');
            $table->date('valid_until')->nullable();
            $table->text('vendor_note')->nullable();
            $table->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('title', 255);
            $table->string('variant_name', 120)->nullable();
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('list_price', 10, 2);
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('quote_item_id')->nullable()->after('quantity')->constrained('quote_items')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('quote_item_id')->nullable()->after('quantity')->constrained('quote_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('quote_item_id'));
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('quote_item_id'));
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quote_requests');
    }
};
