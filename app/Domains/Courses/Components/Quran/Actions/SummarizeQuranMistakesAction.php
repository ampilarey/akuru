<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Components\Quran\Enums\QuranMistakeType;
use App\Domains\Courses\Components\Quran\Models\QuranMemorizationProgress;
use App\Domains\Courses\Components\Quran\Models\QuranMistakeMark;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Domains\People\Actions\ListTeachersByIdsAction;

/**
 * F4 supervisor/dean surface (§52.11–52.12 non-AI subset): submission and
 * mistake aggregates, teacher activity, per-student progress. Letter/haraka
 * columns stay bare IDS here — the Quran component never references Arabic
 * component code; the engine-level oversight controller decorates ids with
 * names from the Arabic reference (the documented engine→component
 * direction).
 */
class SummarizeQuranMistakesAction
{
    /**
     * @return array{
     *     total_submissions: int,
     *     by_status: array<string, int>,
     *     mistake_types: list<array{type: string, count: int}>,
     *     wrong_letters: list<array{letter_id: int, count: int}>,
     *     wrong_harakas: list<array{haraka_id: int, count: int}>,
     *     teacher_activity: list<array<string, mixed>>,
     *     students: list<array<string, mixed>>
     * }
     */
    public function execute(): array
    {
        $byStatus = QuranRecitationSubmission::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->getRawOriginal('status') => (int) $row->total])
            ->all();

        $mistakeTypes = QuranMistakeMark::query()
            ->selectRaw('mistake_type, count(*) as total')
            ->groupBy('mistake_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['type' => (string) $row->getRawOriginal('mistake_type'), 'count' => (int) $row->total])
            ->values()
            ->all();

        $wrongLetters = QuranMistakeMark::query()
            ->where('mistake_type', QuranMistakeType::WrongLetter->value)
            ->whereNotNull('expected_letter_id')
            ->selectRaw('expected_letter_id, count(*) as total')
            ->groupBy('expected_letter_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['letter_id' => (int) $row->expected_letter_id, 'count' => (int) $row->total])
            ->values()
            ->all();

        $wrongHarakas = QuranMistakeMark::query()
            ->where('mistake_type', QuranMistakeType::WrongHaraka->value)
            ->whereNotNull('expected_haraka_id')
            ->selectRaw('expected_haraka_id, count(*) as total')
            ->groupBy('expected_haraka_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['haraka_id' => (int) $row->expected_haraka_id, 'count' => (int) $row->total])
            ->values()
            ->all();

        $activity = QuranMistakeMark::query()
            ->whereNotNull('teacher_id')
            ->selectRaw('teacher_id, count(*) as marks, count(distinct quran_recitation_submission_id) as submissions')
            ->groupBy('teacher_id')
            ->orderByDesc('marks')
            ->get();
        $teacherNames = app(ListTeachersByIdsAction::class)
            ->execute($activity->pluck('teacher_id')->all())
            ->keyBy('id');
        $teacherActivity = $activity
            ->map(fn ($row) => [
                'teacher_id' => (int) $row->teacher_id,
                'name' => $teacherNames->get((int) $row->teacher_id)['name'] ?? ('#'.$row->teacher_id),
                'marks' => (int) $row->marks,
                'submissions' => (int) $row->submissions,
            ])
            ->values()
            ->all();

        $progressRows = QuranMemorizationProgress::query()->get();
        $studentNames = app(ListStudentsByIdsAction::class)
            ->execute($progressRows->pluck('student_id')->unique()->all())
            ->keyBy('id');
        $students = $progressRows
            ->groupBy('student_id')
            ->map(function ($rows, $studentId) use ($studentNames) {
                $strengths = $rows->pluck('strength_score')->filter(fn ($score) => $score !== null);

                return [
                    'student_id' => (int) $studentId,
                    'name' => $studentNames->get((int) $studentId)['name'] ?? ('#'.$studentId),
                    'ranges' => $rows->count(),
                    'passed' => $rows->filter(fn ($row) => in_array($row->status?->value, ['passed', 'strong'], true))->count(),
                    'mistakes' => (int) $rows->sum(fn ($row) => (int) ($row->mistake_count ?? 0)),
                    'avg_strength' => $strengths->isEmpty() ? null : (int) round($strengths->avg()),
                ];
            })
            ->values()
            ->all();

        return [
            'total_submissions' => array_sum($byStatus),
            'by_status' => $byStatus,
            'mistake_types' => $mistakeTypes,
            'wrong_letters' => $wrongLetters,
            'wrong_harakas' => $wrongHarakas,
            'teacher_activity' => $teacherActivity,
            'students' => $students,
        ];
    }
}
