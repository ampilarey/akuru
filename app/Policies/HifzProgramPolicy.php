<?php

namespace App\Policies;

use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Identity\Models\User;

class HifzProgramPolicy
{
    public function __construct(protected HifzScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('view_hifz_programs');
    }

    public function view(User $user, HifzProgram $program): bool
    {
        return $user->can('view_hifz_programs') && $this->scope->canAccessProgram($user, $program);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_hifz_programs');
    }

    public function update(User $user, HifzProgram $program): bool
    {
        return $user->can('manage_hifz_programs') && $this->scope->canAccessProgram($user, $program);
    }

    public function assignSupervisor(User $user): bool
    {
        return $user->can('assign_hifz_supervisors');
    }
}
