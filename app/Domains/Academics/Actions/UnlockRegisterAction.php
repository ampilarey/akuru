<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Academics\Models\RegisterUnlock;
use Illuminate\Validation\ValidationException;

class UnlockRegisterAction
{
    public function execute(LessonLog $log, int $actorId, string $reason): RegisterUnlock
    {
        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'reason' => __('academics.error_unlock_reason'),
            ]);
        }

        $log->status = $log->submitted_at ? LessonLogStatus::Submitted : LessonLogStatus::Draft;
        $log->locked_at = null;
        $log->unlocked_until = now()->addDay();
        $log->save();

        return RegisterUnlock::query()->create([
            'lesson_log_id' => $log->id,
            'unlocked_by' => $actorId,
            'reason' => $trimmed,
            'unlocked_at' => now(),
        ]);
    }
}
