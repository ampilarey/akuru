<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Eloquent morph map (non-enforcing).
 *
 * Must load before domain providers so polymorphic relations resolve aliases
 * as soon as models boot. Do NOT call Relation::enforceMorphMap() here —
 * flip to enforcement in a follow-up after production verification.
 */
class MorphMapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap(config('morph-map', []));
    }
}
