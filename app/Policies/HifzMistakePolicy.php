<?php

namespace App\Policies;

use App\Domains\Hifz\Models\HifzMistake;
use App\Domains\Identity\Models\User;
use App\Domains\Hifz\Services\HifzScopeService;

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
