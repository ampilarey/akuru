<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Eloquent morph map and enforces it.
 *
 * Must load before domain providers so polymorphic relations resolve aliases
 * as soon as models boot.
 *
 * Enforcement (ADR-005) means any model used polymorphically without an alias
 * throws ClassMorphViolationException instead of silently writing an FQCN.
 * The assurance ADR-005 originally deferred to "production verification" is
 * provided instead by MorphMapConfigTest: every model under app/Domains has an
 * alias, aliases are unique, and no Eloquent models live outside app/Domains.
 */
class MorphMapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::enforceMorphMap(config('morph-map', []));
    }
}
