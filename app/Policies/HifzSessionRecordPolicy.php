<?php

namespace App\Policies;

use App\Models\HifzSessionRecord;
use App\Models\User;
use App\Services\Hifz\HifzScopeService;

class HifzSessionRecordPolicy
{
    public function __construct(protected HifzScopeService $scope) {}

    public function view(User $user, HifzSessionRecord $record): bool
    {
        return $this->scope->canAccessRecord($user, $record);
    }

    public function create(User $user): bool
    {
        return $user->can('create_hifz_session_records');
    }

    public function update(User $user, HifzSessionRecord $record): bool
    {
        if ($user->can('override_hifz_records') && $user->isHifzDean()) {
            return $this->scope->canAccessRecord($user, $record);
        }

        return $user->can('update_hifz_session_records') && $this->scope->canAccessRecord($user, $record);
    }

    public function review(User $user, HifzSessionRecord $record): bool
    {
        return $user->can('review_hifz_session_records') && $this->scope->canAccessRecord($user, $record);
    }
}
