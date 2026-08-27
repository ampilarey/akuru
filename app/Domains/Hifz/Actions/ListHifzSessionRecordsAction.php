<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzSessionRecord;

class ListHifzSessionRecordsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $sessionId): array
    {
        return HifzSessionRecord::query()
            ->where('hifz_session_id', $sessionId)
            ->orderBy('id')
            ->get()
            ->map(fn (HifzSessionRecord $record): array => [
                'id' => $record->id,
                'student_id' => (int) $record->student_id,
                'attendance_status' => $record->attendance_status?->value ?? $record->attendance_status,
            ])
            ->values()
            ->all();
    }
}
