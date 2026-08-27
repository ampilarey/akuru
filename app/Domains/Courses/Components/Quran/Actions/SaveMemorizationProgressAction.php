<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\MemorizationStatus;
use App\Domains\Courses\Components\Quran\Models\QuranMemorizationProgress;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §52.22 — upsert on (student, surah, ayah range): one row per memorised
 * range, updated as reviews land, never duplicated.
 */
class SaveMemorizationProgressAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): QuranMemorizationProgress
    {
        $status = MemorizationStatus::tryFrom((string) ($data['status'] ?? ''));
        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Invalid memorization status.']);
        }

        $row = QuranMemorizationProgress::query()->firstOrNew([
            'student_id' => (int) $data['student_id'],
            'surah_id' => $data['surah_id'] ?? null,
            'start_ayah_number' => $data['start_ayah_number'] ?? null,
            'end_ayah_number' => $data['end_ayah_number'] ?? null,
        ]);
        $row->fill([
            'academic_year_id' => $data['academic_year_id'] ?? $row->academic_year_id,
            'status' => $status,
            'last_reviewed_at' => $data['last_reviewed_at'] ?? $row->last_reviewed_at,
            'strength_score' => $data['strength_score'] ?? $row->strength_score,
            'mistake_count' => $data['mistake_count'] ?? $row->mistake_count,
            'teacher_id' => $data['teacher_id'] ?? $row->teacher_id,
        ]);
        $row->save();

        return $row->refresh();
    }
}
