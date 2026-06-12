<?php

namespace App\Domains\People\Providers;

use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Policies\RegistrationStudentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(RegistrationStudent::class, RegistrationStudentPolicy::class);
    }
}
