<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B4: the storefront designer, part 1 — identity and
 * theme. Additive only (rule 9).
 *
 *  - `vendor_storefronts`: one row per vendor. The design is data (plan §6:
 *    "JSON on the vendor, never code"): a draft the vendor edits and
 *    previews, and the published copy the public page renders. Sections
 *    and pages (B5) join this table later.
 *  - `vendor_storefront_versions`: append-only snapshots of what was
 *    published, for roll-back (§6.2 "named versions"). Never updated.
 *  - `vendors.badges`: what the office grants (§6.1: verified vendor,
 *    Akuru partner); decision 10 ties Akuru's own palette to the partner
 *    badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained('vendors')->cascadeOnDelete();
            $table->json('draft_identity')->nullable();
            $table->json('draft_theme')->nullable();
            $table->json('published_identity')->nullable();
            $table->json('published_theme')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_storefront_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_storefront_id')->constrained('vendor_storefronts')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->json('identity')->nullable();
            $table->json('theme')->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['vendor_storefront_id', 'number']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->json('badges')->nullable()->after('return_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('badges');
        });
        Schema::dropIfExists('vendor_storefront_versions');
        Schema::dropIfExists('vendor_storefronts');
    }
};
