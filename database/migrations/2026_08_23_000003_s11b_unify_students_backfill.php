<?php

use App\Domains\People\Actions\UnifyStudentsAction;
use Illuminate\Database\Migrations\Migration;

/**
 * S1.1b — thin caller for the registration_students → students backfill.
 *
 * Does not switch reads (Deploy 2). down() is a no-op: un-backfilling live
 * legacy aliases would break the 1:1 mapping and any later dual-writes.
 * Gate: `php artisan students:verify-unification`. See ADR-007.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(UnifyStudentsAction::class)->execute();
    }

    public function down(): void
    {
        // Intentionally empty — do not un-backfill live aliases.
    }
};
