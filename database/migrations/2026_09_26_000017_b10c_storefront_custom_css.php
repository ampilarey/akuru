<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B10c: a shop's own CSS (the owner, 2026-09-26, reversing BOOKSHOP_PLAN
 * §6.8's "not planned"; ADR-039). Additive only (rule 9).
 *
 * The CSS that is live, and the one waiting for the office — both already
 * cleaned and confined to the shop's part of its page (`Support/CustomCss`).
 * Nothing the shop writes reaches a visitor until the office approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_storefronts', function (Blueprint $table) {
            $table->text('custom_css')->nullable()->after('locked_section_types');
            $table->text('custom_css_pending')->nullable()->after('custom_css');
            $table->string('custom_css_status', 12)->nullable()->after('custom_css_pending');
            $table->string('custom_css_note', 500)->nullable()->after('custom_css_status');
            $table->timestamp('custom_css_submitted_at')->nullable()->after('custom_css_note');
            $table->timestamp('custom_css_reviewed_at')->nullable()->after('custom_css_submitted_at');
            $table->foreignId('custom_css_reviewed_by')->nullable()->after('custom_css_reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_storefronts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_css_reviewed_by');
            $table->dropColumn(['custom_css', 'custom_css_pending', 'custom_css_status', 'custom_css_note', 'custom_css_submitted_at', 'custom_css_reviewed_at']);
        });
    }
};
