<?php

namespace App\Policies;

use App\Models\HifzMistake;
use App\Models\User;
use App\Services\Hifz\HifzScopeService;

class HifzMistakePolicy
{
    public function __construct(protected HifzScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('view_hifz_mistakes');
    }

    public function create(User $user): bool
    {
        return $user->can('create_hifz_mistakes');
    }

    public function delete(User $user, HifzMistake $mistake): bool
    {
        return $user->can('create_hifz_mistakes')
            && $this->scope->canAccessRecord($user, $mistake->sessionRecord);
    }
}
