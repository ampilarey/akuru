<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\RevisionScheduleStatus;
use App\Domains\Courses\Components\Quran\Models\QuranRevisionSchedule;
use App\Support\Contracts\QuranReferenceReader;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §52.21. Rule 10: schedules carry academic_year_id.
 */
class SaveRevisionScheduleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?QuranRevisionSchedule $schedule = null): QuranRevisionSchedule
    {
        if (empty($data['scheduled_date'])) {
            throw ValidationException::withMessages(['scheduled_date' => 'Scheduled date is required.']);
        }

        $status = RevisionScheduleStatus::tryFrom(
            (string) ($data['status'] ?? RevisionScheduleStatus::Scheduled->value)
        );
        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Invalid revision schedule status.']);
        }

        $surahId = isset($data['surah_id']) && $data['surah_id'] !== '' ? (int) $data['surah_id'] : null;
        if ($surahId !== null && app(QuranReferenceReader::class)->findSurah($surahId) === null) {
            throw ValidationException::withMessages(['surah_id' => 'Unknown surah.']);
        }

        $payload = [
            'student_id' => (int) $data['student_id'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'surah_id' => $surahId,
            'start_ayah_number' => $data['start_ayah_number'] ?? null,
            'end_ayah_number' => $data['end_ayah_number'] ?? null,
            'scheduled_date' => $data['scheduled_date'],
            'frequency' => $data['frequency'] ?? null,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ];

        if ($schedule === null) {
            return QuranRevisionSchedule::query()->create($payload);
        }

        $schedule->fill($payload);
        $schedule->save();

        return $schedule->refresh();
    }
}
