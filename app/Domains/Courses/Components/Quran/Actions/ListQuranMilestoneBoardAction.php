<?php

namespace App\Domains\Courses\Components\Quran\Actions;

use App\Domains\Offerings\Actions\ResolveHalaqaEnrollmentLinksAction;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Support\Contracts\HalaqaReferenceReader;

/**
 * F5-P3 board: every mapped program's milestones with student names, plus
 * the recommendable roster per program (students the F2 mapping knows).
 * Milestones stay in the Hifz store; reads come through the contract.
 */
class ListQuranMilestoneBoardAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?string $status = 'all'): array
    {
        $reader = app(HalaqaReferenceReader::class);
        $rows = [];
        $targets = [];
        $studentIds = [];

        foreach ($reader->listPrograms() as $program) {
            $programId = (int) $program['id'];
            $links = app(ResolveHalaqaEnrollmentLinksAction::class)->execute($programId);
            if ($links === []) {
                continue; // unmapped or empty program — not on the engine board
            }

            foreach ($links as $link) {
                $studentIds[] = $link['student_id'];
                $targets[] = [
                    'hifz_program_id' => $programId,
                    'program_name' => (string) ($program['name'] ?? ''),
                    'student_id' => $link['student_id'],
                ];
            }

            foreach ($reader->listMilestones($programId) as $milestone) {
                if ($status !== null && $status !== '' && $status !== 'all'
                    && ($milestone['status'] ?? null) !== $status) {
                    continue;
                }
                $studentIds[] = (int) $milestone['student_id'];
                $rows[] = $milestone + ['program_name' => (string) ($program['name'] ?? '')];
            }
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($studentIds)
            ->keyBy('id');

        return [
            'rows' => array_values(array_map(fn (array $row): array => $row + [
                'student_name' => $students->get((int) $row['student_id'])['name'] ?? ('Student '.$row['student_id']),
            ], $rows)),
            'targets' => array_values(array_map(fn (array $target): array => $target + [
                'student_name' => $students->get((int) $target['student_id'])['name'] ?? ('Student '.$target['student_id']),
            ], $targets)),
            'options' => [
                'types' => ['surah_completed', 'juz_completed', 'page_completed', 'quran_completed', 'custom'],
                'statuses' => ['pending', 'supervisor_reviewed', 'approved', 'rejected', 'all'],
            ],
        ];
    }
}
