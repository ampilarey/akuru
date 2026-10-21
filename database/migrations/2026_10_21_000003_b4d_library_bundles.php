<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIBRARY_PLAN §19, BACKLOG B4 (B4d, STATUS §5un): bundles — a set of paid
 * works sold together at one price. Buying one makes a purchase per work,
 * its share of the price apportioned by list price, so each writer earns on
 * theirs; `library_purchases.library_bundle_id` names the bundle. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('title', 160);
            $table->string('title_dv', 160)->nullable();
            $table->string('title_ar', 160)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('MVR');
            $table->boolean('is_active')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('library_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_bundle_id')->constrained('library_bundles')->cascadeOnDelete();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['library_bundle_id', 'library_item_id']);
        });

        Schema::table('library_purchases', function (Blueprint $table) {
            $table->foreignId('library_bundle_id')->nullable()->after('library_item_id')->constrained('library_bundles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('library_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('library_bundle_id');
        });
        Schema::dropIfExists('library_bundle_items');
        Schema::dropIfExists('library_bundles');
    }
};
