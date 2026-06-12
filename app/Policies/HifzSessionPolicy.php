<?php

namespace App\Policies;

use App\Domains\Hifz\Models\HifzSession;
use App\Models\User;
use App\Domains\Hifz\Services\HifzScopeService;

class HifzSessionPolicy
{
    public function __construct(protected HifzScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('view_hifz_programs');
    }

    public function view(User $user, HifzSession $session): bool
    {
        return $this->scope->canAccessSession($user, $session);
    }

    public function create(User $user): bool
    {
        return $user->can('create_hifz_sessions');
    }

    public function update(User $user, HifzSession $session): bool
    {
        if ($session->status === \App\Enums\Hifz\HifzSessionStatus::Locked) {
            return $user->can('lock_hifz_sessions') && $user->isHifzDean();
        }

        return $user->can('update_hifz_sessions') && $this->scope->canAccessSession($user, $session);
    }

    public function review(User $user, HifzSession $session): bool
    {
        return $user->can('review_hifz_sessions') && $this->scope->canAccessSession($user, $session);
    }

    public function lock(User $user, HifzSession $session): bool
    {
        return $user->can('lock_hifz_sessions') && $user->isHifzDean();
    }
}
