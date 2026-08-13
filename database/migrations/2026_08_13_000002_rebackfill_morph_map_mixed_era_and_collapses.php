<?php

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-run MorphMap::backfill() after the mixed-era + composite-key collapse fix.
 *
 * 2026_08_13_000001 may already have run on environments that only rewrote
 * App\Models\* rows. Staging (and any DB that ran 000001) still holds
 * App\Domains\* FQCNs written after Phase 0 with no morph map. This migration
 * is a thin re-caller of the same Support logic (idempotent; also collapses
 * duplicate permission pivots that would PK-collide on rewrite).
 *
 * down() is a no-op — same rationale as 000001 / ADR-005.
 */
return new class extends Migration
{
    public function up(): void
    {
        MorphMap::backfill();
    }

    public function down(): void
    {
        // Intentionally empty — see class docblock / ADR-005.
    }
};
