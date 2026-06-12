<?php

namespace App\Policies;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Models\User;
use App\Domains\Hifz\Services\HifzScopeService;

class HifzMilestonePolicy
{
    public function __construct(protected HifzScopeService $scope) {}

    public function viewAny(User $user): bool
    {
        return $user->can('view_hifz_programs');
    }

    public function recommend(User $user, HifzMilestone $milestone): bool
    {
        return $user->can('recommend_hifz_milestones')
            && $this->scope->canAccessStudent($user, $milestone->student);
    }

    public function review(User $user, HifzMilestone $milestone): bool
    {
        return $user->can('review_hifz_milestones')
            && $this->scope->canAccessProgram($user, $milestone->program);
    }

    public function approve(User $user, HifzMilestone $milestone): bool
    {
        return $user->can('approve_hifz_milestones') && $user->isHifzDean();
    }
}
