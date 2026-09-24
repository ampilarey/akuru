<?php

namespace App\Domains\People\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * The `RegistrationStudent` policy and `students:verify-unification` were
 * registered here until S1 Deploy 3 archived the legacy student tables
 * (STATUS §5gh). Kept, empty, as People's provider.
 */
class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
