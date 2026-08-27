<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Courses\Actions\ListEnrollmentTargetsByCourseTypeAction;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentStatus;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentType;
use App\Domains\Courses\Components\Quran\Models\QuranHifzAssignment;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Domains\People\Actions\ListTeachersByIdsAction;
use App\Support\Contracts\QuranReferenceReader;

/**
 * F5-P2 teacher board: assignments with names resolved through People
 * actions, surah names through the reference contract, plus the assignable
 * roster (live enrollments on hifz-type courses via the engine seam).
 */
class ListQuranAssignmentsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters = []): array
    {
        $query = QuranHifzAssignment::query()->orderByDesc('id')->limit(200);
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['teacher_id'])) {
            $query->where('teacher_id', (int) $filters['teacher_id']);
        }
        if (! empty($filters['student_id'])) {
            $query->where('student_id', (int) $filters['student_id']);
        }
        $assignments = $query->get();

        $targets = app(ListEnrollmentTargetsByCourseTypeAction::class)->execute('hifz');
        $students = app(ListStudentsByIdsAction::class)
            ->execute([
                ...$assignments->pluck('student_id')->all(),
                ...array_column($targets, 'student_id'),
            ])
            ->keyBy('id');
        $teachers = app(ListTeachersByIdsAction::class)
            ->execute($assignments->pluck('teacher_id')->all())
            ->keyBy('id');
        $surahs = collect(app(QuranReferenceReader::class)->listSurahs())->keyBy('id');

        return [
            'rows' => $assignments
                ->map(fn (QuranHifzAssignment $row): array => [
                    'id' => $row->id,
                    'student' => $students->get((int) $row->student_id),
                    'teacher' => $teachers->get((int) $row->teacher_id)['name'] ?? null,
                    'assignment_type' => $row->assignment_type?->value,
                    'surah' => $row->surah_id
                        ? ($surahs->get((int) $row->surah_id)['english_name'] ?? null)
                        : null,
                    'start_ayah_number' => $row->start_ayah_number,
                    'end_ayah_number' => $row->end_ayah_number,
                    'expected_letter_id' => $row->expected_letter_id,
                    'expected_haraka_id' => $row->expected_haraka_id,
                    'due_date' => $row->due_date?->toDateString(),
                    'status' => $row->status?->value,
                    'notes' => $row->notes,
                ])
                ->values()
                ->all(),
            'targets' => array_values(array_map(fn (array $target): array => $target + [
                'student_name' => $students->get($target['student_id'])['name'] ?? ('Student '.$target['student_id']),
            ], $targets)),
            'surahs' => $surahs
                ->map(fn (array $surah): array => [
                    'id' => $surah['id'],
                    'index' => $surah['index'],
                    'english_name' => $surah['english_name'],
                ])
                ->values()
                ->all(),
            'options' => [
                'types' => array_map(fn ($case) => $case->value, QuranAssignmentType::cases()),
                'statuses' => array_map(fn ($case) => $case->value, QuranAssignmentStatus::cases()),
            ],
        ];
    }
}
