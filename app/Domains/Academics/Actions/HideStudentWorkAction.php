<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentWork;
use Illuminate\Validation\ValidationException;

/**
 * Take a photo out of the family view immediately.
 *
 * The urgent case is not a wrong pupil — that is a reassignment — but a photo
 * that caught **another child's work, or face, in frame**. A teacher who
 * realises that needs it gone now, and needs it gone without an administrator.
 *
 * Hidden rather than deleted: the row is how the school can later answer what
 * was published and when it stopped.
 */
class HideStudentWorkAction
{
    public function execute(int $workId, int $staffUserId): StudentWork
    {
        $work = StudentWork::query()->find($workId);

        if ($work === null) {
            throw ValidationException::withMessages(['work' => 'That piece of work no longer exists.']);
        }

        if ($work->hidden_at !== null) {
            throw ValidationException::withMessages(['work' => 'That is already hidden from families.']);
        }

        $work->update(['hidden_at' => now(), 'hidden_by' => $staffUserId]);

        return $work->refresh();
    }

    /** Put it back — the mistake was the hiding, not the photo. */
    public function restore(int $workId): StudentWork
    {
        $work = StudentWork::query()->find($workId);

        if ($work === null) {
            throw ValidationException::withMessages(['work' => 'That piece of work no longer exists.']);
        }

        $work->update(['hidden_at' => null, 'hidden_by' => null]);

        return $work->refresh();
    }
}
