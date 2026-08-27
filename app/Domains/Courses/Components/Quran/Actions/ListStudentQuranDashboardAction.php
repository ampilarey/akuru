<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Models\QuranMemorizationProgress;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\Courses\Components\Quran\Models\QuranRevisionSchedule;
use App\Support\Contracts\QuranReferenceReader;

/**
 * F4 student surface (§52.7 non-AI subset): my submissions, my memorization
 * progress, my upcoming revision. Surah names come from the reference
 * contract (rule 11).
 */
class ListStudentQuranDashboardAction
{
    /**
     * @return array{
     *     submissions: list<array<string, mixed>>,
     *     progress: list<array<string, mixed>>,
     *     schedules: list<array<string, mixed>>
     * }
     */
    public function execute(int $studentId): array
    {
        $surahs = collect(app(QuranReferenceReader::class)->listSurahs())->keyBy('id');
        $surahName = fn (?int $id): ?string => $id !== null
            ? ($surahs->get($id)['english_name'] ?? null)
            : null;

        $submissions = QuranRecitationSubmission::query()
            ->where('student_id', $studentId)
            ->withCount('mistakeMarks')
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (QuranRecitationSubmission $row): array => [
                'id' => $row->id,
                'surah' => $surahName($row->surah_id ? (int) $row->surah_id : null),
                'start_ayah_number' => $row->start_ayah_number,
                'end_ayah_number' => $row->end_ayah_number,
                'mode' => $row->mode?->value,
                'status' => $row->status?->value,
                'submitted_at' => $row->submitted_at?->toDateTimeString(),
                'reviewed_at' => $row->reviewed_at?->toDateTimeString(),
                'review_note' => $row->review_note,
                'mistake_count' => (int) $row->mistake_marks_count,
            ])
            ->values()
            ->all();

        $progress = QuranMemorizationProgress::query()
            ->where('student_id', $studentId)
            ->orderBy('surah_id')
            ->orderBy('start_ayah_number')
            ->get()
            ->map(fn (QuranMemorizationProgress $row): array => [
                'id' => $row->id,
                'surah' => $surahName($row->surah_id ? (int) $row->surah_id : null),
                'start_ayah_number' => $row->start_ayah_number,
                'end_ayah_number' => $row->end_ayah_number,
                'status' => $row->status?->value,
                'strength_score' => $row->strength_score,
                'mistake_count' => $row->mistake_count,
                'last_reviewed_at' => $row->last_reviewed_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $schedules = QuranRevisionSchedule::query()
            ->where('student_id', $studentId)
            ->where(fn ($query) => $query
                ->whereDate('scheduled_date', '>=', now()->toDateString())
                ->orWhere('status', 'scheduled'))
            ->orderBy('scheduled_date')
            ->limit(20)
            ->get()
            ->map(fn (QuranRevisionSchedule $row): array => [
                'id' => $row->id,
                'surah' => $surahName($row->surah_id ? (int) $row->surah_id : null),
                'start_ayah_number' => $row->start_ayah_number,
                'end_ayah_number' => $row->end_ayah_number,
                'scheduled_date' => $row->scheduled_date?->toDateString(),
                'frequency' => $row->frequency,
                'status' => $row->status?->value,
                'notes' => $row->notes,
            ])
            ->values()
            ->all();

        return [
            'submissions' => $submissions,
            'progress' => $progress,
            'schedules' => $schedules,
        ];
    }
}
