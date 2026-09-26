<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B9e: storefront analytics beyond basics (§6.8
 * "funnel, top pages"). Additive only (rule 9).
 *
 *  - `shop_daily_stats`: one counter per shop, day, step and subject — a
 *    visit to the shop's page or one of its pages or collections, a view
 *    of a product, an add to cart, a checkout started, an order paid, a
 *    product sold (with its amount). Counts only: no visitor, no IP, no
 *    cookie of its own — nothing personal is kept. Like every Bookstore
 *    commerce table it carries no academic year (precedent B2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('day');
            $table->string('metric', 20);
            $table->string('subject', 100)->default('');
            $table->unsignedInteger('count')->default(0);
            $table->decimal('amount', 12, 2)->default(0);

            $table->unique(['vendor_id', 'day', 'metric', 'subject'], 'shop_daily_stats_unique');
            $table->index(['day', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_daily_stats');
    }
};
