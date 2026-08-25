<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\LessonLog;

class LockOverdueRegistersAction
{
    public function __construct(private ResolveRegisterLockDaysAction $lockDays) {}

    public function execute(?int $days = null): int
    {
        $lockDays = $days ?? $this->lockDays->execute();
        $cutoff = now()->timezone(config('app.timezone'))->startOfDay()->subDays($lockDays)->toDateString();

        $logs = LessonLog::query()
            ->where('status', '!=', LessonLogStatus::Locked->value)
            ->whereDate('date', '<=', $cutoff)
            ->where(function ($query) {
                $query->whereNull('unlocked_until')
                    ->orWhere('unlocked_until', '<', now());
            })
            ->get();

        foreach ($logs as $log) {
            $log->status = LessonLogStatus::Locked;
            $log->locked_at = now();
            $log->save();
        }

        return $logs->count();
    }
}
