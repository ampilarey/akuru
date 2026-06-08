<?php

namespace App\Policies;

use App\Models\QuranMushaf;
use App\Models\User;

class QuranMushafPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_hifz_programs');
    }

    public function manage(User $user): bool
    {
        return $user->can('manage_quran_mushaf') && $user->isHifzDean();
    }

    public function approve(User $user, QuranMushaf $mushaf): bool
    {
        return $user->can('approve_quran_mushaf') && $user->isHifzDean();
    }

    public function lock(User $user, QuranMushaf $mushaf): bool
    {
        return $user->can('approve_quran_mushaf') && $user->isHifzDean();
    }

    public function reviewMapping(User $user): bool
    {
        return $user->can('review_quran_mapping');
    }
}
