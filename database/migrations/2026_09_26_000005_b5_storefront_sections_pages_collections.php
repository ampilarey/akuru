<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B5: the storefront designer, part 2 — sections,
 * pages, collections, navigation, SEO, and the office's moderation.
 * Additive only (rule 9).
 *
 *  - `vendor_storefronts` gains the home page's sections, the storefront
 *    menu and its SEO fields, draft and published (§6.3, §6.4, §6.7), and
 *    the office's hold, note and locked section types (§6.6).
 *  - `vendor_storefront_versions` snapshots those too, and the pages.
 *  - `vendor_pages`: simple pages under the storefront, built from the
 *    same sections (§6.4).
 *  - `vendor_collections` and `vendor_collection_products`: named groups
 *    of the vendor's products, manual or by rule (§5 "Collections").
 *  - `vendor_storefront_images`: the shop's image library, which sections
 *    pick from (plan §10: public media, resized variants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_storefronts', function (Blueprint $table) {
            $table->json('draft_sections')->nullable()->after('draft_theme');
            $table->json('draft_navigation')->nullable()->after('draft_sections');
            $table->json('draft_seo')->nullable()->after('draft_navigation');
            $table->json('published_sections')->nullable()->after('published_theme');
            $table->json('published_navigation')->nullable()->after('published_sections');
            $table->json('published_seo')->nullable()->after('published_navigation');
            $table->timestamp('held_at')->nullable()->after('published_by');
            $table->foreignId('held_by')->nullable()->after('held_at')->constrained('users')->nullOnDelete();
            $table->string('moderation_note', 1000)->nullable()->after('held_by');
            $table->json('locked_section_types')->nullable()->after('moderation_note');
        });

        Schema::table('vendor_storefront_versions', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('theme');
            $table->json('navigation')->nullable()->after('sections');
            $table->json('seo')->nullable()->after('navigation');
            $table->json('pages')->nullable()->after('seo');
        });

        Schema::create('vendor_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('title', 120);
            $table->string('title_dv', 120)->nullable();
            $table->string('title_ar', 120)->nullable();
            $table->json('draft_sections')->nullable();
            $table->json('published_sections')->nullable();
            $table->json('seo')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'slug']);
        });

        Schema::create('vendor_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('name_dv', 120)->nullable();
            $table->string('name_ar', 120)->nullable();
            $table->string('description', 500)->nullable();
            // Null: hand-picked (the pivot). Else {"tags": [...], "category_id": n}.
            $table->json('rule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'slug']);
        });

        Schema::create('vendor_collection_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_collection_id')->constrained('vendor_collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['vendor_collection_id', 'product_id'], 'vendor_collection_product_unique');
        });

        Schema::create('vendor_storefront_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('alt', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_storefront_images');
        Schema::dropIfExists('vendor_collection_products');
        Schema::dropIfExists('vendor_collections');
        Schema::dropIfExists('vendor_pages');
        Schema::table('vendor_storefront_versions', function (Blueprint $table) {
            $table->dropColumn(['sections', 'navigation', 'seo', 'pages']);
        });
        Schema::table('vendor_storefronts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_by');
            $table->dropColumn(['draft_sections', 'draft_navigation', 'draft_seo', 'published_sections', 'published_navigation', 'published_seo', 'held_at', 'moderation_note', 'locked_section_types']);
        });
    }
};
