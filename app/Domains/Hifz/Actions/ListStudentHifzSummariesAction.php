<?php

namespace App\Domains\Hifz\Actions;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\QuranProgress;
use App\Support\Contracts\StudentHifzSummaryReader;

class ListStudentHifzSummariesAction implements StudentHifzSummaryReader
{
    /**
     * @param  list<int>  $studentIds
     * @return list<array<string, mixed>>
     */
    public function summariesForStudents(array $studentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return [];
        }

        $progress = QuranProgress::query()
            ->whereIn('student_id', $ids)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('student_id');

        $rows = HifzEnrollment::query()
            ->with(['program', 'currentSurah'])
            ->whereIn('student_id', $ids)
            ->orderBy('id')
            ->get()
            ->map(function (HifzEnrollment $enrollment) use ($progress): array {
                $studentProgress = $progress->get($enrollment->student_id, collect());

                return [
                    'student_id' => (int) $enrollment->student_id,
                    'program' => (string) ($enrollment->program?->name ?? ''),
                    'status' => $enrollment->status?->value ?? (string) $enrollment->status,
                    'current_surah' => $enrollment->currentSurah?->english_name
                        ?? $enrollment->currentSurah?->arabic_name
                        ?? $enrollment->currentSurah?->transliteration
                        ?? null,
                    'current_juz' => $enrollment->current_juz !== null ? (int) $enrollment->current_juz : null,
                    'completed_surahs' => $studentProgress->where('status', 'completed')->count(),
                    'accuracy_percent' => $studentProgress->avg('accuracy_percentage') !== null
                        ? (int) round((float) $studentProgress->avg('accuracy_percentage'))
                        : null,
                ];
            })
            ->all();

        $enrolled = collect($rows)->pluck('student_id')->all();
        foreach ($ids as $studentId) {
            if (in_array($studentId, $enrolled, true)) {
                continue;
            }
            $studentProgress = $progress->get($studentId, collect());
            if ($studentProgress->isEmpty()) {
                continue;
            }
            $latest = $studentProgress->first();
            $rows[] = [
                'student_id' => $studentId,
                'program' => '',
                'status' => (string) ($latest->status ?? ''),
                'current_surah' => $latest->surah_name,
                'current_juz' => null,
                'completed_surahs' => $studentProgress->where('status', 'completed')->count(),
                'accuracy_percent' => $studentProgress->avg('accuracy_percentage') !== null
                    ? (int) round((float) $studentProgress->avg('accuracy_percentage'))
                    : null,
            ];
        }

        return array_values($rows);
    }
}
