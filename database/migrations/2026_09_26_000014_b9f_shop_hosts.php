<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOOKSHOP_PLAN slice B9f: a shop's own domain (§2 "a host per vendor
 * stays a later mapping", §6.8). Additive only (rule 9).
 *
 * `vendors.custom_host` has existed since B1a, unused. The shop's owner
 * asks for a host (`requested`); the office turns it on (`active`) once the
 * domain points at Akuru. Only an active host is ever answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('custom_host_status', 12)->nullable()->after('custom_host');
            $table->timestamp('custom_host_requested_at')->nullable()->after('custom_host_status');
            $table->timestamp('custom_host_approved_at')->nullable()->after('custom_host_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['custom_host_status', 'custom_host_requested_at', 'custom_host_approved_at']);
        });
    }
};
