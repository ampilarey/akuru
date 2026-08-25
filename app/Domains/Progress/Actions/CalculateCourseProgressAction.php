<?php

namespace App\Domains\Progress\Actions;

class CalculateCourseProgressAction
{
    public function execute(int $completedRequired, int $totalRequired): int
    {
        if ($totalRequired < 1) {
            return 0;
        }

        return (int) floor(($completedRequired / $totalRequired) * 100);
    }
}
