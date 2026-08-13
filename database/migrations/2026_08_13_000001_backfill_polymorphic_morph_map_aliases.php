<?php

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;

/**
 * Thin caller for App\Support\MorphMap::backfill().
 *
 * Placement: central database/migrations/ (not a domain folder). This rewrite
 * spans Identity (roles/permissions), Finance (payments), and Notifications
 * tables, and domain migration directories are not loaded yet — see ADR-005.
 */
return new class extends Migration
{
    public function up(): void
    {
        MorphMap::backfill();
    }

    /**
     * No-op on purpose.
     *
     * Do NOT restore old App\Models\* / App\Notifications\* FQCNs: those classes
     * no longer exist, and rows written after deploy legitimately carry morph
     * aliases (or new notification FQCNs). A "restoring" rollback would corrupt
     * good data and fix nothing.
     */
    public function down(): void
    {
        // Intentionally empty — see class docblock.
    }
};
